import { test, expect } from 'playwright/test';
import { adminPath, staffLogin } from '../helpers/staff-auth.js';

test.describe('dashboard overview compact board', () => {
    test.beforeEach(async ({ page }) => {
        await staffLogin(page);
    });

    test('renders single overview card with eight compact tiles', async ({ page }) => {
        await page.goto(adminPath('/admin/dashboard', 'en'));
        const board = page.locator('.dashboard-action-board');
        await expect(board).toBeVisible();
        await expect(board.locator('.dashboard-action-tile')).toHaveCount(8);
        await expect(board.getByText('Overview')).toBeVisible();
    });

    test('capture mobile and desktop screenshots', async ({ page }) => {
        await page.goto(adminPath('/admin/dashboard', 'en'));
        await expect(page.locator('.dashboard-action-board')).toBeVisible();

        await page.setViewportSize({ width: 375, height: 812 });
        await page.screenshot({
            path: '/opt/cursor/artifacts/dashboard-overview-375.png',
            clip: (await page.locator('.dashboard-action-board').boundingBox()) ?? undefined,
        });

        await page.setViewportSize({ width: 1280, height: 900 });
        await page.screenshot({
            path: '/opt/cursor/artifacts/dashboard-overview-1280.png',
            clip: (await page.locator('.dashboard-action-board').boundingBox()) ?? undefined,
        });
    });
});