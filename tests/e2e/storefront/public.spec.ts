import { VIEW_PROFILES } from '../profiles.js';
import { attachAudit, assertPageHealthy } from '../helpers/audit.js';
import { storefrontPath } from '../helpers/urls.js';
import { test, expect } from 'playwright/test';

for (const profile of VIEW_PROFILES) {
    test(`public storefront [${profile.name}]`, async ({ browser }) => {
        const context = await browser.newContext({ viewport: { width: profile.width, height: profile.height } });
        const page = await context.newPage();
        const failures = attachAudit(page);

        await page.goto(storefrontPath('/', profile.locale));
        await expect(page.locator('header, .cafe-hero, main').first()).toBeVisible();

        if (profile.width < 500) {
            const trigger = page.locator('[data-cafe-header] .flex.shrink-0 .cafe-locale-trigger');
            await expect(trigger).toBeVisible();
            await expect(page.locator('[data-cafe-header] .flex.shrink-0 .cafe-locale-bar')).toBeHidden();
            const brand = page.locator('[data-cafe-header] a').first();
            await expect(brand).toBeVisible();
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
            expect(overflow).toBe(false);

            const menuBtn = page.locator('[data-cafe-header] button.cafe-pill-cta--ghost');
            await menuBtn.click();
            const drawerLink = page.locator('#cafe-mobile-nav .cafe-mobile-nav__link').first();
            await expect(drawerLink).toBeVisible();
            const contrast = await drawerLink.evaluate((el) => {
                const fg = getComputedStyle(el).color;
                const bg = getComputedStyle(el.closest('#cafe-mobile-nav') as Element).backgroundColor;
                return { fg, bg };
            });
            expect(contrast.fg).not.toBe(contrast.bg);

            await menuBtn.click();
            await expect(drawerLink).toBeHidden();

            await page.evaluate(() => window.scrollTo(0, 800));
            await expect(page.locator('[data-cafe-header]')).toHaveClass(/cafe-header--solid/);
            await menuBtn.click();
            await expect(drawerLink).toBeVisible();
            const contrastScrolled = await drawerLink.evaluate((el) => {
                const fg = getComputedStyle(el).color;
                const bg = getComputedStyle(el.closest('#cafe-mobile-nav') as Element).backgroundColor;
                return { fg, bg };
            });
            expect(contrastScrolled.fg).not.toBe(contrastScrolled.bg);
        }

        await page.goto(storefrontPath('/menu', profile.locale));
        await expect(page.getByRole('heading', { name: /menu|القائمة/i })).toBeVisible();
        await expect(page.locator('.cafe-menu-sticky-chips').first()).toBeVisible();
        await expect(page.locator('[data-cafe-header]')).toHaveClass(/cafe-header--solid/);

        if (profile.width < 500) {
            const chips = page.locator('.cafe-menu-sticky-chips');
            await page.locator('.cafe-menu-card').last().scrollIntoViewIfNeeded();
            await expect(chips).toBeVisible();
        }

        const unlockMap = page.locator('[data-cafe-map-unlock]').first();
        if (await unlockMap.isVisible({ timeout: 3000 }).catch(() => false)) {
            await unlockMap.click({ timeout: 5000 });
        }

        if (profile.width < 500) {
            const navBtn = page.getByRole('link', { name: /menu|القائمة/i }).first();
            if (await navBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
                await navBtn.click({ timeout: 5000 });
            }
        }

        await assertPageHealthy(page, failures);
        await context.close();
    });
}
