import { defineConfig, devices } from 'playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname(fileURLToPath(import.meta.url));
const smoke = process.env.E2E_SMOKE === '1';

export default defineConfig({
    testDir: root,
    testMatch: '**/*.spec.ts',
    fullyParallel: false,
    workers: 1,
    timeout: smoke ? 45_000 : 90_000,
    expect: { timeout: smoke ? 10_000 : 20_000 },
    retries: smoke ? 0 : (process.env.CI ? 1 : 0),
    reporter: [['list'], ['html', { open: 'never', outputFolder: path.join(root, 'report') }]],
    outputDir: process.env.E2E_ARTIFACTS || '/opt/cursor/artifacts/e2e',
    use: {
        baseURL: process.env.E2E_BASE_URL || 'http://127.0.0.1:8765',
        trace: 'off',
        screenshot: 'only-on-failure',
        video: 'off',
        actionTimeout: 60_000,
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
