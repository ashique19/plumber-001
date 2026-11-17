import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';

test.describe('customer edit modal', () => {
    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    test('details form has identity fields on desktop', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto(adminPath('/admin/customers', 'bn'));
        await page.waitForLoadState('networkidle');

        await page.locator('[data-testid="customers-table"] button', { hasText: /সম্পাদনা|Edit|تعديل/i }).first().click();
        await expect(page.locator('[data-testid="customer-modal"]')).toBeVisible();
        await expect(page.locator('[data-testid="customer-identity-fields"]')).toBeVisible();
        await expect(page.locator('input#customerLegalNameEn, input#customerLegalNameAr').first()).toBeVisible();
        await expect(page.locator('input#customerAddrCity')).toBeVisible();
        await expect(page.locator('[data-testid="customer-modal-tabs"]')).toHaveCount(0);

        await page.screenshot({ path: '/opt/cursor/artifacts/customer-modal-1280.png', fullPage: false });
    });

    test('modal scroll on mobile', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto(adminPath('/admin/customers?create=customer', 'bn'));
        await page.waitForLoadState('networkidle');

        await expect(page.locator('[data-testid="customer-modal"]')).toBeVisible();
        await expect(page.locator('.x-modal-form__fields')).toBeVisible();

        await page.screenshot({ path: '/opt/cursor/artifacts/customer-modal-375.png', fullPage: false });
    });
});