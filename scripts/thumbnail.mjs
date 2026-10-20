#!/usr/bin/env node
/**
 * Renders an HTML file to a 600x800 PNG with Playwright/Chromium.
 *
 *   node scripts/thumbnail.mjs <input.html> <output.png> [settleMs]
 *
 * Used by App\Modules\Templates\Services\TemplateThumbnailService (docs/45
 * §45.6.2). Exit codes: 0 = PNG written, 2 = bad arguments, 3 = playwright or
 * Chromium unavailable, 1 = any other failure. The PHP side treats every
 * non-zero code as "no thumbnail" (graceful no-op).
 */
import { readFileSync } from 'node:fs';

const [input, output, settle] = process.argv.slice(2);
if (!input || !output) {
    console.error('usage: thumbnail.mjs <input.html> <output.png> [settleMs]');
    process.exit(2);
}

let chromium;
try {
    ({ chromium } = await import('playwright'));
} catch {
    console.error('playwright is not installed');
    process.exit(3);
}

let browser;
try {
    browser = await chromium.launch();
} catch (error) {
    console.error(`chromium unavailable: ${error.message}`);
    process.exit(3);
}

try {
    const page = await browser.newPage({ viewport: { width: 600, height: 800 } });
    await page.setContent(readFileSync(input, 'utf8'), { waitUntil: 'load', timeout: 15000 }).catch(() => {});
    await page.waitForTimeout(Number(settle ?? 2000));
    await page.screenshot({ path: output, type: 'png', clip: { x: 0, y: 0, width: 600, height: 800 } });
} catch (error) {
    console.error(error.message);
    process.exit(1);
} finally {
    await browser.close();
}
