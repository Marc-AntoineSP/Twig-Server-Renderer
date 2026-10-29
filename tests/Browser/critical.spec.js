import {test, expect} from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import {readFileSync, writeFileSync} from 'node:fs';
import {login} from './helpers.js';

const fixtures = () => JSON.parse(readFileSync(process.env.STUDIO_TEST_FIXTURES_FILE, 'utf8'));

async function audit(page, testInfo, exclude = []) {
    await expect(page.locator('html')).not.toHaveAttribute('aria-busy', 'true');
    let builder = new AxeBuilder({page}).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']);
    // Imported previews and the theme demonstration have their own diagnostic scope.
    for (const selector of ['iframe', '#component-showcase', ...exclude]) builder = builder.exclude(selector);
    const results = await builder.analyze();
    const report = testInfo.outputPath('application-accessibility.json');
    writeFileSync(report, JSON.stringify({url: new URL(page.url()).pathname,
        scope: 'application-owned controls; preview frames and theme demonstration excluded',
        violations: results.violations, incomplete: results.incomplete}, null, 2));
    await testInfo.attach('application-accessibility.json', {path: report, contentType: 'application/json'});
    expect(results.violations.map(v => ({id: v.id, impact: v.impact, nodes: v.nodes.map(n => n.target)}))).toEqual([]);
}

test.describe.configure({timeout: 90000});
test.use({reducedMotion: 'reduce'});

test('critical: keyboard navigation, appearance disclosure, personal favorites and reader boundary', async ({page, browser}, testInfo) => {
    await login(page);
    await page.getByRole('button', {name: 'Restore default appearance', exact: true}).click();
    await expect(page.locator('[data-appearance-target="status"]')).toHaveText('Applied Studio default.');
    await page.getByRole('link', {name: 'Catalogue', exact: true}).focus();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/catalogue$/);
    await page.getByRole('searchbox').fill('browser/seed-');
    await page.getByLabel('Favorites only', {exact: true}).check();
    await page.getByRole('button', {name: 'Find items', exact: true}).click();
    await expect(page.locator('article.card')).toHaveCount(1);
    await expect(page.locator('article.card')).toContainText('Seed Component');
    await audit(page, testInfo);
    const reader = await browser.newContext();
    try {
        const other = await reader.newPage();
        await login(other, 'reader');
        await other.goto('/catalogue?favorites=1&q=browser/seed-');
        await expect(other.locator('article.card')).toHaveCount(0);
        // Shared catalogue visibility stays shared; favorite status and writes stay personal/authorized.
        await other.goto('/catalogue/'+fixtures().component.id);
        await expect(other.locator('[data-controller~="favorite"] button')).toHaveAttribute('aria-pressed', 'false');
        expect((await other.request.post('/api/v1/items', {data: {name: 'browser/reader-denied', kind: 'component', title: 'Denied'}})).status()).toBe(403);
    } finally { await reader.close(); }
    await page.locator('summary[aria-controls="account-options"]').focus();
    await page.keyboard.press('Enter');
    await page.getByRole('link', {name: 'Profile', exact: true}).focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('heading', {level: 1})).toContainText('My profile');
});

test('critical: immutable reference selection, JSON property editing and unsaved navigation', async ({page}, testInfo) => {
    await login(page);
    const csrf = (await (await page.request.get('/api/v1/csrf')).json()).token;
    const created = await page.request.post('/api/v1/items', {headers: {'X-CSRF-Token': csrf}, data: {
        name: `browser/critical-${testInfo.project.name}-${Date.now()}`, kind: 'layout', title: 'Critical composer'}});
    expect(created.status()).toBe(201);
    const item = await created.json();
    await page.goto('/catalogue');
    await page.evaluate(async url => {const {visit} = await import('@hotwired/turbo'); visit(url);}, `/catalogue/${item.id}/compose`);
    const picker = page.getByLabel('Component, block or layout to insert', {exact: true});
    const option = picker.locator('option').filter({hasText: 'browser/seed-component@1.0.0'});
    await expect(option).toHaveCount(1);
    await picker.selectOption(await option.getAttribute('value'));
    await page.getByRole('button', {name: 'Add reference', exact: true}).focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('treeitem', {name: 'browser/seed-component@1.0.0', exact: true})).toBeVisible();
    const props = page.getByLabel('Instance props (JSON)', {exact: true});
    const value = JSON.parse(await props.inputValue());
    // The existing source factory's bounded schema controls which properties are editable.
    await props.fill(JSON.stringify({...value, message: 'Keyboard edited property'}));
    await expect(page.locator('[data-composition-target="saveStatus"]')).toHaveText('Unsaved changes');
    await audit(page, testInfo);
    await page.getByRole('link', {name: 'Catalogue', exact: true}).click();
    await expect(page).toHaveURL(new RegExp(`/catalogue/${item.id}/compose`));
    await expect(page.getByRole('dialog', {name: 'Unsaved work', exact: true})).toBeVisible();
    await page.getByRole('button', {name: 'Keep editing', exact: true}).press('Enter');
    expect(JSON.parse(await props.inputValue()).message).toBe('Keyboard edited property');
    const editorUrl = page.url(), historyLength = await page.evaluate(() => history.length);
    await page.evaluate(() => history.back());
    await expect(page.getByRole('dialog', {name: 'Unsaved work', exact: true})).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog', {name: 'Unsaved work', exact: true})).toHaveCount(0);
    expect(page.url()).toBe(editorUrl); expect(await page.evaluate(() => history.length)).toBe(historyLength);
    expect(JSON.parse(await props.inputValue()).message).toBe('Keyboard edited property');
    await page.getByRole('button', {name: 'Save composition', exact: true}).click();
    await expect(page.locator('[data-composition-target="saveStatus"]')).toHaveText('Saved revision 2');
    await page.reload();
    await page.getByRole('treeitem', {name: 'browser/seed-component@1.0.0', exact: true}).click();
    expect(JSON.parse(await props.inputValue()).message).toBe('Keyboard edited property');
});

test('critical: theme detail, disclosure and showcase keyboard dialog', async ({page}, testInfo) => {
    await login(page);
    await page.setViewportSize({width: 390, height: 844});
    await page.goto('/themes/'+fixtures()['theme-light'].id);
    await expect(page.getByRole('heading', {name: 'Component showcase', exact: true})).toBeVisible();
    const details = page.locator('[data-theme-sidebar] > details');
    await expect(details).not.toHaveAttribute('open', '');
    await details.locator(':scope > summary').focus();
    await page.keyboard.press('Enter');
    await expect(details).toHaveAttribute('open', '');
    await expect(details.getByRole('link', {name: 'Edit theme', exact: true})).toBeVisible();
    await audit(page, testInfo);
    const opener = page.locator('[data-component="modals"]').getByRole('button', {name: 'Open sample dialog', exact: true});
    await opener.focus(); await page.keyboard.press('Enter');
    const dialog = page.getByRole('dialog', {name: 'Review your sample', exact: true});
    await expect(dialog).toBeVisible();
    const recovery = dialog.getByRole('button', {name: 'Restore default appearance', exact: true});
    await expect(recovery).toBeVisible(); await recovery.focus(); await expect(recovery).toBeFocused();
    await recovery.press('Tab');
    await expect(dialog.getByRole('button', {name: 'Cancel dialog', exact: true})).toBeFocused();
    // Freeze the real opening fade: transparent controls remain in native keyboard order.
    const box = dialog.locator('.modal-box');
    await box.evaluate(el => {el.style.setProperty('transition', 'none', 'important'); el.style.setProperty('opacity', '0', 'important');});
    try {
        await recovery.press('Tab');
        await expect(dialog.getByRole('button', {name: 'Cancel dialog', exact: true})).toBeFocused();
    } finally {await box.evaluate(el => {el.style.removeProperty('opacity'); el.style.removeProperty('transition');});}
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden(); await expect(opener).toBeFocused();
});
