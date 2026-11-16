import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';

type QuickAddCase = {
    name: string;
    path: string;
    addSelector: string;
    modalReadySelector: string;
};

const CASES: QuickAddCase[] = [
    {
        name: 'simplified-invoice-product',
        path: '/admin/invoices/issue/simplified',
        addSelector: '.invoice-add-line-search .admin-typeahead__add',
        modalReadySelector: 'input#quickProductNameEn',
    },
    {
        name: 'simplified-invoice-customer',
        path: '/admin/invoices/issue/simplified',
        addSelector: '.invoice-customer-picker .admin-typeahead__add',
        modalReadySelector: 'input#customerQuickName',
    },
    {
        name: 'standard-invoice-product',
        path: '/admin/invoices/issue/standard',
        addSelector: '.invoice-add-line-search .admin-typeahead__add',
        modalReadySelector: 'input#quickProductNameEn',
    },
    {
        name: 'standard-invoice-customer',
        path: '/admin/invoices/issue/standard',
        addSelector: '.invoice-customer-picker .admin-typeahead__add',
        modalReadySelector: 'input#customerQuickName',
    },
    {
        name: 'quotation-product',
        path: '/admin/quotations/create',
        addSelector: '.invoice-add-line-search .admin-typeahead__add',
        modalReadySelector: 'input#quickProductNameEn',
    },
    {
        name: 'quotation-customer',
        path: '/admin/quotations/create',
        addSelector: '.invoice-customer-picker .admin-typeahead__add',
        modalReadySelector: 'input#customerQuickName',
    },
    {
        name: 'purchase-supplier',
        path: '/admin/purchases/create',
        addSelector: '.purchase-supplier-picker .admin-typeahead__add',
        modalReadySelector: 'input#quickSupplierName',
    },
    {
        name: 'purchase-product',
        path: '/admin/purchases/create',
        addSelector: '.purchase-add-line-search .admin-typeahead__add',
        modalReadySelector: 'input#quickProductNameEn',
    },
];

async function hardReload(page: import('playwright/test').Page): Promise<void> {
    await page.reload({ waitUntil: 'networkidle' });
    await expect(page.locator('html.admin-app')).toHaveCount(1);
}

for (const viewport of [
    { label: 'desktop', size: { width: 1280, height: 900 } },
    { label: 'mobile', size: { width: 390, height: 844 } },
] as const) {
    test.describe(`quick-add first click after hard reload (${viewport.label})`, () => {
        test.use({ viewport: viewport.size });

        test.beforeEach(async ({ page }) => {
            await staffLogin(page);
        });

        for (const quickCase of CASES) {
            test(quickCase.name, async ({ page }) => {
                await page.goto(adminPath(quickCase.path, 'en'));
                await page.waitForLoadState('networkidle');
                await hardReload(page);

                const add = page.locator(quickCase.addSelector);
                await expect(add).toBeVisible();
                await add.click({ clickCount: 1 });

                const modal = page.locator('.x-modal-backdrop');
                await expect(modal).toBeVisible({ timeout: 10_000 });
                await expect(modal.locator(quickCase.modalReadySelector)).toBeVisible({ timeout: 10_000 });
            });
        }
    });
}