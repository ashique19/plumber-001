import fs from 'node:fs';
import path from 'node:path';

import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';
import { adminSearchInputs, expectSearchIconClearance } from '../helpers/search-icon.js';

const LOCALES = ['en', 'bn', 'ar'] as const;
const ARTIFACT_DIR = process.env.E2E_ARTIFACTS || '/opt/cursor/artifacts/e2e';

type Surface = {
    name: string;
    path: string;
    extra?: (page: import('playwright/test').Page) => Promise<void>;
};

const SURFACES: Surface[] = [
    { name: 'purchase-create', path: '/admin/purchases/create' },
    { name: 'suppliers-hub', path: '/admin/suppliers' },
    { name: 'invoice-simplified', path: '/admin/invoices/issue/simplified' },
    { name: 'invoice-standard', path: '/admin/invoices/issue/standard' },
    { name: 'quotation-create', path: '/admin/quotations/create' },
];

for (const locale of LOCALES) {
    test.describe(`admin search icon padding (${locale})`, () => {
        test.use({ viewport: { width: 1280, height: 900 } });

        test.beforeEach(async ({ page }) => {
            await staffLogin(page);
        });

        for (const surface of SURFACES) {
            test(`${surface.name} @ 1280`, async ({ page }) => {
                await page.goto(adminPath(surface.path, locale));
                await page.waitForLoadState('networkidle');
                await expect(page.locator('html.admin-app')).toHaveCount(1);

                if (surface.extra) {
                    await surface.extra(page);
                }

                const inputs = adminSearchInputs(page);
                const count = await inputs.count();
                expect(count, `expected at least one search input on ${surface.name}`).toBeGreaterThan(0);

                for (let i = 0; i < count; i += 1) {
                    const input = inputs.nth(i);
                    await expect(input).toBeVisible();
                    await expectSearchIconClearance(input);
                }

                const shotDir = path.join(ARTIFACT_DIR, 'search-icon-padding', locale);
                fs.mkdirSync(shotDir, { recursive: true });
                await page.screenshot({
                    path: path.join(shotDir, `${surface.name}-1280.png`),
                    fullPage: false,
                });
            });
        }
    });
}