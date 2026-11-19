import path from 'node:path';
import { fileURLToPath } from 'node:url';

const e2eRoot = path.dirname(fileURLToPath(import.meta.url));

/** Playwright failure artifacts (traces, screenshots on failure). */
export function e2eOutputDir(): string {
    return process.env.E2E_ARTIFACTS || path.join(e2eRoot, '..', 'test-results');
}

/** Optional hub/UI screenshots written during successful tests. */
export function e2eScreenshotDir(): string {
    return process.env.E2E_SCREENSHOT_DIR || e2eOutputDir();
}