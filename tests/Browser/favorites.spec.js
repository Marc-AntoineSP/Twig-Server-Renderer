import {test, expect} from '@playwright/test';
import {randomBytes} from 'node:crypto';
import {readFileSync, mkdirSync} from 'node:fs';
import {login} from './helpers.js';

async function api(page, method, url, data) {
    const token = (await (await page.request.get('/api/v1/csrf')).json()).token;
    const response = await page.request.fetch(url, {method, headers: {'X-CSRF-Token': token}, data});
    expect(response.ok(), (await response.text()).slice(0, 800)).toBe(true);
    return response.json();
}
async function item(page, kind, prefix) {
    return api(page, 'POST', '/api/v1/items', {name: `browser/${prefix}-${kind}`, kind, title: `${prefix} ${kind}`, tags: ['preferred']});
}
const star = (page, kind, id) => page.locator(`[data-controller~="favorite"][data-favorite-kind-value="${kind}"][data-favorite-id-value="${id}"] button`);
const pressed = (button, value) => expect(button).toHaveAttribute('aria-pressed', String(value));
async function shot(page, name) {
    mkdirSync('var/favorites-verification', {recursive: true});
    await page.screenshot({path: `var/favorites-verification/${process.env.APPEARANCE_PRODUCTION ? 'production' : 'development'}-${name}.png`, fullPage: !page.url().includes('/themes/')});
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
}

test('catalogue stars persist across devices, combine filters and return links, and stay private', async ({page, browser}) => {
    test.setTimeout(90000);
    await login(page); await page.getByRole('button', {name:'Restore default appearance', exact:true}).click(); await expect(page.locator('[data-appearance-target="status"]')).toHaveText('Applied Studio default.'); const prefix = 'favorite-'+randomBytes(5).toString('hex');
    const items = [];
    for (const kind of ['component','block','layout']) items.push(await item(page, kind, prefix));
    await page.goto('/catalogue?q='+prefix);
    await page.evaluate(() => window.favoritePageSentinel = true);
    for (const i of items) { await star(page, 'catalogue', i.id).click(); await pressed(star(page, 'catalogue', i.id), true); }
    expect(await page.evaluate(() => window.favoritePageSentinel)).toBe(true);
    await page.getByLabel('Favorites only', {exact: true}).check(); await page.getByRole('button', {name: 'Find items', exact: true}).click();
    await expect(page).toHaveURL(/favorites=1/); await expect(page.locator('article.card')).toHaveCount(3);
    await shot(page, 'catalogue-desktop-light');
    await page.getByRole('combobox', {name: 'Kind', exact: true}).selectOption('block'); await page.getByRole('button', {name: 'Find items', exact: true}).click();
    await expect(page.locator('article.card')).toHaveCount(1); const listUrl = page.url();
    await page.getByRole('link', {name: 'Open', exact: true}).click(); await pressed(star(page, 'catalogue', items[1].id), true);
    await page.getByRole('link', {name: '← Catalogue', exact: true}).click(); await expect(page).toHaveURL(listUrl);
    await page.setViewportSize({width: 390, height: 844});
    await page.getByRole('button', {name: 'Switch to dark mode', exact: true}).click(); await expect(page.locator('html')).toHaveAttribute('data-color-mode', 'dark');
    await shot(page, 'catalogue-mobile-dark');
    const second = await browser.newContext(); const otherDevice = await second.newPage(); await login(otherDevice);
    await otherDevice.goto(`/catalogue/${items[0].id}`); await pressed(star(otherDevice, 'catalogue', items[0].id), true); await second.close();
    const privateContext = await browser.newContext(); const differentUser = await privateContext.newPage(); await login(differentUser, 'reader');
    await differentUser.goto(`/catalogue/${items[0].id}`); await pressed(star(differentUser, 'catalogue', items[0].id), false);
    await star(differentUser, 'catalogue', items[0].id).click(); await pressed(star(differentUser, 'catalogue', items[0].id), true); await privateContext.close();
    await star(page, 'catalogue', items[1].id).click(); await pressed(star(page, 'catalogue', items[1].id), false); await page.reload();
    await expect(page.getByText('You have favorites, but none match the current search and filters.', {exact: true})).toBeVisible();
    await page.getByRole('link', {name: 'Clear favorite filter', exact: true}).click(); await expect(page.locator('article.card')).toHaveCount(1);
    for (const i of items) await api(page, 'DELETE', `/api/v1/favorites/catalogue/${i.id}`);
});

test('theme variant stars stay independent of Apply and work on mobile in both modes', async ({page}) => {
    test.setTimeout(60000); await login(page); const prefix = 'favorite-theme-'+randomBytes(5).toString('hex');
    await page.getByRole('button', {name: 'Restore default appearance', exact: true}).click();
    await expect(page.locator('[data-appearance-target="status"]')).toHaveText('Applied Studio default.');
    if (await page.locator('html').getAttribute('data-color-mode') !== 'dark') {
        await page.getByRole('button', {name: 'Switch to dark mode', exact: true}).click();
    }
    await expect(page.locator('html')).toHaveAttribute('data-color-mode', 'dark');
    const doc = JSON.parse(readFileSync('agent-kit/examples/packages/ember/meta/theme.json', 'utf8')); doc.name='browser/'+prefix; doc.cssName=prefix;
    const family = await api(page, 'POST', '/api/v1/themes', {document: doc, variants: 'pair', familyLabel: prefix});
    const light = family.family.variants.light.id, dark = family.family.variants.dark.id;
    const before = await (await page.request.get('/api/v1/appearance')).json();
    await page.goto('/themes?q='+prefix); await expect(page.locator('article.card')).toHaveCount(2);
    await star(page, 'theme', dark).click(); await pressed(star(page, 'theme', dark), true); await pressed(star(page, 'theme', light), false);
    expect(await (await page.request.get('/api/v1/appearance')).json()).toEqual(before);
    await page.getByLabel('Favorites only', {exact: true}).check(); await page.getByRole('combobox', {name: 'Mode', exact: true}).selectOption('dark'); await page.getByRole('button', {name: 'Find themes', exact: true}).click();
    await expect(page.locator('article.card')).toHaveCount(1); await expect(page.locator('article.card')).toContainText('dark · revision'); await shot(page, 'theme-desktop');
    await page.locator('article.card h2 a').click(); await expect(page).toHaveURL(new RegExp('/themes/'+dark)); await expect(page.locator('[data-theme-sidebar]')).toBeVisible(); await pressed(star(page, 'theme', dark), true);
    await page.getByRole('button', {name: 'Apply to application', exact: true}).click(); await expect(page.locator('[data-appearance-target="status"]')).toContainText('Applied '+prefix);
    await pressed(star(page, 'theme', dark), true);
    await page.setViewportSize({width: 390, height: 844});
    const disclosure = page.locator('[data-theme-sidebar] > details');
    await expect(disclosure).not.toHaveAttribute('open', '');
    await disclosure.locator(':scope > summary').click();
    await star(page, 'theme', dark).click(); await pressed(star(page, 'theme', dark), false);
    await expect(page.locator('html')).toHaveAttribute('data-appearance-family', family.family.id);
    await shot(page, 'theme-detail-mobile-dark');
    await page.getByRole('link', {name: 'Showcase light', exact: true}).click(); await expect(page).toHaveURL(new RegExp('/themes/'+light));
    // Turbo changes the URL before replacing the old variant's DOM/disclosure.
    await expect(star(page, 'theme', light)).toBeAttached();
    await expect(disclosure).not.toHaveAttribute('open', '');
    await disclosure.locator(':scope > summary').click();
    await pressed(star(page, 'theme', light), false); await star(page, 'theme', light).click(); await pressed(star(page, 'theme', light), true);
    await page.getByRole('button', {name: 'Switch to light mode', exact: true}).click(); await expect(page.locator('html')).toHaveAttribute('data-color-mode','light');
    await shot(page, 'theme-detail-mobile-light');
    await page.getByRole('button', {name:'Restore default appearance', exact:true}).click();
});

test('pending and lost responses reconcile against real saved favorite state without reloading', async ({page}) => {
    await login(page); const i = await item(page, 'component', 'favorite-network-'+randomBytes(5).toString('hex'));
    await page.goto('/catalogue/'+i.id); const button = star(page, 'catalogue', i.id);
    let release; let committed; const committedPromise = new Promise(resolve=>committed=resolve); const gate = new Promise(resolve=>release=resolve);
    const url = `**/api/v1/favorites/catalogue/${i.id}`;
    await page.route(url, async route => {
        if (route.request().method() !== 'PUT') return route.continue();
        const response = await route.fetch(); expect(response.ok()).toBe(true); committed(); await gate; await route.abort('failed');
    });
    await button.focus(); await page.keyboard.press('Enter'); await committedPromise;
    await expect(button).toBeDisabled(); await expect(button).toHaveAttribute('aria-busy','true'); release();
    await pressed(button, true); await expect(button).toBeEnabled();
    await expect(page.locator('[data-favorite-target="status"]')).toContainText('Status refreshed'); await page.unroute(url);
    await button.click(); await pressed(button, false); await page.reload(); await pressed(star(page,'catalogue',i.id),false);
});

test('composer favorites filter eligible immutable components, blocks and layouts without changing the tree', async ({page}) => {
    test.setTimeout(100000); await login(page); const prefix='favorite-picker-'+randomBytes(5).toString('hex');
    const references=[];
    for(const kind of ['component','block','layout']) {
        const i=await item(page,kind,prefix); references.push(i);
        await api(page,'POST',`/api/v1/items/${i.id}/releases`,{version:'1.0.0',expectedRevision:1});
        await api(page,'PUT',`/api/v1/favorites/catalogue/${i.id}`);
    }
    const unpublished=await item(page,'block',prefix+'-unpublished'); await api(page,'PUT',`/api/v1/favorites/catalogue/${unpublished.id}`);
    const target=await item(page,'layout',prefix+'-target');
    await page.goto(`/catalogue/${target.id}/compose`); await page.getByLabel('Favorites only',{exact:true}).check();
    const picker=page.getByLabel('Component, block or layout to insert',{exact:true});
    for(const i of references) await expect(picker.locator('option').filter({hasText:i.name+'@1.0.0'})).toHaveCount(1);
    await expect(picker.locator('option').filter({hasText:unpublished.name})).toHaveCount(0);
    await expect(page.locator('[data-composition-target="saveStatus"]')).toHaveText('Saved revision 1');
    await picker.selectOption({label: (await picker.locator('option').filter({hasText:references[0].name+'@1.0.0'}).textContent())});
    await page.getByRole('button',{name:'Add reference',exact:true}).click();
    await expect(page.getByRole('treeitem',{name:references[0].name+'@1.0.0',exact:true})).toBeVisible();
    await page.getByRole('button',{name:'Save composition',exact:true}).click(); await expect(page.locator('[data-composition-target="saveStatus"]')).toHaveText('Saved revision 2');
    await page.reload(); await expect(page.getByLabel('Favorites only',{exact:true})).toBeChecked();
    await page.getByRole('treeitem',{name:references[0].name+'@1.0.0',exact:true}).click();
    // An existing reference stays visible if it ceases to be a favorite; filtering cannot rewrite it.
    await api(page,'DELETE',`/api/v1/favorites/catalogue/${references[0].id}`); await page.reload();
    await page.getByRole('treeitem',{name:references[0].name+'@1.0.0',exact:true}).click();
    await expect(page.getByLabel('Immutable reference',{exact:true}).locator('option:checked')).toContainText('(current, outside favorites)');
    await star(page,'catalogue',target.id).click(); await pressed(star(page,'catalogue',target.id),true);
    await expect(page.locator('[data-composition-target="saveStatus"]')).toHaveText('Saved revision 2');
    await page.setViewportSize({width:390,height:844}); await shot(page,'composer-mobile');
    await expect(page.locator('iframe[title="Composition preview"]')).toHaveAttribute('sandbox','allow-scripts');
});
