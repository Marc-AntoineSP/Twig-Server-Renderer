import { createServer } from 'node:http';
import { spawn } from 'node:child_process';
import { timingSafeEqual } from 'node:crypto';

let busy = false;
const reply = (response, status, value) => {
    response.writeHead(status, { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' });
    response.end(JSON.stringify(value));
};
createServer(async (request, response) => {
    if (request.url === '/health') return reply(response, 200, { status: 'ok' });
    const expected = Buffer.from(`Bearer ${process.env.WORKER_TOKEN || ''}`);
    const supplied = Buffer.from(request.headers.authorization || '');
    if (!process.env.WORKER_TOKEN || expected.length !== supplied.length || !timingSafeEqual(expected, supplied)) return reply(response, 403, { ok: false, error: { code: 'forbidden', message: 'Worker authentication required.' } });
    if (request.method !== 'POST' || request.url !== '/build') return reply(response, 404, { ok: false, error: { code: 'not_found', message: 'Unknown worker operation.' } });
    if (busy) return reply(response, 429, { ok: false, error: { code: 'busy', message: 'Compiler is busy.' } });
    busy = true;
    try {
        const chunks = [];
        let length = 0;
        for await (const chunk of request) {
            length += chunk.length;
            if (length > 3145728) throw new Error('Input exceeds 3 MiB.');
            chunks.push(chunk);
        }
        const input = Buffer.concat(chunks);
        JSON.parse(input.toString());
        const result = await new Promise((resolve, reject) => {
            const child = spawn(process.execPath, ['--max-old-space-size=192', '/toolchain/build.mjs'], {
                cwd: '/toolchain', detached: true,
                env: { PATH: '/usr/local/bin:/usr/bin:/bin', LANG: 'C.UTF-8', RAYON_NUM_THREADS: '1', NODE_ENV: 'production' },
                stdio: ['pipe', 'pipe', 'ignore'],
            });
            let output = '';
            let finished = false;
            const kill = () => { try { process.kill(-child.pid, 'SIGKILL'); } catch {} };
            const fail = error => { if (!finished) { finished = true; kill(); reject(error); } };
            const timeout = setTimeout(() => fail(new Error('Compilation exceeded its time/memory/output budget.')), 10000);
            child.on('error', error => { clearTimeout(timeout); fail(error); });
            child.stdout.on('data', chunk => {
                output += chunk.toString();
                if (Buffer.byteLength(output) > 4194304) fail(new Error('Compilation exceeded its output budget.'));
            });
            child.on('close', code => {
                clearTimeout(timeout);
                if (finished) return;
                finished = true;
                if (code !== 0) { kill(); reject(new Error('Compiler process failed.')); return; }
                try { resolve(JSON.parse(output)); } catch { reject(new Error('Invalid compiler result.')); }
            });
            child.stdin.on('error', () => {});
            child.stdin.end(input);
        });
        reply(response, result.ok ? 200 : 422, result);
    } catch (error) {
        reply(response, 422, { ok: false, error: { code: 'build_failed', message: String(error.message).slice(0, 500) } });
    } finally {
        busy = false;
    }
}).listen(8080, '0.0.0.0');
