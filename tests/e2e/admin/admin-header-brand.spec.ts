import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';

test.describe('admin header brand deduplication', () => {
    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    test('sidebar keeps brand; header shows page title only', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto(adminPath('/admin/dashboard', 'en'));
        await page.waitForLoadState('networkidle');

        await expect(page.locator('.app-sidebar--admin .admin-sidebar-nav__brand-name')).toBeVisible();
        await expect(page.locator('.app-topbar__page-title')).toBeVisible();
        await expect(page.locator('.app-topbar__brand')).toHaveCount(0);

        await page.screenshot({ path: '/opt/cursor/artifacts/admin-header-1280.png', fullPage: false });
    });

    test('mobile header has menu control and no duplicate brand', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto(adminPath('/admin/dashboard', 'bn'));
        await page.waitForLoadState('networkidle');

        await expect(page.locator('.app-topbar__menu-btn')).toBeVisible();
        await expect(page.locator('.app-topbar__brand')).toHaveCount(0);

        await page.locator('.app-topbar__menu-btn').click();
        await expect(page.locator('.app-nav-drawer .admin-sidebar-nav__brand-name')).toBeVisible();

        await page.screenshot({ path: '/opt/cursor/artifacts/admin-header-375.png', fullPage: false });
    });
});