import { type Locator, type Page, expect } from 'playwright/test';
import { adminPath } from './staff-auth.js';

export async function openAddCustomerModal(page: Page, locale: 'en' | 'bn' | 'ar'): Promise<void> {
    await page.goto(adminPath('/admin/customers?create=customer', locale));
    await page.waitForLoadState('networkidle');
    await expect(page.locator('[data-testid="customer-modal"]')).toBeVisible({ timeout: 20_000 });
}

export async function expectCustomerModalFormSpacing(modal: Locator): Promise<void> {
    const identity = modal.locator('[data-testid="customer-identity-fields"]').first();

    await expect(identity).toBeVisible();

    const modalBox = await modal.boundingBox();
    const identityBox = await identity.boundingBox();

    expect(modalBox, 'modal panel').not.toBeNull();
    expect(identityBox, 'identity grid').not.toBeNull();

    const contentPadding = 40;
    const innerWidth = modalBox!.width - contentPadding;
    expect(identityBox!.width, 'identity grid uses modal content width').toBeGreaterThanOrEqual(innerWidth * 0.88);

    const fullWidthInput = identity.locator('#customerLegalNameEn');
    await expect(fullWidthInput).toBeVisible({ timeout: 15_000 });
    const fullWidthBox = await fullWidthInput.boundingBox();
    expect(fullWidthBox!.width, 'full-width row inputs span the grid').toBeGreaterThanOrEqual(identityBox!.width * 0.88);

    const vatSegment = identity.locator('.admin-segmented').first();
    await expect(vatSegment).toBeVisible();
    const segBox = await vatSegment.boundingBox();
    expect(segBox!.width, 'VAT segmented toggle spans grid').toBeGreaterThanOrEqual(identityBox!.width * 0.9);

    const firstControl = identity.locator('.form-field__control').first();
    const vatLabel = identity.locator('.form-field__label', { hasText: /.+/ }).nth(2);
    const firstBox = await firstControl.boundingBox();
    const vatLabelBox = await vatLabel.boundingBox();

    expect(firstBox).not.toBeNull();
    expect(vatLabelBox).not.toBeNull();

    const secondFieldLabel = identity.locator('.form-field__label').nth(1);
    const secondLabelBox = await secondFieldLabel.boundingBox();
    expect(secondLabelBox).not.toBeNull();
    const verticalGap = secondLabelBox!.y - (firstBox!.y + firstBox!.height);
    expect(verticalGap, 'space between first control and second label').toBeGreaterThanOrEqual(12);

    const optionLabels = vatSegment.locator('.admin-segmented__option span');
    const optionCount = await optionLabels.count();
    expect(optionCount).toBeGreaterThanOrEqual(2);

    for (let i = 0; i < optionCount; i++) {
        const opt = optionLabels.nth(i);
        const optBox = await opt.boundingBox();
        expect(optBox!.height, `segment option ${i} readable height`).toBeGreaterThanOrEqual(20);
    }
}