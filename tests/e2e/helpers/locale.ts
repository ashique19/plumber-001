import { type Page } from 'playwright/test';

export async function applyLocale(page: Page, locale: 'en' | 'ar'): Promise<void> {
    const url = new URL(page.url());
    url.searchParams.set('locale', locale);
    await page.goto(url.toString());
    await page.waitForLoadState('domcontentloaded');
}

export async function switchLocaleNav(page: Page, locale: 'en' | 'ar'): Promise<void> {
    const link = page.locator(`a[href*="locale=${locale}"]`).first();
    await link.click();
    await page.waitForLoadState('domcontentloaded');
}
