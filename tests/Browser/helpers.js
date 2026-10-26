import { readFileSync } from 'node:fs';

export async function login(page, role = 'editor') {
    const account = JSON.parse(readFileSync('var/browser/accounts.json', 'utf8'))[role];
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill(account.email);
    await page.getByLabel('Password', { exact: true }).fill(account.password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await page.waitForURL('/');
    return account;
}
