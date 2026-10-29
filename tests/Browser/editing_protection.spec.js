import {test, expect} from '@playwright/test';
import {randomBytes} from 'node:crypto';
import {readFileSync} from 'node:fs';
import {login} from './helpers.js';

test.describe.configure({timeout: 90000});
const confirmation = page => page.getByRole('dialog', {name: 'Unsaved work', exact: true});
async function api(page, method, url, data) {
    const token = (await (await page.request.get('/api/v1/csrf')).json()).token;
    const result = await page.request.fetch(url, {method, data, headers: {'X-CSRF-Token': token}});
    expect(result.ok(), (await result.text()).slice(0, 1000)).toBe(true); return result.json();
}
async function fixture(page, kind) {
    const slug = 'protection-'+randomBytes(5).toString('hex');
    if (kind === 'theme') {
        const document = JSON.parse(readFileSync('agent-kit/examples/packages/ember/meta/theme.json', 'utf8'));
        document.name = 'browser/'+slug; document.cssName = slug; document.default = false;
        const item = await api(page, 'POST', '/api/v1/themes', {document, variants: 'pair', familyLabel: slug});
        return {item, url: '/themes/'+item.id+'/edit', saveUrl: '/api/v1/themes/'+item.id, field: 'primary', value: '#234567', newer: '#345678', button: 'Save theme', controller: 'theme-editor'};
    }
    const item = await api(page, 'POST', '/api/v1/items', {name: 'browser/'+slug, kind: kind === 'source' ? 'component' : 'layout', title: slug});
    return {item, url: `/catalogue/${item.id}/${kind === 'source' ? 'edit' : 'compose'}`, saveUrl: `/api/v1/items/${item.id}/${kind === 'source' ? 'draft' : 'composition'}`,
        field: kind === 'source' ? 'Twig source' : 'Literal utility classes', value: kind === 'source' ? '<p>Saved navigation text</p>' : 'grid gap-7', newer: kind === 'source' ? '<p>Newer unsaved text</p>' : 'grid gap-8', button: kind === 'source' ? 'Save draft' : 'Save composition', controller: kind === 'source' ? 'editor' : 'composition'};
}
async function enter(page, f) {
    await page.goto('/catalogue');
    await page.evaluate(async url => {const {visit} = await import('@hotwired/turbo'); visit(url);}, f.url);
    await expect(page.getByLabel(f.field, {exact: true})).toBeVisible();
    await expect(page.locator(`[data-controller~="${f.controller}"]`)).toHaveAttribute('data-editing-state', /clean|saved/);
}

for (const kind of ['source', 'composition', 'theme']) {
    test(`editing protection: ${kind} header, Back/Forward, native reload/close, failure and discard`, async ({page}) => {
        await login(page); const f = await fixture(page, kind); await enter(page, f);
        const editor = page.locator(`[data-controller~="${f.controller}"]`), field = page.getByLabel(f.field, {exact: true});
        // Merely opening tabs or selecting a node/variant must keep a clean snapshot.
        if (kind === 'source') {await page.getByRole('tab', {name: 'Examples', exact: true}).click(); await page.getByRole('tab', {name: 'Twig', exact: true}).click();}
        if (kind === 'theme') {const variants = page.getByLabel('Edit family variant', {exact: true}); const original = await variants.inputValue(); await variants.selectOption(f.item.family.variants.dark.id); await variants.selectOption(original);}
        await expect(editor).toHaveAttribute('data-editing-state', /clean|saved/);
        await field.fill(f.value); await expect(editor).toHaveAttribute('data-editing-state', 'dirty');
        const url = page.url(), length = await page.evaluate(() => history.length);
        await page.getByRole('link', {name: 'Themes', exact: true}).click(); await expect(confirmation(page)).toBeVisible();
        await expect(confirmation(page).getByRole('button', {name: 'Keep editing', exact: true})).toBeFocused();
        await page.keyboard.press('Enter'); await expect(confirmation(page)).toHaveCount(0);
        expect(page.url()).toBe(url); await expect(field).toHaveValue(f.value);
        await page.evaluate(() => history.back()); await expect(confirmation(page)).toBeVisible(); expect(page.url()).toBe(url);
        await page.keyboard.press('Escape'); await expect(confirmation(page)).toHaveCount(0); await expect(field).toHaveValue(f.value);
        expect(await page.evaluate(() => history.length)).toBe(length);
        let native = 0;
        page.once('dialog', async dialog => {expect(dialog.type()).toBe('beforeunload'); native++; await dialog.dismiss();});
        await page.reload({timeout: 5000}).catch(() => null); expect(native).toBe(1); await expect(field).toHaveValue(f.value);
        page.once('dialog', async dialog => {expect(dialog.type()).toBe('beforeunload'); native++; await dialog.dismiss();});
        await page.close({runBeforeUnload: true}); await expect.poll(() => native).toBe(2); expect(page.isClosed()).toBe(false);
        await page.route('**'+f.saveUrl, route => route.request().method() === 'PUT' ? route.fulfill({status: 503, json: {message: 'Storage unavailable; local edits are kept.'}}) : route.continue());
        await page.getByRole('button', {name: f.button, exact: true}).click(); await expect(editor).toHaveAttribute('data-editing-state', 'failed');
        await page.getByRole('link', {name: 'Themes', exact: true}).click(); await expect(confirmation(page)).toBeVisible(); await page.getByRole('button', {name: 'Keep editing', exact: true}).click();
        await expect(field).toHaveValue(f.value); await page.unroute('**'+f.saveUrl);
        await page.getByRole('button', {name: f.button, exact: true}).click(); await expect(editor).toHaveAttribute('data-editing-state', 'saved');
        await page.getByRole('link', {name: 'Themes', exact: true}).click(); await expect(page.locator('main h1')).toHaveText('Theme laboratory');
        await page.evaluate(() => history.back()); await expect(field).toHaveValue(f.value); await expect(editor).toHaveAttribute('data-editing-state', /clean|saved/);
        await field.fill(f.newer); await page.evaluate(() => history.forward()); await expect(confirmation(page)).toBeVisible(); expect(page.url()).toBe(url);
        await page.getByRole('button', {name: 'Keep editing', exact: true}).click(); await expect(field).toHaveValue(f.newer);
        await page.evaluate(() => history.forward()); await expect(confirmation(page)).toBeVisible();
        await page.getByRole('button', {name: 'Discard changes and leave', exact: true}).click(); await expect(page.locator('main h1')).toHaveText('Theme laboratory');
        await page.evaluate(() => history.back()); await expect(field).toHaveValue(f.value); await expect(confirmation(page)).toHaveCount(0);
    });

    test(`editing protection: ${kind} earlier save response preserves later edits and conflict`, async ({page}) => {
        await login(page); const f = await fixture(page, kind); await enter(page, f);
        const editor = page.locator(`[data-controller~="${f.controller}"]`), field = page.getByLabel(f.field, {exact: true});
        await field.fill(f.value);
        let release, started; const held = new Promise(resolve => {release = resolve;}); const saved = new Promise(resolve => {started = resolve;}); let first = true;
        await page.route('**'+f.saveUrl, async route => {
            if (route.request().method() !== 'PUT' || !first) return route.continue(); first = false;
            const response = await route.fetch(); expect(response.ok()).toBe(true); started(); await held; await route.fulfill({response});
        });
        await page.getByRole('button', {name: f.button, exact: true}).click(); await saved; await expect(editor).toHaveAttribute('data-editing-state', 'saving');
        await field.fill(f.newer); release(); await expect(editor).toHaveAttribute('data-editing-state', 'dirty'); await expect(field).toHaveValue(f.newer);
        await page.unroute('**'+f.saveUrl); await page.getByRole('button', {name: f.button, exact: true}).click(); await expect(editor).toHaveAttribute('data-editing-state', 'saved');
        const data = await (await page.request.get(f.saveUrl)).json(); expect(data.revision).toBe(3);
        if (kind === 'theme') {data.document.colors.primary = '#456789'; await api(page, 'PUT', f.saveUrl, {document: data.document, expectedRevision: 3});}
        else if (kind === 'composition') await api(page, 'PUT', f.saveUrl, {document: data.document, expectedRevision: 3, title: 'Externally saved title'});
        else await page.request.put(f.saveUrl, {headers: {'X-CSRF-Token': (await (await page.request.get('/api/v1/csrf')).json()).token, 'If-Match': '"3"'}, data: {manifestData: data.manifestData, sourceMap: data.sourceMap, title: 'Externally saved title', description: data.description, tags: data.tags}});
        await field.fill(f.value); await page.getByRole('button', {name: f.button, exact: true}).click(); await expect(editor).toHaveAttribute('data-editing-state', 'conflict'); await expect(field).toHaveValue(f.value);
        expect((await (await page.request.get(f.saveUrl)).json()).revision).toBe(4);
    });
}

test('editing protection: theme family label and inactive variant are real unsaved snapshots', async ({page}) => {
    await login(page); const f = await fixture(page, 'theme'); await enter(page, f);
    const editor = page.locator('[data-controller~="theme-editor"]'), label = page.getByLabel('Family display name', {exact: true});
    const name = await label.inputValue(); await label.fill('Unsaved family label'); await expect(editor).toHaveAttribute('data-editing-state', 'dirty');
    await label.fill(name); await expect(editor).toHaveAttribute('data-editing-state', 'clean');
    await page.getByLabel('primary', {exact: true}).fill(f.value);
    await page.getByLabel('Edit family variant', {exact: true}).selectOption(f.item.family.variants.dark.id);
    await page.getByRole('link', {name: 'Themes', exact: true}).click(); await expect(confirmation(page)).toBeVisible(); await page.keyboard.press('Escape');
    await page.getByLabel('Edit family variant', {exact: true}).selectOption(f.item.id); await expect(page.getByLabel('primary', {exact: true})).toHaveValue(f.value);
});

test('editing protection: a committed source save preserves later invalid JSON and advances its revision', async ({page}) => {
    await login(page); const f = await fixture(page, 'source'); await enter(page, f);
    await page.getByLabel('Twig source', {exact: true}).fill(f.value);
    let release, started; const held = new Promise(resolve => {release = resolve;}); const saved = new Promise(resolve => {started = resolve;});
    await page.route('**'+f.saveUrl, async route => {
        if (route.request().method() !== 'PUT') return route.continue();
        const response = await route.fetch(); started(); await held; await route.fulfill({response});
    });
    await page.getByRole('button', {name: 'Save draft', exact: true}).click(); await saved;
    await page.getByRole('tab', {name: 'Props', exact: true}).click();
    const defaults = page.getByLabel('Default props JSON', {exact: true}); await defaults.fill('{"unfinished":'); release();
    await expect(page.locator('[data-controller~="editor"]')).toHaveAttribute('data-editor-revision-value', '2');
    await expect(defaults).toHaveValue('{"unfinished":');
    await expect(page.locator('[data-controller~="editor"]')).toHaveAttribute('data-editing-state', 'dirty');
    await page.getByRole('link', {name: 'Themes', exact: true}).click(); await expect(confirmation(page)).toBeVisible(); await page.keyboard.press('Escape');
    await expect(defaults).toHaveValue('{"unfinished":'); await page.unroute('**'+f.saveUrl);
    const document = await (await page.request.get(f.saveUrl)).json();
    expect(document.revision).toBe(2); expect(document.sourceMap[document.manifestData.entry]).toBe(f.value);
    await defaults.fill(JSON.stringify(document.manifestData.defaults));
    await expect(page.locator('[data-controller~="editor"]')).toHaveAttribute('data-editing-state', 'saved');
    await page.getByRole('tab', {name: 'Twig', exact: true}).click(); await page.getByLabel('Twig source', {exact: true}).fill(f.newer);
    const nextHeld = new Promise(resolve => {release = resolve;}); const nextSaved = new Promise(resolve => {started = resolve;});
    await page.route('**'+f.saveUrl, async route => {
        if (route.request().method() !== 'PUT') return route.continue();
        const response = await route.fetch(); started(); await nextHeld; await route.fulfill({response});
    });
    await page.getByRole('button', {name: 'Save draft', exact: true}).click(); await nextSaved;
    await page.getByText('Advanced JSON', {exact: true}).click();
    const raw = page.getByLabel('Source files JSON', {exact: true}); await raw.fill('{invalid source JSON'); release();
    await expect(page.locator('[data-controller~="editor"]')).toHaveAttribute('data-editor-revision-value', '3');
    await expect(raw).toHaveValue('{invalid source JSON'); await expect(page.locator('[data-controller~="editor"]')).toHaveAttribute('data-editing-state', 'dirty');
    expect((await (await page.request.get(f.saveUrl)).json()).revision).toBe(3);
});
