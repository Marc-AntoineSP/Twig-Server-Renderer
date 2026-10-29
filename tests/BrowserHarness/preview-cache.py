#!/usr/bin/env python3
"""Prove private preview reuse with real workers unavailable in a verified owned run."""
import argparse
import fcntl
import secrets

from run import Run

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--run-id', required=True)
options = parser.parse_args()
run = Run(options.run_id)
cache_message = "Cold cache proof "+secrets.token_hex(12)

script = r'''
import {chromium} from 'playwright';
import {readFileSync} from 'node:fs';
import {login} from './tests/Browser/helpers.js';

const fixtures = JSON.parse(readFileSync(process.env.STUDIO_TEST_FIXTURES_FILE, 'utf8'));
const browser = await chromium.launch();
async function actor(role, operation) {
    const context = await browser.newContext({baseURL: process.env.STUDIO_BASE_URL});
    try {
        const page = await context.newPage();
        await login(page, role);
        return await operation(page);
    } finally { await context.close(); }
}
async function preview(page, {channel, props} = {}) {
    await page.goto('/catalogue/'+fixtures.component.id);
    const releaseId = await page.getByLabel('Source version', {exact: true}).inputValue();
    const token = (await (await page.request.get('/api/v1/csrf')).json()).token;
    const request = {
        sequence: 1, channel: channel || 'a'.repeat(32), releaseId,
        preset: {themeReleaseId: fixtures['theme-dark'].releaseId, rootSize: 19, bodyFont: 'theme', headingFont: 'theme'},
        props: props || {message: process.env.CACHE_PROOF_MESSAGE},
    };
    const response = await page.request.post('/api/v1/catalogue-previews/'+fixtures.component.id, {
        headers: {'X-CSRF-Token': token}, data: request,
    });
    if (response.status() !== 202) throw Error('Preview create '+response.status());
    const job = await response.json();
    for (let attempt = 0; attempt < 200; attempt++) {
        const status = await (await page.request.get('/api/v1/preview-jobs/'+job.id)).json();
        if (['failed', 'expired', 'cancelled'].includes(status.status)) return status;
        if (status.status === 'succeeded') {
            const result = await (await page.request.get('/api/v1/preview-jobs/'+job.id+'/document')).json();
            return {status: 'succeeded', html: result.html};
        }
        await new Promise(resolve => setTimeout(resolve, 100));
    }
    throw Error('Preview did not finish');
}
try {
    if (process.env.CACHE_PROOF_PHASE === 'warm') {
        const result = await actor('reader', page => preview(page));
        if (result.status !== 'succeeded') throw Error('Cold real-worker preview failed');
        console.log('PASS: cold exact-release/theme preview completed with real workers.');
    } else {
        const cached = await actor('reader', page => preview(page, {channel: 'b'.repeat(32)}));
        if (cached.status !== 'succeeded' || !cached.html.includes('const channel="'+'b'.repeat(32)+'"')) {
            throw Error('Same-owner cached preview failed without workers');
        }
        const changed = await actor('reader', page => preview(page, {props: {message: 'Cache must miss changed props'}}));
        if (changed.status !== 'failed' || changed.error?.code !== 'worker_unavailable') throw Error('Changed props improperly reused cache');
        const other = await actor('editor', page => preview(page));
        if (other.status !== 'failed' || other.error?.code !== 'worker_unavailable') throw Error('Another owner improperly reused private cache');
        console.log('PASS: same owner/release/theme inputs succeed with both workers stopped and a fresh channel.');
        console.log('PASS: changed props and another account fail through the unavailable workers.');
    }
} finally { await browser.close(); }
'''


def phase(name):
    result = run.compose('run', '--rm', '--no-deps', '-e', 'CACHE_PROOF_PHASE='+name,
                         '-e', 'CACHE_PROOF_MESSAGE='+cache_message,
                         'browser', 'node', '--input-type=module', '-e', script, capture=True, check=False)
    print(run.redact((result.stdout or '')+(result.stderr or '')), flush=True)
    if result.returncode:
        raise RuntimeError('Cache proof phase failed: '+name)


with (run.directory / '.lifecycle.lock').open('w') as lock:
    fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
    run.verify_resources()
    phase('warm')
    try:
        run.compose('stop', 'render-worker', 'build-worker')
        phase('stopped')
    finally:
        run.compose('up', '-d', '--wait', 'render-worker', 'build-worker')
