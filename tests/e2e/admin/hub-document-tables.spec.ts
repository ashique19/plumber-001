import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';

test.describe('invoice hub table', () => {
    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    test('table visible with sticky column and actions', async ({ page }) => {
        await page.goto(adminPath('/admin/invoices', 'en'));
        await page.waitForLoadState('networkidle');

        const table = page.locator('.hub-doc-table');
        await expect(table).toBeVisible({ timeout: 20_000 });
        await expect(table.locator('th.hub-doc-table__sticky')).toBeVisible();
        await expect(table.locator('th').filter({ hasText: /zatca/i }).first()).toBeVisible();
        const printOrDisabled = table.locator('.hub-doc-table__action, .hub-doc-table__action--disabled');
        const empty = table.getByText(/no issued invoices/i);
        await expect(printOrDisabled.or(empty).first()).toBeVisible();
    });

    test('screenshots at 375px and 1280px', async ({ page }) => {
        await page.goto(adminPath('/admin/invoices', 'ar'));
        await expect(page.locator('.hub-doc-table')).toBeVisible({ timeout: 20_000 });

        await page.setViewportSize({ width: 375, height: 812 });
        await page.screenshot({
            path: '/opt/cursor/artifacts/invoices-table-375.png',
            fullPage: false,
        });

        await page.setViewportSize({ width: 1280, height: 900 });
        await page.screenshot({
            path: '/opt/cursor/artifacts/invoices-table-1280.png',
            fullPage: false,
        });
    });
});

test.describe('quotation hub table', () => {
    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    test('table layout', async ({ page }) => {
        await page.goto(adminPath('/admin/quotations', 'en'));
        await page.waitForLoadState('networkidle');

        const wrap = page.locator('.hub-doc-table-wrap');
        const table = page.locator('.hub-doc-table');
        if (await table.count()) {
            await expect(wrap).toBeVisible();
            await expect(table.locator('th.hub-doc-table__sticky')).toBeVisible();
        } else {
            await expect(page.getByText(/no quotations/i)).toBeVisible();
        }
    });
});