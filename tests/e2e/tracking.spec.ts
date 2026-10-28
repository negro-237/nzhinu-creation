import { test, expect, type Page } from '@playwright/test';

test.skip(process.env.E2E_CAMEROON_ONLY !== '1', 'Recette de la boutique publique actuelle.');

async function placeOrder(page: Page) {
    await page.goto('/produits/piece-e2e');
    await page.getByLabel('Stock de départ', { exact: true }).selectOption({ index: 1 });
    await page.getByRole('button', { name: 'Mettre au panier', exact: true }).click();
    await page.getByRole('link', { name: 'Demander ma commande', exact: true }).click();
    await page.getByLabel('Nom complet', { exact: true }).fill('Client recette suivi');
    await page.getByLabel('Téléphone', { exact: true }).fill('+237699000004');
    await page.getByRole('radio', { name: 'Retrait', exact: true }).check();
    await page.getByLabel('Point de retrait', { exact: true }).selectOption({ index: 1 });
    await page.getByRole('button', { name: 'Envoyer ma demande', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Demande reçue', exact: true })).toBeVisible();
    return page.url();
}

test('confirmation privée, montants en attente, contact et actualisation signée', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const url = await placeOrder(page);
    const number = (await page.locator('p').allTextContents()).find((value) => /^NZ-/.test(value))!;
    expect(number).toBeTruthy();
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex,nofollow');
    await expect(page.locator('body')).not.toContainText('+237699000004');
    await expect(page.locator('body')).toContainText('•••• 0004');
    const progress = page.getByRole('region', { name: 'Les étapes de votre commande', exact: true });
    await expect(progress.locator('li[aria-current="step"]')).toContainText('Confirmation par l’équipe');
    const finances = page.getByRole('complementary', { name: 'Total définitif', exact: true });
    await expect(finances.getByText('À confirmer par téléphone', { exact: true })).toBeVisible();
    await expect(page.locator('main address')).toContainText('Douala, Bonaberi Rail, Marché ChaCha 223');
    const contact = page.getByRole('link', { name: 'Contacter l’équipe sur WhatsApp', exact: true });
    const contactUrl = new URL((await contact.getAttribute('href'))!);
    expect(contactUrl.origin + contactUrl.pathname).toBe('https://wa.me/23755278597');
    expect(contactUrl.searchParams.get('text')).toContain(number);
    expect(contactUrl.searchParams.get('text')).not.toContain('signature=');
    await expect(contact).toHaveAttribute('rel', 'noopener noreferrer');
    await page.getByRole('button', { name: 'Actualiser le suivi', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Actualiser le suivi', exact: true })).toBeEnabled();
    await expect(page).toHaveURL(url);
    const response = await page.request.get(url);
    expect(response.headers()['cache-control']).toContain('no-store');
    expect((await page.request.get(url.split('?')[0])).status()).toBe(403);
    const tampered = new URL(url); tampered.searchParams.set('signature', '0');
    expect((await page.request.get(tampered.toString())).status()).toBe(403);
    await expect(page.locator('body')).not.toContainText(/Cameroun|Canada|\bXAF\b|\bFCFA\b|francs?\s+CFA/i);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({ path: test.info().outputPath('confirmation.png'), fullPage: true });
    expect(errors).toEqual([]);
});

test('suivi des paiements, préparation, remise, remboursement et demandes clôturées', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    const url = await placeOrder(page);
    // Each UI state is layered over a real authorized summary. No payment or handover is written by this fixture.
    let state: Record<string, unknown> = {};
    await page.route('**/commandes/**', async (route) => {
        if (route.request().headers()['x-inertia'] !== 'true') return route.continue();
        const response = await route.fetch();
        const body = await response.json();
        body.props.order = { ...body.props.order, ...state };
        await route.fulfill({ response, json: body });
    });
    async function refresh(next: Record<string, unknown>) {
        state = { ...state, ...next };
        await page.getByRole('button', { name: 'Actualiser le suivi', exact: true }).click();
        await expect(page.getByRole('button', { name: 'Actualiser le suivi', exact: true })).toBeEnabled();
        await expect(page).toHaveURL(url);
    }
    await refresh({ status_code: 'confirmed', status: 'Confirmée', total_minor: 25000, shipping_fee_minor: 0, tax_minor: 0,
        payment_status_code: 'partial', payment_status: 'Partiellement payé', verified_paid_minor: 10000, verified_net_minor: 10000, outstanding_minor: 15000 });
    await expect(page.getByText('Partiellement payé', { exact: true })).toBeVisible();
    const ledger = page.getByRole('region', { name: 'Paiements et remboursements', exact: true });
    await expect(ledger.getByText('Solde à régler', { exact: true }).locator('..').locator('dd')).toHaveText(/15\s*000/);
    await refresh({ payment_status_code: 'paid', payment_status: 'Payé', verified_paid_minor: 25000, verified_net_minor: 25000,
        outstanding_minor: 0, fulfillment_status_code: 'packed', fulfillment_status: 'Emballé' });
    await expect(page.getByRole('region', { name: 'Les étapes de votre commande', exact: true }).locator('li[aria-current="step"]')).toContainText('Retrait enregistré');
    await refresh({ status_code: 'completed', status: 'Remise effectuée', fulfillment_status_code: 'collected', fulfillment_status: 'Retiré',
        handover: { at: '2026-10-28T14:00:00Z', carrier: null, reference: 'RETRAIT-E2E' } });
    await expect(page.getByRole('heading', { name: 'Suivi de votre commande', exact: true })).toBeVisible();
    await expect(page.getByText('Retiré', { exact: true })).toBeVisible();
    await expect(page.getByText('RETRAIT-E2E', { exact: true })).toBeVisible();
    await expect(page.getByRole('region', { name: 'Les étapes de votre commande', exact: true }).locator('li[aria-current="step"]')).toHaveCount(0);
    await refresh({ method: 'delivery', fulfillment_status_code: 'shipped', fulfillment_status: 'Expédié',
        fulfillment: { name: 'Zone de test', address: 'Adresse fictive de test, Douala', country: 'CM' },
        handover: { at: '2026-10-28T14:00:00Z', carrier: 'Transport de test', reference: 'EXPEDITION-E2E' } });
    await expect(page.getByText('Expédié', { exact: true })).toBeVisible();
    await expect(page.getByText('EXPEDITION-E2E', { exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Expédition enregistrée', exact: true })).toBeVisible();
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({ path: test.info().outputPath('suivi-expedition.png'), fullPage: true });
    await refresh({ payment_status_code: 'refunded', payment_status: 'Remboursé', verified_refunded_minor: 25000,
        verified_net_minor: 0, outstanding_minor: 25000 });
    await expect(page.getByText('Remboursé', { exact: true })).toBeVisible();
    await expect(ledger.getByText('Solde à régler', { exact: true })).toHaveCount(0);
    await expect(ledger.getByText('Écart avec le total', { exact: true }).locator('..').locator('dd')).toHaveText(/25\s*000/);
    await expect(page.getByText('EXPEDITION-E2E', { exact: true })).toBeVisible();
    await refresh({ status_code: 'expired', status: 'Expirée', payment_status_code: 'unpaid', payment_status: 'Non payé',
        fulfillment_status_code: 'unfulfilled', fulfillment_status: 'Non remis', handover: null, total_minor: null,
        verified_paid_minor: 0, verified_refunded_minor: 0, verified_net_minor: 0, outstanding_minor: null });
    await expect(page.locator('main')).toContainText('Cette demande a expiré.');
    await expect(page.getByRole('region', { name: 'Les étapes de votre commande', exact: true })).toHaveCount(0);
    await expect(page.getByText('Réservation jusqu’au', { exact: true })).toHaveCount(0);
    await refresh({ status_code: 'cancelled', status: 'Annulée' });
    await expect(page.locator('main')).toContainText('Cette commande est annulée.');
    await expect(page.getByText('Solde à régler', { exact: true })).toHaveCount(0);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
});
