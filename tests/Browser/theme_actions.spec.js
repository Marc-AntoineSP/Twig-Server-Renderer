import {test, expect} from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import {readFileSync, writeFileSync} from 'node:fs';
import {randomBytes} from 'node:crypto';
import {login} from './helpers.js';

test.use({reducedMotion: 'reduce'});
test.describe.configure({timeout: 90000});
const previewLabel = 'Preview published theme in component editor';
const appearance = async page => (await page.request.get('/api/v1/appearance')).json();
async function api(page, method, url, data) {
    const token = (await (await page.request.get('/api/v1/csrf')).json()).token;
    const response = await page.request.fetch(url, {method, data, headers: {'X-CSRF-Token': token}});
    expect(response.ok(), (await response.text()).slice(0, 1200)).toBe(true);
    return response.json();
}
async function details(page) {
    const disclosure = page.locator('[data-theme-sidebar] > details');
    if (!await disclosure.evaluate(el => el.open)) {
        await disclosure.locator(':scope > summary').focus(); await page.keyboard.press('Enter');
    }
    return page.locator('[data-theme-sidebar] [data-theme-actions]');
}

for (const width of [320, 390, 1440]) for (const mode of ['light', 'dark']) {
    test(`theme actions: draft browsing and clear labels ${width}px ${mode}`, async ({page}, info) => {
        await page.setViewportSize({width, height: 1000}); await page.emulateMedia({colorScheme: mode});
        await login(page, 'visual');
        await page.getByRole('button', {name: 'Restore default appearance', exact: true}).click();
        await expect(page.locator('[data-appearance-target="status"]')).toHaveText('Applied Studio default.');
        const fixture = JSON.parse(readFileSync(process.env.STUDIO_TEST_FIXTURES_FILE, 'utf8'))['theme-'+mode];
        const before = await appearance(page); let mutations = 0;
        const observe = request => {if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method())) mutations++;};
        page.on('request', observe);
        await page.goto('/themes?q=browser%2Fseed-theme&mode='+mode);
        const card = page.locator(`article[data-theme-id="${fixture.id}"]`);
        await expect(card).toContainText('Draft revision 1');
        await expect(card).toContainText('Published preview:');
        await expect(card.getByRole('button', {name: previewLabel, exact: true})).toBeEnabled();
        await expect(card.getByRole('button', {name: 'Apply to application', exact: true})).toBeVisible();
        const browse = card.getByRole('link', {name: 'Browse component showcase', exact: true});
        await expect(browse).toHaveAttribute('href', new RegExp('/themes/'+fixture.id+'\\?.*#component-showcase$'));
        await browse.focus(); await page.keyboard.press('Enter');
        await expect(page).toHaveURL(new RegExp('/themes/'+fixture.id+'\\?.*#component-showcase$'));
        await expect(page.locator('#component-showcase')).toHaveAttribute('data-color-mode', mode);
        await expect(page.locator('[data-showcase-revision]')).toContainText('Draft revision 1');
        const actions = await details(page);
        await expect(actions.getByRole('button', {name: previewLabel, exact: true})).toBeVisible();
        await expect(actions).toContainText('Application appearance is unchanged.');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        expect(await appearance(page)).toEqual(before); expect(mutations).toBe(0);
        page.off('request', observe);
        const report = await new AxeBuilder({page}).exclude('#component-showcase').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
        writeFileSync(info.outputPath('theme-actions-accessibility.json'), JSON.stringify({violations: report.violations, incomplete: report.incomplete}, null, 2));
        expect(report.violations.map(v => ({id: v.id, targets: v.nodes.map(n => n.target)}))).toEqual([]);
        await actions.getByRole('button', {name: previewLabel, exact: true}).focus();
        await page.screenshot({path: info.outputPath(`theme-actions-${width}-${mode}.png`)});
    });
}

test('theme actions: exact published release, draft-only variant, saved renderer and appearance are separate', async ({page, browser}) => {
    await login(page); await page.emulateMedia({colorScheme: 'light'});
    const document = JSON.parse(readFileSync('agent-kit/examples/packages/ember/meta/theme.json', 'utf8'));
    const slug = 'actions-'+randomBytes(5).toString('hex'); document.name = 'browser/'+slug; document.cssName = slug;
    document.colors.primary = '#123456'; document.default = false;
    const created = await api(page, 'POST', '/api/v1/themes', {document, variants: 'pair', familyLabel: slug});
    const first = await api(page, 'POST', `/api/v1/themes/${created.id}/releases`, {expectedRevision: 1});
    const draft = {...document, version: '2.0.0', colors: {...document.colors, primary: '#654321'}};
    await api(page, 'PUT', '/api/v1/themes/'+created.id, {document: draft, expectedRevision: 1});
    const originalAppearance = await appearance(page);
    await page.goto('/themes?q='+slug+'&mode=light');
    const card = page.locator(`article[data-theme-id="${created.id}"]`);
    await expect(card).toContainText('Draft revision 2'); await expect(card).toContainText(document.name+'@1.0.0');
    // Publish another version after rendering the form. The displayed release must stay pinned.
    await api(page, 'POST', `/api/v1/themes/${created.id}/releases`, {expectedRevision: 2});
    await card.getByRole('button', {name: previewLabel, exact: true}).focus(); await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/catalogue\/[0-9A-Z]{26}\/edit$/);
    await expect(page.locator('[data-flash]')).toContainText(document.name+'@1.0.0 (light)');
    await expect(page.getByLabel('Published theme', {exact: true})).toHaveValue(first.id);
    const frame = page.locator('iframe[title="Component preview"]');
    await expect(frame).toHaveAttribute('sandbox', 'allow-scripts');
    await expect(page.locator('[data-controller~="preview"]')).toHaveAttribute('data-preview-state', 'current', {timeout: 30000});
    await expect(page.frameLocator('iframe[title="Component preview"]').getByRole('button', {name: 'Primary', exact: true})).toHaveCSS('background-color', 'rgb(18, 52, 86)');
    const itemId = new URL(page.url()).pathname.split('/')[2];
    const savedRenderer = await (await page.request.get(`/api/v1/items/${itemId}/renderer-preset`)).json();
    expect(savedRenderer.themeReleaseId).toBe(first.id); expect(await appearance(page)).toEqual(originalAppearance);
    await page.reload(); await expect(page.getByLabel('Published theme', {exact: true})).toHaveValue(first.id);
    await page.goto('/themes/'+created.id); let actions = await details(page);
    await expect(actions).toContainText(document.name+'@2.0.0');
    await expect(page.locator('[data-showcase-revision]')).toContainText('Draft revision 2');
    await expect(page.locator('#component-showcase [data-component="buttons"]').getByRole('button', {name: 'Primary', exact: true})).toHaveCSS('background-color', 'rgb(101, 67, 33)');
    await page.getByRole('link', {name: 'Showcase dark', exact: true}).click();
    await expect(page.locator('#component-showcase')).toHaveAttribute('data-color-mode', 'dark');
    actions = await details(page); await expect(actions.getByRole('button', {name: previewLabel, exact: true})).toBeDisabled();
    await expect(actions).toContainText('No published release');
    await expect(actions.getByRole('link', {name: 'Edit theme', exact: true})).toBeVisible();
    const darkId = created.family.variants.dark.id;
    await actions.getByRole('link', {name: 'Browse component showcase', exact: true}).click();
    await expect(page).toHaveURL(new RegExp('/themes/'+darkId+'\\?.*#component-showcase$'));
    expect(await (await page.request.get(`/api/v1/items/${itemId}/renderer-preset`)).json()).toEqual(savedRenderer);
    await details(page); await page.getByRole('button', {name: 'Apply to application', exact: true}).click();
    await expect(page.locator('[data-appearance-target="status"]')).toHaveText('Applied '+slug+'.');
    expect((await appearance(page)).familyId).toBe(created.family.id);
    expect((await (await page.request.get(`/api/v1/items/${itemId}/renderer-preset`)).json()).themeReleaseId).toBe(first.id);
    const reader = await browser.newContext(); const other = await reader.newPage();
    try {
        await login(other, 'reader'); await other.goto('/themes/'+created.id); const readerActions = await details(other);
        await expect(readerActions.getByRole('button', {name: previewLabel, exact: true})).toHaveCount(0);
        await expect(readerActions.getByRole('link', {name: 'Browse component showcase', exact: true})).toBeVisible();
        expect((await other.request.post(`/themes/${created.id}/apply`, {form: {releaseId: first.id}})).status()).toBe(403);
        expect((await other.request.get(`/api/v1/items/${itemId}/renderer-preset`)).status()).toBe(403);
    } finally {await reader.close();}
    expect((await (await page.request.get(`/api/v1/items/${itemId}/renderer-preset`)).json()).themeReleaseId).toBe(first.id);
});
