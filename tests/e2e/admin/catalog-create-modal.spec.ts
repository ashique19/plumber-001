import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';

test.describe('catalog create deep links and add buttons', () => {
    test.use({ viewport: { width: 1280, height: 900 } });

    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    test('products tab opens add product sheet from ?create=product', async ({ page }) => {
        await page.goto(adminPath('/admin/catalog?tab=products&create=product', 'en'));
        await page.waitForLoadState('networkidle');
        await expect(page.locator('#catalog-product-form')).toBeVisible({ timeout: 15_000 });
        await expect(page.locator('.x-modal-backdrop')).toBeVisible();
    });

    test('products tab opens add product sheet from add button after cold load', async ({ page }) => {
        await page.goto(adminPath('/admin/catalog?tab=products', 'en'));
        await page.waitForLoadState('networkidle');

        const add = page.getByRole('button', { name: /add product/i });
        await expect(add).toBeVisible();
        await add.click({ delay: 30 });

        await expect(page.locator('#catalog-product-form')).toBeVisible({ timeout: 15_000 });
        await expect(page.locator('.x-modal-backdrop')).toBeVisible();
    });

    test('categories tab opens add category sheet from add button', async ({ page }) => {
        await page.goto(adminPath('/admin/catalog?tab=categories', 'en'));
        await page.waitForLoadState('networkidle');

        await page.getByRole('button', { name: /add category/i }).click({ delay: 30 });
        await expect(page.locator('#catalog-category-form')).toBeVisible({ timeout: 15_000 });
        await expect(page.locator('.x-modal-backdrop')).toBeVisible();
    });

    test('modifiers tab opens add modifier sheet from add button', async ({ page }) => {
        test.skip(process.env.FEATURE_PRODUCT_MODIFIERS !== 'true', 'modifiers feature off (default plumber)');
        await page.goto(adminPath('/admin/catalog?tab=modifiers', 'en'));
        await page.waitForLoadState('networkidle');

        await page.getByRole('button', { name: /add modifier/i }).click({ delay: 30 });
        await expect(page.locator('#catalog-modifier-form')).toBeVisible({ timeout: 15_000 });
        await expect(page.locator('.x-modal-backdrop')).toBeVisible();
    });

    test('customers hub opens add sheet from ?create=customer', async ({ page }) => {
        await page.goto(adminPath('/admin/customers?create=customer', 'en'));
        await page.waitForLoadState('networkidle');
        await expect(page.locator('[data-testid="customer-identity-fields"]')).toBeVisible({ timeout: 15_000 });
        await expect(page.locator('input#customerLegalNameEn')).toBeVisible();
        await expect(page.locator('.x-modal-backdrop')).toBeVisible();
    });

    test('customers hub opens add sheet from add button', async ({ page }) => {
        await page.goto(adminPath('/admin/customers', 'en'));
        await page.waitForLoadState('networkidle');

        await page.getByRole('button', { name: /add customer/i }).click({ delay: 30 });
        await expect(page.locator('[data-testid="customer-identity-fields"]')).toBeVisible({ timeout: 15_000 });
        await expect(page.locator('input#customerLegalNameEn')).toBeVisible();
    });
});