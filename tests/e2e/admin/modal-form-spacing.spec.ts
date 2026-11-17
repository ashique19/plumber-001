import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';
import {
    expectCustomerModalFormSpacing,
    openAddCustomerModal,
} from '../helpers/modal-form-spacing.js';

const LOCALES = ['en', 'bn', 'ar'] as const;
const VIEWPORTS = [
    { width: 390, height: 844, name: '390' },
    { width: 1280, height: 900, name: '1280' },
] as const;

for (const locale of LOCALES) {
    for (const viewport of VIEWPORTS) {
        test(`customer modal form spacing [${locale} @ ${viewport.name}]`, async ({ page }) => {
            await page.setViewportSize({ width: viewport.width, height: viewport.height });
            await staffLogin(page);
            await openAddCustomerModal(page, locale);

            const modal = page.locator('.x-modal-backdrop .x-modal').first();
            await expectCustomerModalFormSpacing(modal);
        });
    }
}

for (const viewport of VIEWPORTS) {
    test(`product add modal uses shared field stack [en @ ${viewport.name}]`, async ({ page }) => {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });
        await staffLogin(page);
        await page.goto(adminPath('/admin/catalog?tab=products&create=product', 'en'));
        await expect(page.locator('#catalog-product-form')).toBeVisible({ timeout: 20_000 });

        const stack = page.locator('#catalog-product-form .admin-modal-field-stack').first();
        await expect(stack).toBeVisible();
    });

    test(`expense add sheet uses modal field rhythm [en @ ${viewport.name}]`, async ({ page }) => {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });
        await staffLogin(page);
        await page.goto(adminPath('/admin/expenses', 'en'));
        const addExpense = page.getByRole('button', { name: /add expense/i }).first();
        await addExpense.click();
        await expect(page.locator('.x-modal-form__fields .form-field__control').first()).toBeVisible({ timeout: 15_000 });
    });
}