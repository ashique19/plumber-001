import { chromium, devices } from 'playwright';
import path from 'node:path';

const base = process.env.E2E_BASE_URL || 'http://127.0.0.1:8891';
const out = process.env.SCREENSHOT_DIR || '/opt/cursor/artifacts';

const shots = [
  { file: 'login-hero-en-mobile-390', locale: 'en', viewport: { width: 390, height: 844 } },
  { file: 'login-hero-ar-mobile-390', locale: 'ar', viewport: { width: 390, height: 844 } },
  { file: 'login-hero-en-desktop-1280', locale: 'en', viewport: { width: 1280, height: 800 } },
  { file: 'login-hero-ar-desktop-1280', locale: 'ar', viewport: { width: 1280, height: 800 } },
];

const browser = await chromium.launch();
for (const shot of shots) {
  const context = await browser.newContext({
    viewport: shot.viewport,
    locale: shot.locale === 'ar' ? 'ar-SA' : 'en-US',
  });
  const page = await context.newPage();
  await page.goto(`${base}/?locale=${shot.locale}`, { waitUntil: 'networkidle' });
  await page.waitForSelector('.auth-glass-card');
  await page.screenshot({
    path: path.join(out, `${shot.file}.png`),
    fullPage: false,
  });
  await context.close();
}
await browser.close();
console.log('Saved login hero screenshots to', out);
