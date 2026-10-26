import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

test('private studio requires a real login and signs out', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByRole('heading', { name: 'Sign in to your studio' })).toBeVisible();
    const account = await login(page, 'reader');
    await expect(page.locator('nav')).toContainText(account.email);
    await page.getByRole('button', { name: 'Sign out' }).click();
    await expect(page).toHaveURL(/\/login$/);
    await page.goto('/');
    await expect(page).toHaveURL(/\/login$/);
});
