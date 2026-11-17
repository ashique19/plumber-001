import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';

test.describe('dashboard quick action tiles only', () => {
    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    test('shows eight separate tiles and no widgets below on mobile', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto(adminPath('/admin/dashboard', 'en'));
        await page.waitForLoadState('networkidle');

        const dashboard = page.locator('[data-testid="admin-dashboard"]');
        await expect(dashboard).toBeVisible({ timeout: 20_000 });
        await expect(dashboard.locator('h1.app-page-title')).toBeVisible();
        await expect(dashboard.locator('.app-page-lead')).toBeVisible();

        const grid = dashboard.locator('.dashboard-action-grid');
        await expect(grid).toBeVisible();
        await expect(grid.locator('.dashboard-action-tile')).toHaveCount(8);
        await expect(page.locator('.dashboard-action-board')).toHaveCount(0);

        await expect(dashboard.locator('.dashboard-monthly-report')).toHaveCount(0);
        await expect(page.getByText(/recent sales/i)).toHaveCount(0);
        await expect(page.getByText(/orders today/i)).toHaveCount(0);

        const cols = await grid.evaluate((el) => getComputedStyle(el).gridTemplateColumns.split(' ').length);
        expect(cols).toBe(2);
    });

    test('uses four columns on 1280px and captures screenshot', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto(adminPath('/admin/dashboard', 'en'));
        await page.waitForLoadState('networkidle');

        const grid = page.locator('.dashboard-action-grid');
        const cols = await grid.evaluate((el) => getComputedStyle(el).gridTemplateColumns.split(' ').length);
        expect(cols).toBe(4);

        const content = page.locator('.app-content');
        const mainBox = await content.boundingBox();
        expect(mainBox).not.toBeNull();
        expect(mainBox!.width).toBeLessThanOrEqual(1152 + 48);

        await page.screenshot({ path: '/opt/cursor/artifacts/dashboard-content-width-1280.png', fullPage: false });
    });

    test('centers content on 1920px wide viewport', async ({ page }) => {
        await page.setViewportSize({ width: 1920, height: 1080 });
        await page.goto(adminPath('/admin/dashboard', 'en'));
        await page.waitForLoadState('networkidle');

        const content = page.locator('.app-content');
        const box = await content.boundingBox();
        expect(box).not.toBeNull();
        expect(box!.width).toBeLessThanOrEqual(1152 + 48);
        expect(box!.x).toBeGreaterThan(200);

        await page.screenshot({ path: '/opt/cursor/artifacts/dashboard-content-width-1920.png', fullPage: false });
    });
});