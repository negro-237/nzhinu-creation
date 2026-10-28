import { test, expect } from '@playwright/test';

test('fiche article, variantes, quantités et ajout réel au panier', async ({ page }) => {
    test.skip(process.env.E2E_CAMEROON_ONLY !== '1', 'Recette de la boutique publique actuelle.');
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/boutique');
    // Enrich only the UI response. The first variant and its stock remain real for the cart submission.
    await page.route('**/produits/piece-e2e', async (route) => {
        const response = await route.fetch();
        const body = await response.json();
        body.props.product.images = [
            { url: '/images/demo/01-front.webp', alt: 'Pièce — vue d’ensemble' },
            { url: '/images/demo/01-detail.webp', alt: 'Pièce — détail' },
        ];
        body.props.product.variants.push({ ...body.props.product.variants[0], id: 999999,
            label: 'Modèle découverte', sku: 'E2E-MOCK', price_minor: 42000, stocks: [] });
        await route.fulfill({ response, json: body });
    });
    await page.getByRole('link', { name: 'Découvrir la pièce : Pièce E2E', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Pièce E2E', exact: true })).toBeVisible();
    if (test.info().project.name === 'edge-mobile') {
        // Compare within one rendered frame after Inertia finishes updating layout and scroll.
        await expect.poll(() => page.evaluate(() => {
            const title = document.querySelector('h1');
            const gallery = document.querySelector('section[aria-label="Galerie du produit"]');
            return !!title && !!gallery && title.getBoundingClientRect().top < gallery.getBoundingClientRect().top;
        })).toBe(true);
    }
    const form = page.getByRole('form', { name: 'Composez votre sélection', exact: true });
    const quantity = form.getByRole('spinbutton', { name: 'Quantité', exact: true });
    const add = form.getByRole('button', { name: 'Mettre au panier', exact: true });
    const plus = form.getByRole('button', { name: 'Augmenter la quantité', exact: true });
    const minus = form.getByRole('button', { name: 'Diminuer la quantité', exact: true });
    await expect(add).toBeDisabled();
    await expect(quantity).toBeDisabled();
    await form.getByLabel('Stock de départ', { exact: true }).selectOption({ index: 1 });
    await expect(add).toBeEnabled();
    await expect(minus).toBeDisabled();
    await plus.click();
    await expect(quantity).toHaveValue('2');
    await minus.click();
    await expect(quantity).toHaveValue('1');
    const maximum = Number(await quantity.getAttribute('max'));
    await quantity.fill(String(maximum));
    await expect(plus).toBeDisabled();
    await quantity.fill(String(maximum + 1));
    expect(await quantity.evaluate((input: HTMLInputElement) => input.checkValidity())).toBe(false);
    await form.getByLabel('Variante', { exact: true }).selectOption('999999');
    await expect(page.locator('main')).toContainText(/42\s*000/);
    await expect(page.locator('main')).toContainText('Référence : E2E-MOCK');
    await expect(add).toBeDisabled();
    await expect(quantity).toBeDisabled();
    await expect(quantity).toHaveValue('1');
    await expect(form.getByLabel('Stock de départ', { exact: true })).toHaveValue('');
    await form.getByLabel('Variante', { exact: true }).selectOption({ index: 0 });
    await expect(page.locator('main')).toContainText(/25\s*000/);
    await expect(add).toBeDisabled();
    const contact = page.getByRole('link', { name: 'Un conseil sur cette pièce ?', exact: true });
    const url = new URL((await contact.getAttribute('href'))!);
    expect(url.origin + url.pathname).toBe('https://wa.me/23755278597');
    expect(url.searchParams.get('text')).toContain('Pièce E2E');
    expect(url.searchParams.get('text')).toContain('/produits/piece-e2e');
    await expect(contact).toHaveAttribute('rel', 'noopener noreferrer');
    await expect(page.getByRole('region', { name: 'La pièce en détail', exact: true })).toBeVisible();
    await expect(page.getByRole('region', { name: 'Vous aimerez aussi', exact: true })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({ path: test.info().outputPath('fiche-article.png'), fullPage: true });
    await form.getByLabel('Stock de départ', { exact: true }).selectOption({ index: 1 });
    await plus.click();
    await add.click();
    await expect(page).toHaveURL(/\/panier$/);
    await expect(page.locator('[data-cart-count]')).toHaveText('2');
    expect(errors).toEqual([]);
});
