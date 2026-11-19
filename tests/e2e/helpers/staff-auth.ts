import { type Page, expect } from 'playwright/test';

const DEFAULT_EMAIL = 'owner@example.test';
const DEFAULT_PASSWORD = 'TestPassword!2026';

export async function staffLogin(
    page: Page,
    email: string = DEFAULT_EMAIL,
    password: string = DEFAULT_PASSWORD,
): Promise<void> {
    await page.goto('/?locale=en');
    await page.waitForLoadState('domcontentloaded');

    if (/\/(admin|app)\//.test(page.url())) {
        return;
    }

    const emailInput = page.locator('input[name="email"]');
    await expect(emailInput).toBeVisible({ timeout: 15_000 });
    await emailInput.fill(email);
    await page.locator('input[name="password"]').fill(password);
    await page.locator('form[action*="login"] button[type="submit"], button[type="submit"]').first().click();
    await expect(page).toHaveURL(/\/(admin|app)\//, { timeout: 60_000 });
}

export function adminPath(path: string, locale: 'en' | 'bn' | 'ar'): string {
    const base = path.startsWith('/') ? path : `/${path}`;
    const join = base.includes('?') ? '&' : '?';
    return `${base}${join}locale=${locale}`;
}