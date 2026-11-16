import { expect, test } from '@playwright/test';
import { staffLogin } from '../helpers/staff-auth';

const templates = ['modern', 'classic'] as const;

test.describe('print template visual regression', () => {
    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    for (const template of templates) {
        test(`invoice template ${template} renders print sheet`, async ({ page }) => {
            await page.goto(`/admin/print/preview/invoice/${template}`);
            await expect(page.locator('.invoice-print-sheet')).toBeVisible({ timeout: 60_000 });
            await page.screenshot({
                path: `test-results/print-invoice-${template}.png`,
                fullPage: true,
            });
        });
    }
});