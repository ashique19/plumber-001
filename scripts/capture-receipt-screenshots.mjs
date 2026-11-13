import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';

const base = process.env.CAFE_BASE_URL || 'http://127.0.0.1:8000';
const out = '/opt/cursor/artifacts';
await mkdir(out, { recursive: true });

const viewports = [
  ['375', 375, 812],
  ['1280', 1280, 800],
];

const browser = await chromium.launch();

for (const [locale, pathSuffix] of [
  ['en', ''],
  ['ar', '?locale=ar'],
]) {
  for (const [vwTag, width, height] of viewports) {
    const ctx = await browser.newContext({ viewport: { width, height }, locale });
    const page = await ctx.newPage();
    const login = `${base}/app/fancy-coffee/login`;
    await page.goto(login, { waitUntil: 'networkidle' });
    const token = await page.locator('input[name="_token"]').inputValue();
    await page.request.post(login, {
      form: { _token: token, email: 'owner@fancy-coffee.test', password: 'TestPassword!2026' },
    });
    await page.goto(`${base}/app/fancy-coffee/pos`, { waitUntil: 'networkidle' });
    await page.goto(`${base}/app/fancy-coffee/pos/branches/1/history`, { waitUntil: 'networkidle' });
    const receiptLink = page.locator('a[href*="/receipt"]').first();
    if (await receiptLink.count()) {
      const href = await receiptLink.getAttribute('href');
      await page.goto(href + (href.includes('?') ? '&' : '?') + 'paid=1' + pathSuffix.replace('?', '&'), {
        waitUntil: 'networkidle',
      });
    } else {
      await page.goto(`${base}/app/fancy-coffee/pos/branches/1/terminal`, { waitUntil: 'networkidle' });
    }
    await page.waitForTimeout(1200);
    await page.screenshot({ path: `${out}/receipt-qr-${locale}-${vwTag}px.png` });
    console.log(`receipt-qr-${locale}-${vwTag}px`);
    await ctx.close();
  }
}

await browser.close();
