import {test, expect} from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import {readFileSync, writeFileSync} from 'node:fs';
import {createHash, randomBytes} from 'node:crypto';
import {login} from './helpers.js';

test.describe.configure({timeout:120000});
const pairing = page => page.locator('#font-pairing');
const contrast = page => page.locator('#theme-contrast');
const row = (page, key) => contrast(page).locator(`[data-background="${key}"]`);
const draft = (page, key) => row(page, key).locator('[data-draft-result]');
const ready = page => expect(contrast(page).locator('[data-theme-contrast-target="status"]')).toContainText('Current draft contrast updated');
async function create(page, normalSnapshot = false) {
    await login(page);
    const token = (await (await page.request.get('/api/v1/csrf')).json()).token;
    const document = JSON.parse(readFileSync('agent-kit/examples/packages/ember/meta/theme.json', 'utf8'));
    const slug = 'pairing-' + randomBytes(5).toString('hex'); document.name = 'browser/' + slug; document.cssName = slug;
    if (normalSnapshot) {
        const pin=JSON.parse(readFileSync('resources/library/fonts.lock.json','utf8')).families.inter;
        const record=JSON.parse(readFileSync(`var/library-staging/fonts/inter/${pin.version}/family.json`,'utf8'));
        const font=Object.fromEntries(['id','kind','family','category','source','sourceVersion','license','licensePath','licenseSha256','faces'].map(key=>[key,record[key]]));
        font.faces=font.faces.filter(face=>face.style==='normal').map(({fontFamily,fontSubfamily,...face})=>face);
        document.fonts.push(font);document.typography.bodyFontId='inter';document.typography.bodyWeight=400;document.typography.bodyStyle='normal';
    }
    const response = await page.request.post('/api/v1/themes', {headers: {'X-CSRF-Token': token}, data: {document, variants: 'single'}});
    expect(response.status()).toBe(201); const theme = await response.json();
    await page.goto(`/themes/${theme.id}/edit`); await ready(page);
    return theme;
}
async function propose(page) {
    for (const [role, family] of [['body','inter'],['heading','lora'],['code','jetbrains-mono']]) await pairing(page).getByLabel(`Proposed ${role} font`, {exact:true}).selectOption(family);
    await pairing(page).getByLabel('Proposed body weight', {exact:true}).fill('472');
    await pairing(page).getByLabel('Proposed body style', {exact:true}).selectOption('italic');
    await pairing(page).getByRole('button', {name:'Preview pairing', exact:true}).click();
    await expect(pairing(page).getByRole('button', {name:'Use pairing in theme fields', exact:true})).toBeEnabled();
    const frame = page.frameLocator('iframe[title="Font pairing proposal"]');
    await expect(frame.locator('html')).toHaveAttribute('data-font-status','loaded');
    return frame;
}

test('pairing loads only requested real faces/subsets and transfers into the existing draft without saving or applying', async ({page}, info) => {
    const theme = await create(page);
    const appearance = await (await page.request.get('/api/v1/appearance')).json();
    const shell = await page.locator('body').evaluate(el=>({font:getComputedStyle(el).fontFamily,background:getComputedStyle(el).backgroundColor}));
    await page.getByLabel('primary', {exact:true}).fill('#123456');
    await page.getByLabel('radius-box (rem)', {exact:true}).fill('2');
    const initial = await page.getByLabel('Theme body font', {exact:true}).inputValue();
    const requests = []; page.on('request', request => requests.push({url:request.url(), method:request.method()}));
    await pairing(page).getByLabel('Search pairing families', {exact:true}).fill('inter');
    await expect(pairing(page).getByLabel('Proposed body font', {exact:true}).locator('option[value="lora"]')).toHaveJSProperty('hidden',true);
    await pairing(page).getByLabel('Search pairing families', {exact:true}).fill('');
    await pairing(page).getByLabel('Proposed body font', {exact:true}).selectOption('inter');
    expect(requests.filter(request => request.url.endsWith('/api/v1/fonts/pairing'))).toEqual([]);
    const responsePromise = page.waitForResponse(response => response.url().endsWith('/api/v1/fonts/pairing') && response.request().method() === 'POST');
    const frame = await propose(page), response = await responsePromise, data = await response.json();
    await expect(page.getByLabel('Theme body font', {exact:true})).toHaveValue(initial);
    await expect(frame.locator('[data-font-role="body"]').first()).toHaveCSS('font-family', /^Inter,/);
    await expect(frame.locator('[data-font-role="heading"]').first()).toHaveCSS('font-family', /^Lora,/);
    await expect(frame.locator('[data-font-role="code"]').first()).toHaveCSS('font-family', /^"?JetBrains Mono/);
    await expect(frame.locator('[data-font-role="body"]').first()).toHaveCSS('font-weight','472');
    const encoded = [...data.html.matchAll(/data:font\/woff2;base64,([A-Za-z0-9+/=]+)/g)];
    const hashes = [...new Set(encoded.map(match => createHash('sha256').update(Buffer.from(match[1],'base64')).digest('hex')))].sort();
    const selected = [...new Set(Object.values(data.roles).flatMap(role => role.faces.map(face=>face.sha256)))].sort();
    expect(hashes).toEqual(selected); expect(hashes.length).toBeGreaterThan(0);
    expect(Object.values(data.roles).every(role => role.faces.every(face => face.subset === 'latin' || face.subset === 'latin-ext'))).toBe(true);
    expect(requests.filter(request => request.url.includes('.woff2') || /fonts\.(googleapis|gstatic)/.test(request.url))).toEqual([]);
    await pairing(page).getByRole('button', {name:'Use pairing in theme fields', exact:true}).click();
    await expect(page.getByLabel('Theme body font', {exact:true})).toHaveValue('inter');
    await expect(page.getByLabel('Theme heading font', {exact:true})).toHaveValue('lora');
    await expect(page.getByLabel('Theme code font', {exact:true})).toHaveValue('jetbrains-mono');
    await expect(page.getByLabel('primary', {exact:true})).toHaveValue('#123456');
    await expect(page.getByLabel('radius-box (rem)', {exact:true})).toHaveValue('2');
    await expect(page.locator('[data-controller~="theme-editor"]')).toHaveAttribute('data-editing-state','dirty');
    expect((await (await page.request.get(`/api/v1/themes/${theme.id}`)).json()).revision).toBe(1);
    expect(await (await page.request.get('/api/v1/appearance')).json()).toEqual(appearance);
    expect(await page.locator('body').evaluate(el=>({font:getComputedStyle(el).fontFamily,background:getComputedStyle(el).backgroundColor}))).toEqual(shell);
    expect(requests.filter(request => request.method === 'PUT' && request.url.includes('/api/v1/themes/'))).toEqual([]);
    writeFileSync(info.outputPath('pairing-selected-fonts.json'), JSON.stringify({hashes, roles:data.roles, noExternalFonts:true},null,2));
    await page.getByRole('button',{name:'Save theme',exact:true}).click();
    await expect(page.locator('[data-theme-editor-target="saveStatus"]')).toContainText('Theme saved.');
    const saved = await (await page.request.get(`/api/v1/themes/${theme.id}`)).json();
    expect(saved.revision).toBe(2); expect(saved.document.typography.bodyWeight).toBe(472); expect(saved.document.typography.bodyStyle).toBe('italic');
    expect(saved.document.colors.primary).toBe('#123456'); expect(saved.document.geometry.radiusBoxRem).toBe(2);
    const exported = await (await page.request.get(`/themes/${theme.id}/export/theme.json`)).json();
    expect(exported.typography).toEqual(saved.document.typography);
    expect(await (await page.request.get('/api/v1/appearance')).json()).toEqual(appearance);
});

test('transfer preserves the requested registered faces when an existing theme captured normal faces only',async({page})=>{
    const theme=await create(page,true);
    const before=await(await page.request.get(`/api/v1/themes/${theme.id}`)).json();
    expect(before.document.fonts.find(font=>font.id==='inter').faces.every(face=>face.style==='normal')).toBe(true);
    await propose(page);
    await pairing(page).getByRole('button',{name:'Use pairing in theme fields',exact:true}).click();
    await expect(page.getByLabel('Body style',{exact:true})).toHaveValue('italic');
    await expect(page.getByLabel('Body weight',{exact:true})).toHaveValue('472');
    await page.getByRole('button',{name:'Save theme',exact:true}).click();
    await expect(page.locator('[data-theme-editor-target="saveStatus"]')).toContainText('Theme saved.');
    const saved=await(await page.request.get(`/themes/${theme.id}/export/theme.json`)).json();
    expect(saved.typography.bodyStyle).toBe('italic');expect(saved.typography.bodyWeight).toBe(472);
    expect(saved.fonts.find(font=>font.id==='inter').faces.some(face=>face.style==='italic')).toBe(true);
});

test('unavailable faces, missing glyph coverage and failed decoding remain explicit', async ({page}) => {
    await create(page);
    await pairing(page).getByLabel('Proposed body font',{exact:true}).selectOption('inter');
    await pairing(page).getByLabel('Proposed body weight',{exact:true}).fill('950');
    await pairing(page).getByRole('button',{name:'Preview pairing',exact:true}).click();
    await expect(pairing(page).locator('[data-font-pairing-target="status"]')).toContainText('no real face');
    await expect(pairing(page).getByRole('button',{name:'Use pairing in theme fields',exact:true})).toBeDisabled();
    await pairing(page).getByLabel('Proposed body weight',{exact:true}).fill('472');
    await pairing(page).getByLabel('Additional sample characters',{exact:true}).fill('漢');
    await pairing(page).getByRole('button',{name:'Preview pairing',exact:true}).click();
    await expect(pairing(page).locator('[data-font-pairing-target="status"]')).toContainText('U+6F22');
    await expect(pairing(page).locator('[data-font-pairing-target="details"][data-role="body"]')).toContainText('100–900 normal');
    await page.route('**/api/v1/fonts/pairing',async route=>{const response=await route.fetch(), data=await response.json();data.html=data.html.replace(/data:font\/woff2;base64,[A-Za-z0-9+/=]+/g,'data:font/woff2;base64,YnJva2VuLWZvbnQ=');await route.fulfill({response,json:data});});
    await pairing(page).getByRole('button',{name:'Preview pairing',exact:true}).click();
    const frame=page.frameLocator('iframe[title="Font pairing proposal"]');
    await expect(frame.locator('html')).toHaveAttribute('data-font-status','fallback');
    await expect(frame.getByRole('status').first()).toContainText('System fallback is active');
});

test('unsaved contrast updates accurately, links to editable fields/examples and preserves the saved baseline',async({page})=>{
    const theme=await create(page), appearance=await(await page.request.get('/api/v1/appearance')).json();
    const baseline=await row(page,'primary').locator('[data-saved-result]').textContent();
    await page.getByLabel('primary',{exact:true}).fill('#777777');await page.getByLabel('primary-content',{exact:true}).fill('#FFFFFF');
    await expect(draft(page,'primary')).toContainText('4.48:1');await expect(draft(page,'primary')).toContainText('Ordinary text: below 4.5');await expect(draft(page,'primary')).toContainText('meets 3');
    await expect(row(page,'primary').locator('[data-saved-result]')).toHaveText(baseline);
    await row(page,'primary').getByRole('link',{name:'primary',exact:true}).press('Enter');await expect(page.getByLabel('primary',{exact:true})).toBeFocused();
    await row(page,'primary').getByRole('button',{name:'Show primary example',exact:true}).press('Enter');
    await expect(page.frameLocator('iframe[title="Draft contrast examples"]').locator('#example-primary')).toBeFocused();
    await page.getByLabel('primary',{exact:true}).fill('#FFF');await expect(draft(page,'primary')).toContainText('Cannot evaluate');
    await expect(page.getByLabel('primary',{exact:true})).toHaveAttribute('aria-invalid','true');
    for (const value of ['#12345680','rgba(0,0,0,.5)']) {
        await page.getByLabel('primary',{exact:true}).evaluate((input,value)=>{input.value=value;input.dispatchEvent(new Event('input',{bubbles:true}));},value);
        await ready(page);await expect(draft(page,'primary')).toContainText('Cannot evaluate');await expect(draft(page,'primary')).not.toContainText(':1');
    }
    await page.getByLabel('primary',{exact:true}).fill('#FFFFFF');await expect(draft(page,'primary')).toContainText('1.00:1');
    await expect(page.getByLabel('primary',{exact:true})).toHaveAttribute('aria-invalid','false');
    const saved=await(await page.request.get(`/api/v1/themes/${theme.id}`)).json();expect(saved.revision).toBe(1);expect(saved.document).toEqual(theme.document);
    expect(await(await page.request.get('/api/v1/appearance')).json()).toEqual(appearance);
    await page.getByRole('button',{name:'Save theme',exact:true}).click();await expect(page.locator('[data-theme-editor-target="saveStatus"]')).toContainText('Theme saved.');
    await expect(row(page,'primary').locator('[data-saved-result]')).toHaveText('1:1');
});

test('delayed contrast and pairing responses cannot replace newer proposals',async({page})=>{
    await create(page);let release;const held=new Promise(resolve=>release=resolve);let intercepted;const interceptedPromise=new Promise(resolve=>intercepted=resolve);
    await page.route('**/api/v1/themes/contrast',async route=>{
        if(route.request().postDataJSON().colors.primary==='#111111'){const response=await route.fetch();intercepted();await held;await route.fulfill({response}).catch(()=>{});}else await route.continue();
    });
    await page.getByLabel('primary',{exact:true}).fill('#111111');await interceptedPromise;
    await page.getByLabel('primary',{exact:true}).fill('#FFFFFF');await page.getByLabel('primary-content',{exact:true}).fill('#FFFFFF');await expect(draft(page,'primary')).toContainText('1.00:1');
    release();await ready(page);await expect(draft(page,'primary')).toContainText('1.00:1');
    let resume;const pending=new Promise(resolve=>resume=resolve);let requested;const requestPromise=new Promise(resolve=>requested=resolve);
    await page.route('**/api/v1/fonts/pairing',async route=>{const response=await route.fetch();requested();await pending;await route.fulfill({response}).catch(()=>{});});
    await pairing(page).getByRole('button',{name:'Preview pairing',exact:true}).click();await requestPromise;
    await pairing(page).getByLabel('Proposed body font',{exact:true}).selectOption('inter');resume();
    await expect(pairing(page).getByRole('button',{name:'Use pairing in theme fields',exact:true})).toBeDisabled();
    await expect(pairing(page).locator('[data-font-pairing-target="frame"]')).not.toHaveAttribute('srcdoc',/./);
});

for(const width of [390,1440])for(const mode of ['light','dark'])test(`pairing and contrast keyboard layout ${width} ${mode}`,async({page},info)=>{
    await page.setViewportSize({width,height:1000});await page.emulateMedia({colorScheme:mode});await create(page);
    await page.getByRole('button',{name:'Restore default appearance',exact:true}).click();
    if(await page.locator('html').getAttribute('data-color-mode')!==mode)await page.getByRole('button',{name:mode==='dark'?'Switch to dark mode':'Switch to light mode',exact:true}).click();
    await expect(page.locator('html')).toHaveAttribute('data-color-mode',mode);
    await pairing(page).getByLabel('Pairing sample appearance',{exact:true}).selectOption(mode);
    const frame=await propose(page);await expect(frame.locator('html')).toHaveCSS('color-scheme',mode);
    await pairing(page).getByRole('button',{name:'Use pairing in theme fields',exact:true}).focus();await page.keyboard.press('Enter');
    await expect(page.getByLabel('Theme code font',{exact:true})).toHaveValue('jetbrains-mono');
    const overflow = await page.evaluate(()=>({width:innerWidth,document:document.documentElement.scrollWidth,elements:[...document.querySelectorAll('main *')].filter(el=>el.getBoundingClientRect().right>innerWidth+1).map(el=>({tag:el.tagName,class:el.className,right:el.getBoundingClientRect().right,text:el.textContent.slice(0,100)}))}));
    writeFileSync(info.outputPath('layout.json'),JSON.stringify(overflow,null,2));
    expect(overflow.document).toBeLessThanOrEqual(overflow.width);
    if(width===390){
        const region=page.getByRole('region',{name:'Draft contrast results',exact:true});
        expect(await region.evaluate(el=>el.scrollWidth>el.clientWidth)).toBe(true);
        await region.focus();await page.keyboard.press('ArrowRight');
        await expect.poll(()=>region.evaluate(el=>el.scrollLeft)).toBeGreaterThan(0);
    }
    await pairing(page).scrollIntoViewIfNeeded();await page.screenshot({path:info.outputPath(`pairing-${width}-${mode}.png`)});
    await page.locator('iframe[title="Font pairing proposal"]').scrollIntoViewIfNeeded();await page.screenshot({path:info.outputPath(`pairing-sample-${width}-${mode}.png`)});
    await contrast(page).scrollIntoViewIfNeeded();await page.screenshot({path:info.outputPath(`contrast-${width}-${mode}.png`)});
    const audit=await new AxeBuilder({page}).exclude('iframe').withTags(['wcag2a','wcag2aa','wcag21a','wcag21aa']).analyze();
    writeFileSync(info.outputPath('font-contrast-accessibility.json'),JSON.stringify({violations:audit.violations,incomplete:audit.incomplete},null,2));
    expect(audit.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)}))).toEqual([]);
});
