import { chromium, expect } from '@playwright/test';
import { readFileSync, writeFileSync } from 'node:fs';
import { randomBytes } from 'node:crypto';
import { login } from './helpers.js';

const browser = await chromium.launch();
const page = await browser.newPage({ baseURL: process.env.STUDIO_BASE_URL || 'http://localhost:8095' });
const statePath = 'var/browser/restart.json';
try {
    await login(page);
    if (process.argv[2] === 'prepare') {
        const name = `restart/component-${randomBytes(6).toString('hex')}`;
        const title = `Restart persistence ${name.split('/')[1]}`;
        await page.goto('/catalogue/new');
        await page.getByLabel('Name (namespace/slug)').fill(name);
        await page.getByLabel('Title', { exact: true }).fill(title);
        await page.getByRole('button', { name: 'Create draft' }).click();
        await expect(page).toHaveURL(/\/edit$/);
        const sources = JSON.parse(await page.getByLabel('Source files JSON').inputValue());
        const path = `templates/catalogue/${name}/component.html.twig`;
        const source = '<div class="grid md:grid-cols-3">{{ message }}</div>\n';
        sources[path] = source;
        await page.getByLabel('Source files JSON').fill(JSON.stringify(sources, null, 2));
        await page.getByLabel('Tags', { exact: true }).fill('restart, acceptance');
        await page.getByRole('button', { name: 'Save draft' }).click();
        await expect(page.locator('[data-flash]')).toContainText('Draft saved.');
        writeFileSync(statePath, JSON.stringify({ name, title, path, source, editUrl: new URL(page.url()).pathname }));
        console.log('PASS: created and edited a real draft before container restart.');
    } else if (process.argv[2] === 'verify') {
        const state = JSON.parse(readFileSync(statePath, 'utf8'));
        await page.goto(state.editUrl);
        await expect(page.getByLabel('Title', { exact: true })).toHaveValue(state.title);
        expect(JSON.parse(await page.getByLabel('Source files JSON').inputValue())[state.path]).toBe(state.source);
        await expect(page.getByLabel('Tags', { exact: true })).toHaveValue('restart, acceptance');
        await expect(page.locator('[data-saved-revision]')).toHaveText('2');
        await page.goto(`/catalogue?q=${encodeURIComponent(state.name)}`);
        await expect(page.getByRole('link', { name: state.title, exact: true })).toBeVisible();
        console.log('PASS: source, metadata, tags and revision persisted and remain searchable after app/database restart.');
    } else {
        throw new Error('Expected prepare or verify.');
    }
} finally {
    await browser.close();
}
