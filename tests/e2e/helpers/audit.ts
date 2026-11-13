import { type Page, expect } from 'playwright/test';

const LIVEWIRE_UPDATE = /\/livewire-(update|upload)/;

export type AuditOptions = {
    allow403?: boolean;
    allowedUrls?: RegExp[];
};

export function attachAudit(page: Page, opts: AuditOptions = {}): string[] {
    const failures: string[] = [];

    page.on('console', (msg) => {
        if (msg.type() === 'error') {
            const text = msg.text();
            if (text.includes('favicon') || text.includes('DevTools')) {
                return;
            }
            if (opts.allow403 && text.includes('403')) {
                return;
            }
            failures.push(`console.error: ${text}`);
        }
    });

    page.on('pageerror', (err) => {
        failures.push(`pageerror: ${err.message}`);
    });

    page.on('response', (response) => {
        const status = response.status();
        const url = response.url();
        if (status < 400) {
            return;
        }
        if (opts.allow403 && status === 403) {
            return;
        }
        if (opts.allowedUrls?.some((re) => re.test(url))) {
            return;
        }
        if (LIVEWIRE_UPDATE.test(url) && status === 419) {
            failures.push(`livewire 419: ${url}`);
            return;
        }
        const isApp = url.includes('127.0.0.1') || url.includes('localhost');
        const isAsset = /\.(css|js|woff2?|png|webp|svg|ico)(\?|$)/i.test(url);
        if (!isApp || (status === 404 && isAsset)) {
            return;
        }
        if (status >= 400 && status < 600) {
            failures.push(`HTTP ${status}: ${url}`);
        }
    });

    return failures;
}

export async function assertPageHealthy(page: Page, failures: string[]): Promise<void> {
    await page.waitForLoadState('domcontentloaded').catch(() => {});

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
    if (overflow) {
        failures.push('horizontal overflow detected');
    }

    const bodyText = await page.locator('body').innerText();
    if (/translation (missing|not found)/i.test(bodyText)) {
        failures.push('untranslated Laravel string marker in DOM');
    }
    const keyLike = bodyText.match(/\b(?:pos|catalog|operations|reports|customers|invoices|zatca_admin|platform|loading)\.[a-z][a-z0-9_.]{2,}\b/gi);
    if (keyLike?.length) {
        failures.push(`possible translation keys visible: ${keyLike.slice(0, 5).join(', ')}`);
    }

    expect(failures, failures.join('\n')).toEqual([]);
}

export async function expectSingleRowLoading(page: Page, label: RegExp): Promise<void> {
    const count = await page.getByRole('button', { name: label }).filter({ hasText: /Issuing|جارٍ|Saving|Loading/i }).count();
    expect(count).toBeLessThanOrEqual(1);
}
