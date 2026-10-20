// Reads the .eml files captured by the SMTP sink and decodes the bits the
// specs assert on (headers incl. RFC 2047 words, text/html bodies).
import { readdirSync, readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { env } from './env.mjs';

function decodeQp(text) {
    return Buffer.from(
        text.replace(/=\r?\n/g, '').replace(/=([0-9A-F]{2})/gi, (_, h) => String.fromCharCode(parseInt(h, 16))),
        'latin1',
    ).toString('utf8');
}

export function decodeWords(value) {
    return value
        .replace(/\?=\s+=\?/g, '?==?')
        .replace(/=\?([^?]+)\?([bq])\?([^?]*)\?=/gi, (_, charset, enc, data) => {
            const buf = enc.toLowerCase() === 'b'
                ? Buffer.from(data, 'base64')
                : Buffer.from(data.replace(/_/g, ' ').replace(/=([0-9A-F]{2})/gi, (m, h) => String.fromCharCode(parseInt(h, 16))), 'latin1');
            return buf.toString(/utf-?8/i.test(charset) ? 'utf8' : 'latin1');
        });
}

function parseHeaders(block) {
    const headers = {};
    for (const line of block.replace(/\r\n[ \t]+/g, ' ').split(/\r?\n/)) {
        const i = line.indexOf(':');
        if (i > 0) headers[line.slice(0, i).toLowerCase()] = decodeWords(line.slice(i + 1).trim());
    }
    return headers;
}

function bodyOf(headers, raw) {
    const enc = (headers['content-transfer-encoding'] ?? '').toLowerCase();
    if (enc === 'base64') return Buffer.from(raw.replace(/\s+/g, ''), 'base64').toString('utf8');
    if (enc === 'quoted-printable') return decodeQp(raw);
    return raw;
}

/** Flattens a MIME entity into [{type, text}] leaf parts. */
function leaves(headers, raw) {
    const ct = headers['content-type'] ?? 'text/plain';
    const boundary = /boundary="?([^";]+)"?/i.exec(ct)?.[1];
    if (/multipart\//i.test(ct) && boundary) {
        return raw.split(`--${boundary}`).slice(1).filter((p) => !p.startsWith('--')).flatMap((part) => {
            const [h, ...rest] = part.replace(/^\r?\n/, '').split(/\r?\n\r?\n/);
            return leaves(parseHeaders(h), rest.join('\n\n'));
        });
    }
    return [{ type: ct.split(';')[0].trim().toLowerCase(), text: bodyOf(headers, raw) }];
}

export function parseEml(file) {
    const raw = readFileSync(file, 'utf8');
    const [headBlock, ...rest] = raw.split(/\r?\n\r?\n/);
    const headers = parseHeaders(headBlock);
    const parts = leaves(headers, rest.join('\n\n'));
    return {
        file,
        headers,
        rcpt: headers['x-rcpt'] ?? '',
        subject: headers.subject ?? '',
        html: parts.find((p) => p.type === 'text/html')?.text ?? '',
        text: parts.find((p) => p.type === 'text/plain')?.text ?? '',
    };
}

export function readMails(dir = env.sinkDir) {
    if (!existsSync(dir)) return [];
    return readdirSync(dir).filter((f) => f.endsWith('.eml')).sort().map((f) => parseEml(join(dir, f)));
}

/** Polls the sink until a mail satisfying `predicate` arrives. */
export async function waitForMail(predicate, { timeout = 30_000, label = 'mail' } = {}) {
    const start = Date.now();
    while (Date.now() - start < timeout) {
        const found = readMails().find(predicate);
        if (found) return found;
        await new Promise((r) => setTimeout(r, 250));
    }
    const seen = readMails().map((m) => `${m.rcpt} | ${m.subject}`).join('\n  ');
    throw new Error(`Timed out waiting for ${label}. Mails captured:\n  ${seen || '(none)'}`);
}
