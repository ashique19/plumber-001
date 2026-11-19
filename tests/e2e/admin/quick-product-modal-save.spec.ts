import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';

async function openQuickProductModal(page: import('playwright/test').Page): Promise<void> {
    const add = page.locator('.invoice-add-line-search .admin-typeahead__add');
    await expect(add).toBeVisible();
    await add.click();
    await expect(page.locator('#quick-product-form')).toBeVisible({ timeout: 20_000 });
}

async function fillQuickProductBasicTab(
    page: import('playwright/test').Page,
    values: { nameEn: string; nameAr: string; price: string },
): Promise<void> {
    await page.locator('#quickProductNameEn').fill(values.nameEn);
    await page.locator('#quickProductNameAr').fill(values.nameAr);
    await page.locator('#quickProductPrice').fill(values.price);
}

test.describe('quick product modal save (basic tab only)', () => {
    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    test('integer price saves, modal closes, line appears on standard invoice', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto(adminPath('/admin/invoices/issue/standard', 'en'));
        await page.waitForLoadState('networkidle');

        const productName = `E2E Quick ${Date.now()}`;
        await openQuickProductModal(page);
        await fillQuickProductBasicTab(page, {
            nameEn: productName,
            nameAr: 'سريع',
            price: '25',
        });

        await page.locator('[data-testid="quick-product-form-primary"]').click();

        await expect(page.locator('[data-testid="quick-product-modal"]')).toBeHidden({ timeout: 30_000 });
        await expect(page.locator('.invoice-document-lines__row').filter({ hasText: productName })).toBeVisible({
            timeout: 30_000,
        });
    });

    test('decimal price is accepted on basic tab', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(adminPath('/admin/invoices/issue/simplified', 'en'));
        await page.waitForLoadState('networkidle');

        const productName = `E2E Quick Dec ${Date.now()}`;
        await openQuickProductModal(page);
        await fillQuickProductBasicTab(page, {
            nameEn: productName,
            nameAr: 'عشري',
            price: '19.99',
        });

        await page.locator('[data-testid="quick-product-form-primary"]').click();

        await expect(page.locator('[data-testid="quick-product-modal"]')).toBeHidden({ timeout: 30_000 });
        await expect(page.locator('.invoice-document-lines__row').filter({ hasText: productName })).toBeVisible({
            timeout: 30_000,
        });
    });
});