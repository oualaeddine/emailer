// Campaign golden path: create -> sender -> subject -> audience -> template ->
// editor (saved section + dynamic content) -> checklist -> test send -> schedule.
import { before, after } from 'node:test';
import assert from 'node:assert/strict';
import { launchBrowser, newContext, login, watchPage, scenario, api } from '../support/browser.mjs';
import { readMails, waitForMail } from '../support/mail.mjs';
import { env } from '../support/env.mjs';

const stamp = Date.now();
const NAME = `E2E campagne ${stamp}`;
const SECTION = `E2E bandeau ${stamp}`;
const SUBJECT_TAIL = ', nos offres d’automne';
const PREHEADER = `Préheader E2E ${stamp}`;
const TARGET = { email: 'nadia@e2e.test', firstName: 'Nadia' }; // seeded contact in wilaya Alger
const OTHER = { email: 'karim@e2e.test', firstName: 'Karim' }; // seeded contact outside it
//

let browser;
let context;
before(async () => {
    browser = await launchBrowser();
    context = await newContext(browser);
    await login(await context.newPage());
});
after(async () => { await browser?.close(); });

scenario('campagne : de la création à la programmation', async (track) => {
    const page = track(await context.newPage());
    const watch = watchPage(page);
    page.on('dialog', (d) => d.accept());

    // 1. create
    await page.goto('/campaigns');
    await page.getByRole('button', { name: 'Créer une campagne' }).last().click();
    await page.getByRole('button', { name: 'Continuer' }).click();
    await page.getByLabel(/Nom interne/).fill(NAME);
    await page.getByRole('button', { name: 'Créer la campagne' }).click();
    await page.waitForURL(/\/campaigns\/[0-9a-f-]{36}\/setup/);
    const setupUrl = page.url();
    // Regression guard: the dialog must not leave the app hidden from the accessibility tree.
    await page.waitForFunction(() => document.getElementById('app')?.getAttribute('aria-hidden') !== 'true');

    // 2. sender
    await page.getByText(/Sélectionner l.expéditeur/).click();
    await page.getByRole('combobox').first().click();
    await page.getByRole('option', { name: /Équipe E2E/ }).click();
    await page.getByRole('button', { name: 'Enregistrer', exact: true }).first().click();
    await page.getByRole('button', { name: 'Modifier l’expéditeur' }).waitFor();

    // 3. subject, with the first name inserted through the personalisation menu
    const subject = page.getByPlaceholder(/Saisissez l.objet/);
    await clickUntilVisible(page.getByRole('button', { name: 'Ajouter un objet' }), subject);
    await subject.fill(SUBJECT_TAIL);
    await subject.focus();
    await subject.press('Home');
    await page.getByRole('button', { name: 'Personnaliser' }).first().click();
    await page.getByRole('menuitem', { name: '{{ contact.PRENOM }}' }).click();
    await expectValue(subject, `{{ contact.PRENOM }}${SUBJECT_TAIL}`);
    await page.locator('input[type=text]').nth(1).fill(PREHEADER);
    await page.getByRole('button', { name: 'Enregistrer', exact: true }).first().click();
    await page.getByRole('button', { name: 'Modifier l’objet' }).waitFor();

    // 4. recipients: include two lists, exclude a third, the estimate must be > 0
    await page.getByRole('button', { name: 'Ajouter des destinataires' }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByRole('checkbox', { name: /E2E Liste Alpha/ }).first().check();
    await dialog.getByRole('checkbox', { name: /E2E Liste Bravo/ }).first().check();
    await dialog.getByRole('checkbox', { name: /E2E Liste Charlie/ }).nth(1).check();
    await dialog.getByText('Calcul en cours…').waitFor({ state: 'hidden' });
    // Alpha (3) + Bravo (3) share one contact = 5, minus Charlie's overlap with Alpha (karim) = 4
    // The estimate shows the number and its unit separately (« 4 » / « destinataires »).
    await dialog.getByText(/^4$/).waitFor();
    await dialog.getByText(/^destinataires$/).waitFor();
    await dialog.getByText(/^1 exclus$/).waitFor();
    await dialog.getByRole('button', { name: 'Enregistrer' }).click();
    await page.getByRole('button', { name: 'Modifier les destinataires' }).waitFor();

    // 5. template from the gallery
    await page.getByRole('button', { name: 'Commencer la conception' }).click();
    await page.getByText('Offre saisonnière').click();
    await page.getByRole('button', { name: 'Modifier le design' }).click();
    await page.waitForURL(/\/design/);

    // 6. editor
    const frame = page.frameLocator('iframe').first();
    await frame.getByText('Idée n° 1').waitFor();
    const toolbarParent = async () => { await page.locator('.gjs-toolbar-item').first().click(); };

    // 6a. save a section (hero) from the canvas
    await frame.locator('body').click({ position: { x: 300, y: 120 } });
    await toolbarParent();
    await page.getByRole('tab', { name: 'Enregistrés' }).click();
    await page.getByRole('button', { name: 'Enregistrer la section' }).click();
    await page.getByRole('dialog').locator('input').fill(SECTION);
    await page.getByRole('dialog').getByRole('button', { name: /Enregistrer/ }).last().click();
    await page.getByText(SECTION).first().waitFor();

    // 6b. dynamic-content block (wilaya rule) inside the « Idées » section
    await frame.getByText('Idée n° 1').click();
    await toolbarParent();
    await toolbarParent();
    await page.getByRole('tab', { name: 'Blocs' }).click();
    await page.getByText('Contenu dynamique').first().dblclick();
    const rule = page.getByRole('dialog');
    await rule.getByRole('button', { name: 'Ajouter une condition' }).click();
    const criterion = rule.getByRole('combobox', { name: 'Choisir un critère' });
    await criterion.click();
    await criterion.fill('Wilaya');
    await page.getByRole('option', { name: /Wilaya/ }).first().click();
    await rule.getByLabel(/Wilaya — Valeur/).fill('Alger');
    await rule.getByRole('button', { name: 'Appliquer' }).click();
    await rule.waitFor({ state: 'hidden' });
    await frame.getByText('Saisissez votre texte ici.').dblclick();
    await page.keyboard.press('Control+A');
    await page.keyboard.type('Offre réservée à nos clients de la wilaya d’Alger.');
    await frame.locator('body').click({ position: { x: 10, y: 10 } });

    // 6c. insert the saved section (double click)
    await page.getByRole('tab', { name: 'Enregistrés' }).click();
    await page.getByText(SECTION).first().dblclick();
    // the saved hero is now on the canvas twice, and the dynamic block text is there
    await expectCount(frame.getByText('Une sélection pour la saison'), 2);
    await frame.getByText('Offre réservée à nos clients de la wilaya d’Alger.').waitFor();

    // 7. save & quit -> back on the setup page, nothing blocking left
    await page.getByRole('button', { name: 'Enregistrer et quitter' }).click();
    await page.waitForURL(/\/setup$/);
    await page.getByText('Liste de vérification').waitFor();
    await page.getByText('Expéditeur vérifié', { exact: true }).first().waitFor();
    await expectCount(page.getByText('Chargement…'), 0);
    await expectCount(page.getByText('À corriger', { exact: true }), 0);
    await expectCount(page.getByText('Bloquée', { exact: true }), 0);
    const campaignId = new URL(setupUrl).pathname.split('/')[2];
    const saved = (await api(context, 'GET', `/api/v1/campaigns/${campaignId}`)).body.data;
    assert.match(saved.html_body, /data-dynamic-rule/, 'dynamic block compiled into html_body');
    assert.match(saved.html_body, /Offre réservée à nos clients/);

    // 8. test send to two seeded contacts: Nadia (wilaya Alger) and Karim (no wilaya)
    await page.getByRole('button', { name: 'Aperçu & test' }).click();
    await page.getByPlaceholder('a@exemple.dz, b@exemple.dz').fill(`${TARGET.email}, ${OTHER.email}`);
    await page.getByRole('button', { name: 'Envoyer un test' }).click();
    const mine = (who) => (m) => m.rcpt.includes(who.email) && m.html.includes(PREHEADER);
    const mail = await waitForMail(mine(TARGET), { label: `test mail to ${TARGET.email}` });
    const other = await waitForMail(mine(OTHER), { label: `test mail to ${OTHER.email}` });
    assert.match(mail.headers.from, new RegExp(`<${env.senderEmail}>`), 'From = sender');
    assert.equal(mail.headers['reply-to'], env.replyTo, 'Reply-To = sender reply-to');
    assert.equal(mail.subject, `${TARGET.firstName}${SUBJECT_TAIL}`, 'subject personalised');
    assert.equal(other.subject, `${OTHER.firstName}${SUBJECT_TAIL}`);
    assert.ok(mail.html.includes('Offre réservée à nos clients'), 'dynamic block kept for a wilaya Alger contact');
    assert.ok(!other.html.includes('Offre réservée à nos clients'), 'dynamic block dropped for a contact outside Alger');
    assert.equal((mail.html.match(/Une sélection pour la saison/g) ?? []).length, 2, 'saved section inserted');
    await page.getByRole('dialog').getByRole('button', { name: 'Fermer' }).click();

    // 9. schedule at the best time
    await page.getByRole('button', { name: 'Programmer', exact: true }).click();
    await page.getByRole('dialog').getByText('Envoyer au meilleur moment').click();
    await page.getByRole('button', { name: 'Programmer au meilleur moment' }).click();
    await page.waitForURL(/\/campaigns\?status_tab=running/);
    await page.waitForFunction(() => document.getElementById('app')?.getAttribute('aria-hidden') !== 'true');
    await page.getByRole('tab', { name: /En cours/, selected: true }).waitFor();
    await page.getByText(NAME).first().waitFor();
    assert.deepEqual([...new Set(watch.issues)], [], 'no failed request / page error along the way');
});

/** Fluent cards re-render right after a save: retry the click if the editor did not open. */
async function clickUntilVisible(trigger, target) {
    for (let attempt = 1; ; attempt++) {
        await trigger.click();
        try {
            await target.waitFor({ timeout: 4_000 });
            return;
        } catch (error) {
            if (attempt >= 3) throw error;
        }
    }
}

async function expectValue(locator, expected) {
    assert.equal(await locator.inputValue(), expected);
}

async function expectCount(locator, n) {
    for (let i = 0; i < 20; i++) {
        if ((await locator.count()) === n) return;
        await new Promise((r) => setTimeout(r, 250));
    }
    assert.equal(await locator.count(), n);
}
