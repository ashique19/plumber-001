import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';

test.describe('admin header brand deduplication', () => {
    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    test('sidebar keeps brand; top bar has no page title on desktop', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto(adminPath('/admin/dashboard', 'en'));
        await page.waitForLoadState('networkidle');

        await expect(page.locator('.app-sidebar--admin .admin-sidebar-nav__brand-name')).toBeVisible();
        await expect(page.locator('.app-topbar__page-title')).toHaveCount(0);
        await expect(page.locator('.app-content h1.app-page-title')).toBeVisible();
        await expect(page.locator('[data-testid="app-topbar-mobile-brand"]')).toHaveCount(0);
    });

    test('mobile header has menu, mobile brand, and actions', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto(adminPath('/admin/dashboard', 'bn'));
        await page.waitForLoadState('networkidle');

        await expect(page.locator('.app-topbar__menu-btn')).toBeVisible();
        await expect(page.locator('[data-testid="app-topbar-mobile-brand"]')).toBeVisible();
        await expect(page.locator('.app-topbar__page-title')).toHaveCount(0);
        await expect(page.locator('.app-content h1.app-page-title')).toBeVisible();

        await page.locator('.app-topbar__menu-btn').click();
        await expect(page.locator('.app-nav-drawer .admin-sidebar-nav__brand-name')).toBeVisible();
    });
});