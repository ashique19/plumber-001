import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';

const base = process.env.CAFE_BASE_URL || 'http://127.0.0.1:8000';
const out = '/opt/cursor/artifacts';
await mkdir(out, { recursive: true });

const viewports = [
  ['375', 375, 812],
  ['1280', 1280, 800],
];

const login = {
  url: `${base}/app/fancy-coffee/login`,
  email: 'owner@fancy-coffee.test',
  password: 'TestPassword!2026',
};

const pages = [
  { file: 'tenant-pos-terminal', path: (locale) => `${base}/app/fancy-coffee/pos/branches/1/terminal${locale === 'ar' ? '?locale=ar' : ''}` },
  { file: 'tenant-pos-ops', path: (locale) => `${base}/app/fancy-coffee/pos/branches/1/ops${locale === 'ar' ? '?locale=ar' : ''}` },
  { file: 'tenant-pos-history', path: (locale) => `${base}/app/fancy-coffee/pos/branches/1/history${locale === 'ar' ? '?locale=ar' : ''}` },
];

const browser = await chromium.launch();

async function loginSession(page) {
  await page.goto(login.url, { waitUntil: 'networkidle' });
  const token = await page.locator('input[name="_token"]').inputValue();
  await page.request.post(login.url, {
    form: { _token: token, email: login.email, password: login.password },
  });
}

for (const { file, path } of pages) {
  for (const locale of ['en', 'ar']) {
    for (const [vwTag, width, height] of viewports) {
      const ctx = await browser.newContext({ viewport: { width, height }, locale });
      const page = await ctx.newPage();
      await loginSession(page);
      await page.goto(path(locale), { waitUntil: 'networkidle' });
      await page.waitForTimeout(800);
      const name = `${file}-${locale}-${vwTag}px`;
      await page.screenshot({ path: `${out}/${name}.png`, fullPage: false });
      console.log(name);
      await ctx.close();
    }
  }
}

await browser.close();
