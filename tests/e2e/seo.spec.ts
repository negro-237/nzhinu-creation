import { test, expect } from '@playwright/test';

test('métadonnées et données structurées restent cohérentes après navigation Inertia', async ({ page }) => {
    await page.addInitScript(() => {
        (window as any).seoViolations = [];
        document.addEventListener('securitypolicyviolation', (event) => (window as any).seoViolations.push(event.violatedDirective));
    });
    await page.goto('/');
    await expect(page).toHaveTitle('Mode et accessoires africains à Douala | Nzhinu Création');
    await expect(page.locator('link[rel="canonical"]')).toHaveCount(1);
    await expect(page.locator('meta[property="og:url"]')).toHaveAttribute('content', 'http://127.0.0.1:8765/');
    await page.getByRole('link', { name: 'La boutique', exact: true }).click();
    await expect(page).toHaveURL(/\/boutique$/);
    await expect(page.getByRole('heading', { name: 'La boutique', exact: true })).toBeVisible();
    await page.getByRole('link', { name: 'Découvrir la pièce : Pièce E2E', exact: true }).click();
    await expect(page).toHaveTitle('Pièce E2E | Nzhinu Création');
    await expect(page.locator('link[rel="canonical"]')).toHaveCount(1);
    await expect(page.locator('meta[property="og:url"]')).toHaveAttribute('content', 'http://127.0.0.1:8765/produits/piece-e2e');
    await expect(page.locator('meta[name="description"]')).toHaveCount(1);
    const graph = JSON.parse(await page.locator('script[type="application/ld+json"]').textContent() || '{}');
    expect(graph['@graph'].find((node: any) => node['@type'] === 'Product').name).toBe('Pièce E2E');
    await page.getByRole('contentinfo').getByRole('link', { name: 'Toutes les pièces', exact: true }).click();
    await expect(page).toHaveURL(/\/boutique$/);
    await expect(page.getByRole('heading', { name: 'La boutique', exact: true })).toBeVisible();
    const filters = page.getByRole('button', { name: 'Filtres et recherche' });
    if (await filters.isVisible()) await filters.click();
    await page.getByRole('combobox', { name: /^Catégorie/ }).selectOption({ label: 'Vêtements' });
    await page.getByRole('button', { name: 'Appliquer les filtres', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Vêtements', exact: true })).toBeVisible();
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', 'http://127.0.0.1:8765/boutique?category=vetements');
    await page.getByRole('contentinfo').getByRole('link', { name: 'Voir mon panier', exact: true }).click();
    await expect(page).toHaveURL(/\/panier$/);
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex,nofollow');
    await expect(page.locator('script[type="application/ld+json"]')).toHaveCount(0);
    expect(await page.evaluate(() => (window as any).seoViolations)).toEqual([]);
});

test('les pages essentielles et leurs liens sont lisibles sans JavaScript', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto('http://127.0.0.1:8765/');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page.getByText('Douala, Bonaberi Rail, Marché ChaCha 223', { exact: true })).toBeVisible();
    await page.goto('http://127.0.0.1:8765/boutique?category=vetements');
    await expect(page.getByRole('heading', { name: 'Vêtements', exact: true })).toBeVisible();
    await page.getByRole('link', { name: 'Découvrir la pièce : Pièce E2E', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Pièce E2E', exact: true })).toBeVisible();
    await expect(page.locator('main')).toContainText(/25\s*000/);
    await expect(page.locator('[data-interactive-notice]')).toContainText('Activez JavaScript');
    await context.close();
});
