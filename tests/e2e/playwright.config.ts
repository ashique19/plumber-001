import { defineConfig, devices } from 'playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    testDir: root,
    testMatch: '**/*.spec.ts',
    fullyParallel: false,
    workers: 1,
    timeout: 300_000,
    expect: { timeout: 25_000 },
    retries: process.env.CI ? 1 : 0,
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
