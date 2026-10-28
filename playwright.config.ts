import { defineConfig } from '@playwright/test';
import path from 'node:path';

Object.assign(process.env, {
    APP_ENV: 'testing', APP_DEBUG: 'false', APP_URL: 'http://127.0.0.1:8765', E2E_TESTING: '1',
    STOREFRONT_MARKETS: process.env.E2E_CAMEROON_ONLY === '1' ? 'CM' : 'CA,CM',
    DB_CONNECTION: 'sqlite', DB_URL: '', DB_DATABASE: path.resolve('storage/framework/testing/e2e.sqlite'),
    CACHE_STORE: 'database', SESSION_DRIVER: 'database', SESSION_SECURE_COOKIE: 'false', SESSION_ENCRYPT: 'true',
    QUEUE_CONNECTION: 'database', MAIL_MAILER: 'array', ORDER_EMAIL_ENABLED: 'false', BCRYPT_ROUNDS: '4',
    WHATSAPP_CA: '', WHATSAPP_CM: '23755278597', SHOP_CONTACT_EMAIL: 'infos@nhinu-creation.com',
    SOCIAL_FACEBOOK_URL: 'https://www.facebook.com/profile.php?id=100071095101171', SOCIAL_TIKTOK_URL: '', SOCIAL_YOUTUBE_URL: '',
});

export default defineConfig({
    testDir: './tests/e2e', workers: 1, fullyParallel: false, timeout: 180000, expect: { timeout: 15000 },
    reporter: 'list', outputDir: 'storage/framework/testing/playwright-results',
    use: { baseURL: process.env.APP_URL, channel: 'msedge', headless: true, actionTimeout: 15000, trace: 'retain-on-failure', screenshot: 'only-on-failure' },
    projects: [
        { name: 'edge-desktop', use: { viewport: { width: 1280, height: 900 } } },
        { name: 'edge-mobile', use: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } },
    ],
    webServer: { command: 'php tools/e2e-server.php', url: 'http://127.0.0.1:8765/up', reuseExistingServer: false, timeout: 180000 },
});
