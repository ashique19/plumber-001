import { type Locator, type Page, expect } from 'playwright/test';

export async function expectHubMoreMenuAnchoredToTrigger(page: Page, moreButton: Locator): Promise<void> {
    await expect(moreButton).toBeVisible();

    const triggerBox = await moreButton.boundingBox();
    expect(triggerBox).not.toBeNull();

    await moreButton.click();

    const panel = page.locator('.hub-doc-table__more-panel--teleport[role="menu"][data-hub-row-more-panel-open]');
    await expect(panel).toBeVisible({ timeout: 8_000 });

    const panelBox = await panel.boundingBox();
    expect(panelBox).not.toBeNull();

    if (! triggerBox || ! panelBox) {
        return;
    }

    const triggerRight = triggerBox.x + triggerBox.width;
    const panelRight = panelBox.x + panelBox.width;

    expect(panelBox.top).toBeGreaterThanOrEqual(triggerBox.y + triggerBox.height - 2);
    expect(Math.abs(panelRight - triggerRight)).toBeLessThanOrEqual(12);
}

export function invoiceTableMoreButton(page: Page, viewport: 'table' | 'card'): Locator {
    const root =
        viewport === 'table'
            ? page.locator('div.hidden.sm\\:block >> .hub-doc-table')
            : page.locator('[data-testid="hub-invoice-cards"]');

    return root
        .locator(`[data-testid^="hub-row-action-more-invoice-"][data-testid$="-${viewport}"]`)
        .first();
}

export function quotationTableMoreButton(page: Page, viewport: 'table' | 'card'): Locator {
    const root =
        viewport === 'table'
            ? page.locator('div.hidden.sm\\:block >> .hub-doc-table')
            : page.locator('[data-testid="hub-quotation-cards"]');

    return root
        .locator(`[data-testid^="hub-row-action-more-quotation-"][data-testid$="-${viewport}"]`)
        .first();
}

export function customerTableMoreButton(page: Page): Locator {
    return page
        .locator('[data-testid="customers-table"]')
        .locator('[data-testid^="hub-row-action-more-customer-"]')
        .first();
}