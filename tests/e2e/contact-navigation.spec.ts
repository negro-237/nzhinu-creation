import { test, expect } from '@playwright/test';

test.skip(process.env.E2E_CAMEROON_ONLY !== '1', 'Recette de la boutique publique actuelle.');

test('contact WhatsApp, menu clavier et accueil avec photo du catalogue', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await page.goto('/');
    const contact = page.getByRole('link', { name: 'Contacter la boutique sur WhatsApp — nouvel onglet', exact: true });
    await expect(contact).toBeVisible();
    const contactUrl = new URL((await contact.getAttribute('href'))!);
    expect(contactUrl.origin + contactUrl.pathname).toBe('https://wa.me/23755278597');
    expect(contactUrl.searchParams.get('text')).toContain('Bonjour');
    await expect(contact).toHaveAttribute('target', '_blank');
    await expect(contact).toHaveAttribute('rel', 'noopener noreferrer');
    await expect(contact.locator('svg')).toHaveAttribute('aria-hidden', 'true');
    await expect(contact.locator('.shop-whatsapp-pulse')).toHaveCSS('animation-iteration-count', '3');
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await expect(contact.locator('.shop-whatsapp-pulse')).toHaveCSS('animation-name', 'none');
    const header = page.locator('header');
    const open = header.locator('button[aria-controls="shop-menu"]');
    await open.click();
    await expect(open).toHaveAttribute('aria-expanded', 'true');
    const category = header.getByRole('link', { name: 'Vêtements', exact: true });
    await category.focus();
    await page.keyboard.press('Escape');
    await expect(open).toHaveAttribute('aria-expanded', 'false');
    await expect(open).toBeFocused();
    await open.click();
    await category.click();
    await expect(page).toHaveURL(/\/boutique\?category=vetements$/);
    await expect(open).toHaveAttribute('aria-expanded', 'false');
    // Supply an existing local photo through the real homepage response, without changing stored catalog data.
    await page.route('http://127.0.0.1:8765/', async (route) => {
        const response = await route.fetch();
        const body = await response.json();
        body.props.featured[0].image = { url: '/images/demo/01-front.webp', alt: 'Pièce de la sélection Nzhinu' };
        await route.fulfill({ response, json: body });
    });
    await header.getByRole('link', { name: 'Nzhinu Création', exact: true }).click();
    await expect(page.locator('#brand-title')).toBeVisible();
    const photo = page.locator('figure img').first();
    await expect.poll(() => photo.evaluate((image: HTMLImageElement) => image.complete && image.naturalWidth > 0)).toBe(true);
    await expect(photo).toHaveAttribute('src', '/images/demo/01-front.webp');
    await page.screenshot({ path: test.info().outputPath('accueil-professionnel.png'), fullPage: true });
    await expect(page.getByRole('contentinfo').getByRole('link', { name: 'infos@nhinu-creation.com', exact: true })).toHaveAttribute('href', 'mailto:infos@nhinu-creation.com');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    expect(errors).toEqual([]);
});
