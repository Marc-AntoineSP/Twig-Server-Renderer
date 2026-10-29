import {test, expect} from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import {readFileSync, writeFileSync} from 'node:fs';
import {login} from './helpers.js';

test.use({reducedMotion: 'reduce'});

for (const width of [390, 1440]) for (const mode of ['light', 'dark']) for (const role of ['reader', 'editor']) {
    test(`workspace: ${role} ${width}px ${mode}`, async ({page}, testInfo) => {
        const fixtures = JSON.parse(readFileSync(process.env.STUDIO_TEST_FIXTURES_FILE, 'utf8'));
        await page.setViewportSize({width, height: 1000});
        // This actor is an editor with a bounded, stable favorite set. The ordinary
        // editor's other regression tests deliberately create more than six favorites.
        await login(page, role === 'editor' ? 'visual' : 'reader');
        const csrf = (await (await page.request.get('/api/v1/csrf')).json()).token;
        const headers = {'X-CSRF-Token': csrf};
        const targets = [
            `/api/v1/favorites/catalogue/${fixtures.component.id}`,
            `/api/v1/favorites/theme/${fixtures['theme-dark'].id}`,
        ];
        const previous = [];
        try {
            for (const target of targets) {
                previous.push((await (await page.request.get(target)).json()).favorite);
                expect((await page.request.put(target, {headers})).status()).toBe(200);
            }
            await page.getByRole('button', {name: 'Restore default appearance', exact: true}).click();
            await expect(page.locator('[data-appearance-target="status"]')).toHaveText('Applied Studio default.');
            if (mode === 'dark') await page.getByRole('button', {name: 'Switch to dark mode', exact: true}).click();
            await expect(page.locator('html')).toHaveAttribute('data-color-mode', mode);
            await page.goto('/');
            const main = page.locator('main');
            await expect(main.getByRole('heading', {name: 'Recently updated', exact: true})).toBeVisible();
            const recent = page.locator('#recent-items article');
            expect(await recent.count()).toBeGreaterThan(0);
            expect(await recent.count()).toBeLessThanOrEqual(6);
            const favorites = page.locator('#personal-favorites');
            await expect(favorites.locator(`[data-workspace-id="${fixtures.component.id}"]`)).toBeVisible();
            const theme = favorites.locator(`[data-workspace-id="${fixtures['theme-dark'].id}"]`);
            await expect(theme).toContainText('Dark');
            await expect(theme).toContainText('Released v1.0.0');
            await expect(theme.locator('h3 a')).toHaveAttribute('href', `/themes/${fixtures['theme-dark'].id}`);
            if (role === 'reader') {
                await expect(main.getByRole('link', {name: /^Resume /})).toHaveCount(0);
                await expect(main.locator('a[href="/catalogue/new"], a[href="/themes/new"], a[href="/imports"]')).toHaveCount(0);
            } else {
                await expect(main.getByRole('link', {name: 'Create item', exact: true})).toBeVisible();
                await expect(main.getByRole('link', {name: 'Import package', exact: true})).toHaveAttribute('href', '/imports');
            }
            await expect(page.locator('h1')).toHaveCSS('font-weight', '700');
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
            const audit = await new AxeBuilder({page}).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
            writeFileSync(testInfo.outputPath('workspace-accessibility.json'), JSON.stringify({violations: audit.violations, incomplete: audit.incomplete}, null, 2));
            expect(audit.violations.map(v => ({id: v.id, targets: v.nodes.map(n => n.target)}))).toEqual([]);
            await page.screenshot({path: testInfo.outputPath('workspace.png'), fullPage: true, animations: 'disabled'});
            const shortcut = favorites.locator(`[data-workspace-id="${fixtures.component.id}"] .card-actions a`);
            await shortcut.focus();
            await expect(shortcut).toBeFocused();
            expect(await shortcut.evaluate(el => getComputedStyle(el).outlineStyle)).not.toBe('none');
            await page.keyboard.press('Enter');
            await expect(page).toHaveURL(url => url.pathname === `/catalogue/${fixtures.component.id}${role === 'editor' ? '/edit' : ''}`
                && (role === 'editor' ? url.search === '' : /^[0-9A-Z]{26}$/.test(url.searchParams.get('release') || '') && url.searchParams.get('example') === '-1'));
        } finally {
            for (let index = 0; index < previous.length; index++) {
                if (!previous[index]) expect((await page.request.delete(targets[index], {headers})).status()).toBe(200);
            }
        }
    });
}
