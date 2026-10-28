import { test, expect } from '@playwright/test';

test('pied de page structuré et logo réellement transparent', async ({ page }) => {
    await page.goto('/');
    const footer = page.getByRole('contentinfo');
    await footer.scrollIntoViewIfNeeded();
    await expect(footer.getByRole('heading', { name: 'Découvrir', exact: true })).toBeVisible();
    await expect(footer.getByRole('heading', { name: 'Informations pratiques', exact: true })).toBeVisible();
    await expect(footer.getByRole('heading', { name: 'Venez nous rencontrer', exact: true })).toBeVisible();
    await expect(footer.locator('address')).toHaveText('Douala, Bonaberi Rail, Marché ChaCha 223');
    await expect(footer).toContainText('©');
    await expect(footer.getByRole('link', { name: 'Facebook — nouvel onglet', exact: true })).toHaveAttribute('href', 'https://www.facebook.com/profile.php?id=100071095101171');
    await expect(footer.getByRole('link', { name: /TikTok|YouTube/ })).toHaveCount(0);
    const logos = page.locator('img[src="/images/logo-transparent.png"]');
    await expect(logos).toHaveCount(3);
    for (const logo of await logos.all()) {
        await expect.poll(() => logo.evaluate((element: HTMLImageElement) => element.complete && element.naturalWidth > 0)).toBe(true);
        await expect(logo).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
    }
    // Verify the PNG alpha channel itself, not a CSS blend-mode imitation of transparency.
    const alpha = await page.evaluate(async () => {
        const image = await createImageBitmap(await (await fetch('/images/logo-transparent.png')).blob());
        const canvas = document.createElement('canvas'); canvas.width = image.width; canvas.height = image.height;
        const context = canvas.getContext('2d')!; context.drawImage(image, 0, 0);
        const pixels = context.getImageData(0, 0, image.width, image.height).data;
        let transparent = 0; let opaque = 0;
        for (let index = 3; index < pixels.length; index += 4) {
            if (pixels[index] === 0) transparent++;
            if (pixels[index] === 255) opaque++;
        }
        return { corner: pixels[3], transparent: transparent / (pixels.length / 4), opaque };
    });
    expect(alpha.corner).toBe(0);
    expect(alpha.transparent).toBeGreaterThan(0.5);
    expect(alpha.opaque).toBeGreaterThan(1000);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await footer.screenshot({ path: test.info().outputPath('pied-de-page.png') });
    await page.screenshot({ path: test.info().outputPath('accueil-logo-transparent.png'), fullPage: true });
    await footer.getByRole('link', { name: 'Toutes les pièces', exact: true }).click();
    await expect(page).toHaveURL(/\/boutique$/);
    await page.getByRole('contentinfo').getByRole('link', { name: 'Voir mon panier', exact: true }).click();
    await expect(page).toHaveURL(/\/panier$/);
});
