import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';

const PATHS = [
    '/admin/invoices/issue/simplified',
    '/admin/invoices/issue/standard',
    '/admin/quotations/create',
] as const;

async function openQuickProductModal(page: import('playwright/test').Page): Promise<void> {
    const add = page.locator('.invoice-add-line-search .admin-typeahead__add');
    await expect(add).toBeVisible();
    await add.click();
    await expect(page.locator('.x-modal-backdrop input#quickProductNameEn')).toBeVisible({ timeout: 15_000 });
}

async function expectNameInputsAligned(page: import('playwright/test').Page): Promise<void> {
    const en = page.locator('input#quickProductNameEn');
    const ar = page.locator('input#quickProductNameAr');
    await expect(en).toBeVisible();
    await expect(ar).toBeVisible();

    const enBox = await en.boundingBox();
    const arBox = await ar.boundingBox();
    expect(enBox).not.toBeNull();
    expect(arBox).not.toBeNull();

    const delta = Math.abs((enBox?.y ?? 0) - (arBox?.y ?? 0));
    expect(delta, 'EN/AR name inputs should top-align within 2px').toBeLessThanOrEqual(2);

    const hint = page.locator('#quickProductNameAr').locator('xpath=ancestor::label[1]//span[contains(@class,"form-field__hint--after")]');
    await expect(hint).toBeVisible();
    const hintBox = await hint.boundingBox();
    const inputBottom = (arBox?.y ?? 0) + (arBox?.height ?? 0);
    expect(hintBox?.y ?? 0, 'Arabic hint should sit below the input').toBeGreaterThanOrEqual(inputBottom - 1);
}

test.describe('quick product modal name fields @ 1280', () => {
    test.use({ viewport: { width: 1280, height: 900 } });

    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    for (const path of PATHS) {
        test(`aligned names on ${path}`, async ({ page }) => {
            await page.goto(adminPath(path, 'en'));
            await page.waitForLoadState('networkidle');
            await openQuickProductModal(page);
            await expectNameInputsAligned(page);

            const modal = page.locator('.x-modal-backdrop');
            await modal.locator('button.tap-action-primary').click();
            await expect(modal.locator('input#quickProductNameAr').locator('xpath=ancestor::label[1]//span[contains(@class,"form-field__error")]')).toBeVisible({
                timeout: 15_000,
            });
            await expectNameInputsAligned(page);
        });
    }

    test('aligned names in Arabic RTL', async ({ page }) => {
        await page.goto(adminPath('/admin/invoices/issue/simplified', 'ar'));
        await page.waitForLoadState('networkidle');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await openQuickProductModal(page);
        await expectNameInputsAligned(page);
    });
});