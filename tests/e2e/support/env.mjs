// Configuration shared by run.mjs and the specs. run.mjs exports the E2E_*
// variables into the environment of the spec processes.
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export const root = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');

export const env = {
    root,
    baseUrl: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8090',
    sinkDir: process.env.E2E_SINK_DIR ?? resolve(root, 'tests/e2e/artifacts/mail'),
    artifactsDir: process.env.E2E_ARTIFACTS_DIR ?? resolve(root, 'tests/e2e/artifacts'),
    totpSecret: process.env.E2E_TOTP_SECRET || 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
    adminEmail: process.env.E2E_ADMIN_EMAIL ?? 'e2e-admin@pagejaunes-mailer.local',
    adminPassword: process.env.E2E_ADMIN_PASSWORD ?? 'password',
    senderEmail: 'e2e-sender@pagejaunes.test',
    replyTo: 'e2e-reply@pagejaunes.test',
    chromiumPath: process.env.PLAYWRIGHT_CHROMIUM_PATH || undefined,
    headed: process.env.E2E_HEADED === '1',
};
