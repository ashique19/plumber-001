import path from 'node:path';

import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';
import { assertBoxesAligned, mainContentBox } from '../helpers/main-content.js';

const LOCALES = ['en', 'bn', 'ar'] as const;
const ARTIFACT_DIR = process.env.E2E_ARTIFACTS || '/opt/cursor/artifacts/e2e';

async function closeModalWithCancel(page: import('playwright/test').Page): Promise<void> {
    const cancel = page.locator('.x-modal__footer button.tap-action-muted, .x-modal__close').first();
    await cancel.click();
    await expect(page.locator('.x-modal-backdrop')).toHaveCount(0, { timeout: 15_000 });
    await page.waitForFunction(() => document.body.style.overflow !== 'hidden');
}

for (const locale of LOCALES) {
    test.describe(`modal scroll lock (${locale})`, () => {
        test.use({ viewport: { width: 1280, height: 900 } });

        test.beforeEach(async ({ page }) => {
            await staffLogin(page);
        });

        test(`purchase quick supplier open+cancel preserves main layout (${locale})`, async ({ page }) => {
            await page.goto(adminPath('/admin/purchases/create', locale));
            await page.waitForLoadState('networkidle');

            const before = await mainContentBox(page);
            await page.locator('.purchase-supplier-picker .admin-typeahead__add').click();
            await expect(page.locator('.x-modal-backdrop')).toBeVisible();

            await closeModalWithCancel(page);

            const after = await mainContentBox(page);
            assertBoxesAligned(before, after);

            const shotDir = path.join(ARTIFACT_DIR, 'modal-scroll-lock', locale);
            await import('node:fs').then((fs) => fs.mkdirSync(shotDir, { recursive: true }));
            await page.screenshot({ path: path.join(shotDir, 'purchase-after-cancel.png') });
        });

        test(`purchase quick product open+cancel preserves main layout (${locale})`, async ({ page }) => {
            await page.goto(adminPath('/admin/purchases/create', locale));
            await page.waitForLoadState('networkidle');

            const before = await mainContentBox(page);
            const addProduct = page.locator('.purchase-add-line-search .admin-typeahead__add');
            if ((await addProduct.count()) === 0) {
                test.skip();

                return;
            }

            await addProduct.click();
            await expect(page.locator('.x-modal-backdrop')).toBeVisible();

            await closeModalWithCancel(page);

            const after = await mainContentBox(page);
            assertBoxesAligned(before, after);
        });

        test(`suppliers hub create open+cancel preserves main layout (${locale})`, async ({ page }) => {
            await page.goto(adminPath('/admin/suppliers', locale));
            await page.waitForLoadState('networkidle');

            const before = await mainContentBox(page);
            await page.getByRole('button', { name: new RegExp('add|যোগ|إضافة', 'i') }).first().click();
            await expect(page.locator('.x-modal-backdrop')).toBeVisible();

            await closeModalWithCancel(page);

            const after = await mainContentBox(page);
            assertBoxesAligned(before, after);
        });
    });
}