import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';
import { expectDialogInViewport } from '../helpers/modal-viewport.js';

const VIEWPORTS = [
    { width: 1280, height: 800, name: '1280' },
    { width: 1920, height: 1072, name: '1920' },
    { width: 390, height: 844, name: '390' },
] as const;

test.describe('admin shared modal viewport placement', () => {
    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    for (const viewport of VIEWPORTS) {
        test(`add product modal is inside viewport at ${viewport.name}`, async ({ page }) => {
            await page.setViewportSize({ width: viewport.width, height: viewport.height });
            await page.goto(adminPath('/admin/catalog?tab=products&create=product', 'en'));
            await page.waitForLoadState('networkidle');

            await expect(page.locator('#catalog-product-form')).toBeVisible({ timeout: 20_000 });
            await expectDialogInViewport(page);

            await page.screenshot({
                path: `/opt/cursor/artifacts/catalog-product-modal-${viewport.name}.png`,
                fullPage: false,
            });
        });

        test(`add customer modal is inside viewport at ${viewport.name}`, async ({ page }) => {
            await page.setViewportSize({ width: viewport.width, height: viewport.height });
            await page.goto(adminPath('/admin/customers?create=customer', 'en'));
            await page.waitForLoadState('networkidle');

            await expect(page.locator('[data-testid="customer-identity-fields"]')).toBeVisible({ timeout: 20_000 });
            await expectDialogInViewport(page);

            await page.screenshot({
                path: `/opt/cursor/artifacts/customer-modal-viewport-${viewport.name}.png`,
                fullPage: false,
            });
        });
    }
});