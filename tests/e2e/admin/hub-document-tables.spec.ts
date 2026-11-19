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

    test('row more menu aligns to trigger on first click', async ({ page }) => {
        await page.goto(adminPath('/admin/invoices', 'en'));
        await expect(page.locator('.hub-doc-table')).toBeVisible({ timeout: 20_000 });

        const more = page.locator('[data-testid="hub-row-action-more"]').first();
        if ((await more.count()) === 0) {
            test.skip();
        }

        const triggerBox = await more.boundingBox();
        expect(triggerBox).not.toBeNull();

        await more.click();
        const panel = page.locator('.hub-doc-table__more-panel--teleport[role="menu"]').first();
        await expect(panel).toBeVisible({ timeout: 5_000 });
        await expect(panel).not.toHaveClass(/hub-doc-table__more-panel--positioning/);

        const panelBox = await panel.boundingBox();
        expect(panelBox).not.toBeNull();
        if (triggerBox && panelBox) {
            expect(panelBox.x).toBeGreaterThan(120);
            expect(panelBox.x + panelBox.width).toBeLessThanOrEqual(triggerBox.x + triggerBox.width + 24);
            expect(Math.abs(panelBox.x + panelBox.width - (triggerBox.x + triggerBox.width))).toBeLessThan(32);
        }
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