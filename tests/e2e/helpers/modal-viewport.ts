import { type Page, expect } from 'playwright/test';

export async function expectDialogInViewport(page: Page, dialogSelector = '.x-modal-backdrop .x-modal'): Promise<void> {
    const dialog = page.locator(dialogSelector).first();
    await expect(dialog).toBeVisible({ timeout: 20_000 });

    const box = await dialog.boundingBox();
    const viewport = page.viewportSize();

    expect(box, 'dialog bounding box').not.toBeNull();
    expect(viewport, 'viewport size').not.toBeNull();

    const tolerance = 4;
    expect(box!.x).toBeGreaterThanOrEqual(-tolerance);
    expect(box!.y).toBeGreaterThanOrEqual(-tolerance);
    expect(box!.x + box!.width).toBeLessThanOrEqual(viewport!.width + tolerance);
    expect(box!.y + box!.height).toBeLessThanOrEqual(viewport!.height + tolerance);
}
