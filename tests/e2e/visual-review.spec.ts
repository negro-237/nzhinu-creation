import { test, expect, type Page, type TestInfo } from '@playwright/test';

test.skip(process.env.E2E_CAMEROON_ONLY !== '1', 'Recette visuelle de la boutique publique actuelle.');

async function capture(page: Page, info: TestInfo, name: string) {
    const titles: Record<string, string | RegExp> = {
        '01': /L’élégance africaine/, '03': 'Notre maison E2E', '04': 'Panier', '05': 'La boutique',
        '07': 'Pièce E2E', '09': 'Panier', '10': 'Votre demande de commande', '11': 'Votre demande de commande',
        '12': 'Demande reçue', '13': 'Suivi de votre commande',
    };
    // Wait for the destination component, not merely the previous page's persistent layout.
    await expect(page.locator('main h1')).toHaveText(titles[name.slice(0, 2)]);
    await expect(page.locator('.storefront')).toBeVisible();
    await page.evaluate(() => document.fonts.ready);
    await page.evaluate(() => window.scrollTo(0, 0));
    await expect(page.locator('main h1')).toHaveCount(1);
    expect.soft(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${name}: aucun débordement horizontal`).toBe(true);
    const contact = page.locator('.shop-whatsapp');
    await expect(contact).toHaveAttribute('data-floating', String(page.viewportSize()!.width >= 1440));
    const overlaps = await page.evaluate(() => {
        const contact = document.querySelector('.shop-whatsapp')!.getBoundingClientRect();
        return [...document.querySelectorAll('main input, main select, main button, main dd')].filter((element) => {
            const box = element.getBoundingClientRect();
            return box.width > 0 && box.height > 0 && box.left < contact.right && box.right > contact.left && box.top < contact.bottom && box.bottom > contact.top;
        }).map((element) => element.tagName);
    });
    expect.soft(overlaps, `${name}: contact hors des champs et montants`).toEqual([]);
    if (page.viewportSize()!.width < 640) {
        for (const field of await page.locator('.shop-field:visible').all()) {
            expect.soft(await field.evaluate((element) => parseFloat(getComputedStyle(element).fontSize)), 'Champs lisibles sur petit écran').toBeGreaterThanOrEqual(16);
        }
    }
    await page.getByRole('contentinfo').scrollIntoViewIfNeeded();
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({ path: info.outputPath(`${name}-viewport.png`) });
    await page.screenshot({ path: info.outputPath(`${name}-full.png`), fullPage: true });
}

for (const width of [320, 390, 768, 1024, 1440]) {
    test(`revue complète à ${width}px`, async ({ page }, info) => {
        test.skip(info.project.name !== (width < 1024 ? 'edge-mobile' : 'edge-desktop'), 'Format testé par le navigateur correspondant.');
        test.setTimeout(300000);
        await page.setViewportSize({ width, height: width < 1024 ? 844 : 900 });
        await page.emulateMedia({ reducedMotion: 'reduce' });
        const errors: string[] = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.route(/^http:\/\/127\.0\.0\.1:8765\/(?:boutique|produits\/piece-e2e|panier|commander)?(?:\?.*)?$/, async (route) => {
            if (route.request().method() !== 'GET' || route.request().headers()['x-inertia'] !== 'true') return route.continue();
            const response = await route.fetch();
            const body = await response.json();
            const cards = body.props.featured ?? body.props.products?.items ?? [];
            cards.forEach((item: any, index: number) => { item.image = { url: `/images/demo/0${index % 2 + 1}-front.webp`, alt: item.name }; });
            if (body.props.product) body.props.product.images = [
                { url: '/images/demo/01-front.webp', alt: 'Pièce — vue d’ensemble' },
                { url: '/images/demo/01-detail.webp', alt: 'Pièce — détail' },
            ];
            (body.props.cart?.items ?? body.props.checkout?.cart.items ?? []).forEach((item: any) => {
                item.image = { url: '/images/demo/01-front.webp', alt: item.name };
            });
            await route.fulfill({ response, json: body });
        });
        await page.goto('/boutique');
        await expect(page.locator('.storefront')).toBeVisible();
        await page.getByRole('banner').getByRole('link', { name: 'Nzhinu Création', exact: true }).click();
        await capture(page, info, '01-accueil');
        const brand = await page.getByRole('banner').getByRole('link', { name: 'Nzhinu Création', exact: true }).boundingBox();
        const menu = page.getByRole('button', { name: 'Ouvrir le menu', exact: true });
        const menuBox = await menu.boundingBox();
        expect.soft(Math.abs(brand!.y + brand!.height / 2 - menuBox!.y - menuBox!.height / 2), 'Le menu reste sur la même ligne que le logo').toBeLessThanOrEqual(16);
        await menu.click();
        await page.screenshot({ path: info.outputPath('02-menu-viewport.png') });
        await page.keyboard.press('Escape');
        await page.getByRole('contentinfo').getByRole('link', { name: 'Notre maison E2E', exact: true }).click();
        await capture(page, info, '03-information');
        await page.goto('/panier');
        await capture(page, info, '04-panier-vide');
        await page.getByRole('banner').getByRole('link', { name: 'La boutique', exact: true }).click();
        await capture(page, info, '05-boutique');
        const filters = page.getByRole('button', { name: 'Filtres et recherche', exact: true });
        if (await filters.isVisible()) {
            await filters.click();
            await page.screenshot({ path: info.outputPath('06-filtres-viewport.png') });
            await filters.click();
        }
        await page.getByRole('link', { name: 'Découvrir la pièce : Pièce E2E', exact: true }).click();
        await capture(page, info, '07-article');
        const gallery = page.getByRole('region', { name: 'Galerie du produit', exact: true });
        await gallery.getByRole('button', { name: 'Image suivante', exact: true }).click();
        await gallery.getByRole('button', { name: 'Agrandir la photo', exact: true }).click();
        const modal = page.getByRole('dialog', { name: 'Photos du produit en grand', exact: true });
        await expect(modal).toBeVisible();
        await page.screenshot({ path: info.outputPath('08-photos-viewport.png') });
        await modal.getByRole('button', { name: 'Zoomer sur la photo', exact: true }).click();
        const bounds = await modal.boundingBox();
        for (const button of await modal.getByRole('button').all()) {
            const box = await button.boundingBox();
            if (box) expect.soft(box.x + box.width, 'Commandes des photos visibles en entier').toBeLessThanOrEqual(bounds!.x + bounds!.width);
        }
        await page.screenshot({ path: info.outputPath('08-zoom-viewport.png') });
        await modal.getByRole('button', { name: 'Revenir à la vue complète', exact: true }).click();
        await page.keyboard.press('Escape');
        await page.getByLabel('Stock de départ', { exact: true }).selectOption({ index: 1 });
        await page.getByRole('button', { name: 'Mettre au panier', exact: true }).click();
        await capture(page, info, '09-panier');
        await page.getByRole('link', { name: 'Demander ma commande', exact: true }).click();
        await capture(page, info, '10-coordonnees-livraison');
        await page.getByLabel('Nom complet', { exact: true }).fill('Client revue visuelle');
        await page.getByLabel('Téléphone', { exact: true }).fill('+237699000005');
        await page.getByLabel('Adresse', { exact: true }).fill('Adresse fictive de recette');
        await page.getByLabel('Ville', { exact: true }).fill('Douala');
        await page.getByRole('radio', { name: 'Retrait', exact: true }).check();
        await page.getByLabel('Point de retrait', { exact: true }).selectOption({ index: 1 });
        await capture(page, info, '11-retrait');
        await page.getByRole('radio', { name: 'Livraison', exact: true }).check();
        await expect(page.getByLabel('Adresse', { exact: true })).toHaveValue('Adresse fictive de recette');
        await page.getByRole('radio', { name: 'Retrait', exact: true }).check();
        await page.getByRole('button', { name: 'Envoyer ma demande', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'Demande reçue', exact: true })).toBeVisible();
        await capture(page, info, '12-confirmation');
        await page.route('**/commandes/**', async (route) => {
            const response = await route.fetch();
            const body = await response.json();
            body.props.order = { ...body.props.order, status_code: 'completed', status: 'Remise effectuée',
                payment_status_code: 'paid', payment_status: 'Payé', fulfillment_status_code: 'collected', fulfillment_status: 'Retiré',
                total_minor: 25000, shipping_fee_minor: 0, tax_minor: 0, verified_paid_minor: 25000, verified_net_minor: 25000, outstanding_minor: 0,
                handover: { at: '2026-10-28T14:00:00Z', carrier: null, reference: 'RETRAIT-VISUEL' } };
            await route.fulfill({ response, json: body });
        });
        await page.getByRole('button', { name: 'Actualiser le suivi', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'Suivi de votre commande', exact: true })).toBeVisible();
        await capture(page, info, '13-suivi');
        expect(errors).toEqual([]);
    });
}
