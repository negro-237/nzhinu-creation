import { test, expect } from '@playwright/test';

// UI fixture uses the real product response and local demo assets; it never writes media to the live public disk.
for (const count of [0, 1, 2]) {
    test(`galerie produit : ${count} images`, async ({ page }, info) => {
        await page.goto('/boutique');
        await page.route('**/produits/piece-e2e', async (route) => {
            const response = await route.fetch();
            const body = await response.json();
            body.props.product.images = [
                { url: '/images/demo/01-front.webp', alt: 'Robe — vue d’ensemble' },
                { url: '/images/demo/01-detail.webp', alt: 'Robe — vue de détail' },
            ].slice(0, count);
            await route.fulfill({ response, json: body });
        });
        await page.getByRole('link', { name: 'Découvrir la pièce : Pièce E2E' }).click();
        const gallery = page.getByRole('region', { name: 'Galerie du produit', exact: true });
        await expect(gallery).toBeVisible();
        if (count === 0) await expect(gallery.getByText('Photo à venir')).toBeVisible();
        else {
            await expect.poll(() => gallery.getByRole('img', { name: 'Robe — vue d’ensemble', exact: true }).evaluate((image: HTMLImageElement) => image.complete && image.naturalWidth > 0)).toBe(true);
        }
        if (count < 2) {
            await expect(gallery.getByRole('button')).toHaveCount(count === 0 ? 0 : 1);
        } else {
            const status = gallery.getByRole('status');
            await expect(status).toHaveText('Image 1 sur 2');
            await gallery.getByRole('button', { name: 'Image suivante', exact: true }).click();
            await expect(status).toHaveText('Image 2 sur 2');
            await expect(gallery.getByRole('img', { name: 'Robe — vue de détail', exact: true })).toBeVisible();
            await expect.poll(() => gallery.getByRole('img', { name: 'Robe — vue de détail', exact: true }).evaluate((image: HTMLImageElement) => image.complete && image.naturalWidth > 0)).toBe(true);
            await gallery.getByRole('button', { name: 'Image suivante', exact: true }).click();
            await expect(status).toHaveText('Image 1 sur 2');
            await gallery.getByRole('button', { name: 'Image précédente', exact: true }).click();
            await expect(status).toHaveText('Image 2 sur 2');
            await gallery.getByRole('button', { name: 'Afficher l’image 1 : Robe — vue d’ensemble', exact: true }).click();
            await expect(status).toHaveText('Image 1 sur 2');
            await expect(gallery.getByRole('button', { name: 'Afficher l’image 1 : Robe — vue d’ensemble', exact: true })).toHaveAttribute('aria-pressed', 'true');
            const viewport = gallery.locator('div[tabindex="0"]');
            await viewport.focus();
            await page.keyboard.press('ArrowRight');
            await expect(status).toHaveText('Image 2 sur 2');
            await page.keyboard.press('Home');
            await expect(status).toHaveText('Image 1 sur 2');
            if (info.project.name === 'edge-mobile') {
                await viewport.dispatchEvent('touchstart', { touches: [{ identifier: 1, clientX: 280, clientY: 220 }] });
                await viewport.dispatchEvent('touchend', { changedTouches: [{ identifier: 1, clientX: 80, clientY: 225 }] });
                await expect(status).toHaveText('Image 2 sur 2');
                await viewport.dispatchEvent('touchstart', { touches: [{ identifier: 1, clientX: 100, clientY: 200 }] });
                await viewport.dispatchEvent('touchend', { changedTouches: [{ identifier: 1, clientX: 105, clientY: 350 }] });
                await expect(status).toHaveText('Image 2 sur 2');
            }
            await page.emulateMedia({ reducedMotion: 'reduce' });
            await expect(gallery.locator('[style*="translateX"]')).toHaveCSS('transition-property', 'none');
            await page.screenshot({ path: info.outputPath('galerie.png'), fullPage: true });
        }
        if (count > 0) {
            const opener = gallery.getByRole('button', { name: 'Agrandir la photo', exact: true });
            await opener.click();
            const viewer = page.getByRole('dialog', { name: 'Photos du produit en grand', exact: true });
            await expect(viewer).toBeVisible();
            await expect(viewer.getByRole('button', { name: 'Fermer les photos', exact: true })).toBeFocused();
            await expect(page.locator('body')).toHaveCSS('overflow', 'hidden');
            await viewer.getByRole('button', { name: 'Zoomer sur la photo', exact: true }).click();
            await expect(viewer.getByRole('button', { name: 'Revenir à la vue complète', exact: true })).toHaveAttribute('aria-pressed', 'true');
            const large = viewer.locator('[id$="-large"]');
            expect(await large.evaluate((element) => element.scrollWidth > element.clientWidth)).toBe(true);
            await viewer.getByRole('button', { name: 'Revenir à la vue complète', exact: true }).click();
            if (count === 2) {
                await page.keyboard.press('Home');
                await expect(viewer.getByRole('status')).toHaveText('Image 1 sur 2');
                await viewer.getByRole('button', { name: 'Image suivante', exact: true }).click();
                await expect(viewer.getByRole('status')).toHaveText('Image 2 sur 2');
                await expect(viewer.getByRole('img', { name: 'Robe — vue de détail', exact: true })).toBeVisible();
            }
            for (let step = 0; step < 6; step++) {
                await page.keyboard.press('Tab');
                expect(await viewer.evaluate((element) => element.contains(document.activeElement))).toBe(true);
            }
            await viewer.screenshot({ path: info.outputPath('photos-en-grand.png') });
            await page.keyboard.press('Escape');
            await expect(viewer).toBeHidden();
            await expect(opener).toBeFocused();
            await expect(page.locator('body')).not.toHaveCSS('overflow', 'hidden');
        }
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
}
