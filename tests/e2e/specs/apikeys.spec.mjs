// A key restricted to « Consulter les journaux transactionnels » (clicking the
// labels in the UI) can read the transactional logs and nothing else.
import { before, after } from 'node:test';
import assert from 'node:assert/strict';
import { request } from 'playwright';
import { launchBrowser, newContext, login, scenario } from '../support/browser.mjs';
import { env } from '../support/env.mjs';

const KEY_NAME = `E2E clé journaux ${Date.now()}`;

let browser;
let context;
before(async () => {
    browser = await launchBrowser();
    context = await newContext(browser);
    await login(await context.newPage());
});
after(async () => { await browser?.close(); });

scenario('clé API limitée aux journaux transactionnels', async (track) => {
    const page = track(await context.newPage());

    await page.goto('/transactional/settings');
    await page.getByRole('tab', { name: 'Clés API' }).click();
    await page.getByRole('button', { name: 'Générer une nouvelle clé API' }).first().click();
    const dialog = page.getByRole('dialog');
    await dialog.locator('input[type=text], input:not([type])').first().fill(KEY_NAME);
    // permissions are checkboxes whose label has to be clicked (the input itself is visually hidden)
    await dialog.getByText('Envoyer des e-mails transactionnels').click();
    await dialog.getByText('Consulter les journaux transactionnels').click();
    await dialog.getByRole('button', { name: 'Créer' }).click();
    await page.waitForFunction(() => /pjm_[A-Za-z0-9]+/.test(document.querySelector('[role=dialog]')?.innerText ?? ''));
    const key = (await dialog.innerText()).match(/pjm_[A-Za-z0-9]+/)?.[0];
    assert.ok(key, 'the new key is displayed once');
    await page.getByRole('button', { name: 'Fermer', exact: true }).last().click();
    await page.getByText(KEY_NAME).first().waitFor();

    const api = await request.newContext({ baseURL: env.baseUrl, extraHTTPHeaders: { Authorization: `Bearer ${key}`, Accept: 'application/json' } });
    try {
        const status = async (method, path, data) => (await api.fetch(path, { method, data, failOnStatusCode: false })).status();
        assert.equal(await status('GET', '/api/v1/transactional/logs'), 200, 'logs are readable');
        assert.equal(await status('GET', '/api/v1/campaigns'), 403, 'campaigns are forbidden');
        assert.equal(await status('GET', '/api/v1/webhooks'), 403, 'webhooks are forbidden');
        assert.equal(await status('POST', '/api/v1/transactional/send', {
            to: [{ email: 'nadia@e2e.test' }], subject: 'x', html: '<p>x</p>',
        }), 403, 'sending is forbidden');
    } finally {
        await api.dispose();
    }
});
