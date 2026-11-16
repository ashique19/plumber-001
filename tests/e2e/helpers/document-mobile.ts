import { type Locator, type Page, expect } from 'playwright/test';

export async function expectNoHorizontalScroll(page: Page): Promise<void> {
    const overflow = await page.evaluate(() => {
        const root = document.documentElement;

        return root.scrollWidth - root.clientWidth;
    });
    expect(overflow, 'page should not scroll horizontally').toBeLessThanOrEqual(2);
}

export async function expectBrandLineClamp(page: Page): Promise<void> {
    const clamp = await page.locator('.app-topbar__brand-name').evaluate((el) => {
        const style = window.getComputedStyle(el);

        return style.getPropertyValue('-webkit-line-clamp') || style.webkitLineClamp;
    });
    expect(clamp).toBe('2');
}

export async function addCatalogLine(page: Page, query = 'SRV'): Promise<void> {
    const search = page.locator('.invoice-add-line-search input[data-admin-search-input="1"]').first();
    await search.fill(query);
    await page.waitForTimeout(250);
    const option = page.locator('.invoice-add-line-search .invoice-customer-picker__option').first();
    await expect(option).toBeVisible({ timeout: 15_000 });
    await option.click();
}

export async function expectSingleMobilePrimarySubmit(page: Page, stickyLabel: RegExp): Promise<void> {
    const desktop = page.locator('.document-primary-submit');
    await expect(desktop).toHaveCount(1);
    await expect(desktop).toBeHidden();

    const stickyButtons = page.locator('.document-sticky-action-bar button[type="submit"]');
    await expect(stickyButtons).toHaveCount(1);
    await expect(stickyButtons.first()).toBeVisible();
    await expect(stickyButtons.first()).toHaveText(stickyLabel);
}

export async function expectStandardLineFieldsVisible(page: Page): Promise<void> {
    await expect(page.locator('input[name^="lines."][name$=".quantity"]').first()).toBeVisible();
    await expect(page.locator('input[name^="lines."][name$=".unit_price"]').first()).toBeVisible();
    await expect(page.locator('input[name^="lines."][name$=".vat_rate"]').first()).toBeVisible();
}

export async function expectQuotationTotalsBreakdown(page: Page): Promise<void> {
    await expect(page.locator('.invoice-issue-preview-totals')).toBeVisible();
    await expect(page.getByText(/Subtotal \(ex VAT\)|উপমোট|الإجمالي بدون ضريبة/i)).toBeVisible();
    await expect(page.getByText(/Total VAT|মোট VAT|إجمالي ضريبة/i)).toBeVisible();
}

async function rectsOverlap(a: DOMRect, b: DOMRect): Promise<boolean> {
    return !(a.right < b.left || a.left > b.right || a.bottom < b.top || a.top > b.bottom);
}

export async function expectToastClearWhenModalOpens(page: Page, openModal: () => Promise<void>): Promise<void> {
    await page.evaluate(() => {
        window.dispatchEvent(
            new CustomEvent('toast', {
                detail: { type: 'success', message: 'E2E toast probe', dedupe: false },
            }),
        );
    });
    await expect(page.locator('#toast-stack .toast-item')).toHaveCount(1);

    await openModal();
    await expect(page.locator('.x-modal-backdrop')).toBeVisible();
    await expect(page.locator('#toast-stack .toast-item')).toHaveCount(0);
}

export async function expectToastDoesNotOverlapPageTitle(page: Page): Promise<void> {
    const h1 = page.locator('h1.app-page-title').first();
    await expect(h1).toBeVisible();

    await page.evaluate(() => {
        window.dispatchEvent(
            new CustomEvent('toast', {
                detail: { type: 'success', message: 'E2E title overlap probe', dedupe: false },
            }),
        );
    });
    await expect(page.locator('#toast-stack .toast-item')).toHaveCount(1);

    const overlaps = await page.evaluate(() => {
        const toast = document.querySelector('#toast-stack .toast-item');
        const title = document.querySelector('h1.app-page-title');
        if (! toast || ! title) {
            return false;
        }

        const a = toast.getBoundingClientRect();
        const b = title.getBoundingClientRect();

        return !(a.right < b.left || a.left > b.right || a.bottom < b.top || a.top > b.bottom);
    });
    expect(overlaps, 'toast should not overlap page title').toBe(false);
}

export async function expectDocumentTotalsRowsSeparated(page: Page): Promise<void> {
    const rows = page.locator('.invoice-issue-preview-totals__row');
    await expect(rows).toHaveCount(2);

    const gapOk = await rows.evaluateAll((elements) => {
        if (elements.length < 2) {
            return false;
        }

        const first = elements[0].getBoundingClientRect();
        const second = elements[1].getBoundingClientRect();

        return second.top >= first.bottom - 1;
    });
    expect(gapOk, 'totals breakdown rows should stack vertically').toBeTruthy();

    const columnGap = await rows.first().evaluate((row) => {
        const style = window.getComputedStyle(row);

        return parseFloat(style.columnGap) || 0;
    });
    expect(columnGap).toBeGreaterThanOrEqual(12);
}

export async function expectFocusedInputNotCoveredByToast(page: Page, input: Locator): Promise<void> {
    await input.focus();
    await page.evaluate(() => {
        window.dispatchEvent(
            new CustomEvent('toast', {
                detail: { type: 'success', message: 'E2E overlap probe', dedupe: false },
            }),
        );
    });
    await expect(page.locator('#toast-stack .toast-item')).toHaveCount(1);

    const overlaps = await page.evaluate(() => {
        const toast = document.querySelector('#toast-stack .toast-item');
        const active = document.activeElement;
        if (! toast || ! active || !(active instanceof HTMLElement)) {
            return false;
        }

        const a = toast.getBoundingClientRect();
        const b = active.getBoundingClientRect();

        return !(a.right < b.left || a.left > b.right || a.bottom < b.top || a.top > b.bottom);
    });
    expect(overlaps, 'toast should not overlap focused control').toBe(false);
}