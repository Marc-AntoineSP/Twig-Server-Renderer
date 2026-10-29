import { test, expect } from '@playwright/test';
import { login, createAlert } from './helpers.js';
test('publishing validates an alert and exposes its immutable version', async ({ page }) => {
    await login(page);
    const { item } = await createAlert(page);
    await page.goto(`/catalogue/${item.id}`);await page.locator("#releases > summary").click();
    await page.getByLabel('Release version', { exact: true }).fill('1.0.0');
    await page.getByRole('button', { name: 'Publish release', exact: true }).click();
    await expect(page.locator('[data-flash]')).toHaveText('Release published immutably.', { timeout: 20000 });
    await page.locator('#releases > summary').click();
    await expect(page.getByLabel('Installation command for 1.0.0',{exact:true})).toHaveValue(`php bin/console styleguide:require ${item.name}@1.0.0 --registry=local`);await page.getByRole('button',{name:'Copy installation command 1.0.0',exact:true}).click();await expect(page.locator('[data-clipboard-target="status"]')).toContainText(/copied|selected/);
    await page.goto(`/catalogue?q=${encodeURIComponent(item.name)}`);
    await page.getByRole('button',{name:'Show published preview',exact:true}).click();
    const thumbnail=page.frameLocator(`iframe[title="Published preview ${item.name}"]`);await expect(thumbnail.locator('[role="status"]')).toContainText('Your component is ready.');await expect(page.locator(`iframe[title="Published preview ${item.name}"]`)).toHaveAttribute('sandbox','');
});
