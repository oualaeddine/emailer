// Every authenticated page loads: HTTP 200, no uncaught page error, no failed
// same-origin request, and (phone) no horizontal page overflow.
import test, { before, after } from 'node:test';
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { launchBrowser, newContext, login, watchPage, api, DESKTOP, PHONE } from '../support/browser.mjs';
import { env } from '../support/env.mjs';

const unique = `E2E pages ${Date.now()}`;
let browser;
const sessions = {};
let pages = [];

/** Resolves record URLs from the seeded fixtures / the API (never hardcoded ids). */
async function buildPages(context) {
    const first = async (path, label) => {
        const res = await api(context, 'GET', path);
        assert.equal(res.status, 200, `${label}: GET ${path} -> ${res.status}`);
        const row = res.body.data?.[0];
        assert.ok(row, `${label}: no fixture returned by ${path}`);
        return row;
    };
    const create = async (path, body, label) => {
        const res = await api(context, 'POST', path, body);
        assert.ok(res.status >= 200 && res.status < 300, `${label}: POST ${path} -> ${res.status} ${JSON.stringify(res.body).slice(0, 300)}`);
        return res.body.data ?? res.body;
    };

    const contact = await first('/api/v1/recipients?per_page=1', 'contact');
    const list = await first('/api/v1/recipient-lists', 'list');
    const segment = await first('/api/v1/segments', 'segment');
    const sent = (await api(context, 'GET', '/api/v1/campaigns')).body.data.find((c) => c.status === 'completed');
    assert.ok(sent, 'seeded sent campaign missing');

    const draft = await create('/api/v1/campaigns', { name: `${unique} campagne` }, 'draft campaign');
    const gallery = (await api(context, 'GET', '/api/v1/templates/gallery')).body.data?.[0];
    assert.ok(gallery, 'no gallery template');
    const template = await create(`/api/v1/templates/${gallery.id}/duplicate`, { name: `${unique} modèle` }, 'template');
    const form = await create('/api/v1/forms', { name: `${unique} formulaire` }, 'form');
    const automation = await create('/api/v1/automations', { name: `${unique} automatisation`, trigger: { type: 'list_added', config: { list_ids: [list.id] } } }, 'automation');

    return [
        ['dashboard', '/dashboard'],
        ['contacts', '/contacts'],
        ['contact-detail', `/contacts/${contact.id}`],
        ['lists', '/contacts/lists'],
        ['list-detail', `/contacts/lists/${list.id}`],
        ['segments', '/contacts/segments'],
        ['segment-edit', `/contacts/segments/${segment.id}`],
        ['segment-new', '/contacts/segments/new'],
        ['attributes', '/contacts/attributes'],
        ['imports', '/contacts/imports'],
        ['import-wizard', '/contacts/imports/new'],
        ['blocklist', '/contacts/blocklist'],
        ['pagejaunes', '/contacts/pagejaunes'],
        ['campaigns', '/campaigns'],
        ['campaign-setup', `/campaigns/${draft.id}/setup`],
        ['campaign-design', `/campaigns/${draft.id}/design`],
        ['campaign-report', `/campaigns/${sent.id}/report`],
        ['templates', '/templates'],
        ['template-edit', `/templates/${template.id}/edit`],
        ['statistics', '/statistics'],
        ['forms', '/marketing/forms'],
        ['form-edit', `/marketing/forms/${form.id}`],
        ['automations', '/automations'],
        ['automation-new', '/automations/new'],
        ['automation-edit', `/automations/${automation.id}`],
        ['compose', '/compose'],
        ['mailbox', '/mailbox'],
        ['transactional-logs', '/transactional/logs'],
        ['transactional-settings', '/transactional/settings'],
        ['senders', '/settings/senders'],
        ['domains', '/settings/domains'],
        ['smtp', '/smtp'],
        ['unsubscribe-pages', '/settings/unsubscribe-pages'],
        ['update-profile-forms', '/settings/update-profile-forms'],
        ['branding', '/settings/branding'],
        ['users', '/admin/users'],
        ['audit-log', '/admin/audit-log'],
        ['help', '/help'],
    ].map(([slug, url]) => ({ slug, url }));
}

before(async () => {
    mkdirSync(env.artifactsDir, { recursive: true });
    browser = await launchBrowser();
    const desktop = await newContext(browser, { device: DESKTOP });
    const storageState = await login(await desktop.newPage());
    sessions.desktop = { context: desktop };
    sessions.phone = { context: await newContext(browser, { device: PHONE, storageState }) };
    for (const s of Object.values(sessions)) {
        s.page = await s.context.newPage();
        s.watch = watchPage(s.page);
    }
    pages = await buildPages(desktop);
});

after(async () => { await browser?.close(); });

for (const variant of ['desktop', 'phone']) {
    test(`pages (${variant})`, { timeout: 600_000 }, async (t) => {
        const { page, watch } = sessions[variant];
        for (const { slug, url } of pages) {
            await t.test(`${variant} ${url}`, async () => {
                watch.clear();
                try {
                    const response = await page.goto(url, { waitUntil: 'networkidle' });
                    assert.equal(response?.status(), 200, `HTTP status of ${url}`);
                    assert.ok(new URL(page.url()).pathname === new URL(url, env.baseUrl).pathname, `redirected to ${page.url()}`);
                    await page.locator('body').waitFor();
                    if (slug === 'campaign-design' || slug === 'template-edit') await page.locator('iframe').first().waitFor({ state: 'attached' });
                    await page.waitForLoadState('networkidle');
                    assert.deepEqual([...new Set(watch.issues)], [], `issues on ${url}`);
                    if (variant === 'phone') {
                        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
                        assert.ok(overflow <= 4, `horizontal overflow of ${overflow}px on ${url}`);
                    }
                } catch (error) {
                    await page.screenshot({ path: join(env.artifactsDir, `pages-${variant}-${slug}.png`) }).catch(() => {});
                    throw error;
                }
            });
        }
    });
}
