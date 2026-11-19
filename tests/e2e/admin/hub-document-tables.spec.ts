import { test, expect } from 'playwright/test';
import path from 'node:path';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';
import { e2eScreenshotDir } from '../helpers/e2e-artifacts.js';
import {
    customerTableMoreButton,
    expectHubMoreMenuAnchoredToTrigger,
    invoiceTableMoreButton,
    quotationTableMoreButton,
} from '../helpers/hub-more-menu.js';

const VIEWPORTS = [
    { width: 390, height: 844, layout: 'card' as const },
    { width: 1280, height: 900, layout: 'table' as const },
];

test.describe('invoice hub table', () => {
    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    test('table visible with sticky column and actions', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto(adminPath('/admin/invoices', 'en'));
        await page.waitForLoadState('networkidle');

        const table = page.locator('div.hidden.sm\\:block >> .hub-doc-table');
        await expect(table).toBeVisible({ timeout: 20_000 });
        await expect(table.locator('th.hub-doc-table__sticky')).toBeVisible();
        const printOrDisabled = table.locator('.hub-doc-table__action, .hub-doc-table__action--disabled');
        const empty = table.getByText(/no issued invoices/i);
        await expect(printOrDisabled.or(empty).first()).toBeVisible();
    });

    for (const viewport of VIEWPORTS) {
        test(`row more menu anchors to trigger on first click @ ${viewport.width}`, async ({ page }) => {
            await page.setViewportSize({ width: viewport.width, height: viewport.height });
            await page.goto(adminPath('/admin/invoices', 'en'));
            await page.waitForLoadState('networkidle');

            const more = invoiceTableMoreButton(page, viewport.layout);
            if ((await more.count()) === 0) {
                test.skip();
            }

            await expectHubMoreMenuAnchoredToTrigger(page, more);
        });
    }

    test('screenshots at 375px and 1280px', async ({ page }) => {
        await page.goto(adminPath('/admin/invoices', 'ar'));
        await expect(page.locator('div.hidden.sm\\:block >> .hub-doc-table')).toBeVisible({ timeout: 20_000 });

        const shotDir = e2eScreenshotDir();

        await page.setViewportSize({ width: 375, height: 812 });
        await page.screenshot({
            path: path.join(shotDir, 'invoices-table-375.png'),
            fullPage: false,
        });

        await page.setViewportSize({ width: 1280, height: 900 });
        await page.screenshot({
            path: path.join(shotDir, 'invoices-table-1280.png'),
            fullPage: false,
        });
    });
});

test.describe('quotation hub table', () => {
    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    test('table layout', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto(adminPath('/admin/quotations', 'en'));
        await page.waitForLoadState('networkidle');

        const table = page.locator('div.hidden.sm\\:block >> .hub-doc-table');
        const wrap = table.locator('xpath=ancestor::div[contains(@class,"hub-doc-table-wrap")]');
        if (await table.count()) {
            await expect(wrap).toBeVisible();
            await expect(table.locator('th.hub-doc-table__sticky')).toBeVisible();
        } else {
            await expect(page.getByText(/no quotations/i)).toBeVisible();
        }
    });

    for (const viewport of VIEWPORTS) {
        test(`row more menu anchors to trigger on first click @ ${viewport.width}`, async ({ page }) => {
            await page.setViewportSize({ width: viewport.width, height: viewport.height });
            await page.goto(adminPath('/admin/quotations', 'en'));
            await page.waitForLoadState('networkidle');

            const more = quotationTableMoreButton(page, viewport.layout);
            if ((await more.count()) === 0) {
                test.skip();
            }

            await expectHubMoreMenuAnchoredToTrigger(page, more);
        });
    }
});

test.describe('customer hub table', () => {
    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    for (const width of [390, 1280] as const) {
        test(`row more menu anchors to trigger on first click @ ${width}`, async ({ page }) => {
            await page.setViewportSize({ width, height: width === 390 ? 844 : 900 });
            await page.goto(adminPath('/admin/customers', 'en'));
            await page.waitForLoadState('networkidle');

            const more = customerTableMoreButton(page);
            if ((await more.count()) === 0) {
                test.skip();
            }

            await expectHubMoreMenuAnchoredToTrigger(page, more);
        });
    }
});