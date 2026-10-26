import { test, expect } from '@playwright/test';
import { login, createAlert, preview } from './helpers.js';

test('included alert renders and Stimulus dismisses it inside an opaque frame', async ({ page }) => {
    await login(page);
    await createAlert(page);
    const frame = await preview(page);
    await expect(frame.getByRole('status')).toContainText('Your component is ready.');
    await expect(frame.getByRole('status')).toHaveCSS('display', 'flex');
    await expect(page.locator('iframe')).toHaveAttribute('sandbox', 'allow-scripts');
    await frame.getByRole('button', { name: 'Dismiss notification' }).click();
    await expect(frame.getByRole('status')).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'Edit draft' })).toBeVisible();
    await page.getByLabel('Disable preview scripts').check();
    await expect(frame.getByRole('status')).toBeVisible();
    await frame.getByRole('button', { name: 'Dismiss notification' }).click();
    await expect(frame.getByRole('status')).toBeVisible();
});

test('new unsaved responsive utility is materialized into real preview CSS', async ({ page }) => {
    await page.setViewportSize({ width: 1400, height: 1000 });
    await login(page);
    const { manifest, sources } = await createAlert(page);
    sources[manifest.entry] = '<div id="new-grid" class="grid md:grid-cols-3 gap-4"><div>One</div><div>Two</div><div>Three</div></div>';
    await page.getByLabel('Source files JSON').fill(JSON.stringify(sources));
    const frame = await preview(page);
    await expect(frame.locator('#new-grid')).toBeVisible();
    expect((await frame.locator('#new-grid').evaluate(element => getComputedStyle(element).gridTemplateColumns)).split(' ')).toHaveLength(3);
    await page.getByRole('button', { name: '375 px', exact: true }).click();
    await expect.poll(async () => (await frame.locator('#new-grid').evaluate(element => getComputedStyle(element).gridTemplateColumns)).split(' ').length).toBe(1);
});

test('untrusted JS cannot read parent DOM or storage and preview CSS is isolated', async ({ page }) => {
    await login(page);
    const { manifest, sources } = await createAlert(page);
    await page.evaluate(() => localStorage.setItem('studio-private-test', 'parent-only'));
    const parentBackground = await page.locator('body').evaluate(element => getComputedStyle(element).backgroundColor);
    sources[manifest.entry] = `<style>body{background:rgb(255,0,0)}</style><div id="boundary" data-controller="${manifest.controllers[0].identifier}">Boundary</div>`;
    sources[manifest.controllers[0].path] = `import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
 connect() {
  const checks = {};
  try { parent.document.body.dataset.escaped = 'yes'; checks.parent = false; } catch { checks.parent = true; }
  try { parent.localStorage.getItem('studio-private-test'); checks.parentStorage = false; } catch { checks.parentStorage = true; }
  try { localStorage.setItem('escaped', 'yes'); checks.storage = false; } catch { checks.storage = true; }
  this.element.dataset.checks = JSON.stringify(checks);
  this.element.dataset.literal = '</script><img id="unexpected-script-breakout">ß';
 }
}`;
    await page.getByLabel('Source files JSON').fill(JSON.stringify(sources));
    const frame = await preview(page);
    await expect(frame.locator('#boundary')).toHaveAttribute('data-checks', JSON.stringify({ parent: true, parentStorage: true, storage: true }));
    await expect(frame.locator('#boundary')).toHaveAttribute('data-literal', '</script><img id="unexpected-script-breakout">ß');
    await expect(frame.locator('#unexpected-script-breakout')).toHaveCount(0);
    expect(await page.locator('body').getAttribute('data-escaped')).toBeNull();
    expect(await page.evaluate(() => localStorage.getItem('studio-private-test'))).toBe('parent-only');
    expect(await page.locator('body').evaluate(element => getComputedStyle(element).backgroundColor)).toBe(parentBackground);
    await expect(frame.locator('body')).toHaveCSS('background-color', 'rgb(255, 0, 0)');
});

test('preview messages require current iframe source, channel and bounded schema', async ({ page }) => {
    await login(page);
    await createAlert(page);
    await preview(page);
    const iframe = page.locator('iframe');
    const channel = await iframe.evaluate(element => element.srcdoc.match(/const channel="([a-f0-9]{32})"/)[1]);
    const frame = page.frames().find(value => value !== page.mainFrame());
    await frame.evaluate(channel => parent.postMessage({ type: 'resize', channel, height: 777 }, '*'), channel);
    await expect(iframe).toHaveCSS('height', '777px');
    await page.evaluate(channel => window.postMessage({ type: 'resize', channel, height: 3999 }, '*'), channel);
    await frame.evaluate(channel => {
        parent.postMessage({ type: 'resize', channel: 'wrong', height: 3998 }, '*');
        parent.postMessage({ type: 'resize', channel, height: 3997, html: '<script>bad()</script>' }, '*');
        parent.postMessage({ type: 'resize', channel, height: 4001 }, '*');
    }, channel);
    await page.waitForTimeout(100);
    await expect(iframe).toHaveCSS('height', '777px');
    await page.getByRole('button', { name: 'Reset preview' }).click();
    await expect(page.locator('[data-preview-target="status"]')).toHaveText('Preview reset');
});
