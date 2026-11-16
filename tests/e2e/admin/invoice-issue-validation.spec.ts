import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';

const UNIQUE_PHONE = '0544002299';
const CUSTOMER_NAME = 'E2E Modal Buyer';

test.describe('standard invoice buyer completion modal', () => {
    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    test('shows modal when customer lacks VAT and address (bn)', async ({ page }) => {
        await page.goto(adminPath('/admin/invoices/issue/simplified', 'bn'));
        await page.locator('.invoice-customer-picker .admin-typeahead__add').click();
        await expect(page.locator('input#customerQuickName')).toBeVisible({ timeout: 15_000 });
        await page.locator('input#customerQuickName').fill(CUSTOMER_NAME);
        await page.locator('input#customerQuickPhone').fill(UNIQUE_PHONE);
        await page.getByRole('button', { name: /save|সংরক্ষণ|حفظ/i }).click();
        await expect(page.locator('.x-modal-backdrop')).toHaveCount(0, { timeout: 20_000 });

        await page.goto(adminPath('/admin/invoices/issue/standard', 'bn'));
        await page.locator('input#customerSearch').fill(CUSTOMER_NAME);
        await page.locator('.invoice-customer-picker__option').first().click();

        const modal = page.locator('.x-modal-backdrop');
        await expect(modal).toBeVisible({ timeout: 15_000 });
        await expect(page.locator('input#buyerCr, input#buyerVat')).toBeVisible();
        await expect(page.locator('input#addrBuilding')).toBeVisible();
    });

    test('shows validation summary when issuing without lines (ar RTL)', async ({ page }) => {
        await page.goto(adminPath('/admin/invoices/issue/standard', 'ar'));
        await page.locator('button.document-primary-submit').first().click();
        await expect(page.locator('#document-validation-summary')).toBeVisible({ timeout: 15_000 });
    });
});