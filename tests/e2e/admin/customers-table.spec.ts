import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';

test.describe('customers table', () => {
    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    test('renders scrollable table with create deep link', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto(adminPath('/admin/customers', 'en'));
        await page.waitForLoadState('networkidle');

        await expect(page.locator('[data-testid="customers-table"]')).toBeVisible();
        await expect(page.locator('[data-testid="customers-table"] thead th').first()).toBeVisible();

        await page.goto(adminPath('/admin/customers?create=customer', 'en'));
        await page.waitForLoadState('networkidle');
        await expect(page.locator('.app-nav-drawer, .x-modal-form').locator('input#customerName')).toBeVisible();

        await page.screenshot({ path: '/opt/cursor/artifacts/customers-table-1280.png', fullPage: false });
    });

    test('sticky name column on mobile', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto(adminPath('/admin/customers', 'bn'));
        await page.waitForLoadState('networkidle');

        await expect(page.locator('[data-testid="customers-table"]')).toBeVisible();
        await expect(page.locator('.admin-data-table__sticky').first()).toBeVisible();

        await page.screenshot({ path: '/opt/cursor/artifacts/customers-table-375.png', fullPage: false });
    });
});