import { readFileSync } from 'node:fs';

export async function login(page, role = 'editor') {
    const account = JSON.parse(readFileSync(process.env.STUDIO_ACCOUNTS_FILE || 'var/browser/accounts.json', 'utf8'))[role];
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill(account.email);
    await page.getByLabel('Password', { exact: true }).fill(account.password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await page.waitForURL('/');
    return account;
}

import { randomBytes } from 'node:crypto';
import { expect } from '@playwright/test';

export async function createAlert(page) {
    const token = (await (await page.request.get('/api/v1/csrf')).json()).token;
    const name = `browser/alert-${randomBytes(5).toString('hex')}`;
    const create = await page.request.post('/api/v1/items', { headers: { 'X-CSRF-Token': token }, data: { name, kind: 'component', title: 'Isolated alert' } });
    expect(create.status()).toBe(201);
    const item = await create.json();
    const root = 'agent-kit/examples/packages/alert/';
    const manifest = JSON.parse(readFileSync(root+'manifest.json', 'utf8'));
    const sources = {};
    for (const file of manifest.files) {
        const path = file.path.replace('awui/alert', name);
        sources[path] = readFileSync(root+file.path, 'utf8').replaceAll('catalogue--awui--alert', `catalogue--${name.replace('/', '--')}`);
    }
    manifest.name = name;
    manifest.title = 'Isolated alert';
    manifest.entry = manifest.entry.replace('awui/alert', name);
    manifest.controllers[0] = { identifier: `catalogue--${name.replace('/', '--')}`, path: `assets/controllers/catalogue/${name}_controller.js` };
    manifest.files = [];
    const save = await page.request.put(`/api/v1/items/${item.id}/draft`, { headers: { 'X-CSRF-Token': token, 'If-Match': '"1"' }, data: { title: 'Isolated alert', manifestData: manifest, sourceMap: sources } });
    expect(save.status()).toBe(200);
    await page.goto(`/catalogue/${item.id}/edit`);
    await page.getByText('Advanced JSON', { exact: true }).click();
    return { item, manifest, sources };
}

export async function preview(page) {
    await page.getByRole('button', { name: 'Build preview', exact: true }).click();
    const section = page.locator('[data-controller~="preview"]');
    await expect(section).toHaveAttribute('data-preview-state', /^(current|error)$/, { timeout: 20000 });
    expect(await section.getAttribute('data-preview-state'), await page.locator('[data-preview-target="status"]').textContent()).toBe('current');
    return page.frameLocator('iframe[title="Component preview"]');
}
