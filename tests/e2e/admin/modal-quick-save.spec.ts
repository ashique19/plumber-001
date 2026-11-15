import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';

const UNIQUE = Date.now().toString().slice(-7);

function saudiMobile(): string {
    return `05${Math.floor(10_000_000 + Math.random() * 89_999_999)}`;
}

async function saveModal(page: import('playwright/test').Page): Promise<void> {
    const backdrop = page.locator('.x-modal-backdrop');
    await backdrop.locator('button.tap-action-primary').click();
    await expect(backdrop).toHaveCount(0, { timeout: 30_000 });
}

test.describe('quick-add modal save', () => {
    test.use({ viewport: { width: 1280, height: 900 } });

    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    test('simplified invoice quick customer saves and selects', async ({ page }) => {
        const customerName = `E2E Customer ${UNIQUE}`;
        const phone = saudiMobile();

        await page.goto(adminPath('/admin/invoices/issue/simplified', 'en'));
        await page.waitForLoadState('networkidle');

        await page.locator('.invoice-customer-picker .admin-typeahead__add').click();
        await expect(page.locator('.x-modal-backdrop')).toBeVisible();

        const modal = page.locator('.x-modal-backdrop');
        await modal.locator('input[name="customerQuickName"]').fill(customerName);
        await modal.locator('input[name="customerQuickPhone"]').fill(phone);
        await saveModal(page);

        await expect(page.locator('input[name="customerSearch"]')).toHaveValue(customerName);
        await expect(page.getByText(/customer linked|linked to invoice/i)).toBeVisible();
    });

    test('simplified invoice quick product saves and adds a line', async ({ page }) => {
        const productName = `E2E Product ${UNIQUE}`;

        await page.goto(adminPath('/admin/invoices/issue/simplified', 'en'));
        await page.waitForLoadState('networkidle');

        await page.locator('.invoice-add-line-search .admin-typeahead__add').click();
        await expect(page.locator('.x-modal-backdrop')).toBeVisible();

        const modal = page.locator('.x-modal-backdrop');
        await modal.locator('input[name="quickProductNameEn"]').fill(productName);
        await modal.locator('input[name="quickProductNameAr"]').fill('منتج');
        await modal.locator('input[name="quickProductPrice"]').fill('99');
        await saveModal(page);

        await expect(page.locator('.invoice-document-lines')).toContainText(productName);
    });

    test('record purchase quick supplier saves and selects', async ({ page }) => {
        const supplierName = `E2E Supplier ${UNIQUE}`;

        await page.goto(adminPath('/admin/purchases/create', 'en'));
        await page.waitForLoadState('networkidle');

        await page.locator('.purchase-supplier-picker .admin-typeahead__add').click();
        await expect(page.locator('.x-modal-backdrop')).toBeVisible();

        const modal = page.locator('.x-modal-backdrop');
        await modal.locator('input[name="quickSupplierName"]').fill(supplierName);
        await saveModal(page);

        await expect(page.locator('.purchase-supplier-picker')).toContainText(supplierName);
    });

    test('record purchase quick product saves and adds line', async ({ page }) => {
        const productName = `E2E Purchase SKU ${UNIQUE}`;

        await page.goto(adminPath('/admin/purchases/create', 'en'));
        await page.waitForLoadState('networkidle');

        const addProduct = page.locator('.purchase-add-line-search .admin-typeahead__add');
        await expect(addProduct).toBeVisible();
        await addProduct.click();
        await expect(page.locator('.x-modal-backdrop')).toBeVisible();

        const modal = page.locator('.x-modal-backdrop');
        await modal.locator('input[name="quickProductNameEn"]').fill(productName);
        await modal.locator('input[name="quickProductNameAr"]').fill('شراء');
        await modal.locator('input[name="quickProductPrice"]').fill('25');
        await saveModal(page);

        await expect(page.getByText(productName)).toBeVisible();
    });

    test('quotation quick customer saves and selects', async ({ page }) => {
        const customerName = `E2E Quote Customer ${UNIQUE}`;
        const phone = saudiMobile();

        await page.goto(adminPath('/admin/quotations/create', 'en'));
        await page.waitForLoadState('networkidle');

        await page.locator('.invoice-customer-picker .admin-typeahead__add').click();
        await expect(page.locator('.x-modal-backdrop')).toBeVisible();

        const modal = page.locator('.x-modal-backdrop');
        await modal.locator('input[name="customerQuickName"]').fill(customerName);
        await modal.locator('input[name="customerQuickPhone"]').fill(phone);
        await saveModal(page);

        await expect(page.locator('input[name="customerSearch"]')).toHaveValue(customerName);
    });
});