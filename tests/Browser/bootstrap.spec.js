import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

test('workspace has compiled styles, working appearance and a resumable draft', async ({ page }) => {
    const failures = [];
    page.on('pageerror', error => failures.push(error.message));
    await login(page);
    await expect(page.getByRole('heading', { name: 'Your workspace' })).toBeVisible();
    const button = page.getByRole('button', { name: 'Restore default appearance', exact: true });
    await button.click();
    await expect(page.locator('[data-appearance-target="status"]')).toHaveText('Applied Studio default.');
    // Tailwind utility and daisyUI component rules must both reach the browser.
    await expect(page.locator('h1')).toHaveCSS('font-weight', '700');
    const create = page.getByRole('link', {name: 'Create item', exact: true});
    await expect(create).toHaveCSS('cursor', 'pointer');
    await expect(create).not.toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
    await create.focus();
    await page.keyboard.press('Enter');
    const name = `browser/workspace-${Date.now()}`;
    await page.getByLabel('Name (namespace/slug)').fill(name);
    await page.getByLabel('Title', {exact: true}).fill('Resume from workspace');
    await page.getByRole('button', {name: 'Create draft', exact: true}).click();
    await expect(page).toHaveURL(/\/edit$/);
    const editUrl = page.url();
    await page.goto('/');
    await page.getByRole('link', {name: 'Resume '+name, exact: true}).focus();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(editUrl);
    await page.getByLabel('Title', {exact: true}).fill('Resumed and saved');
    await page.getByRole('button', {name: 'Save draft', exact: true}).click();
    await expect(page.locator('[data-flash]')).toContainText('Draft saved.');
    await page.goto('/');
    await expect(page.locator('#recent-items article').first()).toContainText('Resumed and saved');
    await expect(page.locator('#recent-items article').first()).toContainText('Draft r2');
    expect(failures).toEqual([]);
});
