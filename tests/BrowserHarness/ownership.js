import {readFileSync} from 'node:fs';
import {createHmac, randomBytes} from 'node:crypto';

export function ownership() {
    if (!process.env.STUDIO_TEST_OWNERSHIP_FILE || !process.env.STUDIO_TEST_RUN_ID || !process.env.STUDIO_BASE_URL || !process.env.STUDIO_ACCOUNTS_FILE) {
        throw new Error('Browser tests require a verified disposable run. Use make browser; no default working-app target is allowed.');
    }
    const owner = JSON.parse(readFileSync(process.env.STUDIO_TEST_OWNERSHIP_FILE, 'utf8'));
    if (owner.runId !== process.env.STUDIO_TEST_RUN_ID || !/^browser-[a-f0-9]{24}$/.test(owner.runId) || !/^[a-f0-9]{64}$/.test(owner.secret)) {
        throw new Error('Browser ownership mismatch.');
    }
    return owner;
}

export async function verifyTarget(request) {
    const owner = ownership();
    const challenge = randomBytes(32).toString('hex');
    const response = await request.get('/health/browser-run?challenge='+challenge, {maxRedirects: 0, timeout: 10000});
    if (response.status() !== 200) throw new Error('Target has no verified disposable database/blob ownership. Refusing browser mutations.');
    const proof = await response.json();
    if (proof.runId !== owner.runId || proof.proof !== createHmac('sha256', owner.secret).update(challenge).digest('hex')) {
        throw new Error('Target ownership challenge failed. Refusing browser mutations.');
    }
}
