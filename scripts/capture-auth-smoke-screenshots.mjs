import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';

const base = process.env.CAFE_BASE_URL || 'http://127.0.0.1:8000';
const out = '/opt/cursor/artifacts';
await mkdir(out, { recursive: true });

const viewports = [
  ['mobile-375', 375, 812],
  ['desktop-1280', 1280, 800],
];

const pages = [
  {
    name: 'tenant-pos-ar',
    login: `${base}/app/fancy-coffee/login`,
    email: 'cashier@fancy-coffee.test',
    password: 'TestPassword!2026',
    after: `${base}/app/fancy-coffee/pos?locale=ar`,
  },
  {
    name: 'platform-dashboard',
    login: `${base}/platform/login`,
    email: 'admin@taskeenlab.test',
    password: 'TestPassword!2026',
    after: `${base}/platform/dashboard`,
  },
];

const browser = await chromium.launch();

for (const page of pages) {
  for (const [tag, width, height] of viewports) {
    const ctx = await browser.newContext({ viewport: { width, height } });
    const tab = await ctx.newPage();
    await tab.goto(page.login, { waitUntil: 'networkidle' });
    const token = await tab.locator('input[name="_token"]').inputValue();
    await tab.request.post(page.login, {
      form: { _token: token, email: page.email, password: page.password },
    });
    await tab.goto(page.after, { waitUntil: 'networkidle' });
    await tab.waitForTimeout(800);
    const errors = [];
    tab.on('pageerror', (e) => errors.push(e.message));
    await tab.screenshot({ path: `${out}/smoke-${page.name}-${tag}.png`, fullPage: true });
    if (errors.length) {
      console.warn(page.name, tag, 'js-errors', errors);
    }
    console.log(`smoke-${page.name}-${tag}`);
    await ctx.close();
  }
}

await browser.close();
