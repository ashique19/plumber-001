import { type Page } from 'playwright/test';
import { livewireFillBlur, withLivewireUpdate } from './livewire.js';

function cartRoot(page: Page, _mobile: boolean) {
    return page.locator('aside.pos-cart-glass');
}

export async function openPosCartIfMobile(page: Page): Promise<void> {
    const viewport = page.viewportSize();
    if (!viewport || viewport.width >= 1024) {
        return;
    }

    const root = cartRoot(page, true);
    if (await root.locator('#pos-payment-method').isVisible({ timeout: 500 }).catch(() => false)) {
        return;
    }

    await page.locator('[data-testid="pos-mobile-cart-open"]').click();
    await root.locator('#pos-payment-method').waitFor({ state: 'visible', timeout: 15_000 });
}

export async function fillPromoDiscount(page: Page, amount: string): Promise<void> {
    await openPosCartIfMobile(page);

    const mobile = (page.viewportSize()?.width ?? 1280) < 1024;
    const promo = cartRoot(page, mobile).locator('#pos-promo-discount');
    await promo.scrollIntoViewIfNeeded();
    await livewireFillBlur(page, promo, amount);
}

export async function payCurrentOrder(page: Page, method: string): Promise<void> {
    await openPosCartIfMobile(page);

    const mobile = (page.viewportSize()?.width ?? 1280) < 1024;
    const root = cartRoot(page, mobile);
    const paymentSelect = root.locator('#pos-payment-method');
    await paymentSelect.scrollIntoViewIfNeeded();
    await withLivewireUpdate(page, () => paymentSelect.selectOption(method));
    const receiptNav = page.waitForURL(/\/receipt/, { timeout: 60_000 });
    await root.getByRole('button', { name: /pay.*print|دفع/i }).click({ force: true });
    await receiptNav;
}
