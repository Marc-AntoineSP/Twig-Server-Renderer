import { test, expect } from '@playwright/test';

test('Twig page has compiled styles and a working Stimulus action', async ({ page }) => {
    const failures = [];
    page.on('pageerror', error => failures.push(error.message));
    await page.goto('/');
    await expect(page.getByRole('heading', { name: 'Build a language for your interface.' })).toBeVisible();
    const button = page.getByRole('button', { name: 'About this workshop' });
    const help = page.locator('#studio-help');
    await expect(help).toBeHidden();
    await button.click();
    await expect(help).toBeVisible();
    await expect(button).toHaveAttribute('aria-expanded', 'true');
    // Tailwind utility and daisyUI component rules must both reach the browser.
    await expect(page.locator('h1')).toHaveCSS('font-weight', '700');
    await expect(button).toHaveCSS('cursor', 'pointer');
    await expect(button).not.toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
    expect(failures).toEqual([]);
});
