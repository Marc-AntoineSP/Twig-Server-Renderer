import { test, expect } from '@playwright/test';
import { login, createAlert } from './helpers.js';
test('publishing validates an alert and exposes its immutable version', async ({ page }) => {
    await login(page);
    const { item } = await createAlert(page);
    await page.goto(`/catalogue/${item.id}`);
    await page.getByLabel('Release version', { exact: true }).fill('1.0.0');
    await page.getByRole('button', { name: 'Publish release', exact: true }).click();
    await expect(page.locator('[data-flash]')).toHaveText('Release published immutably.', { timeout: 20000 });
    await expect(page.getByText(`styleguide:require ${item.name}@1.0.0`, { exact: true })).toBeVisible();
});
