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

test.describe('standard invoice buyer completion modal', () => {
    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    test('shows modal when customer lacks VAT and address (bn)', async ({ page }) => {
        const customerName = `E2E Modal Buyer ${UNIQUE}`;

        await page.goto(adminPath('/admin/invoices/issue/simplified', 'bn'));
        await page.waitForLoadState('networkidle');

        await page.locator('.invoice-customer-picker .admin-typeahead__add').click();
        const createModal = page.locator('.x-modal-backdrop');
        await expect(createModal).toBeVisible({ timeout: 15_000 });
        await createModal.locator('input#customerQuickName').fill(customerName);
        await createModal.locator('input#customerQuickPhone').fill(saudiMobile());
        await createModal.locator('input[name="customerQuickVatRegistered"][value="1"]').check();
        await createModal.locator('input#customerQuickVat').fill('300000000000003');
        await saveModal(page);

        await page.goto(adminPath('/admin/invoices/issue/standard', 'bn'));
        await page.waitForLoadState('networkidle');
        await page.locator('input#customerSearch').fill(customerName);
        await page.locator('.invoice-customer-picker__option').first().click();

        const completionModal = page.locator('.x-modal-backdrop');
        await expect(completionModal).toBeVisible({ timeout: 15_000 });
        await expect(completionModal.locator('#addrBuilding')).toBeVisible();
        await expect(completionModal.locator('#addrStreet')).toBeVisible();
    });

    test('shows validation summary when issuing without lines (ar RTL)', async ({ page }) => {
        await page.goto(adminPath('/admin/invoices/issue/standard', 'ar'));
        await page.locator('button.document-primary-submit').first().click();
        await expect(page.locator('#document-validation-summary')).toBeVisible({ timeout: 15_000 });
    });
});