import { test, expect } from '@playwright/test';
import { randomBytes } from 'node:crypto';
import { login, createAlert, pickReference } from './helpers.js';

async function newComposition(page, kind) {
    await page.goto('/catalogue/new');
    const name = `browser/${kind}-${randomBytes(5).toString('hex')}`;
    await page.getByLabel('Name (namespace/slug)', { exact: true }).fill(name);
    await page.getByLabel('Kind', { exact: true }).selectOption(kind);
    await page.getByLabel('Title', { exact: true }).fill(`Composed ${kind}`);
    await page.getByRole('button', { name: 'Create draft', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Composition builder', exact: true })).toBeVisible();
    return { name, id: page.url().split('/').at(-2) };
}

test('compose distinct instances, keyboard reorder/nest, save, publish block and nest in layout', async ({ page }) => {
    test.setTimeout(100000);
    await login(page);
    const { item, manifest } = await createAlert(page);
    const token = (await (await page.request.get('/api/v1/csrf')).json()).token;
    expect((await page.request.post(`/api/v1/items/${item.id}/releases`, { headers: { 'X-CSRF-Token': token }, data: { version: '1.0.0', expectedRevision: 2 } })).status()).toBe(201);
    const block = await newComposition(page, 'block');
    await pickReference(page, item.name, '1.0.0');
    await page.getByLabel('Instance props (JSON)', { exact: true }).fill(JSON.stringify({ ...manifest.defaults, message: 'First instance' }));
    await page.getByRole('button', { name: 'Duplicate node', exact: true }).click();
    await page.getByLabel('Instance props (JSON)', { exact: true }).fill(JSON.stringify({ ...manifest.defaults, message: 'Second instance' }));
    await page.getByRole('button', { name: 'Move up', exact: true }).focus();
    await page.keyboard.press('Enter');
    const current = page.locator('[data-composition-target="status"]');
    await expect(current).toHaveText('Composition preview current', { timeout: 20000 });
    const frame = page.frameLocator('iframe[title="Composition preview"]');
    await expect(frame.getByRole('status')).toHaveText([/Second instance/, /First instance/]);
    // Arrow keys select the root without a mouse, then add a nested container and text.
    await page.getByRole('treeitem', { name: `${item.name}@1.0.0`, exact: true }).first().focus();
    await page.keyboard.press('ArrowLeft');
    await page.getByRole('button', { name: 'Add container', exact: true }).click();
    await page.getByRole('button', { name: 'Add text', exact: true }).click();
    await page.getByLabel('Literal text', { exact: true }).fill('{{ 7*7 }} <em>literal</em>');
    await page.getByRole('button', { name: 'Outdent', exact: true }).click();
    await page.getByRole('button', { name: 'Indent', exact: true }).click();
    await page.getByRole('button', { name: 'Duplicate node', exact: true }).click();
    await page.getByRole('button', { name: 'Remove node', exact: true }).click();
    await page.getByRole('button', { name: 'Save composition', exact: true }).click();
    await expect(page.locator('[data-composition-target="saveStatus"]')).toHaveText('Saved revision 2');
    await page.reload();
    await expect(current).toHaveText('Composition preview current', { timeout: 20000 });
    await expect(frame.locator('body')).toContainText('{{ 7*7 }} <em>literal</em>');
    await expect(frame.getByRole('status')).toHaveText([/Second instance/, /First instance/]);
    const publish = await page.request.post(`/api/v1/items/${block.id}/releases`, { headers: { 'X-CSRF-Token': token }, data: { version: '1.0.0', expectedRevision: 2 } });
    expect(publish.status(), await publish.text()).toBe(201);
    await newComposition(page, 'layout');
    await pickReference(page, block.name, '1.0.0');
    await expect(current).toHaveText('Composition preview current',{timeout:20000});
    let releasePreparation;let preparationStarted;const held=new Promise(resolve=>{releasePreparation=resolve;});const started=new Promise(resolve=>{preparationStarted=resolve;});let intercepted=false;
    await page.route('**/api/v1/items/*/composition',async route=>{if(route.request().method()==='POST'&&!intercepted){intercepted=true;preparationStarted();await held;}await route.continue();});
    await page.getByRole('button',{name:'Build preview',exact:true}).click();await started;
    await page.getByRole('button', { name: 'Save composition', exact: true }).click();
    await expect(page.locator('[data-composition-target="saveStatus"]')).toHaveText('Saved revision 2');
    const obsolete=page.waitForResponse(response=>response.url().endsWith('/composition')&&response.request().method()==='POST'&&response.status()===412);releasePreparation();await obsolete;

    await expect(page.locator('[data-composition-target="saveStatus"]')).toHaveText('Saved revision 2');
    await expect(current).toHaveText('Composition preview current', { timeout: 20000 });
    await expect(frame.getByRole('status')).toHaveText([/Second instance/, /First instance/]);
    await frame.getByRole('button', { name: manifest.defaults.dismissLabel, exact: true }).first().click();
    await expect(frame.getByRole('status')).toHaveCount(1);
    await page.getByLabel('New source name (namespace/slug)', { exact: true }).fill(`browser/source-${randomBytes(5).toString('hex')}`);
    await page.getByRole('button', { name: 'Duplicate and convert to source', exact: true }).click();
    await expect(page.locator('[data-flash]')).toHaveText('Duplicated into source mode. Original composition preserved.');
    await expect(page.locator('[data-controller~="editor"]')).toBeVisible();
});
