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

async function hardReload(page: import('playwright/test').Page, settleMs = 0): Promise<void> {
    await page.reload({ waitUntil: 'networkidle' });
    await expect(page.locator('html.admin-app')).toHaveCount(1);
    if (settleMs > 0) {
        await page.waitForTimeout(settleMs);
    }
}

async function assertQuickAddOpensModal(
    page: import('playwright/test').Page,
    addSelector: string,
    modalReadySelector: string,
): Promise<void> {
    const add = page.locator(addSelector);
    await expect(add).toBeVisible();
    await add.click({ clickCount: 1, delay: 30 });

    const modal = page.locator('.x-modal-backdrop');
    await expect(modal).toBeVisible({ timeout: 15_000 });
    await expect(modal.locator(modalReadySelector)).toBeVisible({ timeout: 15_000 });
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

                await assertQuickAddOpensModal(page, quickCase.addSelector, quickCase.modalReadySelector);
            });
        }
    });
}

test.describe('quick-add first click after hard reload (standard invoice bn desktop)', () => {
    test.use({ viewport: { width: 1280, height: 900 } });

    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    test('standard-invoice-product opens নতুন পণ্য modal on first click', async ({ page }) => {
        await page.goto(adminPath('/admin/invoices/issue/standard', 'bn'));
        await page.waitForLoadState('networkidle');
        await hardReload(page, 5_000);

        await assertQuickAddOpensModal(
            page,
            '.invoice-add-line-search .admin-typeahead__add',
            'input#quickProductNameEn',
        );

        const modal = page.locator('.x-modal-backdrop');
        await expect(modal.getByRole('heading', { name: 'নতুন পণ্য' })).toBeVisible();
    });
});