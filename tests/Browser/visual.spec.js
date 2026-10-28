import {test, expect} from '@playwright/test';
import {readFileSync} from 'node:fs';
import {login} from './helpers.js';
test.use({reducedMotion: 'reduce'});

// Linux + the pinned Playwright image own these baselines. No timestamps, masks or random fixtures.
for (const width of [390, 1440]) for (const mode of ['light', 'dark']) {
    test(`visual: listing, composer and theme detail ${width}px ${mode}`, async ({page}) => {
        test.setTimeout(90000);
        const fixtures = JSON.parse(readFileSync(process.env.STUDIO_TEST_FIXTURES_FILE, 'utf8'));
        await page.setViewportSize({width, height: 1000});
        await login(page, 'visual');
        await page.getByRole('button', {name: 'Restore default appearance', exact: true}).click();
        await expect(page.locator('[data-appearance-target="status"]')).toHaveText('Applied Studio default.');
        if (mode === 'dark') await page.getByRole('button', {name: 'Switch to dark mode', exact: true}).click();
        await expect(page.locator('html')).toHaveAttribute('data-color-mode', mode);
        for (const [name, path] of [
            ['listing', '/catalogue?q=browser%2Fseed-&kind=component'],
            ['composer', `/catalogue/${fixtures.workspace.id}/compose?favorites=1`],
            ['theme-detail', '/themes/'+fixtures['theme-'+mode].id],
        ]) {
            await page.goto(path);
            if (name === 'composer') {
                await expect(page.locator('[data-composition-target="status"]')).toHaveText('Composition preview current', {timeout: 30000});
                await expect(page.frameLocator('iframe[title="Composition preview"]').locator('body')).toBeVisible();
            }
            if (name === 'theme-detail') await expect(page.locator('#component-showcase')).toBeVisible();
            await page.evaluate(async () => {
                await document.fonts.ready;
                await Promise.all([...document.querySelectorAll('link[rel="stylesheet"]')].map(link => {
                    if (!link.sheet) throw new Error('Required stylesheet has not loaded.');
                }));
            });
            await page.mouse.move(0, 0);
            if (process.env.STUDIO_VISUAL_PROBE === '1') {
                await page.addStyleTag({content: 'main h1 { font-size: 10px !important; }'});
            }
            await expect(page).toHaveScreenshot(`${name}-${width}-${mode}.png`, {animations: 'disabled', caret: 'hide'});
        }
    });
}
