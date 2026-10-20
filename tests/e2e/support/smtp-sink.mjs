// Minimal SMTP sink: accepts every message (any AUTH), keeps it in memory and
// writes it to `<dir>/<n>.eml` (with an `X-Rcpt:` header listing the envelope
// recipients). No dependencies. Port of the former smtpsink.py.
import { createServer } from 'node:net';
import { mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

export async function startSmtpSink({ port = 0, dir, host = '127.0.0.1' } = {}) {
    if (dir) mkdirSync(dir, { recursive: true });
    const messages = [];
    const sockets = new Set();
    let counter = 0;

    const server = createServer((socket) => {
        sockets.add(socket);
        socket.on('close', () => sockets.delete(socket));
        socket.on('error', () => {});
        socket.setEncoding('utf8');

        const send = (line) => socket.write(`${line}\r\n`);
        let buffer = '';
        let state = 'cmd'; // cmd | data | auth-user | auth-pass
        let rcpts = [];
        let from = '';
        let body = [];

        const onLine = (line) => {
            if (state === 'data') {
                if (line === '.') {
                    const raw = body.join('\r\n');
                    const message = { from, rcpts, raw, receivedAt: Date.now() };
                    messages.push(message);
                    if (dir) {
                        const name = `${String(Date.now()).padStart(14, '0')}-${String(++counter).padStart(5, '0')}.eml`;
                        writeFileSync(join(dir, name), `X-Rcpt: ${rcpts.join(',')}\r\n${raw}`);
                    }
                    state = 'cmd';
                    rcpts = [];
                    from = '';
                    body = [];
                    send('250 OK queued');
                } else {
                    body.push(line.startsWith('..') ? line.slice(1) : line);
                }
                return;
            }
            if (state === 'auth-user') { state = 'auth-pass'; send('334 UGFzc3dvcmQ6'); return; }
            if (state === 'auth-pass') { state = 'cmd'; send('235 Authentication successful'); return; }

            const upper = line.toUpperCase();
            if (upper.startsWith('EHLO')) socket.write('250-sink\r\n250-AUTH PLAIN LOGIN\r\n250 8BITMIME\r\n');
            else if (upper.startsWith('HELO')) send('250 sink');
            else if (upper.startsWith('AUTH')) {
                if (upper.startsWith('AUTH LOGIN') && line.trim().split(/\s+/).length === 2) {
                    state = 'auth-user';
                    send('334 VXNlcm5hbWU6');
                } else send('235 Authentication successful');
            } else if (upper.startsWith('MAIL FROM')) { from = line.slice(line.indexOf(':') + 1).trim(); send('250 OK'); }
            else if (upper.startsWith('RCPT TO')) { rcpts.push(line.slice(line.indexOf(':') + 1).trim()); send('250 OK'); }
            else if (upper === 'DATA') { state = 'data'; send('354 End data with <CR><LF>.<CR><LF>'); }
            else if (upper === 'QUIT') { send('221 Bye'); socket.end(); }
            else send('250 OK');
        };

        send('220 sink ESMTP');
        socket.on('data', (chunk) => {
            buffer += chunk;
            let idx;
            while ((idx = buffer.indexOf('\n')) !== -1) {
                const line = buffer.slice(0, idx).replace(/\r$/, '');
                buffer = buffer.slice(idx + 1);
                onLine(line);
            }
        });
    });

    await new Promise((resolve, reject) => {
        server.once('error', reject);
        server.listen(port, host, resolve);
    });

    return {
        host,
        port: server.address().port,
        messages,
        async close() {
            for (const s of sockets) s.destroy();
            await new Promise((resolve) => server.close(resolve));
        },
    };
}
