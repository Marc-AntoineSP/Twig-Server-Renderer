import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

test('editor creates, edits, reloads and searches a draft while sources stay inert', async ({ page }) => {
    await login(page);
    await page.getByRole('link', { name: 'Open catalogue', exact: true }).click();
    await page.getByRole('link', { name: 'New item' }).click();
    const name = `browser/component-${Date.now()}`;
    await page.getByLabel('Name (namespace/slug)').fill(name);
    await page.getByLabel('Title', { exact: true }).fill('Browser created component');
    await page.getByLabel('Tags', { exact: true }).fill('browser, acceptance');
    await page.getByRole('button', { name: 'Create draft' }).click();
    await expect(page).toHaveURL(/\/catalogue\/[^/]+\/edit$/);
    const sources = JSON.parse(await page.getByLabel('Source files JSON').inputValue());
    const path = `templates/catalogue/${name}/component.html.twig`;
    sources[path] = '<div>{{ app.secret }}</div><script>window.studioExecuted = true;</script>';
    await page.getByLabel('Source files JSON').fill(JSON.stringify(sources, null, 2));
    await page.getByLabel('Title', { exact: true }).fill('Browser saved component');
    await page.getByRole('button', { name: 'Save draft' }).click();
    await expect(page.getByRole('status')).toContainText('Draft saved.');
    await page.reload();
    await expect(page.getByLabel('Title', { exact: true })).toHaveValue('Browser saved component');
    expect(JSON.parse(await page.getByLabel('Source files JSON').inputValue())[path]).toBe(sources[path]);
    await page.getByRole('link', { name: 'Catalogue', exact: true }).click();
    await page.getByRole('searchbox').fill(name);
    await page.getByRole('button', { name: 'Find items' }).click();
    await page.getByRole('link', { name: 'Browser saved component', exact: true }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Browser saved component');
    expect(await page.evaluate(() => window.studioExecuted)).toBeUndefined();
});

test('reader can browse and cannot reach editing', async ({ page }) => {
    await login(page, 'reader');
    await page.goto('/catalogue');
    await expect(page.getByRole('heading', { name: 'Catalogue', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'New item' })).toHaveCount(0);
    const response = await page.goto('/catalogue/new');
    expect(response.status()).toBe(403);
});

test('a stale second tab keeps its unsaved text and cannot overwrite the first', async ({ page, context }) => {
    await login(page);
    await page.goto('/catalogue/new');
    await page.getByLabel('Name (namespace/slug)').fill(`browser/race-${Date.now()}`);
    await page.getByLabel('Title', { exact: true }).fill('Before race');
    await page.getByRole('button', { name: 'Create draft' }).click();
    await expect(page).toHaveURL(/\/edit$/);
    const url = page.url();
    const second = await context.newPage();
    await second.goto(url);
    await page.getByLabel('Title', { exact: true }).fill('First tab saved');
    await page.getByRole('button', { name: 'Save draft' }).click();
    await expect(page.getByRole('status')).toContainText('Draft saved.');
    await second.getByLabel('Title', { exact: true }).fill('Second tab unsaved');
    const response = second.waitForResponse(r => r.request().method() === 'POST' && r.url() === url);
    await second.getByRole('button', { name: 'Save draft' }).click();
    expect((await response).status()).toBe(412);
    await expect(second.getByLabel('Title', { exact: true })).toHaveValue('Second tab unsaved');
    await expect(second.getByRole('alert')).toContainText('Your changes were not saved');
    await page.reload();
    await expect(page.getByLabel('Title', { exact: true })).toHaveValue('First tab saved');
});
