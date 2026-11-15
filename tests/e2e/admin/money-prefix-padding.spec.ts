import path from 'node:path';
import fs from 'node:fs';

import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';
import { adminPrefixInputs, expectPrefixClearance } from '../helpers/prefix-padding.js';

const LOCALES = ['en', 'bn', 'ar'] as const;
const ARTIFACT_DIR = process.env.E2E_ARTIFACTS || '/opt/cursor/artifacts/e2e';

type Surface = {
    name: string;
    path: string;
    prepare: (page: import('playwright/test').Page) => Promise<void>;
};

async function addInvoiceLineFromCatalog(page: import('playwright/test').Page): Promise<void> {
    const search = page.locator('.invoice-add-line-search input[data-admin-search-input="1"]').first();
    await search.fill('SRV');
    const option = page.locator('.invoice-add-line-search .invoice-customer-picker__option').first();
    await expect(option).toBeVisible({ timeout: 15_000 });
    await option.click();
    await expect(page.locator('.invoice-document-lines input[data-admin-prefix-input="1"]').first()).toBeVisible({
        timeout: 15_000,
    });
}

async function addPurchaseLineFromCatalog(page: import('playwright/test').Page): Promise<void> {
    const search = page.locator('.purchase-add-line-search input[data-admin-search-input="1"]').first();
    await search.fill('SRV');
    const option = page.locator('.purchase-add-line-search .invoice-customer-picker__option').first();
    await expect(option).toBeVisible({ timeout: 15_000 });
    await option.click();
    await expect(page.locator('input[data-admin-prefix-input="1"]').first()).toBeVisible({ timeout: 15_000 });
}

const SURFACES: Surface[] = [
    {
        name: 'invoice-simplified',
        path: '/admin/invoices/issue/simplified',
        prepare: addInvoiceLineFromCatalog,
    },
    {
        name: 'invoice-standard',
        path: '/admin/invoices/issue/standard',
        prepare: addInvoiceLineFromCatalog,
    },
    {
        name: 'quotation-create',
        path: '/admin/quotations/create',
        prepare: addInvoiceLineFromCatalog,
    },
    {
        name: 'purchase-create',
        path: '/admin/purchases/create',
        prepare: addPurchaseLineFromCatalog,
    },
    {
        name: 'expenses-hub',
        path: '/admin/expenses',
        prepare: async () => {},
    },
];

for (const locale of LOCALES) {
    test.describe(`admin money prefix padding (${locale})`, () => {
        test.use({ viewport: { width: 1280, height: 900 } });

        test.beforeEach(async ({ page }) => {
            await staffLogin(page);
        });

        for (const surface of SURFACES) {
            test(`${surface.name} @ 1280`, async ({ page }) => {
                await page.goto(adminPath(surface.path, locale));
                await page.waitForLoadState('networkidle');
                await expect(page.locator('html.admin-app')).toHaveCount(1);

                await surface.prepare(page);

                const inputs = adminPrefixInputs(page);
                const count = await inputs.count();
                expect(count).toBeGreaterThan(0);

                for (let i = 0; i < count; i++) {
                    const input = inputs.nth(i);
                    await input.scrollIntoViewIfNeeded();
                    const metrics = await expectPrefixClearance(input);

                    const shotDir = path.join(ARTIFACT_DIR, 'money-prefix-padding', locale, surface.name);
                    fs.mkdirSync(shotDir, { recursive: true });
                    await input.screenshot({
                        path: path.join(shotDir, `prefix-input-${i}-pad-${metrics.paddingInlineStart.toFixed(0)}.png`),
                    });
                }
            });
        }
    });
}