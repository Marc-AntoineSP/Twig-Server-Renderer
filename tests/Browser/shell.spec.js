import {test, expect} from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import {readFileSync, writeFileSync} from 'node:fs';
import {login} from './helpers.js';

test.use({reducedMotion: 'reduce'});
const summary = (page, id) => page.locator(`summary[aria-controls="${id}"]`);
const state = async page => (await (await page.request.get('/api/v1/appearance')).json());
async function audit(page, info) {
    const report = await new AxeBuilder({page}).include('header').include('#appearance-recovery').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
    writeFileSync(info.outputPath('shell-accessibility.json'), JSON.stringify({violations: report.violations, incomplete: report.incomplete}, null, 2));
    expect(report.violations.map(v => ({id: v.id, targets: v.nodes.map(n => n.target)}))).toEqual([]);
}
async function noOverflow(page) {expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);}

for (const width of [320, 390, 1440]) for (const mode of ['light', 'dark']) {
    test(`shell: disclosure navigation, account and recovery ${width}px ${mode}`, async ({page}, info) => {
        await page.setViewportSize({width, height: 900}); await login(page, 'visual');
        await page.getByRole('button', {name: 'Restore default appearance', exact: true}).click();
        await expect(page.locator('[data-appearance-target="status"]')).toHaveText('Applied Studio default.');
        if (mode === 'dark') await page.getByRole('button', {name: 'Switch to dark mode', exact: true}).click();
        await expect(page.locator('html')).toHaveAttribute('data-color-mode', mode);
        await page.goto('/catalogue?q=browser%2Fseed-');
        expect((await page.locator('.shell-bar').boundingBox()).height).toBeLessThanOrEqual(64);
        expect((await page.locator('header').boundingBox()).height).toBeLessThanOrEqual(120);
        expect((await page.locator('#appearance-recovery').boundingBox()).height).toBeLessThanOrEqual(60);
        await noOverflow(page);
        await expect(page.locator('header a[href="/catalogue"][aria-current="page"]').first()).toHaveAttribute('aria-current', 'page');
        if (width < 1024) {
            const menu = summary(page, 'mobile-navigation');
            await menu.focus(); await page.keyboard.press('Enter'); await expect(menu).toHaveAttribute('aria-expanded', 'true');
            await page.keyboard.press('Tab'); await expect(page.getByRole('link', {name: 'Workspace', exact: true})).toBeFocused();
            await page.keyboard.press('Escape'); await expect(menu).toBeFocused(); await expect(menu).toHaveAttribute('aria-expanded', 'false');
            await menu.click(); await page.getByRole('searchbox').click(); await expect(menu).toHaveAttribute('aria-expanded', 'false');
            await menu.click();
        }
        const navLink = page.getByRole('link', {name: 'Themes', exact: true});
        await navLink.focus(); await page.keyboard.press('Enter'); await expect(page).toHaveURL(/\/themes$/);
        await expect(page.locator('main h1')).toHaveText('Theme laboratory');
        await expect(page.locator('header a[href="/themes"][aria-current="page"]').first()).toHaveAttribute('aria-current', 'page');
        await summary(page, 'account-options').focus(); await page.keyboard.press('Enter');
        await expect(summary(page, 'account-options')).toHaveAttribute('aria-expanded', 'true');
        await page.evaluate(() => {
            document.querySelector('.shell-account-email').textContent = 'a'.repeat(150)+'@example.test';
            document.querySelector('.shell-context').textContent = 'A very long translated page context '.repeat(10);
            document.querySelectorAll('.shell-control-label').forEach(e => e.textContent = 'A much longer translated label '.repeat(6));
        });
        await noOverflow(page); await audit(page, info);
        await page.screenshot({path: info.outputPath('shell-account.png'), animations: 'disabled'});
        await page.keyboard.press('Escape'); await expect(summary(page, 'account-options')).toBeFocused();
        await summary(page, 'appearance-options').click();
        await page.getByRole('button', {name: 'Appearance details', exact: true}).click();
        const dialog = page.locator('#appearance-details-dialog'); await expect(dialog).toBeVisible();
        const recovery = dialog.getByRole('button', {name: 'Restore default appearance', exact: true});
        await expect(recovery).toBeVisible(); await recovery.focus(); await expect(recovery).toBeFocused();
        await expect(dialog.getByRole('button', {name: 'Close appearance details', exact: true})).toBeVisible();
        await page.keyboard.press('Escape'); await expect(dialog).toBeHidden();
        await expect(summary(page, 'appearance-options')).toBeFocused();
        await page.goBack(); await expect(page).toHaveURL(/\/catalogue/);
        await expect(page.locator('main h1')).toHaveText('Catalogue');
        await page.goForward(); await expect(page.locator('main h1')).toHaveText('Theme laboratory');
        await page.goBack(); await expect(page.locator('main h1')).toHaveText('Catalogue');
        expect(await page.locator('[data-shell-disclosure][open]').count()).toBe(0);
        await page.evaluate(() => document.fonts.ready);
        await page.screenshot({path: info.outputPath('shell.png'), animations: 'disabled'});
    });
}

test('shell: unreadable saved theme, trial, failure and native modal recovery remain independent', async ({page}, info) => {
    test.setTimeout(90000); await page.setViewportSize({width: 390, height: 900}); await login(page);
    await page.emulateMedia({colorScheme: 'light'});
    await page.getByRole('button', {name: 'Restore default appearance', exact: true}).click();
    await expect(page.locator('[data-appearance-target="status"]')).toHaveText('Applied Studio default.');
    await expect(page.locator('html')).toHaveAttribute('data-color-mode', 'light');
    const headers = {'X-CSRF-Token': (await (await page.request.get('/api/v1/csrf')).json()).token};
    const document = JSON.parse(readFileSync('agent-kit/examples/packages/ember/meta/theme.json', 'utf8'));
    const name = `browser/recovery-${Date.now()}`; document.name = name; document.cssName = name.replace('/', '-');
    for (const key of Object.keys(document.colors)) document.colors[key] = '#FFFFFF';
    const created = await page.request.post('/api/v1/themes', {headers, data: {document, variants: 'pair', familyLabel: 'Unreadable test family'}});
    expect(created.status()).toBe(201); const theme = await created.json();
    await page.goto(`/themes/${theme.id}/edit`);
    const savedBefore = await state(page);
    await page.getByRole('button', {name: 'Try on application', exact: true}).click();
    await expect(page.locator('html')).toHaveAttribute('data-appearance-trial', 'true');
    const strip = page.locator('#appearance-recovery');
    await expect(strip).toHaveAttribute('data-recovery-state', 'trial');
    await expect(strip).toContainText('without saving editor changes');
    await expect(strip.getByRole('button', {name: 'Revert trial', exact: true})).toHaveCSS('background-color', 'rgb(255, 255, 255)');
    await expect(strip).toHaveCSS('color', 'rgb(23, 33, 43)');
    await page.getByLabel('primary', {exact: true}).fill('#bad');
    await expect(strip).toHaveAttribute('data-recovery-state', 'error');
    await strip.getByRole('button', {name: 'Revert trial', exact: true}).click();
    expect(await state(page)).toEqual(savedBefore);
    await expect(page.getByLabel('primary', {exact: true})).toHaveValue('#bad');
    await page.getByLabel('primary', {exact: true}).fill('#FFFFFF');
    await page.getByRole('button', {name: 'Save & Apply', exact: true}).click();
    await expect(page.locator('[data-theme-editor-target="saveStatus"]')).toHaveText('Theme saved and applied to your application.');
    await expect(page.locator('html')).toHaveAttribute('data-appearance-family', theme.family.id);
    await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(255, 255, 255)');
    await expect(page.locator('body')).toHaveCSS('color', 'rgb(255, 255, 255)');
    await summary(page, 'appearance-options').click();
    await page.getByRole('button', {name: 'Appearance details', exact: true}).click();
    const dialog = page.locator('#appearance-details-dialog');
    const reset = dialog.getByRole('button', {name: 'Restore default appearance', exact: true});
    await reset.focus(); await expect(reset).toBeFocused();
    await expect(reset).toHaveCSS('color', 'rgb(23, 33, 43)');
    await expect.poll(async () => (await reset.boundingBox()).height).toBeGreaterThanOrEqual(44);
    await page.screenshot({path: info.outputPath('unreadable-modal.png'), animations: 'disabled'});
    await page.route('**/api/v1/appearance/prepare', route => route.fulfill({status: 503, contentType: 'application/json', body: '{"message":"Recovery service unavailable"}'}));
    await reset.click(); await expect(dialog.locator('[data-appearance-recovery-message]')).toContainText('Recovery service unavailable');
    expect((await state(page)).familyId).toBe(theme.family.id);
    await page.unroute('**/api/v1/appearance/prepare');
    let prepares = 0; page.on('request', r => {if (r.url().endsWith('/api/v1/appearance/prepare')) prepares++;});
    await reset.click(); await expect(dialog.locator('[data-appearance-recovery-message]')).toHaveText('Applied Studio default.');
    expect(prepares).toBe(1); expect((await state(page)).familyName).toBe('Studio default');
    const savedTheme = await (await page.request.get(`/api/v1/themes/${theme.id}`)).json();
    expect(Object.values(savedTheme.document.colors).every(color => color.toLowerCase() === '#ffffff')).toBe(true);
    await expect(dialog.getByRole('button', {name: 'Close appearance details', exact: true})).toBeVisible();
    await page.keyboard.press('Escape'); await expect(summary(page, 'appearance-options')).toBeFocused();
});

test('shell: native navigation, account, logout and default recovery work without JavaScript', async ({browser}) => {
    const context = await browser.newContext({javaScriptEnabled: false, viewport: {width: 320, height: 900}});
    try {
        const page = await context.newPage(); await login(page, 'reader');
        await summary(page, 'mobile-navigation').click(); await page.getByRole('link', {name: 'Themes', exact: true}).click();
        await expect(page).toHaveURL(/\/themes$/); await noOverflow(page);
        await page.getByRole('button', {name: 'Restore default appearance', exact: true}).click();
        await expect(page).toHaveURL('/'); expect((await state(page)).familyName).toBe('Studio default');
        await summary(page, 'account-options').click(); await page.getByRole('link', {name: 'Profile', exact: true}).click();
        await expect(page).toHaveURL('/profile'); await summary(page, 'account-options').click();
        await page.getByRole('button', {name: 'Sign out', exact: true}).click(); await expect(page).toHaveURL('/login');
    } finally {await context.close();}
});

test('shell: touch targets and native drawer recovery at 320px', async ({browser}, info) => {
    const context = await browser.newContext({hasTouch: true, isMobile: true, viewport: {width: 320, height: 900}});
    try {
        const page = await context.newPage(); await login(page, 'visual');
        const fixtures = JSON.parse(readFileSync(process.env.STUDIO_TEST_FIXTURES_FILE, 'utf8'));
        await summary(page, 'mobile-navigation').tap();
        await page.getByRole('link', {name: 'Themes', exact: true}).tap(); await expect(page).toHaveURL('/themes');
        await expect(summary(page, 'mobile-navigation')).toHaveAttribute('aria-expanded', 'false');
        for (const control of await page.locator('header summary:visible, header button:visible, #appearance-recovery button:visible').all()) {
            const box = await control.boundingBox(); expect(box.width).toBeGreaterThanOrEqual(44); expect(box.height).toBeGreaterThanOrEqual(44);
        }
        await page.goto('/themes/'+fixtures['theme-light'].id);
        const opener = page.getByRole('button', {name: 'Open sample drawer', exact: true}); await opener.tap();
        const drawer = page.locator('dialog.showcase-drawer');
        const reset = drawer.getByRole('button', {name: 'Restore default appearance', exact: true});
        await reset.focus(); await expect(reset).toBeFocused();
        expect(await reset.evaluate(el => {const r=el.getBoundingClientRect();return el.contains(document.elementFromPoint(r.x+r.width/2, r.y+r.height/2));})).toBe(true);
        await page.keyboard.press('Tab'); await expect(drawer.getByRole('button', {name: 'Close sample drawer', exact: true})).toBeFocused();
        await page.screenshot({path: info.outputPath('touch-drawer.png'), animations: 'disabled'});
        await page.keyboard.press('Escape'); await expect(opener).toBeFocused(); await noOverflow(page);
    } finally {await context.close();}
});

test('shell: 200 percent text reflow and Chromium pinch zoom keep recovery reachable', async ({page}, info) => {
    await page.setViewportSize({width: 640, height: 900}); await login(page, 'visual');
    await page.evaluate(() => document.documentElement.style.fontSize = '200%');
    await summary(page, 'mobile-navigation').click(); await noOverflow(page);
    await page.keyboard.press('Escape');
    const session = await page.context().newCDPSession(page);
    try {
        await session.send('Emulation.setPageScaleFactor', {pageScaleFactor: 2});
        expect(await page.evaluate(() => visualViewport.scale)).toBe(2);
        await page.getByRole('link', {name: 'Skip to appearance recovery', exact: true}).focus();
        await page.keyboard.press('Enter');
        await expect(page.locator('#appearance-recovery')).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(page.locator('#appearance-recovery').getByRole('button', {name: 'Restore default appearance', exact: true})).toBeFocused();
        await page.screenshot({path: info.outputPath('zoom-recovery.png'), animations: 'disabled'});
    } finally {await session.send('Emulation.setPageScaleFactor', {pageScaleFactor: 1}); await session.detach();}
});
