import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';

test.describe('dashboard quick action tiles only', () => {
    test('shows eight tiles and no widgets below on mobile', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });
        await staffLogin(page);
        await page.goto(adminPath('/admin/dashboard', 'en'));
        await page.waitForLoadState('networkidle');

        const dashboard = page.locator('[data-testid="admin-dashboard"]');
        await expect(dashboard).toBeVisible({ timeout: 20_000 });

        const grid = dashboard.locator('.dashboard-action-grid');
        await expect(grid).toBeVisible();
        await expect(grid.locator('.dashboard-action-tile')).toHaveCount(8);

        await expect(dashboard.locator('.dashboard-monthly-report')).toHaveCount(0);
        await expect(page.getByText(/recent sales/i)).toHaveCount(0);
        await expect(page.getByText(/orders today/i)).toHaveCount(0);

        const cols = await grid.evaluate((el) => getComputedStyle(el).gridTemplateColumns.split(' ').length);
        expect(cols).toBe(2);

        await page.screenshot({ path: '/opt/cursor/artifacts/dashboard-tiles-only-375.png', fullPage: true });
    });

    test('uses four columns on desktop', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await staffLogin(page);
        await page.goto(adminPath('/admin/dashboard', 'en'));
        await page.waitForLoadState('networkidle');

        const grid = page.locator('.dashboard-action-grid');
        const cols = await grid.evaluate((el) => getComputedStyle(el).gridTemplateColumns.split(' ').length);
        expect(cols).toBe(4);
    });
});