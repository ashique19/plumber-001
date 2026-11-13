import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';

const base = process.env.CAFE_BASE_URL || 'http://127.0.0.1:8000';
const out = '/opt/cursor/artifacts';
await mkdir(out, { recursive: true });

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
await page.goto(`${base}/fancy-coffee#visit`, { waitUntil: 'networkidle' });
await page.locator('#visit').scrollIntoViewIfNeeded();
await page.waitForTimeout(600);
await page.locator('[data-cafe-map-unlock]').click({ timeout: 5000 }).catch(() => {});
await page.waitForTimeout(1200);
await page.screenshot({ path: `${out}/fancy-coffee-visit-map-mobile.png`, fullPage: false });
console.log('fancy-coffee-visit-map-mobile');
await browser.close();
