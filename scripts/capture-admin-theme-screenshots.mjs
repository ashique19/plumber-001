import { chromium } from 'playwright';
import path from 'node:path';

const base = process.env.E2E_BASE_URL || 'http://127.0.0.1:8891';
const out = process.env.SCREENSHOT_DIR || '/opt/cursor/artifacts';
const loginEmail = process.env.E2E_STAFF_EMAIL || 'owner@example.test';
const loginPassword = process.env.E2E_STAFF_PASSWORD || 'password';

const pages = [
    { path: '/admin/dashboard', name: 'dashboard' },
    { path: '/admin/invoices/issue/simplified', name: 'issue-simplified' },
    { path: '/admin/quotations/create', name: 'quotation-create' },
    { path: '/admin/business', name: 'business-settings' },
    { path: '/admin/catalog', name: 'catalog' },
];

const viewports = [
    { tag: '360', width: 360, height: 780 },
    { tag: '1280', width: 1280, height: 800 },
];

const locales = ['en', 'bn', 'ar'];

async function login(page) {
    await page.goto(`${base}/?locale=en`, { waitUntil: 'networkidle' });
    await page.fill('input[name="email"]', loginEmail);
    await page.fill('input[name="password"]', loginPassword);
    await page.getByRole('button', { name: /log in|login|সাইন ইন/i }).click();
    await page.waitForURL(/\/admin\//, { timeout: 30000 });
}

const browser = await chromium.launch();
for (const locale of locales) {
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page);
  for (const shot of viewports) {
        await context.clearCookies();
        await page.goto(`${base}/?locale=${locale}`, { waitUntil: 'networkidle' });
        await login(page);
        for (const entry of pages) {
            await page.setViewportSize({ width: shot.width, height: shot.height });
            await page.goto(`${base}${entry.path}?locale=${locale}`, { waitUntil: 'networkidle' });
            await page.waitForSelector('.app-shell', { timeout: 15000 });
            const file = `admin-${entry.name}-${locale}-${shot.tag}.png`;
            await page.screenshot({ path: path.join(out, file), fullPage: true });
            console.log('saved', file);
        }
    }
    await context.close();
}
await browser.close();