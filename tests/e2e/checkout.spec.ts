import { test, expect, type Page } from '@playwright/test';

test.skip(process.env.E2E_CAMEROON_ONLY !== '1', 'Recette de la boutique publique actuelle.');

async function prepare(page: Page) {
    await page.goto('/produits/piece-e2e');
    await page.getByLabel('Stock de départ', { exact: true }).selectOption({ index: 1 });
    await page.getByRole('button', { name: 'Mettre au panier', exact: true }).click();
    await page.getByRole('link', { name: 'Demander ma commande', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Votre demande de commande', exact: true })).toBeVisible();
}

test('livraison, estimation, erreurs accessibles et informations conservées au changement de mode', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.emulateMedia({ reducedMotion: 'reduce' });
    // A synthetic zone/photo enrich the initial GET only. The real error response withdraws that zone.
    await page.route('**/commander', async (route) => {
        if (route.request().method() !== 'GET') return route.continue();
        const response = await route.fetch();
        const body = await response.json();
        body.props.checkout.zones = [{ id: 999999, name: 'Zone de démonstration', estimated_fee_minor: 1500 }];
        body.props.checkout.cart.items.forEach((item: any) => { item.image = { url: '/images/demo/01-front.webp', alt: item.name }; });
        await route.fulfill({ response, json: body });
    });
    await prepare(page);
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex,nofollow');
    await page.getByLabel('Nom complet', { exact: true }).fill('Client recette livraison');
    await expect(page.locator('.shop-whatsapp')).toBeVisible();
    const contact = await page.locator('.shop-whatsapp').boundingBox();
    const nameField = await page.getByLabel('Nom complet', { exact: true }).boundingBox();
    expect(contact!.y + contact!.height <= nameField!.y || contact!.x >= nameField!.x + nameField!.width).toBe(true);
    await page.getByLabel('Téléphone', { exact: true }).fill('+237699000001');
    await page.getByLabel('Adresse', { exact: true }).fill('Adresse fictive de test');
    await page.getByLabel('Ville', { exact: true }).fill('Douala');
    await page.getByLabel('Zone de livraison', { exact: true }).selectOption('999999');
    const summary = page.getByRole('complementary', { name: 'Votre sélection', exact: true });
    await expect(summary.locator('dd').last()).toHaveText(/1\s*500/);
    await expect(summary.locator('dd').first()).toHaveText(/25\s*000/);
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({ path: test.info().outputPath('commande-livraison.png'), fullPage: true });
    await page.getByLabel('Téléphone', { exact: true }).fill('abcdefgh');
    await page.getByRole('button', { name: 'Envoyer ma demande', exact: true }).click();
    const errorsSummary = page.getByRole('alert').filter({ hasText: 'Vérifiez les informations suivantes' });
    await expect(errorsSummary).toContainText('Le numéro de téléphone n’est pas valide.');
    await expect(errorsSummary).toBeFocused();
    await expect(page.locator('.shop-whatsapp')).toBeVisible();
    await expect(page.getByLabel('Téléphone', { exact: true })).toHaveAttribute('aria-invalid', 'true');
    await expect(page.getByLabel('Nom complet', { exact: true })).toHaveValue('Client recette livraison');
    await expect(page.getByLabel('Zone de livraison', { exact: true })).toHaveValue('');
    await page.getByRole('radio', { name: 'Retrait', exact: true }).check();
    await expect(page.getByLabel('Adresse', { exact: true })).toHaveCount(0);
    await page.getByLabel('Point de retrait', { exact: true }).selectOption({ index: 1 });
    await expect(page.locator('main address')).toContainText('Marché ChaCha 223');
    await expect(summary.locator('dd').last()).toHaveText('À confirmer');
    await page.getByRole('radio', { name: 'Livraison', exact: true }).check();
    await expect(page.getByLabel('Adresse', { exact: true })).toHaveValue('Adresse fictive de test');
    await expect(page.getByLabel('Ville', { exact: true })).toHaveValue('Douala');
    await expect(page.getByLabel('Zone de livraison', { exact: true })).toHaveValue('');
    await page.getByLabel('Zone de livraison', { exact: true }).selectOption('');
    await page.getByLabel('Téléphone', { exact: true }).fill('+237699000001');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.getByRole('button', { name: 'Envoyer ma demande', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Demande reçue', exact: true })).toBeVisible();
    await expect(page).toHaveURL(/\/commandes\/.+signature=/);
    expect(errors).toEqual([]);
});

test('retrait sans adresse de livraison, choix requis et demande réelle', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await prepare(page);
    await page.getByLabel('Nom complet', { exact: true }).fill('Client recette retrait');
    await page.getByLabel('Téléphone', { exact: true }).fill('+237699000002');
    await page.getByRole('radio', { name: 'Retrait', exact: true }).check();
    const point = page.getByLabel('Point de retrait', { exact: true });
    await expect(point).toHaveAttribute('required', '');
    await expect(page.getByLabel('Adresse', { exact: true })).toHaveCount(0);
    let submissions = 0;
    page.on('request', (request) => { if (request.method() === 'POST' && request.url().endsWith('/commander')) submissions++; });
    await page.getByRole('button', { name: 'Envoyer ma demande', exact: true }).click();
    expect(submissions).toBe(0);
    expect(await point.evaluate((element: HTMLSelectElement) => element.checkValidity())).toBe(false);
    await point.selectOption({ index: 1 });
    await expect(page.locator('main address')).toContainText('Douala, Bonaberi Rail, Marché ChaCha 223');
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({ path: test.info().outputPath('commande-retrait.png'), fullPage: true });
    await page.getByRole('button', { name: 'Envoyer ma demande', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Demande reçue', exact: true })).toBeVisible();
    expect(submissions).toBe(1);
    await expect(page.locator('main')).toContainText('Marché ChaCha 223');
});

test('retrait désactivé lorsqu’aucun point compatible n’est proposé', async ({ page }) => {
    await page.route('**/commander', async (route) => {
        const response = await route.fetch();
        const body = await response.json();
        body.props.checkout.points = [];
        body.props.checkout.zones = [];
        await route.fulfill({ response, json: body });
    });
    await prepare(page);
    await expect(page.getByRole('radio', { name: 'Retrait', exact: true })).toBeDisabled();
    await expect(page.getByRole('radio', { name: 'Livraison', exact: true })).toBeChecked();
    await expect(page.locator('main')).toContainText('Aucun point de retrait compatible');
    await expect(page.getByLabel('Zone de livraison', { exact: true })).toHaveValue('');
    await expect(page.getByRole('complementary', { name: 'Votre sélection', exact: true }).locator('dd').last()).toHaveText('À confirmer');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
});
