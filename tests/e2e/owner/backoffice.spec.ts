import { VIEW_PROFILES } from '../profiles.js';
import { attachAudit, assertPageHealthy } from '../helpers/audit.js';
import { owner } from '../helpers/credentials.js';
import { tenantLogin } from '../helpers/auth.js';
import { tenantPath } from '../helpers/urls.js';
import { withLivewireUpdate } from '../helpers/livewire.js';
import { test, expect } from 'playwright/test';

for (const profile of VIEW_PROFILES) {
    test(`owner backoffice surfaces [${profile.name}]`, async ({ browser }) => {
        const context = await browser.newContext({ viewport: { width: profile.width, height: profile.height } });
        const page = await context.newPage();
        const failures = attachAudit(page);

        await tenantLogin(page, owner.email, owner.password);

        await page.goto(tenantPath('/reports', profile.locale));
        await expect(page.locator('.apexcharts-canvas, [data-apexcharts]').first()).toBeVisible({ timeout: 30_000 });
        await page.getByRole('button', { name: /7 days|7 أيام/i }).first().click();
        const downloadPromise = page.waitForEvent('download', { timeout: 15_000 }).catch(() => null);
        await page.getByRole('link', { name: /export csv|تصدير csv/i }).click();
        await downloadPromise;

        await page.goto(tenantPath('/catalog', profile.locale));
        await page.getByRole('button', { name: /add product|منتج جديد/i }).click();
        await page.locator('#productNameEn, input[wire\\:model="productNameEn"]').fill(`E2E Latte ${Date.now()}`);
        await page.locator('#productPriceInput, input[wire\\:model="productPriceInput"]').fill('28.75');
        const productForm = page.locator('form[wire\\:submit="saveProduct"]');
        await withLivewireUpdate(page, () =>
            productForm.getByRole('button', { name: /save|حفظ/i }).click(),
        );

        await page.goto(tenantPath('/team?tab=staff', profile.locale));

        await page.goto(tenantPath('/customers', profile.locale));
        await expect(page.getByText(/regular guest|ضيف/i).first()).toBeVisible();

        await page.goto(tenantPath('/invoices', profile.locale));
        await expect(page.locator('article, .app-card').filter({ hasText: /POS-|simplified|مبسطة/i }).first()).toBeVisible({ timeout: 15_000 });

        await page.goto(tenantPath('/pos/branches/1/eod', profile.locale));
        await expect(page.getByRole('heading', { name: /end of day|إغلاق اليوم/i })).toBeVisible();
        await page.locator('input[type="number"]').first().fill('100');

        await page.goto(tenantPath('/pos/branches/1/ops', profile.locale));
        await expect(page.locator('.app-kpi-grid, .app-page-title').first()).toBeVisible();

        await assertPageHealthy(page, failures);
        await context.close();
    });
}
