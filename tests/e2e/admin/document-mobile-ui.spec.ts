import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';
import {
    addCatalogLine,
    expectBrandLineClamp,
    expectFocusedInputNotCoveredByToast,
    expectNoHorizontalScroll,
    expectQuotationTotalsBreakdown,
    expectSingleMobilePrimarySubmit,
    expectStandardLineFieldsVisible,
    expectToastClearWhenModalOpens,
} from '../helpers/document-mobile.js';

const MOBILE = { width: 390, height: 844 } as const;

const PAGES = [
    {
        name: 'invoice-simplified',
        path: '/admin/invoices/issue/simplified',
        sticky: /issue invoice|إصدار فاتورة|ইনভয়েস ইস্যু/i,
        standard: false,
    },
    {
        name: 'invoice-standard',
        path: '/admin/invoices/issue/standard',
        sticky: /issue invoice|إصدار فاتورة|ইনভয়েস ইস্যু/i,
        standard: true,
    },
    {
        name: 'quotation-create',
        path: '/admin/quotations/create',
        sticky: /save quotation|حفظ عرض|কোটেশন সংরক্ষণ/i,
        standard: true,
    },
] as const;

for (const pageConfig of PAGES) {
    test.describe(`document mobile UI — ${pageConfig.name}`, () => {
        test.use({ viewport: MOBILE });

        test.beforeEach(async ({ page }) => {
            await staffLogin(page);
        });

        test(`layout + actions (${pageConfig.name})`, async ({ page }) => {
            await page.goto(adminPath(pageConfig.path, 'en'));
            await page.waitForLoadState('networkidle');

            await expectNoHorizontalScroll(page);
            await expectBrandLineClamp(page);
            await expect(page.locator('.app-locale-switch-menu')).toBeVisible();
            await expect(page.locator('.app-locale-switch--pills')).toBeHidden();

            await addCatalogLine(page);

            if (pageConfig.standard) {
                await expectStandardLineFieldsVisible(page);
            }

            await expectQuotationTotalsBreakdown(page);
            await expectSingleMobilePrimarySubmit(page, pageConfig.sticky);

            const focusTarget = page.locator('.invoice-add-line-search input[data-admin-search-input="1"]').first();
            await expectFocusedInputNotCoveredByToast(page, focusTarget);

            await expectToastClearWhenModalOpens(page, async () => {
                await page.locator('.admin-typeahead__add').first().click();
            });
        });
    });
}

test.describe('document mobile UI — theme + RTL spot checks', () => {
    test.use({ viewport: MOBILE });

    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    test('arabic RTL quotation', async ({ page }) => {
        await page.goto(adminPath('/admin/quotations/create', 'ar'));
        await page.waitForLoadState('networkidle');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expectNoHorizontalScroll(page);
        await expect(page.locator('.app-locale-switch-menu')).toBeVisible();
    });

    test('dark mode standard invoice', async ({ page }) => {
        await page.goto(adminPath('/admin/invoices/issue/standard', 'en'));
        await page.waitForLoadState('networkidle');
        await page.locator('.admin-theme-toggle').click();
        await expect
            .poll(async () => page.locator('html').evaluate((el) => el.classList.contains('admin-theme-dark')))
            .toBeTruthy();
        await addCatalogLine(page);
        await expectStandardLineFieldsVisible(page);
        await expectNoHorizontalScroll(page);
    });
});