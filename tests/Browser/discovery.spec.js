import {test, expect} from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import {readFileSync, writeFileSync} from 'node:fs';
import {randomBytes} from 'node:crypto';
import {login} from './helpers.js';

test.describe.configure({timeout: 90000});
test.use({reducedMotion: 'reduce'});
const root = page => page.locator('[data-controller~="discovery"]');
const summary = page => root(page).locator('[data-discovery-target="summary"]');
const star = (page, id) => page.locator(`[data-favorite-id-value="${id}"] button`);
const undo = page => root(page).getByRole('button', {name: /^Undo removal/});
async function api(page, method, url, data) {
    const token = (await (await page.request.get('/api/v1/csrf')).json()).token;
    const response = await page.request.fetch(url, {method, data, headers: {'X-CSRF-Token': token}});
    expect(response.ok(), (await response.text()).slice(0, 1000)).toBe(true); return response.json();
}
async function item(page, prefix, kind = 'component') {
    return api(page, 'POST', '/api/v1/items', {name: 'browser/'+prefix, title: prefix, kind, tags: ['ui', 'preferred']});
}
async function theme(page, prefix, pair = false) {
    const document = JSON.parse(readFileSync('agent-kit/examples/packages/ember/meta/theme.json', 'utf8'));
    document.name = 'browser/'+prefix; document.cssName = prefix; document.default = false;
    return api(page, 'POST', '/api/v1/themes', {document, variants: pair ? 'pair' : 'single', familyLabel: prefix});
}
async function fixture(page, domain) {
    const prefix = 'discovery-'+randomBytes(5).toString('hex');
    if (domain === 'catalogue') {
        await item(page, prefix+'-old'); await item(page, prefix+'-new', 'block');
        return {path: '/catalogue', url: `/catalogue?q=${prefix}&kind=component&tags=preferred&sort=name`, search: 'Search', field: 'Kind', name: 'kind', initial: 'component', next: 'block', nextCount: 1, prefix, submit: 'Find items'};
    }
    if (domain === 'themes') {
        await theme(page, prefix, true);
        return {path: '/themes', url: `/themes?q=${prefix}&mode=light&sort=name`, search: 'Search themes', field: 'Mode', name: 'mode', initial: 'light', next: 'dark', nextCount: 1, prefix, submit: 'Find themes'};
    }
    return {path: '/fonts', url: '/fonts?q=lora&category=serif', search: 'Search families', field: 'Category', name: 'category', initial: 'serif', next: 'sans', nextCount: 0, prefix, submit: 'Find fonts'};
}
async function ready(page) {
    await expect(root(page)).toHaveAttribute('data-discovery-state', 'ready');
    await expect(page.locator('html')).not.toHaveAttribute('aria-busy', 'true');
}

for (const domain of ['catalogue', 'themes', 'fonts']) for (const width of [390, 1440]) for (const mode of ['light', 'dark']) {
    test(`discovery: ${domain} URL, keyboard, Back/Forward and controls ${width}px ${mode}`, async ({page}, info) => {
        await page.setViewportSize({width, height: 1000}); await page.emulateMedia({colorScheme: mode}); await login(page);
        await page.getByRole('button', {name: 'Restore default appearance', exact: true}).click(); await expect(page.locator('[data-appearance-target="status"]')).toHaveText('Applied Studio default.');
        const f = await fixture(page, domain); await page.goto(f.url);
        await expect(page.getByLabel(f.search, {exact: true})).toHaveValue(domain === 'fonts' ? 'lora' : f.prefix);
        await expect(page.getByLabel(f.field, {exact: true})).toHaveValue(f.initial);
        await expect(page.locator('turbo-frame article.card')).toHaveCount(1);
        const report = await new AxeBuilder({page}).include('[data-controller~="discovery"]').exclude('iframe').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
        writeFileSync(info.outputPath('discovery-accessibility.json'), JSON.stringify({violations: report.violations, incomplete: report.incomplete}, null, 2));
        expect(report.violations.map(v => ({id: v.id, targets: v.nodes.map(n => n.target)}))).toEqual([]);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await page.screenshot({path: info.outputPath(`discovery-${domain}-${width}-${mode}.png`)});
        await page.getByLabel(f.field, {exact: true}).selectOption(f.next); await ready(page);
        await expect(page).toHaveURL(url => url.searchParams.get(f.name) === f.next);
        await expect(page.locator('turbo-frame article.card')).toHaveCount(f.nextCount);
        await page.goBack(); await expect(page.getByLabel(f.field, {exact: true})).toHaveValue(f.initial);
        await expect(page.locator('turbo-frame article.card')).toHaveCount(1);
        await page.goForward(); await expect(page.getByLabel(f.field, {exact: true})).toHaveValue(f.next);
        const length = await page.evaluate(() => history.length); await page.evaluate(() => window.discoverySentinel = true);
        await page.getByLabel(f.search, {exact: true}).fill('no-matching-'+f.prefix); await expect(page.locator('turbo-frame article.card')).toHaveCount(0); await ready(page);
        expect(await page.evaluate(() => history.length)).toBe(length); expect(await page.evaluate(() => window.discoverySentinel)).toBe(true);
        await expect(page.getByLabel(f.search, {exact: true})).toBeFocused();
        await root(page).getByRole('link', {name: 'Clear all', exact: true}).focus(); await page.keyboard.press('Enter'); await ready(page);
        await expect(page.getByLabel(f.search, {exact: true})).toHaveValue(''); await expect(page.getByLabel(f.field, {exact: true})).toHaveValue('');
        await expect(root(page).getByRole('list', {name: 'Active filters', exact: true})).toHaveCount(0);
    });
}

for (const domain of ['catalogue', 'themes', 'fonts']) {
    test(`discovery: ${domain} composition input, obsolete response and recoverable failures`, async ({page}) => {
        await login(page); const f = await fixture(page, domain); await page.goto(f.path);
        const search = page.getByLabel(f.search, {exact: true}); const old = domain === 'fonts' ? 'lora' : f.prefix+'-old', newer = domain === 'fonts' ? 'inter' : f.prefix+'-new';
        // Themes have distinct real single-variant names for request ordering.
        if (domain === 'themes') {await theme(page, old); await theme(page, newer);}
        let release, started, requests = 0; const held = new Promise(resolve => {release = resolve;}); const fetched = new Promise(resolve => {started = resolve;});
        await page.route('**'+f.path+'?*', async route => {
            if (!route.request().headers()['turbo-frame']) return route.continue(); requests++;
            if (new URL(route.request().url()).searchParams.get('q') !== old) return route.continue();
            const response = await route.fetch(); started(); await held; await route.fulfill({response}).catch(() => {});
        });
        await search.dispatchEvent('compositionstart'); await search.fill(old); await page.waitForTimeout(450); expect(requests).toBe(0);
        await search.dispatchEvent('compositionend'); await fetched;
        await search.fill(newer); await search.evaluate(el => el.setSelectionRange(3, 3)); await expect(page).toHaveURL(url => url.searchParams.get('q') === newer); await ready(page);
        const current = await page.locator('turbo-frame').innerText(); release(); await page.waitForTimeout(450);
        expect(await page.locator('turbo-frame').innerText()).toBe(current); await expect(search).toHaveValue(newer); expect(await search.evaluate(el => el.selectionStart)).toBe(3);
        await page.unroute('**'+f.path+'?*');
        await page.route('**'+f.path+'?*', route => route.fulfill({status: 503, contentType: 'text/html', body: '<p>Temporarily unavailable</p>'}));
        await search.fill('failed-search'); await expect(root(page)).toHaveAttribute('data-discovery-state', 'error');
        expect(await page.locator('turbo-frame').innerText()).toBe(current); expect(new URL(page.url()).searchParams.get('q')).toBe(newer);
        await expect(root(page).locator('[data-discovery-target="status"]')).toContainText('previous list is kept'); await page.unroute('**'+f.path+'?*');
        await root(page).getByRole('button', {name: 'Retry results', exact: true}).click(); await ready(page);
        await expect(page).toHaveURL(url => url.searchParams.get('q') === 'failed-search'); await expect(page.locator('turbo-frame article.card')).toHaveCount(0);
        if (domain === 'fonts') {
            await page.route('**/fonts?*', route => route.abort('failed'));
            await search.fill('network-failure'); await expect(root(page)).toHaveAttribute('data-discovery-state', 'error');
            await page.unroute('**/fonts?*'); await root(page).getByRole('button', {name: 'Retry results', exact: true}).click(); await ready(page);
            await page.route('**/fonts?*', route => route.fulfill({status: 200, headers: {'X-Discovery-Request': route.request().headers()['x-discovery-request']}, contentType: 'text/html', body: '<html><body><p>Missing results region</p></body></html>'}));
            await search.fill('inter'); await expect(root(page)).toHaveAttribute('data-discovery-state', 'error'); await expect(page.locator('article.card')).toHaveCount(0);
            await page.unroute('**/fonts?*'); await root(page).getByRole('button', {name: 'Retry results', exact: true}).click(); await ready(page); await expect(page.locator('article.card')).toHaveCount(1);
        }
        await page.setViewportSize({width: 390, height: 1000}); await search.fill('x'.repeat(100)); await expect(page).toHaveURL(url => url.searchParams.get('q') === 'x'.repeat(100)); await ready(page);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    });
}

for (const kind of ['catalogue', 'theme']) {
    test(`discovery: ${kind} confirmed removal, last-page clamp, Undo and empty-result failure`, async ({page}) => {
        await login(page); const prefix = 'discovery-pages-'+randomBytes(5).toString('hex'), entries = [];
        for (let i = 0; i < 13; i++) {
            const entry = kind === 'theme' ? await theme(page, prefix+'-'+String(i).padStart(2, '0')) : await item(page, prefix+'-'+String(i).padStart(2, '0'));
            entries.push(entry); await api(page, 'PUT', `/api/v1/favorites/${kind}/${entry.id}`);
        }
        const path = kind === 'theme' ? '/themes' : '/catalogue';
        const filters = `q=${prefix}&favorites=1&sort=name${kind === 'theme' ? '&mode=light' : '&kind=component&tags=ui,preferred'}`;
        await page.goto(path+'?'+filters+'&page=2'); await expect(page.locator('article.card')).toHaveCount(1);
        const id = entries[12].id, url = `/api/v1/favorites/${kind}/${id}`, historyLength = await page.evaluate(() => history.length);
        let release, started; const held = new Promise(resolve => {release = resolve;}); const committed = new Promise(resolve => {started = resolve;});
        await page.route('**'+url, async route => {
            if (route.request().method() !== 'DELETE') return route.continue();
            const response = await route.fetch(); started(); await held; await route.fulfill({response});
        });
        await star(page, id).focus(); await page.keyboard.press('Enter'); await committed;
        await expect(star(page, id)).toBeDisabled(); await expect(page.locator('article.card')).toHaveCount(1); await expect(summary(page)).toContainText('13 ');
        release(); await expect(summary(page)).toContainText('12 '); await ready(page); await expect(page.locator('article.card')).toHaveCount(12);
        expect(new URL(page.url()).searchParams.get('page')).toBe('1'); expect(await page.evaluate(() => history.length)).toBe(historyLength);
        await expect(summary(page)).toBeFocused(); await expect(page.getByRole('link', {name: 'Next', exact: true})).toHaveCount(0); await page.unroute('**'+url);
        await undo(page).click(); await expect(summary(page)).toContainText('13 '); await ready(page);
        expect((await (await page.request.get(url)).json()).favorite).toBe(true);
        await page.getByRole('link', {name: 'Next', exact: true}).click(); await expect(page.locator('article.card')).toHaveCount(1);
        await page.goto(path+'?'+filters.replace('q='+prefix, 'q='+prefix+'-12'));
        await star(page, id).click(); await expect(summary(page)).toContainText('0 '); await ready(page); await expect(page.locator('article.card')).toHaveCount(0);
        await page.route('**'+url, route => route.request().method() === 'PUT' ? route.fulfill({status: 503, json: {message: 'Storage unavailable'}}) : route.continue());
        await undo(page).click(); await expect(root(page).locator('[data-discovery-target="undoMessage"]')).toContainText('could not be restored');
        expect((await (await page.request.get(url)).json()).favorite).toBe(false); await expect(page.locator('article.card')).toHaveCount(0);
        await page.unroute('**'+url); await undo(page).click(); await expect(summary(page)).toContainText('1 '); await ready(page);
        await page.reload(); await expect(star(page, id)).toHaveAttribute('aria-pressed', 'true');
    });
}

test('discovery: regular cards stay, and Undo respects later active filters', async ({page}) => {
    await login(page); const prefix = 'discovery-undo-'+randomBytes(5).toString('hex');
    const first = await item(page, prefix+'-first'), second = await item(page, prefix+'-second');
    for (const entry of [first, second]) await api(page, 'PUT', '/api/v1/favorites/catalogue/'+entry.id);
    await page.goto('/catalogue?q='+prefix); await star(page, first.id).click(); await expect(star(page, first.id)).toHaveAttribute('aria-pressed', 'false'); await expect(page.locator('article.card')).toHaveCount(2);
    await undo(page).click(); await expect(star(page, first.id)).toHaveAttribute('aria-pressed', 'true');
    await page.getByLabel('Favorites only', {exact: true}).check(); await ready(page);
    await star(page, first.id).click(); await expect(page.locator('article.card')).toHaveCount(1); await ready(page);
    await page.getByRole('searchbox', {name: 'Search', exact: true}).fill(second.name); await expect(page).toHaveURL(url => url.searchParams.get('q') === second.name); await ready(page);
    await undo(page).click(); await expect(root(page).locator('[data-discovery-target="undoMessage"]')).toContainText('Restored'); await ready(page);
    await expect(page.locator('article.card')).toHaveCount(1); await expect(page.locator('article.card')).toContainText(second.title);
    expect((await (await page.request.get('/api/v1/favorites/catalogue/'+first.id)).json()).favorite).toBe(true);
});

test('discovery: all three listings retain working no-JavaScript GET forms', async ({browser}) => {
    const context = await browser.newContext({javaScriptEnabled: false}); const page = await context.newPage();
    try {
        await login(page);
        for (const domain of ['catalogue', 'themes', 'fonts']) {
            const f = await fixture(page, domain); await page.goto(f.url);
            await page.getByLabel(f.field, {exact: true}).selectOption(f.next); await page.getByRole('button', {name: f.submit, exact: true}).click();
            await expect(page).toHaveURL(url => url.searchParams.get(f.name) === f.next);
            await expect(page.locator('turbo-frame article.card')).toHaveCount(f.nextCount);
            await page.goBack(); await expect(page.getByLabel(f.field, {exact: true})).toHaveValue(f.initial);
        }
    } finally {await context.close();}
});
