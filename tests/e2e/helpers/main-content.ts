import { type Page, expect } from 'playwright/test';

export type BoxMetrics = {
    x: number;
    y: number;
    width: number;
    height: number;
};

export async function mainContentBox(page: Page): Promise<BoxMetrics> {
    const main = page.locator('main.app-content').first();
    await expect(main).toBeVisible();

    const box = await main.boundingBox();
    expect(box).not.toBeNull();

    return {
        x: box!.x,
        y: box!.y,
        width: box!.width,
        height: box!.height,
    };
}

export function assertBoxesAligned(before: BoxMetrics, after: BoxMetrics, tolerancePx = 2): void {
    expect(Math.abs(before.x - after.x), `main content x shifted (${before.x} -> ${after.x})`).toBeLessThanOrEqual(tolerancePx);
    expect(Math.abs(before.width - after.width), `main content width shifted (${before.width} -> ${after.width})`).toBeLessThanOrEqual(tolerancePx);
}