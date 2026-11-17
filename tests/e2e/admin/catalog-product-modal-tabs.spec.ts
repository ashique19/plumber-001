import { test, expect } from 'playwright/test';
import { staffLogin } from '../helpers/staff-auth.js';
import {
    expectProductFormTabsFullyVisible,
    openAddProductModal,
} from '../helpers/catalog-product-modal.js';

const LOCALES = ['en', 'bn', 'ar'] as const;
const VIEWPORTS = [
    { width: 390, height: 844, name: '390' },
    { width: 1280, height: 900, name: '1280' },
] as const;

for (const locale of LOCALES) {
    for (const viewport of VIEWPORTS) {
        test(`product modal tabs are fully visible below header [${locale} @ ${viewport.name}]`, async ({ page }) => {
            await page.setViewportSize({ width: viewport.width, height: viewport.height });
            await staffLogin(page);
            await openAddProductModal(page, locale);

            const modal = page.locator('[data-testid="catalog-product-modal"]').first();
            await expect(modal).toBeVisible();
            await expectProductFormTabsFullyVisible(modal);
        });
    }
}