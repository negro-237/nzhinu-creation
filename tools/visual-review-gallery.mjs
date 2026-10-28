import fs from 'node:fs/promises';
import path from 'node:path';
import { chromium } from '@playwright/test';

// Render local audit screenshots into small contact sheets; never touch the application database or media.
const results = path.resolve('storage/framework/testing/playwright-results');
const output = path.resolve('storage/framework/testing/visual-review');
await fs.mkdir(output, { recursive: true });
const browser = await chromium.launch({ channel: 'msedge', headless: true });
try {
    for (const directory of (await fs.readdir(results)).filter((name) => name.startsWith('visual-review-revue-complète-à-')).sort()) {
        const width = Number(directory.match(/à-(\d+)px/)?.[1]);
        if (!width) continue;
        const folder = path.join(results, directory);
        const images = (await fs.readdir(folder)).filter((name) => name.endsWith('.png'));
        const archive = path.join(output, 'captures', directory);
        await fs.mkdir(archive, { recursive: true });
        await Promise.all(images.map((name) => fs.copyFile(path.join(folder, name), path.join(archive, name))));
        const files = images.filter((name) => name.endsWith('-viewport.png')).sort();
        const cardWidth = width <= 390 ? width : Math.round(width / 2);
        const page = await browser.newPage({ viewport: { width: cardWidth * 2 + 48, height: 1000 }, deviceScaleFactor: 1 });
        for (let index = 0; index < files.length; index += 4) {
            const panels = await Promise.all(files.slice(index, index + 4).map(async (name) => {
                const data = (await fs.readFile(path.join(folder, name))).toString('base64');
                return `<figure><figcaption>${width}px · ${name}</figcaption><img src="data:image/png;base64,${data}" alt="${name}"></figure>`;
            }));
            await page.setContent(`<html lang="fr"><meta charset="utf-8"><style>body{margin:16px;background:#ddd;font:12px Arial}main{display:grid;grid-template-columns:repeat(2,${cardWidth}px);gap:16px}figure{margin:0;background:white}figcaption{padding:10px;background:#111;color:#ffb511}img{display:block;width:100%;height:auto}</style><main>${panels.join('')}</main></html>`);
            await page.locator('img').evaluateAll((images) => Promise.all(images.map((image) => image.decode())));
            const target = path.join(output, `${width}-${Math.floor(index / 4) + 1}.png`);
            await page.screenshot({ path: target, fullPage: true });
            console.log(target);
        }
        await page.close();
    }
} finally {
    await browser.close();
}
