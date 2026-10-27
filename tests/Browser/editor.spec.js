import { test, expect } from '@playwright/test';
import { login, createAlert, preview } from './helpers.js';

const state = page => page.locator('[data-controller~="preview"]');
const frame = page => page.frameLocator('iframe[title="Component preview"]');

test('Twig and Stimulus edits update without reload, stay unsaved, then persist', async ({ page }) => {
    await login(page);
    const { item, manifest } = await createAlert(page);
    await preview(page);
    await page.getByLabel('Twig source', { exact: true }).fill(`<div id="live" data-controller="${manifest.controllers[0].identifier}" class="text-2xl">Live Twig {{ message }}</div>`);
    await expect(page.locator('[data-editor-target="unsaved"]')).toHaveText('Unsaved changes');
    await expect(frame(page).locator('#live')).toContainText('Live Twig');
    await page.getByRole('tab', { name: 'JavaScript', exact: true }).click();
    await page.getByLabel('Stimulus source', { exact: true }).fill(`import { Controller } from '@hotwired/stimulus'; export default class extends Controller { connect() { this.element.dataset.fromEditor = 'updated'; } }`);
    await expect(frame(page).locator('#live')).toHaveAttribute('data-from-editor', 'updated', { timeout: 15000 });
    const stored = await (await page.request.get(`/api/v1/items/${item.id}/draft`)).json();
    expect(stored.revision).toBe(2);
    expect(stored.sourceMap[manifest.entry]).not.toContain('Live Twig');
    await page.getByRole('button', { name: 'Save draft', exact: true }).click();
    await expect(page.locator('[data-flash]')).toHaveText('Draft saved.');
    await page.reload();
    await expect(page.getByLabel('Twig source', { exact: true })).toHaveValue(/Live Twig/);
    await expect(page.locator('[data-editor-target="unsaved"]')).toHaveText('Saved');
    await expect(frame(page).locator('#live')).toHaveAttribute('data-from-editor', 'updated', { timeout: 15000 });
});

test('invalid Twig gives a location and retains a visibly stale last-good document', async ({ page }) => {
    await login(page);
    await createAlert(page);
    await preview(page);
    const previous = await page.locator('iframe').getAttribute('srcdoc');
    await page.getByLabel('Twig source', { exact: true }).fill('<section>\n{% if %}\n</section>');
    await expect(state(page)).toHaveAttribute('data-preview-state', 'error', { timeout: 15000 });
    await expect(page.locator('[data-preview-target="status"]')).toContainText('Stale preview');
    await expect(page.locator('[data-preview-target="status"]')).toContainText('component.html.twig:2');
    expect(await page.locator('iframe').getAttribute('srcdoc')).toBe(previous);
    await expect(frame(page).getByRole('status')).toContainText('Your component is ready.');
});

test('a delayed old document cannot replace newer output', async ({ page }) => {
    await login(page);
    await createAlert(page);
    await preview(page);
    let release;
    let reached;
    const held = new Promise(resolve => { release = resolve; });
    const seen = new Promise(resolve => { reached = resolve; });
    let intercepted = false;
    await page.route('**/api/v1/preview-jobs/*/document', async route => {
        if (intercepted) { await route.continue(); return; }
        intercepted = true;
        const response = await route.fetch();
        reached();
        await held;
        try { await route.fulfill({ response }); } catch {}
    });
    await page.getByLabel('Twig source', { exact: true }).fill('<div id="result">Older output</div>');
    await seen;
    await page.getByLabel('Twig source', { exact: true }).fill('<div id="result">Newer output</div>');
    await expect(frame(page).locator('#result')).toHaveText('Newer output', { timeout: 15000 });
    release();
    await page.waitForTimeout(300);
    await expect(frame(page).locator('#result')).toHaveText('Newer output');
    await page.unrouteAll({ behavior: 'ignoreErrors' });
});

test('viewport, zoom, typography and private preset survive reload', async ({ page }) => {
    // Several real sequential builds and a save/reload exceed the generic 30s budget.
    test.setTimeout(90000);
    await login(page);
    await createAlert(page);
    await page.getByLabel('Twig source', { exact: true }).fill('<div id="grid" class="grid md:grid-cols-3 gap-4"><p id="inherited">One</p><h2 class="text-2xl">Heading</h2><p>Three</p></div>');
    await expect(frame(page).locator('#grid')).toBeVisible({ timeout: 15000 });
    for (const [width, columns] of [[375, 1], [768, 3], [1280, 3]]) {
        await page.getByRole('button', { name: `${width} px`, exact: true }).click();
        expect(await frame(page).locator('body').evaluate(() => innerWidth)).toBe(width);
        await expect.poll(async () => (await frame(page).locator('#grid').evaluate(element => getComputedStyle(element).gridTemplateColumns)).split(' ').length).toBe(columns);
    }
    await page.getByRole('button', { name: '375 px', exact: true }).click();
    await page.getByLabel('Zoom', { exact: true }).selectOption('75');
    expect(await frame(page).locator('body').evaluate(() => innerWidth)).toBe(375);
    await page.getByLabel('Root font size', { exact: true }).fill('20');
    await page.getByLabel('Body font', { exact: true }).selectOption('system-serif');
    await page.getByLabel('Heading font', { exact: true }).selectOption('system-mono');
    await page.getByLabel('Default text size', { exact: true }).selectOption('text-lg');
    await expect(frame(page).locator('#inherited')).toHaveCSS('font-size', '22.5px', { timeout: 15000 });
    await expect(frame(page).locator('h2')).toHaveCSS('font-size', '30px');
    await expect(frame(page).locator('body')).toHaveCSS('font-family', /Georgia/);
    await page.getByRole('button', { name: 'Save draft', exact: true }).click();
    await expect(page.locator('[data-flash]')).toHaveText('Draft saved.');
    // Source save reloads the page; explicitly set the renderer workspace to persist.
    await page.getByRole('button', { name: '375 px', exact: true }).click();
    await page.getByLabel('Zoom', { exact: true }).selectOption('75');
    await page.getByLabel('Root font size', { exact: true }).fill('20');
    await page.getByLabel('Body font', { exact: true }).selectOption('system-serif');
    await page.getByLabel('Heading font', { exact: true }).selectOption('system-mono');
    await page.getByLabel('Default text size', { exact: true }).selectOption('text-lg');
    await page.getByRole('button', { name: 'Save renderer settings', exact: true }).click();
    await expect(page.locator('[data-preview-target="presetStatus"]')).toHaveText('Renderer settings saved');
    await page.reload();
    await expect(page.getByLabel('Viewport width', { exact: true })).toHaveValue('375');
    await expect(page.getByLabel('Zoom', { exact: true })).toHaveValue('75');
    await expect(page.getByLabel('Root font size', { exact: true })).toHaveValue('20');
    await expect(page.getByLabel('Body font', { exact: true })).toHaveValue('system-serif');
    await expect(frame(page).locator('#inherited')).toHaveCSS('font-size', '22.5px', { timeout: 15000 });
});

test('source tabs support keyboard navigation and reset stays usable', async ({ page }) => {
    await login(page);
    await createAlert(page);
    const twig = page.getByRole('tab', { name: 'Twig', exact: true });
    await twig.focus();
    await page.keyboard.press('ArrowRight');
    await expect(page.getByRole('tab', { name: 'JavaScript', exact: true })).toBeFocused();
    await expect(page.getByLabel('Stimulus source', { exact: true })).toBeVisible();
    await page.keyboard.press('End');
    await expect(page.getByRole('tab', { name: 'Examples', exact: true })).toBeFocused();
    await expect(page.getByLabel('Examples JSON', { exact: true })).toBeVisible();
    await page.getByLabel('Preview example', { exact: true }).selectOption('1');
    await expect(frame(page).getByRole('status')).toContainText('Check the exported source', { timeout: 15000 });
    await page.getByRole('button', { name: 'Reset preview', exact: true }).click();
    await expect(page.locator('[data-preview-target="status"]')).toHaveText('Preview reset');
});

test('rapid edits are debounced and invalid Stimulus source keeps the good frame', async ({ page }) => {
    await page.clock.install();
    await login(page);
    await createAlert(page);
    await preview(page);
    let requests = 0;
    page.on('request', request => { if (request.method() === 'POST' && new URL(request.url()).pathname === '/api/v1/preview-jobs') requests++; });
    // Bound the input interval independently of Docker build/host scheduling latency.
    await page.clock.pauseAt(new Date(Date.now()+1000));
    const source = page.getByLabel('Twig source', { exact: true });
    await source.fill('<div id="debounced">First</div>');
    await source.fill('<div id="debounced">Second</div>');
    await source.fill('<div id="debounced">Final</div>');
    await page.clock.runFor(299);
    expect(requests).toBe(0);
    await page.clock.runFor(1);
    await page.clock.resume();
    await expect(frame(page).locator('#debounced')).toHaveText('Final', { timeout: 15000 });
    expect(requests).toBe(1);
    const good = await page.locator('iframe').getAttribute('srcdoc');
    await page.getByRole('tab', { name: 'JavaScript', exact: true }).click();
    await page.getByLabel('Stimulus source', { exact: true }).fill('export default class {');
    await expect(state(page)).toHaveAttribute('data-preview-state', 'error', { timeout: 15000 });
    await expect(page.locator('[data-preview-target="status"]')).toContainText('Stale preview');
    await expect(page.locator('[data-preview-target="status"]')).toContainText('JavaScript syntax error');
    expect(await page.locator('iframe').getAttribute('srcdoc')).toBe(good);
});
