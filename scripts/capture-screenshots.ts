/**
 * Captures the screenshots used by the in-app documentation
 * (resources/docs/**), into public/docs/screenshots/<slug>/<nn>-<name>.png.
 *
 *   npm run docs:screenshots        # build assets, reseed, capture
 *   npm run docs:screenshots:fast   # skip `vite build` (assets already built)
 *   npm run docs:screenshots:fast -- --skip-seed
 *
 * Determinism: a throwaway MySQL database (pagejaunes_mailer_docs) is
 * migrated and seeded from scratch each run, so every capture shows the same
 * demo data. SQLite is not usable here — one migration declares a fulltext
 * index, which the SQLite driver cannot compile.
 *
 * Selectors: French accessible names imported from the app's own dictionary
 * (resources/js/Lib/i18n/fr.ts), so a copy change updates the app and this
 * script together instead of silently breaking the capture.
 *
 * The interface is French-only (docs/07-ui-design.md §7.12), so a single set
 * of screenshots serves both the French and the Arabic documentation.
 */
import { spawn, spawnSync, type ChildProcess } from 'node:child_process';
import { mkdirSync, rmSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium, type Page } from 'playwright';
import { fr } from '../resources/js/Lib/i18n/fr';

const here = dirname(fileURLToPath(import.meta.url));
const projectRoot = resolve(here, '..');
const outputRoot = resolve(projectRoot, 'public/docs/screenshots');
const fixture = resolve(here, 'fixtures/import-sample.csv');

const PORT = 8090;
const BASE = `http://127.0.0.1:${PORT}`;
const ADMIN_EMAIL = 'admin@pagejaunes-mailer.local';
const ADMIN_PASSWORD = 'password';

/** Overrides applied to both artisan and the dev server: no Redis, no queue worker, isolated DB. */
const captureEnv = {
    ...process.env,
    APP_URL: BASE,
    // The pages read their data from /api/v1 through Sanctum's stateful
    // session guard, which only honours the session cookie for a listed
    // host:port. Without this the capture port is treated as a third-party
    // origin and every page renders its empty state.
    SANCTUM_STATEFUL_DOMAINS: `127.0.0.1:${PORT},127.0.0.1,localhost`,
    DB_DATABASE: 'pagejaunes_mailer_docs',
    SESSION_DRIVER: 'file',
    CACHE_STORE: 'file',
    QUEUE_CONNECTION: 'sync',
};

interface Shot {
    /** Output path, relative to public/docs/screenshots (without .png). */
    name: string;
    url?: string;
    /** Opens dialogs/drawers, advances wizards, … before the capture. */
    actions?: (page: Page) => Promise<void>;
    /** Extra wait beyond the default (network idle + no spinner). */
    waitFor?: (page: Page) => Promise<void>;
}

const shots: Shot[] = [
    { name: 'dashboard/01-dashboard', url: '/dashboard' },
    { name: 'mailbox/01-mailbox', url: '/mailbox' },
    { name: 'composer/01-composer', url: '/compose' },

    { name: 'templates/01-templates', url: '/templates' },
    {
        name: 'templates/02-dialog',
        url: '/templates',
        actions: (page) => openDialog(page, fr.templates.newTemplate),
    },

    { name: 'campaigns/01-campaigns', url: '/campaigns' },
    {
        name: 'campaigns/02-detail-drawer',
        url: '/campaigns',
        actions: async (page) => {
            await page.getByRole('row').nth(1).click();
            await settle(page);
        },
    },
    {
        name: 'campaigns/03-wizard',
        url: '/campaigns',
        actions: (page) => openDialog(page, fr.campaigns.newCampaign),
    },

    { name: 'recipients/01-recipients', url: '/recipients' },
    {
        name: 'recipients/02-dialog',
        url: '/recipients',
        actions: (page) => openDialog(page, fr.recipients.newRecipient),
    },

    { name: 'recipients-import/01-upload', url: '/recipients/import' },
    {
        name: 'recipients-import/02-mapping',
        url: '/recipients/import',
        actions: async (page) => {
            // The file input is visually hidden behind a styled drop zone;
            // setInputFiles drives it directly, which also advances the wizard.
            await page.locator('input[type="file"]').setInputFiles(fixture);
            await settle(page, 1200);
            // Fill the mapping so the capture shows a completed step rather
            // than four empty "Sélectionner une colonne…" dropdowns.
            for (const [field, column] of [
                [fr.imports.mapEmail, 'Email'],
                [fr.imports.mapFirstName, 'Prénom'],
                [fr.imports.mapLastName, 'Nom'],
                [fr.imports.mapCompanyName, 'Société'],
            ] as const) {
                // Exact match: "Nom" would otherwise also match "Prénom".
                await page.getByRole('combobox', { name: field, exact: true }).click();
                await page.getByRole('option', { name: column, exact: true }).click();
            }
            await settle(page, 400);
        },
    },
    { name: 'pagejaunes-search/01-search', url: '/recipients/import/pagejaunes' },

    { name: 'suppression/01-suppression', url: '/suppression' },
    {
        name: 'suppression/02-dialog',
        url: '/suppression',
        actions: (page) => openDialog(page, fr.suppression.newEntry),
    },

    { name: 'reporting/01-reporting', url: '/reporting' },

    { name: 'smtp/01-smtp', url: '/smtp' },
    {
        name: 'smtp/02-dialog',
        url: '/smtp',
        actions: (page) => openDialog(page, fr.smtp.newAccount),
    },

    { name: 'admin-users/01-users', url: '/admin/users' },
    {
        name: 'admin-users/02-dialog',
        url: '/admin/users',
        actions: (page) => openDialog(page, fr.users.newUser),
    },

    { name: 'audit-log/01-audit-log', url: '/admin/audit-log' },
    {
        name: 'audit-log/02-detail',
        url: '/admin/audit-log',
        actions: async (page) => {
            await page.getByRole('row').nth(1).click();
            await settle(page);
        },
    },

    { name: 'settings-branding/01-branding', url: '/settings/branding' },

    { name: 'help/01-help-center', url: '/help' },
];

async function settle(page: Page, extra = 400) {
    await page.waitForLoadState('networkidle').catch(() => undefined);
    // Data arrives from /api/v1 after hydration, so wait out the spinners too.
    await page.locator('[role="progressbar"]').first().waitFor({ state: 'detached', timeout: 5000 }).catch(() => undefined);
    await page.waitForTimeout(extra);
}

async function openDialog(page: Page, buttonLabel: string) {
    await page.getByRole('button', { name: buttonLabel }).first().click();
    // Fluent dialogs animate in; capturing too early yields a half-faded surface.
    await settle(page, 700);
}

function run(command: string, args: string[], label: string) {
    console.log(`→ ${label}`);
    const result = spawnSync(command, args, {
        cwd: projectRoot,
        env: captureEnv,
        stdio: 'inherit',
        shell: process.platform === 'win32',
    });
    if (result.status !== 0) {
        throw new Error(`${label} a échoué (code ${result.status}).`);
    }
}

async function waitForServer(timeoutMs = 30000) {
    const deadline = Date.now() + timeoutMs;
    while (Date.now() < deadline) {
        try {
            const response = await fetch(`${BASE}/login`);
            if (response.ok) return;
        } catch {
            // Server not up yet.
        }
        await new Promise((done) => setTimeout(done, 500));
    }
    throw new Error(`Le serveur n'a pas répondu sur ${BASE} dans le délai imparti.`);
}

function stopServer(server: ChildProcess) {
    if (!server.pid) return;
    if (process.platform === 'win32') {
        // `php artisan serve` spawns a child PHP worker; killing only the
        // parent leaves it holding the port.
        spawnSync('taskkill', ['/pid', String(server.pid), '/T', '/F'], { stdio: 'ignore' });
    } else {
        server.kill('SIGTERM');
    }
}

async function main() {
    const skipBuild = process.argv.includes('--skip-build');
    const skipSeed = process.argv.includes('--skip-seed');

    run('php', ['artisan', 'config:clear'], 'Nettoyage du cache de configuration');

    if (!skipSeed) {
        run('php', ['artisan', 'migrate:fresh', '--seed', '--force'], 'Migration + jeu de données de démonstration');
    }

    if (!skipBuild) {
        run('npm', ['run', 'build'], 'Compilation des assets');
    }

    console.log('→ Démarrage du serveur');
    const server = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', `--port=${PORT}`], {
        cwd: projectRoot,
        env: captureEnv,
        stdio: 'ignore',
        shell: process.platform === 'win32',
    });

    const browser = await chromium.launch();

    try {
        await waitForServer();

        const context = await browser.newContext({
            viewport: { width: 1280, height: 800 },
            deviceScaleFactor: 2,
            locale: 'fr-FR',
        });
        const page = await context.newPage();

        rmSync(outputRoot, { recursive: true, force: true });

        // Login page first — it is the only capture taken while signed out.
        await page.goto(`${BASE}/login`);
        await settle(page);
        await capture(page, 'login/01-login');

        await page.getByLabel(fr.auth.email).fill(ADMIN_EMAIL);
        await page.getByLabel(fr.auth.password).fill(ADMIN_PASSWORD);
        await page.getByRole('button', { name: fr.auth.submit }).click();
        await page.waitForURL(`${BASE}/dashboard`, { timeout: 15000 });
        await settle(page);

        for (const shot of shots) {
            if (shot.url) {
                await page.goto(`${BASE}${shot.url}`);
                await settle(page);
            }
            if (shot.actions) await shot.actions(page);
            if (shot.waitFor) await shot.waitFor(page);
            await capture(page, shot.name);
        }

        console.log(`\n✓ ${shots.length + 1} captures écrites dans public/docs/screenshots`);
    } finally {
        await browser.close();
        stopServer(server);
    }
}

async function capture(page: Page, name: string) {
    const path = resolve(outputRoot, `${name}.png`);
    mkdirSync(dirname(path), { recursive: true });
    await page.screenshot({ path });
    console.log(`  ✓ ${name}.png`);
}

main().catch((error) => {
    console.error(error);
    process.exit(1);
});
