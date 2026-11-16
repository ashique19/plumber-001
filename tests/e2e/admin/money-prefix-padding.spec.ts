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

async function pickFirstCatalogOption(
    page: import('playwright/test').Page,
    container: string,
    query: string,
): Promise<void> {
    const search = page.locator(`${container} input[data-admin-search-input="1"]`).first();
    await search.fill(query);
    await page.waitForTimeout(250);
    const option = page.locator(`${container} .invoice-customer-picker__option`).first();
    await expect(option).toBeVisible({ timeout: 15_000 });
    await option.click();
}

async function addInvoiceLineFromCatalog(page: import('playwright/test').Page): Promise<void> {
    await pickFirstCatalogOption(page, '.invoice-add-line-search', 'SRV');
    await expect(page.locator('.invoice-document-lines input[data-admin-prefix-input="1"]:visible').first()).toBeVisible({
        timeout: 15_000,
    });
}

async function addPurchaseLineFromCatalog(page: import('playwright/test').Page): Promise<void> {
    await pickFirstCatalogOption(page, '.purchase-add-line-search', 'PTFE');
    await expect(page.locator('input[name^="lines."][data-admin-prefix-input="1"]:visible').first()).toBeVisible({
        timeout: 15_000,
    });
}

async function openExpensesAmountField(page: import('playwright/test').Page): Promise<void> {
    const open = page.getByRole('button', { name: /add expense|খরচ যোগ|إضافة مصروف/i }).first();
    await open.click();
    await expect(page.locator('input[data-admin-prefix-input="1"]:visible').first()).toBeVisible({ timeout: 15_000 });
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
        prepare: openExpensesAmountField,
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
                expect(count, `expected visible SAR-prefixed inputs on ${surface.name}`).toBeGreaterThan(0);

                let tested = 0;
                for (let i = 0; i < count; i++) {
                    const input = inputs.nth(i);
                    if (!(await input.isVisible())) {
                        continue;
                    }
                    await input.scrollIntoViewIfNeeded();
                    const metrics = await expectPrefixClearance(input);
                    tested += 1;

                    const shotDir = path.join(ARTIFACT_DIR, 'money-prefix-padding', locale, surface.name);
                    fs.mkdirSync(shotDir, { recursive: true });
                    await input.screenshot({
                        path: path.join(shotDir, `prefix-input-${tested - 1}-pad-${metrics.paddingInlineStart.toFixed(0)}.png`),
                    });
                }

                expect(tested, `expected at least one visible SAR-prefixed input on ${surface.name}`).toBeGreaterThan(0);
            });
        }
    });
}