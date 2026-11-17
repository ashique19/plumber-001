import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';

const LOCALES = ['en', 'bn', 'ar'] as const;
const VIEWPORTS = [
    { width: 390, height: 844, label: '390' },
    { width: 1280, height: 900, label: '1280' },
] as const;

const PAGES = [
    { path: '/admin/dashboard', heading: /Dashboard|ড্যাশবোর্ড|لوحة/i },
    { path: '/admin/invoices', heading: /Invoices|ইনভয়েস|الفواتير/i },
    { path: '/admin/quotations', heading: /Quotations|কোটেশন|عروض/i },
] as const;

for (const locale of LOCALES) {
    for (const viewport of VIEWPORTS) {
        test.describe(`admin topbar without page title duplication [${locale} @ ${viewport.label}]`, () => {
            test.beforeEach(async ({ page }) => {
                await page.setViewportSize({ width: viewport.width, height: viewport.height });
                await staffLogin(page);
            });

            for (const { path, heading } of PAGES) {
                test(`${path} keeps in-page heading only`, async ({ page }) => {
                    await page.goto(adminPath(path, locale));
                    await page.waitForLoadState('networkidle');

                    const topbar = page.locator('.app-topbar');
                    await expect(topbar.locator('.app-topbar__page-title')).toHaveCount(0);

                    const h1 = page.locator('.app-content h1.app-page-title').first();
                    await expect(h1).toBeVisible({ timeout: 15_000 });
                    await expect(h1).toHaveText(heading);

                    const documentTitle = await page.title();
                    expect(documentTitle.length).toBeGreaterThan(3);
                });
            }

            if (viewport.label === '390') {
                test('mobile top bar keeps menu, brand, and actions', async ({ page }) => {
                    await page.goto(adminPath('/admin/dashboard', locale));
                    await page.waitForLoadState('networkidle');

                    await expect(page.locator('.app-topbar__menu-btn')).toBeVisible();
                    await expect(page.locator('[data-testid="app-topbar-mobile-brand"]')).toBeVisible();
                    await expect(page.locator('.app-topbar__inner .app-locale-switch-menu')).toBeVisible();
                });
            }
        });
    }
}