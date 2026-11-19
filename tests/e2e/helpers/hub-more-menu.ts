import { type Locator, type Page, expect } from 'playwright/test';
import { adminPath } from './staff-auth.js';

type Rect = { top: number; right: number; bottom: number; left: number; width: number; height: number };

async function clientRect(locator: Locator): Promise<Rect> {
    return locator.evaluate((el) => {
        const r = el.getBoundingClientRect();

        return {
            top: r.top,
            right: r.right,
            bottom: r.bottom,
            left: r.left,
            width: r.width,
            height: r.height,
        };
    });
}

export async function expectHubMoreMenuAnchoredToTrigger(page: Page, moreButton: Locator): Promise<void> {
    await moreButton.scrollIntoViewIfNeeded();
    await expect(moreButton).toBeVisible();

    const triggerRect = await clientRect(moreButton);

    await moreButton.click();

    const panel = page.locator('.hub-doc-table__more-panel--teleport[role="menu"][data-hub-row-more-panel-open]');
    await expect(panel).toBeVisible({ timeout: 8_000 });

    const panelRect = await clientRect(panel);
    const flipped = await panel.evaluate((el) => el.classList.contains('hub-doc-table__more-panel--flip-up'));

    if (flipped) {
        expect(panelRect.bottom).toBeLessThanOrEqual(triggerRect.top + 4);
    } else {
        expect(panelRect.top).toBeGreaterThanOrEqual(triggerRect.bottom - 4);
    }

    expect(Math.abs(panelRect.right - triggerRect.right)).toBeLessThanOrEqual(12);

    await page.keyboard.press('Escape');
}

export async function prepareInvoiceHub(page: Page): Promise<void> {
    await page.goto(adminPath('/admin/invoices', 'en'));
    await page.waitForLoadState('networkidle');
    const issued = page.locator('.hub-doc-table__tab').filter({ hasText: /issued/i }).first();
    if (await issued.count()) {
        await issued.click();
        await page.waitForLoadState('networkidle');
    }
}

export async function prepareQuotationHub(page: Page): Promise<void> {
    await page.goto(adminPath('/admin/quotations', 'en'));
    await page.waitForLoadState('networkidle');
    const all = page.locator('[data-testid="hub-quotation-lifecycle-tabs"] .hub-doc-table__tab').first();
    if (await all.count()) {
        await all.click();
        await page.waitForLoadState('networkidle');
    }
}

export async function prepareCustomerHub(page: Page): Promise<void> {
    await page.goto(adminPath('/admin/customers', 'en'));
    await page.waitForLoadState('networkidle');
    await expect(page.locator('[data-testid="customers-table"]')).toBeVisible({ timeout: 20_000 });

    const actionsCell = page.locator('.admin-data-table__actions-cell').first();
    if (await actionsCell.count()) {
        await actionsCell.scrollIntoViewIfNeeded();
    }
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
        .locator('[data-testid="customers-table"] [data-testid^="hub-row-action-more-customer-"]')
        .filter({ visible: true })
        .first();
}