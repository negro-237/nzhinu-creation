import { test, expect, type Page } from '@playwright/test';

test.skip(process.env.E2E_CAMEROON_ONLY !== '1', 'Recette de la boutique publique actuelle.');

async function addPiece(page: Page) {
    await page.goto('/produits/piece-e2e');
    await page.getByLabel('Stock de départ', { exact: true }).selectOption({ index: 1 });
    await page.getByRole('button', { name: 'Mettre au panier', exact: true }).click();
    await expect(page).toHaveURL(/\/panier$/);
    await expect(page.getByRole('heading', { name: 'Panier', exact: true })).toBeVisible();
}

test('panier vide, quantités enregistrées, récapitulatif, coordonnées et retrait d’une ligne', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.emulateMedia({ reducedMotion: 'reduce' });
    const response = await page.goto('/panier');
    expect(response?.headers()['cache-control']).toContain('no-store');
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex,nofollow');
    await expect(page.getByRole('heading', { name: 'Votre panier attend vos découvertes', exact: true })).toBeVisible();
    await page.screenshot({ path: test.info().outputPath('panier-vide.png'), fullPage: true });
    await addPiece(page);
    const summary = page.getByRole('complementary', { name: 'Votre récapitulatif', exact: true });
    const line = page.getByRole('article', { name: 'Pièce E2E', exact: true });
    const quantity = line.getByRole('spinbutton', { name: 'Quantité', exact: true });
    const update = line.getByRole('button', { name: 'Actualiser', exact: true });
    await expect(summary.locator('dd').first()).toHaveText(/25\s*000/);
    await expect(update).toBeDisabled();
    await line.getByRole('button', { name: 'Augmenter la quantité', exact: true }).click();
    await expect(quantity).toHaveValue('2');
    await expect(summary.getByRole('link', { name: 'Demander ma commande', exact: true })).toHaveCount(0);
    await expect(summary.getByRole('button', { name: 'Demander ma commande', exact: true })).toBeDisabled();
    await expect(summary).toContainText('Actualisez les quantités modifiées');
    await expect(summary.locator('dd').first()).toHaveText(/25\s*000/);
    await update.click();
    await expect(quantity).toHaveValue('2');
    await expect(update).toBeDisabled();
    await expect(summary.locator('dd').first()).toHaveText(/50\s*000/);
    await expect(page.locator('[data-cart-count]')).toHaveText('2');
    await expect(summary.getByRole('link', { name: 'Demander ma commande', exact: true })).toBeVisible();
    await quantity.fill(String(Number(await quantity.getAttribute('max')) + 1));
    expect(await quantity.evaluate((input: HTMLInputElement) => input.checkValidity())).toBe(false);
    await expect(summary.getByRole('link', { name: 'Demander ma commande', exact: true })).toHaveCount(0);
    await quantity.fill('2');
    await expect(summary.getByRole('link', { name: 'Demander ma commande', exact: true })).toBeVisible();
    await line.getByRole('button', { name: 'Diminuer la quantité', exact: true }).click();
    await update.click();
    await expect(quantity).toHaveValue('1');
    await expect(summary.locator('dd').first()).toHaveText(/25\s*000/);
    // Add a local photo only to the UI response, leaving the real cart and database intact.
    await page.route('**/panier', async (route) => {
        const response = await route.fetch();
        const body = await response.json();
        body.props.cart.items.forEach((item: any) => { item.image = { url: '/images/demo/01-front.webp', alt: item.name }; });
        await route.fulfill({ response, json: body });
    });
    await page.getByRole('banner').getByRole('link', { name: /^Panier/ }).click();
    await expect(line.getByRole('img')).toBeVisible();
    await expect.poll(() => line.getByRole('img').evaluate((image: HTMLImageElement) => image.complete && image.naturalWidth > 0)).toBe(true);
    await expect(page.locator('body')).not.toContainText(/Cameroun|Canada|\bXAF\b|\bFCFA\b|francs?\s+CFA/i);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.evaluate(() => window.scrollTo(0, 0));
    if (test.info().project.name === 'edge-mobile') {
        const total = await line.getByText('Total de l’article', { exact: true }).locator('..').boundingBox();
        const contact = await page.locator('.shop-whatsapp').boundingBox();
        const overlaps = total!.x < contact!.x + contact!.width && total!.x + total!.width > contact!.x
            && total!.y < contact!.y + contact!.height && total!.y + total!.height > contact!.y;
        expect(overlaps).toBe(false);
    }
    await page.screenshot({ path: test.info().outputPath('panier.png'), fullPage: true });
    await summary.getByRole('link', { name: 'Demander ma commande', exact: true }).click();
    await expect(page).toHaveURL(/\/commander$/);
    await page.getByRole('banner').getByRole('link', { name: /^Panier/ }).click();
    await line.getByRole('button', { name: 'Retirer : Pièce E2E', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Votre panier attend vos découvertes', exact: true })).toBeVisible();
    await expect(page.locator('[data-cart-count]')).toHaveText('0');
    expect(errors).toEqual([]);
});

test('vidage du panier et article indisponible empêchent une demande incohérente', async ({ page }) => {
    await addPiece(page);
    await page.getByRole('button', { name: 'Vider le panier', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Votre panier attend vos découvertes', exact: true })).toBeVisible();
    await addPiece(page);
    await page.route('**/panier', async (route) => {
        const response = await route.fetch();
        const body = await response.json();
        if (body.props.cart.items.length) {
            body.props.cart.has_issues = true;
            body.props.cart.subtotal_minor = null;
            body.props.cart.items[0] = { ...body.props.cart.items[0], name: 'Article indisponible', slug: null,
                variant: null, location: null, available: 0, price_minor: null, line_total_minor: null, image: null, valid: false };
        }
        await route.fulfill({ response, json: body });
    });
    await page.getByRole('banner').getByRole('link', { name: /^Panier/ }).click();
    const summary = page.getByRole('complementary', { name: 'Votre récapitulatif', exact: true });
    await expect(page.getByRole('heading', { name: 'Article indisponible', exact: true })).toBeVisible();
    await expect(summary.locator('dd').first()).toHaveText('—');
    await expect(summary.getByRole('link', { name: 'Demander ma commande', exact: true })).toHaveCount(0);
    await expect(summary.getByRole('button', { name: 'Demander ma commande', exact: true })).toBeDisabled();
    await expect(page.getByRole('article', { name: 'Article indisponible', exact: true }).getByRole('alert')).toContainText('n’est plus disponible');
    await page.getByRole('button', { name: 'Retirer : Article indisponible', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Votre panier attend vos découvertes', exact: true })).toBeVisible();
});
