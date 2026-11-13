import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';

const base = process.env.CAFE_BASE_URL || 'http://127.0.0.1:8000';
const out = '/opt/cursor/artifacts';
await mkdir(out, { recursive: true });

const shots = [
  ['tenant-login-mobile', `${base}/app/fancy-coffee/login`, 390, 844],
  ['tenant-login-desktop', `${base}/app/fancy-coffee/login`, 1280, 800],
  ['tenant-forgot-mobile', `${base}/app/fancy-coffee/forgot-password`, 390, 844],
  ['tenant-forgot-desktop', `${base}/app/fancy-coffee/forgot-password`, 1280, 800],
  ['platform-login-mobile', `${base}/platform/login`, 390, 844],
  ['platform-login-desktop', `${base}/platform/login`, 1280, 800],
];

const browser = await chromium.launch();
for (const [name, url, width, height] of shots) {
  const page = await browser.newPage({ viewport: { width, height } });
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.waitForTimeout(800);
  await page.screenshot({ path: `${out}/${name}.png`, fullPage: true });
  await page.close();
  console.log(name);
}
await browser.close();
