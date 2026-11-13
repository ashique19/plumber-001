import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';

const base = process.env.CAFE_BASE_URL || 'http://127.0.0.1:8000';
const out = '/opt/cursor/artifacts';
await mkdir(out, { recursive: true });

const viewports = [
  ['375', 375, 812],
  ['1280', 1280, 800],
];

const scenarios = [
  {
    file: 'tenant-pos-terminal',
    login: `${base}/app/fancy-coffee/login`,
    email: 'owner@fancy-coffee.test',
    password: 'TestPassword!2026',
    targets: [
      { locale: 'en', path: `${base}/app/fancy-coffee/pos/branches/1/terminal` },
      { locale: 'ar', path: `${base}/app/fancy-coffee/pos/branches/1/terminal?locale=ar` },
    ],
  },
];

const browser = await chromium.launch();

for (const scenario of scenarios) {
  for (const { locale, path } of scenario.targets) {
    for (const [vwTag, width, height] of viewports) {
      const ctx = await browser.newContext({ viewport: { width, height }, locale });
      const page = await ctx.newPage();
      await page.goto(scenario.login, { waitUntil: 'networkidle' });
      const token = await page.locator('input[name="_token"]').inputValue();
      await page.request.post(scenario.login, {
        form: { _token: token, email: scenario.email, password: scenario.password },
      });
      await page.goto(path, { waitUntil: 'networkidle' });
      await page.evaluate(() => {
        window.showToast?.({ type: 'success', message: 'Saved' });
      });
      await page.waitForTimeout(600);
      const name = `${scenario.file}-${locale}-${vwTag}px`;
      await page.screenshot({ path: `${out}/${name}.png`, fullPage: false });
      console.log(name);
      await ctx.close();
    }
  }
}

await browser.close();
