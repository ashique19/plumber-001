import { type Locator, type Page } from 'playwright/test';

const IDLE_TIMEOUT = 60_000;
const REQUEST_START_TIMEOUT = 15_000;

function livewireIdlePredicate(): boolean {
    const inFlight = (window as unknown as { __livewireInFlight?: () => number }).__livewireInFlight?.() ?? 0;
    const navigating = document.documentElement.classList.contains('livewire-navigate-loading');

    return inFlight === 0 && !navigating;
}

/** Wait until the app reports no in-flight Livewire requests. */
export async function waitForLivewireIdle(page: Page): Promise<void> {
    await page.waitForFunction(livewireIdlePredicate, { timeout: IDLE_TIMEOUT });
}

/**
 * Perform a Livewire-driving action, then wait until all Livewire HTTP round-trips finish.
 * Uses the app's {@link window.__livewireInFlight} counter (no arbitrary sleeps).
 */
export async function withLivewireUpdate(page: Page, action: () => Promise<void>): Promise<void> {
    const before = await page.evaluate(() => (window as unknown as { __livewireInFlight?: () => number }).__livewireInFlight?.() ?? 0);

    await action();

    if (before === 0) {
        await page
            .waitForFunction(() => ((window as unknown as { __livewireInFlight?: () => number }).__livewireInFlight?.() ?? 0) > 0, {
                timeout: REQUEST_START_TIMEOUT,
            })
            .catch(() => null);
    }

    await waitForLivewireIdle(page);
}

/** wire:model.blur fields only sync on blur — fill then blur inside one Livewire round-trip. */
export async function livewireFillBlur(page: Page, field: Locator, value: string): Promise<void> {
    await withLivewireUpdate(page, async () => {
        await field.fill(value);
        await field.blur();
    });
}
