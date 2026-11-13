import { VIEW_PROFILES } from '../profiles.js';
import { attachAudit, assertPageHealthy } from '../helpers/audit.js';
import { owner, TENANT_SLUG } from '../helpers/credentials.js';
import { tenantLogin, tenantLogout } from '../helpers/auth.js';
import { tenantPath } from '../helpers/urls.js';
import { fillPromoDiscount, payCurrentOrder } from '../helpers/pos.js';
import { waitForLivewireIdle, withLivewireUpdate } from '../helpers/livewire.js';
import { test, expect } from 'playwright/test';

for (const profile of VIEW_PROFILES) {
    test(`owner POS terminal pay QR locale logout [${profile.name}]`, { timeout: 300_000 }, async ({ browser }) => {
        const context = await browser.newContext({ viewport: { width: profile.width, height: profile.height } });
        const page = await context.newPage();
        const failures = attachAudit(page);

        await tenantLogin(page, owner.email, owner.password);
        await page.goto(tenantPath('/pos', profile.locale));
        await waitForLivewireIdle(page);
        const terminalHref = await page.locator('a[href*="/terminal"]').first().getAttribute('href');
        expect(terminalHref).toBeTruthy();

        const methods = profile.name === 'en-1280' ? (['cash', 'card'] as const) : (['cash'] as const);

        for (const method of methods) {
            await page.goto(`${terminalHref!}${terminalHref!.includes('?') ? '&' : '?'}locale=${profile.locale}`);
            await waitForLivewireIdle(page);

            const addBtn = page.getByRole('button', { name: /add|أضف|إضافة/i }).first();
            await withLivewireUpdate(page, () => addBtn.click());

            await fillPromoDiscount(page, '1.5');
            await payCurrentOrder(page, method);

            const tlv = await page.locator('#receipt-qr').getAttribute('data-tlv');
            expect(tlv).toBeTruthy();
            expect(Buffer.from(tlv ?? '', 'base64').length).toBeGreaterThan(8);
        }

        await assertPageHealthy(page, failures);

        const otherLocale = profile.locale === 'en' ? 'ar' : 'en';
        await page.goto(tenantPath('/pos', otherLocale));
        await expect(page.locator('html')).toHaveAttribute('lang', otherLocale);
        await tenantLogout(page);
        await expect(page).toHaveURL(new RegExp(`/app/${TENANT_SLUG}/login`));
        await context.close();
    });
}
