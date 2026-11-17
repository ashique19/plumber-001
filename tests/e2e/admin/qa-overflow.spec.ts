import { expect, test } from '@playwright/test';
import { loginAsOwner } from '../helpers/auth.js';

const viewports = [
    { name: '390', width: 390, height: 844 },
    { name: '1280', width: 1280, height: 900 },
];

for (const viewport of viewports) {
    test(`no horizontal overflow on dues and reports [${viewport.name}]`, async ({ page }) => {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });
        await loginAsOwner(page);

        for (const path of ['/admin/payments/dues', '/admin/reports']) {
            await page.goto(path);
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            expect(overflow, `overflow on ${path}`).toBeLessThanOrEqual(2);
        }
    });

    test(`classic print preview fits viewport [${viewport.name}]`, async ({ page }) => {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });
        await loginAsOwner(page);
        await page.goto('/admin/print/preview/invoice/classic');
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(4);
    });
}