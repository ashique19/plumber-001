import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';

const base = process.env.CAFE_BASE_URL || 'http://127.0.0.1:8000';
const out = '/opt/cursor/artifacts';
await mkdir(out, { recursive: true });

const shots = [
  ['fancy-coffee-home-en-mobile', `${base}/fancy-coffee`, 390, 844, 'en'],
  ['fancy-coffee-home-en-desktop', `${base}/fancy-coffee`, 1280, 800, 'en'],
  ['fancy-coffee-home-ar-mobile', `${base}/fancy-coffee?locale=ar`, 390, 844, 'ar'],
  ['fancy-coffee-home-ar-desktop', `${base}/fancy-coffee?locale=ar`, 1280, 800, 'ar'],
  ['fancy-coffee-menu-en-mobile', `${base}/fancy-coffee/menu`, 390, 844, 'en'],
  ['fancy-coffee-menu-en-desktop', `${base}/fancy-coffee/menu`, 1280, 800, 'en'],
  ['fancy-coffee-menu-ar-mobile', `${base}/fancy-coffee/menu?locale=ar`, 390, 844, 'ar'],
  ['fancy-coffee-menu-ar-desktop', `${base}/fancy-coffee/menu?locale=ar`, 1280, 800, 'ar'],
];

const browser = await chromium.launch();
for (const [name, url, width, height, locale] of shots) {
  const page = await browser.newPage({ viewport: { width, height } });
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1200);
  await page.screenshot({ path: `${out}/${name}.png`, fullPage: true });
  await page.close();
  console.log(name);
}
await browser.close();
