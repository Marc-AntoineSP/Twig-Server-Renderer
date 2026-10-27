import { createServer } from 'node:http';
import { spawn } from 'node:child_process';
import {readFileSync} from 'node:fs';
import {readdir,rm} from 'node:fs/promises';
import { timingSafeEqual } from 'node:crypto';

const workerToken=process.env.WORKER_TOKEN_FILE?readFileSync(process.env.WORKER_TOKEN_FILE,'utf8').trim():process.env.WORKER_TOKEN;
if(!workerToken)throw Error('Worker token is required.');
let busy = false;
let activeChild=null;
let stopping=false;
async function cleanup(){for(const entry of await readdir('/tmp',{withFileTypes:true}))if(entry.isDirectory()&&/^studio-build-[A-Za-z0-9]+$/.test(entry.name))await rm('/tmp/'+entry.name,{recursive:true,force:true});}
const reply = (response, status, value) => {
    response.writeHead(status, { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' });
    response.end(JSON.stringify(value));
};
const server=createServer(async (request, response) => {
    if(stopping)return reply(response,503,{ok:false,error:{code:"stopping",message:"Compiler is stopping."}});
    if (request.url === '/health') return reply(response, 200, { status: 'ok' });
    const expected = Buffer.from(`Bearer ${workerToken || ''}`);
    const supplied = Buffer.from(request.headers.authorization || '');
    if (!workerToken || expected.length !== supplied.length || !timingSafeEqual(expected, supplied)) return reply(response, 403, { ok: false, error: { code: 'forbidden', message: 'Worker authentication required.' } });
    if (request.method !== 'POST' || request.url !== '/build') return reply(response, 404, { ok: false, error: { code: 'not_found', message: 'Unknown worker operation.' } });
    if (busy) return reply(response, 429, { ok: false, error: { code: 'busy', message: 'Compiler is busy.' } });
    busy = true;
    try {
        await cleanup();
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
            activeChild=child;
            let output = '';
            let failure=null;
            let finished = false;
            const kill = () => { try { process.kill(-child.pid, 'SIGKILL'); } catch {} };
            const fail = error => { if (!finished && !failure) { failure=error; kill(); } };
            const timeout = setTimeout(() => fail(new Error('Compilation exceeded its time/memory/output budget.')), 10000);
            child.on('error', error => { clearTimeout(timeout); fail(error); });
            child.stdout.on('data', chunk => {
                if(failure||finished)return;
                output += chunk.toString();
                if (Buffer.byteLength(output) > 4194304) fail(new Error('Compilation exceeded its output budget.'));
            });
            child.on('close', code => {
                activeChild=null;
                clearTimeout(timeout);
                if(failure){finished=true;reject(failure);return;}
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
        await cleanup();
        busy = false;
    }
});
server.headersTimeout=5000;server.requestTimeout=10000;server.keepAliveTimeout=5000;server.maxConnections=32;
server.listen(8080,'0.0.0.0');
process.on('SIGTERM',()=>{stopping=true;server.close(()=>process.exit(0));setTimeout(()=>{if(activeChild)try{process.kill(-activeChild.pid,'SIGKILL');}catch{}process.exit(0);},12000).unref();});
