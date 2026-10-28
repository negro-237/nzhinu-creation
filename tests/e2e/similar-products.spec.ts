import { test, expect } from '@playwright/test';

test('articles similaires visibles et navigation entre les pièces', async ({ page }) => {
    await page.goto('/produits/piece-e2e');
    const similar = page.getByRole('region', { name: 'Vous aimerez aussi', exact: true });
    await similar.scrollIntoViewIfNeeded();
    await expect(similar).toBeVisible();
    await expect(similar.getByRole('link', { name: 'Découvrir la pièce : Pièce E2E', exact: true })).toHaveCount(0);
    await expect(similar.getByRole('link', { name: 'Découvrir la pièce : Création assortie', exact: true })).toBeVisible();
    await expect(similar.getByRole('link', { name: 'Voir la catégorie', exact: false })).toHaveAttribute('href', /category=/);
    await page.screenshot({ path: test.info().outputPath('articles-similaires.png'), fullPage: true });
    await similar.getByRole('link', { name: 'Découvrir la pièce : Création assortie', exact: true }).click();
    await expect(page).toHaveURL(/\/produits\/creation-assortie$/);
    await expect(page.getByRole('heading', { name: 'Création assortie', exact: true })).toBeVisible();
    await expect(page.getByRole('region', { name: 'Vous aimerez aussi', exact: true }).getByRole('link', { name: 'Découvrir la pièce : Pièce E2E', exact: true })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});
