#!/usr/bin/env node
/**
 * Renders HTML to a 600x800 PNG with Playwright/Chromium.
 *
 *   node scripts/thumbnail.mjs <input.html|-> <output.png> [settleMs]
 *
 * `-` reads the HTML from stdin (used by TemplateThumbnailService; the HTML is
 * never passed as a shell argument). The HTML is untrusted user content, so:
 * JavaScript is disabled, only `data:` and `https://` requests to public hosts
 * are allowed (hostnames are resolved with dns.lookup and loopback / private /
 * link-local / metadata addresses are refused), and the whole run is capped by
 * a hard timeout.
 *
 * Exit codes: 0 = PNG written, 2 = bad arguments, 3 = playwright or Chromium
 * unavailable, 1 = any other failure. The PHP side treats every non-zero code
 * as "no thumbnail" (graceful no-op).
 */
import { readFileSync } from 'node:fs';
import { lookup } from 'node:dns/promises';
import { isIP } from 'node:net';
import { fileURLToPath } from 'node:url';

const HARD_TIMEOUT_MS = 45000;

/** True for addresses that must never be reached from user HTML. */
export function isPrivateAddress(address) {
    const v = isIP(address);
    if (v === 4) {
        const [a, b] = address.split('.').map(Number);
        return a === 0 || a === 10 || a === 127
            || (a === 169 && b === 254)
            || (a === 172 && b >= 16 && b <= 31)
            || (a === 192 && b === 168)
            || (a === 100 && b >= 64 && b <= 127)
            || a >= 224;
    }
    if (v === 6) {
        const x = address.toLowerCase();
        if (x === '::' || x === '::1') return true;
        const mapped = x.match(/^::ffff:(\d+\.\d+\.\d+\.\d+)$/);
        if (mapped) return isPrivateAddress(mapped[1]);
        return /^f[cd]/.test(x) || /^fe[89ab]/.test(x) || x.startsWith('ff');
    }
    return true;
}

/** Decides whether a request URL may be fetched. */
export async function isAllowedUrl(rawUrl, resolver = lookup) {
    if (rawUrl.startsWith('data:')) return true;
    let url;
    try {
        url = new URL(rawUrl);
    } catch {
        return false;
    }
    if (url.protocol !== 'https:') return false;
    const host = url.hostname.replace(/^\[|\]$/g, '');
    if (host === '' || host === 'localhost' || host.endsWith('.localhost') || host.endsWith('.internal')) return false;
    if (isIP(host)) return !isPrivateAddress(host);
    try {
        const records = await resolver(host, { all: true });
        return records.length > 0 && records.every((r) => !isPrivateAddress(r.address));
    } catch {
        return false;
    }
}

async function main() {
    const [input, output, settle] = process.argv.slice(2);
    if (!input || !output) {
        console.error('usage: thumbnail.mjs <input.html|-> <output.png> [settleMs]');
        process.exit(2);
    }

    const killer = setTimeout(() => {
        console.error('thumbnail timeout');
        process.exit(1);
    }, HARD_TIMEOUT_MS);

    let chromium;
    try {
        ({ chromium } = await import('playwright'));
    } catch {
        console.error('playwright is not installed');
        process.exit(3);
    }

    let browser;
    try {
        // THUMBNAIL_CHROMIUM_PATH lets a server use a system Chromium instead of
        // the browser downloaded by `npx playwright install chromium`.
        browser = await chromium.launch({ executablePath: process.env.THUMBNAIL_CHROMIUM_PATH || undefined });
    } catch (error) {
        console.error(`chromium unavailable: ${error.message}`);
        process.exit(3);
    }

    try {
        const html = readFileSync(input === '-' ? 0 : input, 'utf8');
        const context = await browser.newContext({
            viewport: { width: 600, height: 800 },
            javaScriptEnabled: false,
            serviceWorkers: 'block',
        });
        await context.route('**/*', async (route) => {
            if (await isAllowedUrl(route.request().url())) {
                await route.continue();
            } else {
                await route.abort('blockedbyclient');
            }
        });
        const page = await context.newPage();
        await page.setContent(html, { waitUntil: 'load', timeout: 15000 }).catch(() => {});
        await page.waitForTimeout(Math.min(Number(settle ?? 2000), 5000));
        await page.screenshot({ path: output, type: 'png', clip: { x: 0, y: 0, width: 600, height: 800 } });
    } catch (error) {
        console.error(error.message);
        process.exit(1);
    } finally {
        clearTimeout(killer);
        await browser.close();
    }
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
    await main();
}
