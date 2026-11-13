import { VIEW_PROFILES } from '../profiles.js';
import { attachAudit, assertPageHealthy } from '../helpers/audit.js';
import { cashier } from '../helpers/credentials.js';
import { tenantLogin } from '../helpers/auth.js';
import { tenantPath } from '../helpers/urls.js';
import { test, expect } from 'playwright/test';

const forbidden = ['/catalog', '/team', '/reports', '/customers', '/invoices', '/zatca'];

for (const profile of VIEW_PROFILES) {
    test(`cashier 403 vs allowed POS [${profile.name}]`, async ({ browser }) => {
        const context = await browser.newContext({ viewport: { width: profile.width, height: profile.height } });
        const page = await context.newPage();
        const failures = attachAudit(page, { allow403: true });

        await tenantLogin(page, cashier.email, cashier.password);
        await expect(page).toHaveURL(/\/pos/);

        for (const path of forbidden) {
            const response = await page.goto(tenantPath(path, profile.locale));
            expect(response?.status()).toBe(403);
            await expect(page.locator('body')).toContainText(/403|denied|مرفوض|Access denied/i);
        }

        await page.goto(tenantPath('/pos', profile.locale));
        await expect(page.getByRole('link', { name: /terminal|نقطة البيع/i }).first()).toBeVisible();

        await assertPageHealthy(page, failures);
        await context.close();
    });
}
