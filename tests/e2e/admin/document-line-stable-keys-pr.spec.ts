import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';
import { addCatalogLine, expectNoHorizontalScroll } from '../helpers/document-mobile.js';

const ARTIFACTS = '/opt/cursor/artifacts';

async function addMultipleLines(page: import('playwright').Page, count: number): Promise<void> {
    for (let i = 0; i < count; i++) {
        await addCatalogLine(page, 'SRV');
        await page.waitForTimeout(400);
    }
}

async function desktopLineRows(page: import('playwright').Page) {
    return page.locator('tr[data-document-line-id]');
}

async function removeMiddleDesktopLine(page: import('playwright').Page): Promise<void> {
    const lines = await desktopLineRows(page);
    const before = await lines.count();
    expect(before).toBeGreaterThanOrEqual(3);
    const middle = lines.nth(Math.floor(before / 2));
    await middle.locator('td').last().locator('button[type="button"]').click();
    await expect(lines).toHaveCount(before - 1, { timeout: 20_000 });
}

test.describe('document line stable keys — PR screenshots', () => {
    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    test('desktop 1280 — five lines then remove middle', async ({ page }) => {
        test.skip(!process.env.CAPTURE_LINE_SCREENSHOTS, 'set CAPTURE_LINE_SCREENSHOTS=1');
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto(adminPath('/admin/invoices/issue/standard', 'en'));
        await page.waitForLoadState('networkidle');

        await addMultipleLines(page, 5);
        await expect(page.locator('tr[data-document-line-id]')).toHaveCount(5);
        const cards = page.locator('.document-product-line-cards');
        const tableWrap = page.locator('.document-product-lines-scroll');
        await expect(cards).toHaveCSS('display', 'none');
        await expect(tableWrap).toBeVisible();
        await expectNoHorizontalScroll(page);
        await page.screenshot({ path: `${ARTIFACTS}/invoice-lines-desktop-5.png`, fullPage: true });

        await removeMiddleDesktopLine(page);
        await expect(await desktopLineRows(page)).toHaveCount(4);
        await page.screenshot({ path: `${ARTIFACTS}/invoice-lines-desktop-4-after-remove.png`, fullPage: true });
    });

    test('desktop 1280 — single line', async ({ page }) => {
        test.skip(!process.env.CAPTURE_LINE_SCREENSHOTS, 'set CAPTURE_LINE_SCREENSHOTS=1');
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto(adminPath('/admin/invoices/issue/standard', 'en'));
        await page.waitForLoadState('networkidle');
        await addCatalogLine(page);
        await expect(await desktopLineRows(page)).toHaveCount(1);
        await page.screenshot({ path: `${ARTIFACTS}/invoice-lines-desktop-1.png`, fullPage: true });
    });

    test('mobile 390 — two lines', async ({ page }) => {
        test.skip(!process.env.CAPTURE_LINE_SCREENSHOTS, 'set CAPTURE_LINE_SCREENSHOTS=1');
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(adminPath('/admin/invoices/issue/standard', 'en'));
        await page.waitForLoadState('networkidle');

        await addMultipleLines(page, 2);
        await expect(page.locator('.document-product-line-cards [data-document-line-id]')).toHaveCount(2);
        await expect(page.locator('.document-product-lines-scroll')).toBeHidden();
        await expectNoHorizontalScroll(page);
        await page.screenshot({ path: `${ARTIFACTS}/invoice-lines-mobile-2.png`, fullPage: true });
    });
});