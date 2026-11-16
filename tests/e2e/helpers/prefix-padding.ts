import { type Locator, expect } from 'playwright/test';

export type PrefixPaddingMetrics = {
    ok: boolean;
    reason?: string;
    paddingInlineStart: number;
    prefixWidth: number;
    dir: string;
};

export async function measurePrefixClearance(input: Locator): Promise<PrefixPaddingMetrics> {
    return input.evaluate((el) => {
        const wrap = el.closest('.admin-field-wrap--with-prefix');
        const prefix = wrap?.querySelector('.admin-field-prefix');
        if (! wrap || ! prefix) {
            return {
                ok: false,
                reason: 'missing admin-field-wrap--with-prefix or .admin-field-prefix',
                paddingInlineStart: 0,
                prefixWidth: 0,
                dir: document.documentElement.getAttribute('dir') || 'ltr',
            };
        }

        const style = window.getComputedStyle(el);
        const paddingInlineStart = parseFloat(style.paddingInlineStart) || 0;
        const dir = document.documentElement.getAttribute('dir') || 'ltr';
        const prefixRect = prefix.getBoundingClientRect();
        const inputRect = el.getBoundingClientRect();
        const prefixWidth = Math.max(prefixRect.width, prefixRect.height);
        const requiredPad = prefixWidth + 12;

        if (paddingInlineStart < requiredPad) {
            return {
                ok: false,
                reason: `paddingInlineStart ${paddingInlineStart.toFixed(1)}px < ${requiredPad.toFixed(1)}px (prefix + 12px gap)`,
                paddingInlineStart,
                prefixWidth,
                dir,
            };
        }

        const prefixOverlapsTextStart =
            dir === 'rtl'
                ? prefixRect.left < inputRect.right - paddingInlineStart
                : prefixRect.right > inputRect.left + paddingInlineStart;

        if (prefixOverlapsTextStart) {
            return {
                ok: false,
                reason: 'currency prefix bounding box extends past padded text inset',
                paddingInlineStart,
                prefixWidth,
                dir,
            };
        }

        return {
            ok: true,
            paddingInlineStart,
            prefixWidth,
            dir,
        };
    });
}

export async function expectPrefixClearance(input: Locator): Promise<PrefixPaddingMetrics> {
    const metrics = await measurePrefixClearance(input);
    expect(metrics.ok, metrics.reason ?? JSON.stringify(metrics)).toBeTruthy();

    return metrics;
}

export function adminPrefixInputs(page: import('playwright/test').Page): Locator {
    return page.locator('input[data-admin-prefix-input="1"]:visible');
}