import { type Locator, type Page, expect } from 'playwright/test';
import { adminPath } from './staff-auth.js';

export async function openAddProductModal(page: Page, locale: 'en' | 'bn' | 'ar'): Promise<void> {
    await page.goto(adminPath('/admin/catalog?tab=products&create=product', locale));
    await page.waitForLoadState('networkidle');
    await expect(page.locator('#catalog-product-form')).toBeVisible({ timeout: 20_000 });
}

export async function expectProductFormTabsFullyVisible(modal: Locator): Promise<void> {
    const header = modal.locator('.x-modal__header').first();
    const tabs = modal.locator('.catalog-product-form-tabs [role="tab"]');
    const tabCount = await tabs.count();

    expect(tabCount, 'product form tab buttons').toBeGreaterThanOrEqual(3);

    const headerBox = await header.boundingBox();
    const modalBox = await modal.boundingBox();

    expect(headerBox, 'modal header box').not.toBeNull();
    expect(modalBox, 'modal box').not.toBeNull();

    const headerBottom = headerBox!.y + headerBox!.height;

    for (let i = 0; i < tabCount; i++) {
        const tab = tabs.nth(i);
        await expect(tab).toBeVisible();

        const tabBox = await tab.boundingBox();
        expect(tabBox, `tab ${i} bounding box`).not.toBeNull();

        const overlapPx = headerBottom - tabBox!.y;
        expect(overlapPx, `tab ${i} must sit below header (overlap px)`).toBeLessThanOrEqual(1);

        expect(tabBox!.y + tabBox!.height, `tab ${i} bottom`).toBeLessThanOrEqual(modalBox!.y + modalBox!.height + 1);
        expect(tabBox!.x, `tab ${i} left`).toBeGreaterThanOrEqual(modalBox!.x - 1);
        expect(tabBox!.x + tabBox!.width, `tab ${i} right`).toBeLessThanOrEqual(modalBox!.x + modalBox!.width + 1);

        const geometryOk = await tab.evaluate(
            (el, headerBottomY) => {
                const rect = el.getBoundingClientRect();
                return rect.top >= headerBottomY - 1 && rect.height >= 28 && rect.width >= 24;
            },
            headerBottom,
        );

        expect(geometryOk, `tab ${i} bounding box must be fully below header with readable size`).toBe(true);
    }
}