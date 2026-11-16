import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';

test.describe('catalog product gallery', () => {
    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    test('renders gallery grid and opens action modal on tile click', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto(adminPath('/admin/catalog?tab=products', 'en'));
        await page.waitForLoadState('networkidle');

        const gallery = page.locator('[data-testid="catalog-product-gallery"]');
        await expect(gallery).toBeVisible({ timeout: 20_000 });
        await expect(gallery.locator('.catalog-product-gallery__grid')).toBeVisible();

        const tile = gallery.locator('.catalog-product-tile__media').first();
        await expect(tile).toBeVisible();
        await tile.click();

        const modal = page.locator('[data-testid="catalog-product-action-modal"]');
        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(modal.locator('.x-modal__title')).not.toBeEmpty();
    });

    test('mobile layout uses two-column gallery grid', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto(adminPath('/admin/catalog?tab=products', 'en'));
        await page.waitForLoadState('networkidle');

        const gallery = page.locator('[data-testid="catalog-product-gallery"]');
        await expect(gallery).toBeVisible({ timeout: 20_000 });

        const grid = gallery.locator('.catalog-product-gallery__grid');
        const cols = await grid.evaluate((el) => getComputedStyle(el).gridTemplateColumns.split(' ').length);
        expect(cols).toBe(2);

        await page.screenshot({ path: '/opt/cursor/artifacts/catalog-gallery-375.png', fullPage: true });
    });

    test('desktop layout renders wide gallery grid', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto(adminPath('/admin/catalog?tab=products', 'en'));
        await page.waitForLoadState('networkidle');

        await expect(page.locator('[data-testid="catalog-product-gallery"]')).toBeVisible({ timeout: 20_000 });

        await page.screenshot({ path: '/opt/cursor/artifacts/catalog-gallery-1280.png', fullPage: true });
    });
});