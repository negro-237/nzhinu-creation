import { test, expect, type Page } from '@playwright/test';

async function openFilters(page: Page) {
    const toggle = page.getByRole('button', { name: 'Filtres et recherche', exact: true });
    if (await toggle.isVisible() && await toggle.getAttribute('aria-expanded') === 'false') await toggle.click();
}

test('filtres cumulés, tri, retrait individuel et réinitialisation restent synchronisés', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/boutique');
    await expect(page.getByRole('heading', { name: 'La boutique', exact: true })).toBeVisible();
    await openFilters(page);
    await page.getByRole('searchbox').fill('Pièce E2E');
    await page.getByRole('combobox', { name: /^Catégorie/ }).selectOption('vetements');
    await page.getByLabel('Prix minimum', { exact: true }).fill('1');
    await page.getByRole('checkbox', { name: 'En stock uniquement', exact: true }).check();
    await page.getByRole('button', { name: 'Appliquer les filtres', exact: true }).click();
    const results = page.getByRole('region', { name: 'Les pièces', exact: true });
    await expect(results.getByRole('status')).toHaveText('1 résultat(s)');
    await expect(page.getByRole('heading', { name: 'Vêtements', exact: true })).toBeVisible();
    const chips = page.getByRole('navigation', { name: 'Votre sélection de filtres', exact: true });
    await chips.getByRole('link', { name: 'Retirer le filtre : Prix minimum : 1', exact: true }).click();
    await expect(page.getByLabel('Prix minimum', { exact: true })).toHaveValue('');
    expect(new URL(page.url()).searchParams.has('min_price')).toBe(false);
    await expect(page.getByRole('searchbox')).toHaveValue('Pièce E2E');
    await expect(page.getByRole('checkbox', { name: 'En stock uniquement', exact: true })).toBeChecked();
    await page.getByRole('combobox', { name: /^Trier par/ }).selectOption('price_desc');
    await expect(page).toHaveURL(/sort=price_desc/);
    await expect(results.getByRole('status')).toHaveText('1 résultat(s)');
    const shortcuts = page.getByRole('navigation', { name: 'Parcourir les catégories', exact: true });
    await shortcuts.getByRole('link', { name: 'Toutes les pièces', exact: true }).click();
    await expect(page.getByRole('combobox', { name: /^Catégorie/ })).toHaveValue('');
    await expect(page.getByRole('searchbox')).toHaveValue('Pièce E2E');
    await page.goBack();
    await openFilters(page);
    await expect(page.getByRole('combobox', { name: /^Catégorie/ })).toHaveValue('vetements');
    await page.getByRole('searchbox').fill('Aucun-article-xyz');
    await page.getByRole('button', { name: 'Appliquer les filtres', exact: true }).click();
    await expect(results.getByRole('heading', { name: 'Votre pièce se fait désirer', exact: true })).toBeVisible();
    await results.getByRole('link', { name: 'Toutes les pièces', exact: true }).click();
    await expect(page).toHaveURL(/\/boutique$/);
    await openFilters(page);
    await expect(page.getByRole('searchbox')).toHaveValue('');
    await expect(page.getByRole('checkbox', { name: 'En stock uniquement', exact: true })).not.toBeChecked();
    await expect(page.getByRole('combobox', { name: /^Trier par/ })).toHaveValue('newest');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    expect(errors).toEqual([]);
});

test('présentation du catalogue avec photos, raccourcis et navigation produit', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');
    await page.route('**/boutique', async (route) => {
        const response = await route.fetch();
        const body = await response.json();
        body.props.products.items.forEach((product: any, index: number) => {
            product.image = { url: `/images/demo/0${index + 1}-front.webp`, alt: product.name };
        });
        await route.fulfill({ response, json: body });
    });
    await page.getByRole('link', { name: 'La boutique', exact: true }).click();
    const shortcuts = page.getByRole('navigation', { name: 'Parcourir les catégories', exact: true });
    await expect(shortcuts.getByRole('link', { name: 'Toutes les pièces', exact: true })).toHaveAttribute('aria-current', 'page');
    const results = page.getByRole('region', { name: 'Les pièces', exact: true });
    for (const photo of await results.getByRole('img').all()) {
        await expect.poll(() => photo.evaluate((image: HTMLImageElement) => image.complete && image.naturalWidth > 0)).toBe(true);
    }
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: test.info().outputPath('boutique.png'), fullPage: true });
    if (test.info().project.name === 'edge-mobile') {
        await openFilters(page);
        await expect(page.getByRole('searchbox')).toBeVisible();
        await page.screenshot({ path: test.info().outputPath('boutique-filtres.png'), fullPage: true });
        await page.getByRole('button', { name: 'Filtres et recherche', exact: true }).click();
    }
    await page.getByRole('link', { name: 'Découvrir la pièce : Pièce E2E', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Pièce E2E', exact: true })).toBeVisible();
});
