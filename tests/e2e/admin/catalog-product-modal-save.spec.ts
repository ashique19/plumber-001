import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';
import { fillCatalogProductBasicTab, openAddProductModal } from '../helpers/catalog-product-modal.js';

test.describe('catalog product modal save (basic tab only)', () => {
    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    test('decimal price saves, modal closes, product appears in hub @ 390', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });

        const productName = `E2E Catalog ${Date.now()}`;
        await openAddProductModal(page, 'en');
        await fillCatalogProductBasicTab(page, {
            nameEn: productName,
            nameAr: 'اختبار',
            price: '19.99',
        });

        await page.locator('[data-testid="catalog-product-form-primary"]').click();

        await expect(page.locator('[data-testid="catalog-product-modal"]')).toBeHidden({ timeout: 30_000 });
        await expect(page.getByText(productName, { exact: false }).first()).toBeVisible({ timeout: 30_000 });
    });
});