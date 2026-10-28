import { test, expect } from '@playwright/test';

test('catégories après les produits, icône et lien vers le catalogue filtré', async ({ page }) => {
    await page.goto('/');
    const section = page.getByRole('region', { name: 'Explorez nos catégories', exact: true });
    await section.scrollIntoViewIfNeeded();
    await expect(section).toBeVisible();
    const card = section.getByRole('link', { name: 'Découvrir la catégorie : Vêtements', exact: true });
    await expect(card).toBeVisible();
    await expect(card.locator('svg')).toHaveAttribute('aria-hidden', 'true');
    await expect(card).toContainText('Des tenues et des imprimés');
    expect(await section.evaluate((element) => Boolean(document.getElementById('featured-title')!.compareDocumentPosition(element) & Node.DOCUMENT_POSITION_FOLLOWING))).toBe(true);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.screenshot({ path: test.info().outputPath('categories-accueil.png'), fullPage: true });
    await card.click();
    await expect(page).toHaveURL(/\/boutique\?category=vetements$/);
    await expect(page.getByRole('link', { name: 'Découvrir la pièce : Pièce E2E' })).toBeVisible();
});
