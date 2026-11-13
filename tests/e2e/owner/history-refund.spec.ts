import { VIEW_PROFILES } from '../profiles.js';
import { attachAudit, assertPageHealthy } from '../helpers/audit.js';
import { owner } from '../helpers/credentials.js';
import { tenantLogin } from '../helpers/auth.js';
import { tenantPath } from '../helpers/urls.js';
import { payCurrentOrder } from '../helpers/pos.js';
import { waitForLivewireIdle, withLivewireUpdate } from '../helpers/livewire.js';
import { test, expect } from 'playwright/test';

for (const profile of VIEW_PROFILES) {
    test(`owner history search refund idempotent [${profile.name}]`, async ({ browser }) => {
        const context = await browser.newContext({ viewport: { width: profile.width, height: profile.height } });
        const page = await context.newPage();
        const failures = attachAudit(page);

        await tenantLogin(page, owner.email, owner.password);

        await page.goto(tenantPath('/pos', profile.locale));
        const terminalHref = await page.locator('a[href*="/terminal"]').first().getAttribute('href');
        await page.goto(`${terminalHref}${terminalHref?.includes('?') ? '&' : '?'}locale=${profile.locale}`);
        await waitForLivewireIdle(page);
        await withLivewireUpdate(page, () => page.getByRole('button', { name: /add|أضف|إضافة/i }).first().click());
        await payCurrentOrder(page, 'cash');

        const orderNumber = (await page.locator('article.receipt-sheet dd').nth(1).textContent())?.trim();
        expect(orderNumber).toBeTruthy();

        const historyHref = terminalHref?.replace('/terminal', '/history') ?? tenantPath('/pos/branches/1/history', profile.locale);
        await page.goto(`${historyHref}${historyHref.includes('?') ? '&' : '?'}locale=${profile.locale}`);
        await waitForLivewireIdle(page);

        await withLivewireUpdate(page, () => page.locator('input[type="search"]').fill(orderNumber!));
        await withLivewireUpdate(page, () => page.selectOption('select[wire\\:model\\.live="statusFilter"]', 'paid'));

        const orderRow = page.locator('article:visible, tbody tr:visible').filter({ hasText: orderNumber! }).first();
        await expect(orderRow).toBeVisible({ timeout: 15_000 });

        const refundBtn = orderRow.getByRole('button', { name: /refund|استرجاع/i });
        await withLivewireUpdate(page, () => refundBtn.click());
        await expect(page.locator('#refundReason')).toBeVisible();

        await page.locator('#refundReason').fill('E2E customer changed mind');
        await withLivewireUpdate(page, () => page.getByRole('button', { name: /issue credit|إصدار إشعار/i }).click());
        await expect(page.locator('#refundReason')).toBeHidden({ timeout: 20_000 });

        await expect(orderRow.getByRole('button', { name: /refund|استرجاع/i })).toHaveCount(0);
        await withLivewireUpdate(page, () => page.selectOption('select[wire\\:model\\.live="statusFilter"]', 'refunded'));
        await expect(orderRow).toContainText(/refunded/i);

        await assertPageHealthy(page, failures);
        await context.close();
    });
}
