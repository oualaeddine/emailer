#!/usr/bin/env node
// End-to-end smoke suite entry point (see tests/e2e/README.md).
//
//   npm run build            # once: the suite runs against public/build
//   npm run test:e2e         # all specs
//   npm run test:e2e -- campaign forms     # only specs whose file name matches
//   E2E_SERVE=1 npm run test:e2e           # seed + serve only (to write/debug specs)
//
// 1. migrates a dedicated database fresh + seeds it (incl. Database\Seeders\E2eSeeder)
// 2. starts a Node SMTP sink and `php -S` on free ports
// 3. runs the specs with Node's built-in test runner (`node --test`)
// 4. stops everything; the exit code is the test runner's.
import { spawn, spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, openSync, closeSync, readdirSync, rmSync } from 'node:fs';
import { createServer } from 'node:net';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { startSmtpSink } from './support/smtp-sink.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const e2eDir = join(root, 'tests/e2e');
const artifacts = join(e2eDir, 'artifacts');
const sinkDir = join(artifacts, 'mail');
const php = process.env.PHP_BINARY || 'php';

const log = (...a) => console.log('[e2e]', ...a);
const fail = (msg) => { console.error(`[e2e] ${msg}`); process.exit(1); };

// ---- preconditions -------------------------------------------------------
if (!existsSync(join(root, 'public/build/manifest.json'))) {
    fail('public/build/manifest.json is missing. The suite runs against built assets: run `npm run build` first.');
}
if (!existsSync(join(root, 'vendor/autoload.php'))) fail('vendor/ is missing. Run `composer install` first.');
const router = join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');
if (!existsSync(router)) fail(`PHP built-in server router not found: ${router}`);

const dbName = process.env.E2E_DB_DATABASE || 'pagejaunes_mailer_e2e';
if (!/e2e|test/i.test(dbName) && process.env.E2E_ALLOW_ANY_DB !== '1') {
    fail(`Refusing to wipe database "${dbName}" (migrate:fresh). Use a name containing "e2e" or "test", or set E2E_ALLOW_ANY_DB=1.`);
}
const totpSecret = process.env.E2E_TOTP_SECRET || 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

// ---- helpers -------------------------------------------------------------
const freePort = () => new Promise((res, rej) => {
    const s = createServer();
    s.once('error', rej);
    s.listen(0, '127.0.0.1', () => { const { port } = s.address(); s.close(() => res(port)); });
});

function run(cmd, args, opts = {}) {
    const r = spawnSync(cmd, args, { cwd: root, stdio: 'inherit', ...opts });
    if (r.status !== 0) fail(`Command failed (${r.status ?? r.signal}): ${cmd} ${args.join(' ')}`);
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// ---- environment -----------------------------------------------------------
rmSync(artifacts, { recursive: true, force: true });
mkdirSync(sinkDir, { recursive: true });

const sink = await startSmtpSink({ dir: sinkDir });
const port = await freePort();
const baseUrl = `http://127.0.0.1:${port}`;

const appEnv = {
    ...process.env,
    APP_ENV: 'local',
    APP_DEBUG: 'true',
    APP_URL: baseUrl,
    SANCTUM_STATEFUL_DOMAINS: `127.0.0.1:${port},127.0.0.1,localhost`,
    DB_CONNECTION: process.env.DB_CONNECTION || 'mysql',
    DB_HOST: process.env.DB_HOST || '127.0.0.1',
    DB_PORT: process.env.DB_PORT || '3306',
    DB_DATABASE: dbName,
    DB_USERNAME: process.env.DB_USERNAME || 'root',
    DB_PASSWORD: process.env.DB_PASSWORD ?? '',
    SESSION_DRIVER: 'file',
    CACHE_STORE: 'file',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'log',
    LOG_CHANNEL: 'single',
    BROADCAST_CONNECTION: 'log',
    E2E_TOTP_SECRET: totpSecret,
    E2E_SMTP_HOST: '127.0.0.1',
    E2E_SMTP_PORT: String(sink.port),
};
delete appEnv.NODE_TEST_CONTEXT;

let server = null;
let exitCode = 1;

async function stop() {
    if (server && server.exitCode === null) {
        server.kill('SIGTERM');
        await Promise.race([new Promise((r) => server.once('exit', r)), sleep(3000)]);
        if (server.exitCode === null) server.kill('SIGKILL');
    }
    await sink.close().catch(() => {});
}
for (const sig of ['SIGINT', 'SIGTERM']) process.on(sig, async () => { await stop(); process.exit(130); });

try {
    // The dedicated database may not exist yet (CI creates it through the service container, locally nobody does).
    if (appEnv.DB_CONNECTION === 'mysql') {
        log(`ensuring database ${dbName} exists`);
        run(php, ['-r', `
            $pdo = new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));
            $pdo->exec('CREATE DATABASE IF NOT EXISTS \`'.str_replace('\`', '', getenv('DB_DATABASE')).'\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        `], { env: appEnv });
    }

    log(`migrate:fresh --seed on ${appEnv.DB_HOST}:${appEnv.DB_PORT}/${dbName}`);
    run(php, ['artisan', 'migrate:fresh', '--seed', '--force'], { env: appEnv, stdio: ['ignore', 'ignore', 'inherit'] });
    run(php, ['artisan', 'db:seed', '--class=E2eSeeder', '--force'], { env: appEnv, stdio: ['ignore', 'ignore', 'inherit'] });
    run(php, ['artisan', 'cache:clear'], { env: appEnv, stdio: ['ignore', 'ignore', 'inherit'] });

    log(`starting php -S on ${baseUrl}, SMTP sink on 127.0.0.1:${sink.port}`);
    const serverLog = openSync(join(artifacts, 'server.log'), 'a');
    server = spawn(php, ['-S', `127.0.0.1:${port}`, router], {
        cwd: join(root, 'public'),
        env: { ...appEnv, PHP_CLI_SERVER_WORKERS: '4' },
        stdio: ['ignore', serverLog, serverLog],
    });
    closeSync(serverLog);
    server.once('exit', (code) => { if (exitCode === 1 && code) console.error(`[e2e] php -S exited early (${code}), see ${join(artifacts, 'server.log')}`); });

    for (let i = 0; ; i++) {
        try {
            const r = await fetch(`${baseUrl}/login`, { redirect: 'manual' });
            if (r.status < 500) break;
        } catch { /* not up yet */ }
        if (i > 100 || server.exitCode !== null) fail(`Server did not come up, see ${join(artifacts, 'server.log')}`);
        await sleep(200);
    }

    if (process.env.E2E_SERVE === '1') {
        // Authoring mode: leave the seeded app + SMTP sink running, run no spec.
        log(`serving ${baseUrl} (admin ${process.env.E2E_ADMIN_EMAIL || 'e2e-admin@pagejaunes-mailer.local'} / password, TOTP secret ${totpSecret}); mails in ${sinkDir}. Ctrl+C to stop.`);
        await new Promise(() => {});
    }

    // ---- specs ---------------------------------------------------------------
    const filters = process.argv.slice(2);
    const specs = readdirSync(join(e2eDir, 'specs'))
        .filter((f) => f.endsWith('.spec.mjs'))
        .filter((f) => filters.length === 0 || filters.some((x) => f.includes(x)))
        .sort()
        .map((f) => join('tests/e2e/specs', f));
    if (specs.length === 0) fail('No spec matches.');

    const started = Date.now();
    // async on purpose: the SMTP sink lives in this process and must keep answering while the specs run
    const runner = spawn(process.execPath, ['--test', '--test-reporter=spec', '--test-concurrency=1', '--test-timeout=300000', ...specs], {
        cwd: root,
        stdio: 'inherit',
        env: {
            ...process.env,
            E2E_BASE_URL: baseUrl,
            E2E_SINK_DIR: sinkDir,
            E2E_ARTIFACTS_DIR: artifacts,
            E2E_TOTP_SECRET: totpSecret,
            // used by specs that need artisan (e.g. signed confirmation link)
            ...Object.fromEntries(Object.entries(appEnv).filter(([k]) => /^(APP_|DB_|SESSION_|CACHE_|QUEUE_|MAIL_|LOG_|SANCTUM_)/.test(k))),
        },
    });
    const status = await new Promise((resolve) => runner.once('exit', (code, signal) => resolve(code ?? (signal ? 1 : 0))));
    exitCode = status;
    log(`${exitCode === 0 ? 'PASS' : 'FAIL'} in ${((Date.now() - started) / 1000).toFixed(1)}s` + (exitCode === 0 ? '' : ` (screenshots: ${artifacts})`));
} finally {
    await stop();
}
process.exit(exitCode);
