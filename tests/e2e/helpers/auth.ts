import { type Page, expect } from 'playwright/test';
import { TENANT_SLUG } from './credentials.js';

export async function tenantLogin(page: Page, email: string, password: string): Promise<void> {
    await page.goto(`/app/${TENANT_SLUG}/login`);
    await page.locator('input[name="email"]').fill(email);
    await page.locator('input[name="password"]').fill(password);
    await page.getByRole('button', { name: /sign in|log in|دخول/i }).click();
    await expect(page).toHaveURL(new RegExp(`/app/${TENANT_SLUG}/pos`));
}

export async function tenantLogout(page: Page): Promise<void> {
    let logout = page.getByRole('button', { name: /log out|logout|خروج/i });
    if (!(await logout.isVisible().catch(() => false))) {
        const menu = page.locator('header.app-topbar button[aria-expanded], header.app-topbar button.tap-action-muted').first();
        await menu.click();
    }

    logout = page.getByRole('button', { name: /log out|logout|خروج/i });
    await logout.click();
    await expect(page).toHaveURL(new RegExp(`/app/${TENANT_SLUG}/login`));
}

export async function platformLogin(page: Page, email: string, password: string): Promise<void> {
    await page.goto('/platform/login');
    await page.locator('input[name="email"]').fill(email);
    await page.locator('input[name="password"]').fill(password);
    await page.getByRole('button', { name: /sign in|log in|دخول/i }).click();
    await expect(page).toHaveURL(/\/platform\/dashboard/);
}

export async function platformLogout(page: Page): Promise<void> {
    await page.goto('/platform/dashboard');
    const logout = page.getByRole('button', { name: /log out|logout|خروج/i });
    if (await logout.count()) {
        await logout.first().click();
    }
}
