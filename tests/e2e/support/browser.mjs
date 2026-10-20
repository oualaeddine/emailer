// Shared Playwright plumbing: browser launch, 2FA login, issue collection,
// screenshot-on-failure and tiny API helpers.
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import test from 'node:test';
import { env } from './env.mjs';
import { totp } from './totp.mjs';

export const DESKTOP = { viewport: { width: 1440, height: 900 } };
export const PHONE = { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true };

export async function launchBrowser() {
    return chromium.launch({
        executablePath: env.chromiumPath,
        headless: !env.headed,
        args: process.getuid?.() === 0 ? ['--no-sandbox'] : [],
    });
}

export async function newContext(browser, { device = DESKTOP, storageState } = {}) {
    const context = await browser.newContext({
        ...device,
        storageState,
        baseURL: env.baseUrl,
        locale: 'fr-FR',
        timezoneId: 'Africa/Algiers',
        colorScheme: 'light',
    });
    context.setDefaultTimeout(15_000);
    context.setDefaultNavigationTimeout(30_000);
    return context;
}

/** Password + TOTP login. Returns the context's storageState. */
export async function login(page) {
    await page.goto('/login');
    await page.locator('input[type="email"], input[name="email"]').first().fill(env.adminEmail);
    await page.locator('input[type="password"]').first().fill(env.adminPassword);
    await page.locator('button[type="submit"]').first().click();
    await page.waitForURL(/two-factor\/challenge|dashboard/);
    if (page.url().includes('two-factor')) {
        const input = page.locator('input').first();
        await input.fill(totp(env.totpSecret));
        await page.locator('button[type="submit"]').first().click();
        await page.waitForURL((u) => !u.pathname.includes('two-factor'));
    }
    return page.context().storageState();
}

/**
 * Collects the problems the smoke suite cares about on a page: uncaught page
 * errors, same-origin HTTP >= 400 (assets under /build/ excepted) and failed
 * same-origin requests (net::ERR_ABORTED = navigation cancelled, ignored).
 */
export function watchPage(page) {
    const issues = [];
    const origin = env.baseUrl;
    const rel = (u) => u.replace(origin, '');
    page.on('pageerror', (e) => issues.push(`pageerror: ${String(e).slice(0, 300)}`));
    page.on('response', (r) => {
        const u = r.url();
        if (r.status() >= 400 && u.startsWith(origin)) issues.push(`http ${r.status()} ${r.request().method()} ${rel(u)}`);
    });
    page.on('requestfailed', (r) => {
        const err = r.failure()?.errorText ?? '';
        if (r.url().startsWith(origin) && !err.includes('ERR_ABORTED')) issues.push(`failed ${r.method()} ${rel(r.url())} ${err}`);
    });
    return { issues, clear: () => issues.splice(0) };
}

const slug = (s) => s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 80);

/**
 * `test()` that screenshots every registered page when the body throws.
 * The body receives `track(page)` to register pages for that screenshot.
 */
export function scenario(name, fn, options) {
    return test(name, options ?? {}, async (t) => {
        const pages = [];
        const track = (p) => { pages.push(p); return p; };
        try {
            await fn(track, t);
        } catch (error) {
            mkdirSync(env.artifactsDir, { recursive: true });
            let i = 0;
            for (const p of pages) {
                await p.screenshot({ path: join(env.artifactsDir, `${slug(name)}-${i++}.png`), fullPage: false }).catch(() => {});
            }
            throw error;
        }
    });
}

/** Authenticated JSON call through the browser session (Sanctum stateful). */
export async function api(context, method, path, data) {
    const cookies = await context.cookies(env.baseUrl);
    const xsrf = decodeURIComponent(cookies.find((c) => c.name === 'XSRF-TOKEN')?.value ?? '');
    const res = await context.request.fetch(`${env.baseUrl}${path}`, {
        method,
        data,
        failOnStatusCode: false,
        headers: {
            Accept: 'application/json',
            Referer: `${env.baseUrl}/dashboard`,
            Origin: env.baseUrl,
            ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
        },
    });
    const text = await res.text();
    let body = null;
    try { body = JSON.parse(text); } catch { body = text; }
    return { status: res.status(), body };
}

/** Runs an artisan command against the e2e application (same DB/env as the server). */
export function artisan(...args) {
    const r = spawnSync(process.env.PHP_BINARY || 'php', ['artisan', ...args], { cwd: env.root, env: process.env, encoding: 'utf8' });
    if (r.status !== 0) throw new Error(`artisan ${args.join(' ')} failed: ${r.stderr || r.stdout}`);
    return r.stdout;
}
