import { test, expect } from '@playwright/test';

test('animations natives et préférence de mouvement réduit', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await page.goto('/');
    const hero = page.locator('#brand-title').locator('..');
    await expect(hero).toHaveAttribute('data-reveal', 'true');
    await expect(hero).toBeVisible();
    await expect.poll(() => hero.evaluate((element) => getComputedStyle(element).opacity)).toBe('1');

    // Observe the native animation started on the first viewport entry, with no hidden-content CSS.
    await page.evaluate(() => {
        const original = Element.prototype.animate;
        (window as any).revealAnimations = 0;
        Element.prototype.animate = function (...args: Parameters<Element['animate']>) {
            if (this.hasAttribute('data-reveal')) (window as any).revealAnimations++;
            return original.apply(this, args);
        };
    });
    const market = page.locator('#marches li').first();
    await market.scrollIntoViewIfNeeded();
    await expect.poll(() => page.evaluate(() => (window as any).revealAnimations)).toBeGreaterThan(0);
    await expect.poll(() => market.evaluate((element) => getComputedStyle(element).opacity)).toBe('1');

    await page.emulateMedia({ reducedMotion: 'reduce' });
    await expect.poll(() => page.evaluate(() => document.getAnimations().filter((animation) => animation.playState === 'running').length)).toBe(0);
    const count = await page.evaluate(() => (window as any).revealAnimations);
    await page.getByRole('link', { name: 'La boutique', exact: true }).click();
    await expect(page).toHaveURL(/\/boutique$/);
    const card = page.locator('article[data-reveal]').first();
    await expect(card).toBeVisible();
    await expect(card).toHaveCSS('opacity', '1');
    expect(await page.evaluate(() => (window as any).revealAnimations)).toBe(count);
    await expect(page.locator('main')).toHaveCSS('animation-name', 'none');
    expect(await page.evaluate(() => document.getAnimations().filter((animation) => animation.playState === 'running').length)).toBe(0);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    expect(errors).toEqual([]);
});

test('contenu accessible lorsque les API d’animation sont absentes', async ({ page }) => {
    await page.addInitScript(() => {
        Object.defineProperty(window, 'IntersectionObserver', { value: undefined, configurable: true });
    });
    await page.goto('/');
    await expect(page.locator('#brand-title')).toBeVisible();
    await page.getByRole('link', { name: 'La boutique', exact: true }).click();
    await expect(page.locator('article[data-reveal]').first()).toBeVisible();
});
