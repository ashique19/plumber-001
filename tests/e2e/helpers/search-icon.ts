import { type Locator, expect } from 'playwright/test';

export type SearchIconMetrics = {
    ok: boolean;
    reason?: string;
    paddingInlineStart: number;
    paddingInlineEnd: number;
    iconWidth: number;
    dir: string;
};

export async function measureSearchIconClearance(input: Locator): Promise<SearchIconMetrics> {
    return input.evaluate((el) => {
        const wrap = el.closest('.admin-field-wrap--with-icon, .admin-typeahead__input-wrap.admin-field-wrap--with-icon');
        const icon = wrap?.querySelector('.admin-field-icon');
        if (! wrap || ! icon) {
            return {
                ok: false,
                reason: 'missing admin-field-wrap--with-icon or .admin-field-icon',
                paddingInlineStart: 0,
                paddingInlineEnd: 0,
                iconWidth: 0,
                dir: document.documentElement.getAttribute('dir') || 'ltr',
            };
        }

        const style = window.getComputedStyle(el);
        const paddingInlineStart = parseFloat(style.paddingInlineStart) || 0;
        const paddingInlineEnd = parseFloat(style.paddingInlineEnd) || 0;
        const dir = document.documentElement.getAttribute('dir') || 'ltr';
        const iconRect = icon.getBoundingClientRect();
        const inputRect = el.getBoundingClientRect();
        const iconWidth = Math.max(iconRect.width, iconRect.height);
        const requiredPad = iconWidth + 12;

        if (paddingInlineStart < requiredPad) {
            return {
                ok: false,
                reason: `paddingInlineStart ${paddingInlineStart.toFixed(1)}px < ${requiredPad.toFixed(1)}px (icon + 12px gap)`,
                paddingInlineStart,
                paddingInlineEnd,
                iconWidth,
                dir,
            };
        }

        const iconOverlapsTextStart =
            dir === 'rtl'
                ? iconRect.left < inputRect.right - paddingInlineStart
                : iconRect.right > inputRect.left + paddingInlineStart;

        if (iconOverlapsTextStart) {
            return {
                ok: false,
                reason: 'search icon bounding box extends past padded text inset',
                paddingInlineStart,
                paddingInlineEnd,
                iconWidth,
                dir,
            };
        }

        return {
            ok: true,
            paddingInlineStart,
            paddingInlineEnd,
            iconWidth,
            dir,
        };
    });
}

export async function expectSearchIconClearance(input: Locator): Promise<SearchIconMetrics> {
    const metrics = await measureSearchIconClearance(input);
    expect(metrics.ok, metrics.reason ?? JSON.stringify(metrics)).toBeTruthy();

    return metrics;
}

export function adminSearchInputs(locator: import('playwright/test').Page | import('playwright/test').Locator): Locator {
    return locator.locator('input[data-admin-search-input="1"]');
}