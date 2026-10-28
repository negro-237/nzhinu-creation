import { test, expect, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';

test('contenus publiés, logo, marché et protection contre les scripts', async ({ page }) => {
    const response = await page.goto('/');
    expect(response?.headers()['x-frame-options']).toBe('DENY');
    const withoutToken = await page.request.post('/marche', { data: { code: 'CA' } });
    expect(withoutToken.status()).toBe(419);
    await expect(page.getByRole('heading', { name: 'Bienvenue E2E' })).toBeVisible();
    await expect(page.locator('header img[src="/images/logo-transparent.png"]')).toBeVisible();
    const facebook = page.getByRole('link', { name: 'Facebook — nouvel onglet', exact: true });
    await expect(facebook).toHaveAttribute('href', 'https://www.facebook.com/profile.php?id=100071095101171');
    await expect(facebook).toHaveAttribute('target', '_blank');
    await expect(facebook).toHaveAttribute('rel', 'noopener noreferrer');
    await expect(page.getByRole('link', { name: 'TikTok — nouvel onglet', exact: true })).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'YouTube — nouvel onglet', exact: true })).toHaveCount(0);
    expect(await page.locator('header img').evaluate((image: HTMLImageElement) => image.complete && image.naturalWidth > 0)).toBe(true);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.screenshot({ path: test.info().outputPath('accueil.png'), fullPage: true });
    await page.getByRole('link', { name: 'Notre histoire E2E' }).click();
    await expect(page.getByRole('heading', { name: 'Notre maison E2E' })).toBeVisible();
    await expect(page.getByText('<script>window.cmsInjected=true</script>', { exact: false })).toBeVisible();
    expect(await page.evaluate(() => (window as any).cmsInjected)).toBeUndefined();
    await page.getByLabel('Votre marché').selectOption('CM');
    await expect(page.getByLabel('Votre marché')).toHaveValue('CM');
    await expect(page.getByRole('link', { name: 'Notre maison E2E' })).toBeVisible();
});

async function submitModal(page: Page, _label: string) {
    const submit = page.getByRole('button', { name: /Confirmer|Soumettre/ });
    await submit.click();
    await expect(submit).not.toBeVisible();
}

async function login(page: Page, role = 'admin') {
    page.setDefaultTimeout(15000);
    await page.goto('/admin/login');
    await page.getByLabel(/mail/i).fill(`e2e-${role}@example.test`);
    await page.getByLabel(/Mot de passe/).fill('E2e-Only-123456!');
    await page.getByRole('button', { name: 'Connexion', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/?$/);
}

test('panier : annuler puis confirmer le changement de marché', async ({ page }) => {
    await page.goto('/produits/piece-e2e');
    await page.getByLabel('Stock de départ').selectOption({ index: 1 });
    await page.getByRole('button', { name: 'Mettre au panier' }).click();
    await expect(page).toHaveURL(/\/panier$/);
    await page.getByLabel('Votre marché').selectOption('CM');
    await expect(page.getByRole('alert')).toContainText('Changer de marché videra votre panier');
    await page.getByRole('button', { name: 'Annuler', exact: true }).click();
    await expect(page.getByLabel('Votre marché')).toHaveValue('CA');
    await page.getByLabel('Votre marché').selectOption('CM');
    await page.getByRole('button', { name: 'Changer de marché et vider le panier', exact: true }).click();
    await expect(page.getByLabel('Votre marché')).toHaveValue('CM');
    await page.goto('/panier');
    await expect(page.getByRole('heading', { name: 'Votre panier attend vos découvertes' })).toBeVisible();
});

for (const market of ['CA', 'CM']) for (const method of ['delivery', 'pickup']) {
    test(`commande invitée et remise après paiement vérifié : ${market} ${method}`, async ({ browser, page }) => {
        const errors: string[] = []; page.on('pageerror', (error) => errors.push(error.message));
        const fee = method === 'pickup' ? '0' : market === 'CA' ? '5' : '1500';
        const total = market === 'CA' ? method === 'pickup' ? '50' : '55' : method === 'pickup' ? '25000' : '26500';
        if (market === 'CM') {
            await page.goto('/'); await page.getByLabel('Votre marché').selectOption('CM');
            await expect(page.getByLabel('Votre marché')).toHaveValue('CM');
        }
        await page.goto('/produits/piece-e2e');
        await page.getByLabel('Stock de départ').selectOption({ index: 1 });
        await page.getByRole('button', { name: 'Mettre au panier' }).click();
        await page.getByRole('link', { name: 'Demander ma commande' }).click();
        await page.getByLabel('Nom complet', { exact: true }).fill('Client navigateur');
        await page.getByLabel('Téléphone', { exact: true }).fill(market === 'CA' ? '+15145550123' : '+237699000000');
        if (method === 'pickup') {
            await page.getByRole('radio', { name: 'Retrait', exact: true }).check();
            await page.getByLabel('Point de retrait').selectOption({ index: 1 });
        } else {
            await page.getByLabel('Adresse', { exact: true }).fill('Adresse fictive navigateur');
            await page.getByLabel('Ville', { exact: true }).fill('Ville test');
        }
        await page.getByRole('button', { name: /Envoyer ma demande/ }).click();
        await expect(page).toHaveURL(/\/commandes\/.+signature=/);
        const privateUrl = page.url();
        const number = (await page.locator('p').allTextContents()).find((text) => /^NZ-/.test(text))!;
        expect(number).toBeTruthy();
        const adminContext = await browser.newContext(); const admin = await adminContext.newPage();
        await login(admin);
        await admin.goto('/admin/orders');
        const row = admin.getByRole('row').filter({ hasText: number });
        await row.getByRole('button', { name: 'Appel confirmé', exact: true }).click();
        await admin.getByLabel('Frais définitifs dans la devise de la commande').fill(fee);
        await admin.getByLabel('Montant des taxes après revue').fill('0');
        await admin.getByLabel('Compte rendu de l’appel').fill('Appel navigateur confirmé');
        await submitModal(admin, 'Appel confirmé');
        await row.getByRole('button', { name: 'Préparer', exact: true }).click();
        await admin.getByLabel('Contrôle de la préparation').fill('Tentative avant paiement');
        const [blocked] = await Promise.all([admin.waitForResponse((response) => /\/livewire[^/]*\/update/.test(response.url()) && response.request().method() === 'POST'), admin.getByRole('dialog').getByRole('button', { name: 'Soumettre', exact: true }).click()]);
        expect(blocked.status()).toBe(200);
        expect(blocked.headers()['cache-control']).toContain('no-store');
        await expect(row.getByRole('button', { name: 'Non remis', exact: true })).toBeVisible();
        await expect(row.getByRole('button', { name: 'Expédier ou remettre', exact: true })).toHaveCount(0);
        await admin.getByRole('dialog').getByRole('button', { name: 'Annuler', exact: true }).click();
        const amounts = market === 'CM' && method === 'delivery' ? ['10000', '16500'] : [total];
        for (const [index, amount] of amounts.entries()) {
        await row.getByRole('button', { name: 'Déclarer un paiement', exact: true }).click();
        await admin.getByLabel('Montant dans la devise de la commande').fill(amount);
        await admin.getByLabel(/Moyen/).selectOption('bank_transfer');
        await admin.getByLabel('Référence unique du mouvement').fill(`BANK-${number}-${index}`);
        await admin.getByLabel('Note interne').fill('Paiement fictif navigateur');
        await submitModal(admin, 'Déclarer un paiement');
        await admin.goto('/admin/order-payments');
        const payment = admin.getByRole('row').filter({ hasText: `BANK-${number}-${index}` });
        if (market === 'CA' && method === 'delivery') {
            const managerContext = await browser.newContext(); const manager = await managerContext.newPage(); await login(manager, 'manager');
            await manager.goto('/admin/order-payments');
            const managerPayment = manager.getByRole('row').filter({ hasText: number });
            await expect(managerPayment).toBeVisible();
            await expect(managerPayment.getByRole('button', { name: 'Vérifier', exact: true })).toHaveCount(0);
            await managerContext.close();
            const staffContext = await browser.newContext(); const staff = await staffContext.newPage(); await login(staff, 'staff');
            const forbidden = await staff.goto('/admin/orders'); expect(forbidden?.status()).toBe(403);
            await staffContext.close();
        }
        await payment.getByRole('button', { name: 'Vérifier', exact: true }).click();
        await admin.getByLabel('Source indépendante').selectOption('bank_statement');
        await admin.getByLabel('Résultat du contrôle').fill('Contrôle indépendant du relevé de test navigateur');
        await submitModal(admin, 'Vérifier');
        await admin.goto('/admin/orders');
        if (amounts.length > 1 && index === 0) {
            await page.goto(privateUrl); await expect(page.getByText('Partiellement payé', { exact: true })).toBeVisible();
            await expect(row.getByRole('button', { name: 'Expédier ou remettre', exact: true })).toHaveCount(0);
        }
        }
        await row.getByRole('button', { name: 'Préparer', exact: true }).click();
        await admin.getByLabel('Contrôle de la préparation').fill('Articles contrôlés et préparés');
        await submitModal(admin, 'Préparer');
        await row.getByRole('button', { name: 'Expédier ou remettre', exact: true }).click();
        if (method === 'delivery') await admin.getByLabel('Transporteur (livraison)', { exact: true }).fill('Transport test');
        await admin.getByLabel('Numéro de suivi ou reçu de retrait').fill(`REMISE-${number}`);
        await admin.getByLabel('Compte rendu de la remise').fill('Remise navigateur effectuée');
        await submitModal(admin, 'Expédier ou remettre');
        await page.goto(privateUrl);
        await expect(page.getByText(method === 'delivery' ? 'Expédié' : 'Retiré', { exact: true })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Suivi de votre commande', exact: true })).toBeVisible();
        await expect(page.getByText('Votre demande a été enregistrée. Notre équipe vous contactera au numéro indiqué.', { exact: true })).toHaveCount(0);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        if (market === 'CM' && method === 'delivery') await page.screenshot({ path: test.info().outputPath('commande-cm.png'), fullPage: true });
        const result = JSON.parse(execFileSync('php', ['tools/e2e-assert.php', number], { env: process.env, windowsHide: true }).toString());
        expect(result.completed).toBe(true); expect(result.reserved).toBe(0); expect(result.notifications).toBe(true);
        const invalid = await page.goto(privateUrl.split('?')[0]); expect(invalid?.status()).toBe(403);
        expect(errors).toEqual([]);
        await adminContext.close();
    });
}
