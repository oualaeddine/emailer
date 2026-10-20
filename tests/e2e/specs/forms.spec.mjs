// Signup form with double opt-in + « Formulaire envoyé » automation:
// publish -> anonymous submission -> no membership yet -> confirmation link ->
// membership + automation e-mail captured by the SMTP sink.
import { before, after } from 'node:test';
import assert from 'node:assert/strict';
import { launchBrowser, newContext, login, watchPage, scenario, api, artisan } from '../support/browser.mjs';
import { readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { readMails, waitForMail } from '../support/mail.mjs';
import { env } from '../support/env.mjs';

const stamp = Date.now();
const FORM = `E2E formulaire ${stamp}`;
const LIST = `E2E liste formulaire ${stamp}`;
const AUTOMATION = `E2E bienvenue ${stamp}`;
const TEMPLATE = `E2E modèle bienvenue ${stamp}`;
const WELCOME_SUBJECT = `Bienvenue E2E ${stamp}`;
const SUBSCRIBER = `inscrit-${stamp}@e2e.test`;

let browser;
let context;
let listId;
before(async () => {
    browser = await launchBrowser();
    context = await newContext(browser);
    await login(await context.newPage());

    // fixtures owned by this spec: its own list and its own (user) e-mail template
    const list = await api(context, 'POST', '/api/v1/recipient-lists', { name: LIST, type: 'static' });
    assert.equal(list.status, 201, JSON.stringify(list.body));
    listId = list.body.data.id;
    const template = await api(context, 'POST', '/api/v1/templates', {
        name: TEMPLATE,
        subject: WELCOME_SUBJECT,
        scope: 'marketing',
        editor_type: 'html',
        html_content: '<html><body><p>Bienvenue {{ contact.PRENOM }} !</p><p><a href="{{unsubscribe_url}}">Se désinscrire</a></p></body></html>',
    });
    assert.equal(template.status, 201, JSON.stringify(template.body));
});
after(async () => { await browser?.close(); });

/** The contact (if it exists) and the list ids it belongs to, read as the admin. */
async function membership(email) {
    const found = await api(context, 'GET', `/api/v1/recipients?q=${encodeURIComponent(email)}`);
    assert.equal(found.status, 200);
    const row = found.body.data.find((r) => r.email === email);
    if (!row) return { exists: false, lists: [] };
    const detail = await api(context, 'GET', `/api/v1/recipients/${row.id}`);
    return { exists: true, lists: detail.body.data.lists.map((l) => l.id) };
}

scenario('formulaire double opt-in + automatisation', async (track) => {
    const page = track(await context.newPage());
    const watch = watchPage(page);

    // 1. create + configure + publish the form
    await page.goto('/marketing/forms');
    await page.getByRole('button', { name: 'Créer un formulaire d’inscription' }).first().click();
    await page.getByRole('dialog').locator('input').fill(FORM);
    await page.getByRole('dialog').getByRole('button', { name: 'Créer' }).click();
    await page.waitForURL(/\/marketing\/forms\/[0-9a-f-]{36}$/);
    await page.waitForFunction(() => document.getElementById('app')?.getAttribute('aria-hidden') !== 'true');

    await page.getByRole('combobox').filter({ hasText: 'Attribut' }).click();
    await page.getByRole('option', { name: /Prénom/ }).click();
    await page.getByRole('tab', { name: 'Paramètres' }).click();
    await page.getByRole('switch', { name: /Double opt-in/ }).waitFor();
    assert.ok(await page.getByRole('switch', { name: /Double opt-in/ }).isChecked(), 'double opt-in is on');
    await page.getByPlaceholder('Sélectionnez une ou plusieurs listes').click();
    await page.getByText(new RegExp(`^${LIST}`)).click();
    await page.keyboard.press('Escape');
    await page.getByRole('button', { name: 'Enregistrer' }).click();
    await page.getByRole('button', { name: 'Publier' }).click();
    await page.getByRole('tab', { name: 'Partager' }).click();
    const publicLink = await page.getByLabel('Lien de partage rapide').inputValue();
    assert.match(publicLink, /\/f\/[0-9a-f-]{36}$/);

    // 2. automation: trigger « Formulaire envoyé » on that form, one send-email step, activated
    await page.goto('/automations');
    await page.getByRole('button', { name: 'Créer une automatisation' }).first().click();
    const dialog = page.getByRole('dialog');
    await dialog.locator('input[type=text], input:not([type])').first().fill(AUTOMATION);
    await dialog.getByText('Formulaire envoyé').click();
    await dialog.getByRole('combobox').last().click();
    await page.getByText(FORM, { exact: true }).last().click();
    await page.keyboard.press('Escape');
    await dialog.getByRole('button', { name: 'Créer et ouvrir l’éditeur' }).click();
    await page.waitForURL(/\/automations\/[0-9a-f-]{36}$/);
    await page.goto(page.url());
    await page.getByRole('button', { name: 'Insérer une étape ici' }).click();
    await page.getByRole('menuitem', { name: /Envoyer un e-mail/ }).click();
    const drawer = page.getByRole('dialog').last();
    await drawer.getByRole('combobox').first().click();
    await page.getByRole('option', { name: TEMPLATE }).click();
    await drawer.getByRole('button', { name: 'Fermer', exact: true }).last().click();
    await page.getByRole('button', { name: 'Enregistrer' }).click();
    await page.getByRole('button', { name: 'Activer' }).click();
    await expectEventually(async () => {
        const list = await api(context, 'GET', '/api/v1/automations');
        return list.body.data.find((a) => a.name === AUTOMATION)?.status;
    }, 'active', 'automation status');

    // 3. anonymous visitor submits the public form
    const visitor = track(await (await newContext(browser)).newPage());
    await visitor.goto(publicLink);
    await visitor.getByLabel(/E-mail/).fill(SUBSCRIBER);
    await visitor.getByLabel(/Prénom/).fill('Nadia');
    await visitor.locator('input[type=checkbox]').first().check();
    const robot = visitor.getByText('Vérification anti-robot');
    if (await robot.count()) await robot.click();
    await visitor.getByRole('button', { name: 'S’inscrire' }).click();
    await visitor.waitForURL(/\/merci/);
    await visitor.getByText('Consultez votre boîte de réception').waitFor();

    // 4. double opt-in: nothing is added before the confirmation
    const before = await membership(SUBSCRIBER);
    assert.ok(!before.lists.includes(listId), 'not a member of the list before confirming');
    assert.equal(before.lists.length, 0);
    assert.equal(mailsFor(SUBSCRIBER).length, 0, 'no welcome e-mail before the confirmation');

    // 5. the confirmation link. MAIL_MAILER=log, so the DOI e-mail only lands in storage/logs/laravel.log:
    // check it was sent there, then sign the very same URL with artisan (immune to quoted-printable wrapping).
    const token = Buffer.from(SUBSCRIBER).toString('base64url');
    assert.ok(readLaravelLog().includes(`/preferences/${token}/confirm`), 'DOI e-mail logged for the subscriber');
    const link = artisan(
        'tinker',
        `--execute=echo app(App\\Modules\\Suppression\\Preferences\\Services\\PreferenceTokenService::class)->confirmUrl('${SUBSCRIBER}');`,
    ).trim();
    assert.match(link, new RegExp(`/preferences/${token}/confirm\\?`));
    await visitor.goto(link);
    await visitor.getByRole('button').first().click();
    await visitor.getByText('Merci, votre inscription est confirmée').waitFor();

    // 6. membership + the automation e-mail
    await expectEventually(async () => (await membership(SUBSCRIBER)).lists.includes(listId), true, 'member of the list after confirming');
    const welcome = await waitForMail((m) => m.rcpt.includes(SUBSCRIBER) && m.subject === WELCOME_SUBJECT, { label: 'automation e-mail' });
    assert.ok(welcome.html.includes('Bienvenue'), 'template body rendered');
    assert.deepEqual([...new Set(watch.issues)], [], 'no failed request / page error in the admin session');
});

function mailsFor(email) {
    return readMails().filter((m) => m.rcpt.includes(email));
}

function readLaravelLog() {
    const file = join(env.root, 'storage/logs/laravel.log');
    return existsSync(file) ? readFileSync(file, 'utf8') : '';
}

async function expectEventually(read, expected, label, timeout = 15_000) {
    const start = Date.now();
    let last;
    while (Date.now() - start < timeout) {
        last = await read();
        if (last === expected) return;
        await new Promise((r) => setTimeout(r, 300));
    }
    assert.equal(last, expected, label);
}
