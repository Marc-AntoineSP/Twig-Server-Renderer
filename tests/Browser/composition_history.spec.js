import {test, expect} from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import {randomBytes} from 'node:crypto';
import {writeFileSync} from 'node:fs';
import {login} from './helpers.js';

test.describe.configure({timeout: 90000});
test.use({reducedMotion: 'reduce'});
async function api(page, method, url, data) {
    const token = (await (await page.request.get('/api/v1/csrf')).json()).token;
    const response = await page.request.fetch(url, {method, data, headers: {'X-CSRF-Token': token}});
    expect(response.ok(), (await response.text()).slice(0, 1200)).toBe(true); return response.json();
}
async function historyFixture(page) {
    const item = await api(page, 'POST', '/api/v1/items', {name: 'browser/history-'+randomBytes(5).toString('hex'), kind: 'layout', title: 'Original layout'});
    const url = '/api/v1/items/'+item.id+'/composition';
    let saved = await (await page.request.get(url)).json();
    saved.document.root.children.push({type: 'text', value: 'Historical text <em>literal</em>'});
    saved = await api(page, 'PUT', url, {document: saved.document, expectedRevision: 1, title: 'Historical layout'});
    const oldDocument = structuredClone(saved.document); saved.document.root.children[0].value = 'Current saved text';
    saved = await api(page, 'PUT', url, {document: saved.document, expectedRevision: 2, title: 'Current layout'});
    return {item, url, saved, oldDocument};
}
const panel = page => page.getByRole('dialog', {name: 'Saved composition history', exact: true});

for (const width of [390, 1440]) for (const mode of ['light', 'dark']) {
    test(`composition history: inspect, keep local work and restore new revision ${width}px ${mode}`, async ({page}, info) => {
        await page.setViewportSize({width, height: 1000}); await page.emulateMedia({colorScheme: mode}); await login(page);
        await page.getByRole('button', {name: 'Restore default appearance', exact: true}).click();
        await expect(page.locator('[data-appearance-target="status"]')).toHaveText('Applied Studio default.');
        const f = await historyFixture(page); await page.goto('/catalogue/'+f.item.id+'/compose');
        await page.getByLabel('Title', {exact: true}).fill('Unsaved local title');
        await page.getByLabel('Literal utility classes', {exact: true}).fill('grid gap-8');
        await page.getByRole('button', {name: 'Saved history', exact: true}).focus(); await page.keyboard.press('Enter');
        await expect(panel(page)).toBeVisible(); await expect(panel(page).getByRole('button', {name: 'Close history', exact: true})).toBeFocused();
        await panel(page).getByRole('button', {name: 'Revision 2', exact: true}).click();
        await expect(panel(page).getByRole('heading', {name: 'Restore revision 2 as a new revision', exact: true})).toBeFocused();
        await expect(panel(page)).toContainText('Historical text <em>literal</em>');
        await expect(panel(page)).toContainText('Snapshot actor:');
        expect(await panel(page).evaluate(el => el.scrollWidth <= el.clientWidth)).toBe(true);
        const report = await new AxeBuilder({page}).include('[data-composition-target="historyDialog"]').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
        writeFileSync(info.outputPath('history-accessibility.json'), JSON.stringify({violations: report.violations, incomplete: report.incomplete}, null, 2));
        expect(report.violations.map(v => ({id: v.id, targets: v.nodes.map(n => n.target)}))).toEqual([]);
        await page.screenshot({path: info.outputPath(`history-${width}-${mode}.png`)});
        await panel(page).getByRole('button', {name: 'Restore as new revision', exact: true}).click();
        const confirm = page.getByRole('dialog', {name: 'Unsaved work', exact: true}); await expect(confirm).toBeVisible();
        await expect(confirm.getByRole('button', {name: 'Keep editing', exact: true})).toBeFocused(); await page.keyboard.press('Enter');
        await expect(page.getByLabel('Title', {exact: true})).toHaveValue('Unsaved local title');
        expect((await (await page.request.get(f.url)).json()).revision).toBe(3);
        await panel(page).getByRole('button', {name: 'Restore as new revision', exact: true}).click(); await confirm.getByRole('button', {name: 'Discard local edits and restore', exact: true}).click();
        await expect(panel(page)).toBeHidden(); await expect(page.locator('[data-composition-target="saveStatus"]')).toHaveText('Restored revision 2 as new revision 4');
        await expect(page.getByLabel('Title', {exact: true})).toHaveValue('Historical layout');
        await expect(page.locator('[data-controller~="composition"]')).toHaveAttribute('data-composition-revision-value', '4');
        await expect(page.locator('[data-controller~="composition"]')).toHaveAttribute('data-editing-state', 'saved');
        await expect(page.locator('[data-composition-target="status"]')).toHaveText('Composition preview current', {timeout: 30000});
        await expect(page.frameLocator('iframe[title="Composition preview"]').getByText('Historical text <em>literal</em>', {exact: true})).toBeVisible();
        const restored = await (await page.request.get(f.url)).json(); expect(restored.document).toEqual(f.oldDocument); expect(restored.title).toBe('Historical layout');
        const history = await (await page.request.get(f.url+'/history')).json(); expect(history.history.map(entry => entry.revision)).toEqual([4, 3, 2, 1]);
        await page.getByRole('button', {name: 'Saved history', exact: true}).click(); await page.keyboard.press('Escape');
        await expect(page.getByRole('button', {name: 'Saved history', exact: true})).toBeFocused();
        await page.reload(); await expect(page.getByLabel('Title', {exact: true})).toHaveValue('Historical layout');
    });
}

test('composition history: external write after opening conflicts, retains local work and offers deliberate reconciliation', async ({page}) => {
    await login(page); const f = await historyFixture(page); await page.goto('/catalogue/'+f.item.id+'/compose');
    await page.getByLabel('Title', {exact: true}).fill('Local work must survive');
    await page.getByRole('button', {name: 'Saved history', exact: true}).click(); await panel(page).getByRole('button', {name: 'Revision 2', exact: true}).click();
    await api(page, 'PUT', f.url, {document: f.saved.document, expectedRevision: 3, title: 'External head'});
    await panel(page).getByRole('button', {name: 'Restore as new revision', exact: true}).click(); await page.getByRole('button', {name: 'Discard local edits and restore', exact: true}).click();
    await expect(panel(page).locator('[data-composition-target="historyStatus"]')).toContainText('Composition changed');
    await expect(page.getByLabel('Title', {exact: true})).toHaveValue('Local work must survive');
    await expect(page.locator('[data-controller~="composition"]')).toHaveAttribute('data-editing-state', 'conflict');
    await panel(page).getByRole('button', {name: 'Load latest saved revision', exact: true}).click(); await page.getByRole('button', {name: 'Keep editing', exact: true}).click();
    await expect(page.getByLabel('Title', {exact: true})).toHaveValue('Local work must survive');
    await panel(page).getByRole('button', {name: 'Load latest saved revision', exact: true}).click(); await page.getByRole('button', {name: 'Discard local edits and load saved revision', exact: true}).click();
    await expect(page.getByLabel('Title', {exact: true})).toHaveValue('External head');
    await expect(page.locator('[data-controller~="composition"]')).toHaveAttribute('data-composition-revision-value', '4');
    expect((await (await page.request.get(f.url+'/history')).json()).history.map(entry => entry.revision)).toEqual([4, 3, 2, 1]);
});

test('composition history: empty and long histories use bounded pages and do not change the head', async ({page}) => {
    await login(page); const f = await historyFixture(page);
    let revision = 3;
    for (; revision < 25; revision++) await api(page, 'PUT', f.url, {document: f.saved.document, expectedRevision: revision, title: 'History '+revision});
    await page.goto('/catalogue/'+f.item.id+'/compose'); await page.getByRole('button', {name: 'Saved history', exact: true}).click();
    await expect(panel(page).locator('[data-composition-target="historyList"] li')).toHaveCount(20);
    await panel(page).getByRole('button', {name: 'Older revisions', exact: true}).click(); await expect(panel(page).locator('[data-composition-target="historyList"] li')).toHaveCount(5);
    await expect(panel(page).getByRole('button', {name: 'Older revisions', exact: true})).toBeDisabled();
    await panel(page).getByRole('button', {name: 'Newer revisions', exact: true}).click(); await expect(panel(page).locator('[data-composition-target="historyList"] li')).toHaveCount(20);
    expect((await (await page.request.get(f.url)).json()).revision).toBe(25);
    await panel(page).getByRole('button', {name: 'Close history', exact: true}).click();
    const fresh = await api(page, 'POST', '/api/v1/items', {name: 'browser/empty-history-'+randomBytes(5).toString('hex'), kind: 'block', title: 'No history'});
    await page.goto('/catalogue/'+fresh.id+'/compose'); await page.getByRole('button', {name: 'Saved history', exact: true}).click();
    await expect(panel(page)).toContainText('No saved history yet.'); await expect(panel(page).getByRole('button', {name: 'Restore as new revision', exact: true})).toBeDisabled();
});
