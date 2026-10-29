import {test, expect} from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import {randomBytes} from 'node:crypto';
import {readFileSync,writeFileSync} from 'node:fs';
import {login} from './helpers.js';

test.describe.configure({timeout: 90000});
test.use({reducedMotion: 'reduce'});
const fixtures = {};
const state = page => page.locator('[data-controller="catalogue-preview"]');
const frame = page => page.frameLocator('iframe[title="Catalogue item preview"]');
const csrf = async page => (await (await page.request.get('/api/v1/csrf')).json()).token;

async function create(page, kind, publish = true) {
    const token = await csrf(page), name = `browser/detail-${kind}-${randomBytes(5).toString('hex')}`;
    const response = await page.request.post('/api/v1/items', {headers: {'X-CSRF-Token': token}, data: {name, kind, title: `Detail ${kind}`}});
    expect(response.status()).toBe(201);
    const item = await response.json();
    if (kind !== 'component') {
        const document = {schemaVersion:1, root:{type:'container', tag:'section', classes:[kind === 'block' ? 'w-[1100px]' : 'min-h-[5000px]', 'p-6', 'bg-base-200'], children:[{type:'reference',name:fixtures.component.item.name,version:'1.0.0',props:{message:'Helpful published sample'}}]}};
        const saved = await page.request.put(`/api/v1/items/${item.id}/composition`, {headers:{'X-CSRF-Token':token}, data:{expectedRevision:1,document}});
        expect(saved.status(),await saved.text()).toBe(200);
        const result = await page.request.post(`/api/v1/items/${item.id}/releases`,{headers:{'X-CSRF-Token':token},data:{version:'1.0.0',expectedRevision:2}});
        expect(result.status(),await result.text()).toBe(201);
        return {item,release:await result.json(),revision:2};
    }
    const saved = await (await page.request.get(`/api/v1/items/${item.id}/draft`)).json();
    const {manifestData: manifest, sourceMap: sources} = saved;
    const identifier = `catalogue--${name.replace('/', '--')}`, path = `assets/controllers/catalogue/${name}_controller.js`;
    manifest.controllers = [{identifier, path}];
    sources[path] = `import {Controller} from '@hotwired/stimulus'; export default class extends Controller {open(){this.element.querySelector('dialog').showModal();} close(){this.element.querySelector('dialog').close();} submit(event){event.preventDefault();} save(){this.element.querySelector('[data-result]').textContent='Demo saved locally';}}`;
    const size = kind === 'block' ? 'w-[1100px]' : kind === 'layout' ? 'min-h-[5000px]' : 'max-w-md';
    sources[manifest.entry] = `<section class="${size} rounded-box bg-base-200 p-6" data-controller="${identifier}"><h1 class="text-2xl">{{ message }}</h1><button class="btn btn-primary mt-4" type="button" data-action="${identifier}#open">Open demo dialog</button><dialog class="modal"><div class="modal-box"><h2>Local demo dialog</h2><button type="button" data-action="${identifier}#close">Close demo dialog</button></div></dialog><form action="/api/v1/items" method="post" data-action="submit->${identifier}#submit"><label>Demo name<input class="input" name="name" value="Local only"></label><button class="btn" type="button" data-action="${identifier}#save">Save demo</button><button class="btn" type="submit">Attempt submission</button><p data-result role="status"></p></form></section>`;
    sources['meta/examples.json'] = JSON.stringify([{name: 'Helpful sample', props: {...manifest.defaults, message: 'Helpful published sample'}}, {name: 'Second sample', props: {...manifest.defaults, message: 'Second published sample'}}]);
    const save = await page.request.put(`/api/v1/items/${item.id}/draft`, {headers: {'X-CSRF-Token': token, 'If-Match': '"1"'}, data: {title: `Detail ${kind}`, manifestData: manifest, sourceMap: sources}});
    expect(save.status(), await save.text()).toBe(200);
    let release;
    if (publish) {
        const result = await page.request.post(`/api/v1/items/${item.id}/releases`, {headers: {'X-CSRF-Token': token}, data: {version: '1.0.0', expectedRevision: 2}});
        expect(result.status(), await result.text()).toBe(201); release = await result.json();
    }
    return {item, manifest, sources, release, revision: 2};
}

async function load(page) {
    await page.getByRole('button', {name: 'Load preview', exact: true}).click();
    await expect(state(page)).toHaveAttribute('data-preview-state', /current|error/, {timeout: 30000});
    expect(await page.locator('[data-catalogue-preview-target="status"]').textContent()).not.toMatch(/retry|failed|could not/i);
    await expect(state(page)).toHaveAttribute('data-preview-state', 'current');
    await expect(frame(page).locator('body')).toBeVisible();
}

test.beforeAll(async ({browser}) => {
    const context = await browser.newContext();
    try {
        const page = await context.newPage(); await login(page);
        const token = await csrf(page), document = JSON.parse(readFileSync('agent-kit/examples/packages/ember/meta/theme.json','utf8'));
        const slug='detail-theme-'+randomBytes(5).toString('hex');document.name='browser/'+slug;document.cssName=slug;document.default=false;
        const theme=await page.request.post('/api/v1/themes',{headers:{'X-CSRF-Token':token},data:{document}});expect(theme.status(),await theme.text()).toBe(201);const saved=await theme.json();
        const published=await page.request.post(`/api/v1/themes/${saved.id}/releases`,{headers:{'X-CSRF-Token':token},data:{expectedRevision:saved.revision}});expect(published.status(),await published.text()).toBe(201);fixtures.theme={...await published.json(),cssName:slug};
        for (const kind of ['component','block','layout']) fixtures[kind] = await create(page, kind);
    } finally {await context.close();}
});

test('reader loads the pinned immutable release on demand, changes examples/themes and keeps writes private', async ({page, browser}) => {
    await login(page, 'reader'); const f = fixtures.component;
    let creations = 0; page.on('request', request => {if (request.url().includes('/api/v1/catalogue-previews/')) creations++;});
    await page.goto(`/catalogue/${f.item.id}?release=${f.release.id}&example=0`);
    await expect(page.getByRole('heading', {name: 'Preview', exact: true})).toBeVisible();
    await expect(page.getByRole('link', {name: 'Edit draft', exact: true})).toHaveCount(0);
    await expect(state(page)).toHaveAttribute('data-preview-state', 'idle'); expect(creations).toBe(0);
    await page.getByRole('button', {name: 'Load preview', exact: true}).focus(); await page.keyboard.press('Enter');
    await expect(state(page)).toHaveAttribute('data-preview-state', 'current', {timeout: 30000});
    await expect(frame(page).getByRole('heading', {name: 'Helpful published sample', exact: true})).toBeVisible();
    await expect(page.locator('[data-catalogue-preview-target="identity"]')).toContainText('Published release 1.0.0');
    await page.getByLabel('Example', {exact: true}).selectOption('1');
    await expect(frame(page).getByRole('heading', {name: 'Second published sample', exact: true})).toBeVisible();
    await expect(page).toHaveURL(/example=1/);
    const choices = page.getByLabel('Published theme', {exact: true}).locator('option');
    expect(await choices.count()).toBeGreaterThan(1);
    const themeId = fixtures.theme.id;
    const previousDocument=await page.locator('iframe[title="Catalogue item preview"]').getAttribute('srcdoc');
    await page.getByLabel('Published theme', {exact: true}).selectOption(themeId);
    await expect.poll(()=>page.locator('iframe[title="Catalogue item preview"]').getAttribute('srcdoc')).not.toBe(previousDocument);
    await expect(state(page)).toHaveAttribute('data-preview-state','current',{timeout:30000});
    await expect(frame(page).locator('html')).toHaveAttribute('data-theme',fixtures.theme.cssName);
    await expect(page).toHaveURL(new RegExp('theme='+themeId));
    await page.reload(); await expect(page.getByLabel('Example',{exact:true})).toHaveValue('1'); await expect(page.getByLabel('Published theme',{exact:true})).toHaveValue(themeId);
    await load(page); await expect(frame(page).getByRole('heading',{name:'Second published sample',exact:true})).toBeVisible();
    const token = await csrf(page);
    expect((await page.request.post(`/api/v1/catalogue-previews/${f.item.id}`,{headers:{'X-CSRF-Token':token},data:{sequence:1,channel:'a'.repeat(32),draftRevision:2}})).status()).toBe(403);
    expect((await page.request.get(`/catalogue/${f.item.id}/edit`)).status()).toBe(403);
    await page.locator('#releases > summary').click();
    await expect(page.getByRole('link',{name:'Download immutable source ZIP',exact:true})).toBeVisible();
    await page.locator('#source > summary').click(); await expect(page.locator('#source')).toContainText('Published release 1.0.0');
});

test('sandboxed dialogs and forms remain local; source/channel checks bound page growth', async ({page}) => {
    await login(page,'reader');const f=fixtures.component;await page.goto(`/catalogue/${f.item.id}?release=${f.release.id}`);await load(page);
    const requests=[];page.on('request',request=>{if(request.frame()?.parentFrame()&&/^https?:/.test(request.url()))requests.push(request.url());});
    const iframe=page.locator('iframe[title="Catalogue item preview"]');
    await expect(iframe).toHaveAttribute('sandbox','allow-scripts');
    const document=await iframe.getAttribute('srcdoc');const csp=await frame(page).locator('meta[http-equiv="Content-Security-Policy"]').getAttribute('content');expect(csp).toContain("connect-src 'none'");expect(csp).toContain("form-action 'none'");
    await frame(page).getByRole('button',{name:'Open demo dialog',exact:true}).click();await expect(frame(page).getByRole('dialog')).toBeVisible();await frame(page).getByRole('button',{name:'Close demo dialog',exact:true}).click();await expect(frame(page).getByRole('dialog')).not.toBeVisible();
    await frame(page).getByLabel('Demo name',{exact:true}).fill('Private demonstration');await frame(page).getByRole('button',{name:'Save demo',exact:true}).click();await expect(frame(page).getByRole('status')).toHaveText('Demo saved locally');await frame(page).getByRole('button',{name:'Attempt submission',exact:true}).click();await expect(frame(page).getByRole('status')).toHaveText('Demo saved locally');
    expect(await frame(page).locator('body').evaluate(()=>{try{void parent.document.body;return false;}catch{return true;}})).toBe(true);
    expect(await frame(page).locator('body').evaluate(()=>{try{localStorage.setItem('escape','no');return false;}catch{return true;}})).toBe(true);
    const channel=document.match(/const channel="([a-f0-9]{32})"/)[1];
    await frame(page).locator('body').evaluate((_,channel)=>parent.postMessage({type:'resize',channel,height:777},'*'),channel);await expect(iframe).toHaveCSS('height','777px');
    await page.evaluate(channel=>window.postMessage({type:'resize',channel,height:3999},'*'),channel);
    await frame(page).locator('body').evaluate((_,channel)=>{parent.postMessage({type:'resize',channel:'wrong',height:3998},'*');parent.postMessage({type:'resize',channel,height:4001},'*');parent.postMessage({type:'resize',channel,height:3997,html:'bad'},'*');},channel);
    await page.waitForTimeout(120);await expect(iframe).toHaveCSS('height','777px');
    await page.getByLabel('Preview height',{exact:true}).selectOption('320');await expect(page.locator('[data-catalogue-preview-target="viewport"]')).toHaveCSS('height','320px');
    await page.getByLabel('Disable preview scripts',{exact:true}).check();await expect(iframe).toHaveAttribute('sandbox','');
    await expect(frame(page).getByRole('heading')).toBeVisible();await frame(page).getByRole('button',{name:'Open demo dialog',exact:true}).click();await expect(frame(page).getByRole('dialog')).not.toBeVisible();expect(requests).toEqual([]);
});

test('a late obsolete document cannot replace the current example', async ({page}) => {
    await login(page,'reader');const f=fixtures.component;await page.goto(`/catalogue/${f.item.id}?release=${f.release.id}&example=0`);
    let releaseOld,oldSeen; const hold=new Promise(resolve=>{releaseOld=resolve;}),seen=new Promise(resolve=>{oldSeen=resolve;});let first=true;
    await page.route('**/api/v1/preview-jobs/*/document',async route=>{const response=await route.fetch();if(first){first=false;oldSeen();await hold;}await route.fulfill({response});});
    await page.getByRole('button',{name:'Load preview',exact:true}).click();await seen;
    await page.getByLabel('Example',{exact:true}).selectOption('1');await expect(frame(page).getByRole('heading',{name:'Second published sample',exact:true})).toBeVisible({timeout:30000});releaseOld();
    await page.waitForTimeout(200);await expect(frame(page).getByRole('heading',{name:'Second published sample',exact:true})).toBeVisible();await expect(frame(page).getByRole('heading',{name:'Helpful published sample',exact:true})).toHaveCount(0);
});

test('queued, rendering, failed and expired states offer an actual retry', async ({page}) => {
    await login(page,'reader');const f=fixtures.component;await page.goto(`/catalogue/${f.item.id}?release=${f.release.id}`);
    let mode='queued';
    await page.route(/\/api\/v1\/preview-jobs\/[^/]+$/,async route=>{if(route.request().method()!=='GET')return route.continue();const response=await route.fetch();const body=await response.json();await route.fulfill({response,json:{...body,status:mode,error:mode==='failed'?{message:'Deliberate worker diagnostic'}:null}});});
    await page.getByRole('button',{name:'Load preview',exact:true}).click();await expect(page.locator('[data-catalogue-preview-target="status"]')).toContainText('Queued');mode='running';await expect(page.locator('[data-catalogue-preview-target="status"]')).toContainText('Rendering and building');mode='failed';await expect(state(page)).toHaveAttribute('data-preview-state','error');await expect(page.locator('[data-catalogue-preview-target="status"]')).toContainText('Deliberate worker diagnostic');
    mode='expired';await page.getByRole('button',{name:'Load preview',exact:true}).click();await expect(page.locator('[data-catalogue-preview-target="status"]')).toContainText('expired');
    await page.unroute(/\/api\/v1\/preview-jobs\/[^/]+$/);await load(page);
});

test('draft-only, missing examples and genuine invalid Twig states remain explicit', async ({page,browser}) => {
    await login(page);const f=await create(page,'component',false),token=await csrf(page);delete f.sources['meta/examples.json'];f.sources[f.manifest.entry]='{% unknown_tag %}';
    const save=await page.request.put(`/api/v1/items/${f.item.id}/draft`,{headers:{'X-CSRF-Token':token,'If-Match':'"2"'},data:{title:'Broken draft',manifestData:f.manifest,sourceMap:f.sources}});expect(save.status(),await save.text()).toBe(200);
    await page.goto(`/catalogue/${f.item.id}`);await expect(page.locator('[data-catalogue-preview-target="identity"]')).toContainText('Saved draft r3');await expect(page.getByText('No named examples.',{exact:false})).toBeVisible();await page.getByRole('button',{name:'Load preview',exact:true}).click();await expect(state(page)).toHaveAttribute('data-preview-state','error',{timeout:30000});await expect(page.locator('[data-catalogue-preview-target="status"]')).toContainText(/unknown_tag|Unknown/);
    const reader=await browser.newContext();try{const other=await reader.newPage();await login(other,'reader');await other.goto(`/catalogue/${f.item.id}`);await expect(other.getByRole('heading',{name:'No published preview',exact:true})).toBeVisible();await expect(other.getByRole('button',{name:'Load preview',exact:true})).toHaveCount(0);}finally{await reader.close();}
});

test('published versions stay pinned after a later publication and drafts keep their own revision',async({page})=>{
    await login(page);const f=await create(page,'component'),token=await csrf(page);const url=`/catalogue/${f.item.id}?release=${f.release.id}&example=0`;
    f.manifest.defaults.message='New draft only';f.sources['meta/examples.json']=JSON.stringify([{name:'Changed example',props:{...f.manifest.defaults,message:'New release sample'}}]);
    const save=await page.request.put(`/api/v1/items/${f.item.id}/draft`,{headers:{'X-CSRF-Token':token,'If-Match':'"2"'},data:{title:'New title',manifestData:f.manifest,sourceMap:f.sources}});expect(save.status()).toBe(200);
    const published=await page.request.post(`/api/v1/items/${f.item.id}/releases`,{headers:{'X-CSRF-Token':token},data:{version:'2.0.0',expectedRevision:3}});expect(published.status()).toBe(201);const second=await published.json();
    await page.goto(url);await load(page);await expect(frame(page).getByRole('heading',{name:'Helpful published sample',exact:true})).toBeVisible();await expect(page.locator('[data-catalogue-preview-target="identity"]')).toContainText('Published release 1.0.0');
    await page.getByLabel('Source version',{exact:true}).selectOption(second.id);await expect(page).toHaveURL(new RegExp('release='+second.id));await load(page);await expect(frame(page).getByRole('heading',{name:'New draft only',exact:true})).toBeVisible();await page.getByLabel('Example',{exact:true}).selectOption('0');await expect(frame(page).getByRole('heading',{name:'New release sample',exact:true})).toBeVisible();
    await page.getByLabel('Source version',{exact:true}).selectOption('draft');await expect(page.locator('[data-catalogue-preview-target="identity"]')).toContainText('Saved draft r3');await load(page);await expect(frame(page).getByRole('heading',{name:'New draft only',exact:true})).toBeVisible();
});

test('listing requests no preview documents or jobs until one bounded cached thumbnail is requested',async({page})=>{
    await login(page,'reader');const f=fixtures.component;let thumbnails=0,jobs=0;page.on('request',r=>{if(r.url().includes('/api/v1/release-thumbnails/'))thumbnails++;if(r.url().includes('/api/v1/catalogue-previews/'))jobs++;});await page.goto('/catalogue?q='+encodeURIComponent(f.item.name));
    await expect(page.locator('article.card')).toHaveCount(1);await expect(page.locator('article.card iframe')).toHaveCount(0);expect(thumbnails).toBe(0);expect(jobs).toBe(0);
    await page.getByRole('button',{name:'Show published preview',exact:true}).click();await expect(page.locator('article.card iframe')).toHaveCount(1);expect(thumbnails).toBe(1);expect(jobs).toBe(0);await expect(page.locator('article.card iframe')).toHaveAttribute('sandbox','');
    const box=await page.locator('[data-controller="release-thumbnail"]').boundingBox();expect(Math.abs(box.width/box.height-16/9)).toBeLessThan(.03);
});

for(const width of [390,1440])for(const mode of ['light','dark'])test(`component, wide block and tall layout ${width}px ${mode}`,async({page},testInfo)=>{
    await page.setViewportSize({width,height:1000});await login(page,'reader');
    await page.getByRole('button',{name:'Restore default appearance',exact:true}).click();await expect(page.locator('[data-appearance-target="status"]')).toHaveText('Applied Studio default.');
    if(mode==='dark')await page.getByRole('button',{name:'Switch to dark mode',exact:true}).click();await expect(page.locator('html')).toHaveAttribute('data-color-mode',mode);
    for(const kind of ['component','block','layout']){const f=fixtures[kind];await page.goto(`/catalogue/${f.item.id}?release=${f.release.id}&example=0`);await load(page);await expect(frame(page).getByRole('heading',{name:'Helpful published sample',exact:true})).toBeVisible();
        expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
        const viewport=await page.locator('[data-catalogue-preview-target="viewport"]').boundingBox();expect(viewport.height).toBe(560);expect(viewport.y).toBeLessThan(800);
        await page.getByRole('button',{name:'1280 px',exact:true}).click();await expect(page.locator('iframe[title="Catalogue item preview"]')).toHaveCSS('width','1280px');expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
        await page.getByRole('button',{name:'Fit width',exact:true}).click();await page.getByText('Properties & renderer settings',{exact:true}).click();await expect(page.getByLabel('Preview props (JSON)',{exact:true})).toBeVisible();
        await expect(page.locator('html')).not.toHaveAttribute('aria-busy','true');const result=await new AxeBuilder({page}).exclude('iframe').withTags(['wcag2a','wcag2aa','wcag21a','wcag21aa']).analyze();const report=testInfo.outputPath(`${kind}-accessibility.json`);writeFileSync(report,JSON.stringify({violations:result.violations,incomplete:result.incomplete},null,2));expect(result.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)}))).toEqual([]);
        await page.getByText('Properties & renderer settings',{exact:true}).click();await page.screenshot({path:testInfo.outputPath(`${kind}-${width}-${mode}.png`),fullPage:true});
    }
});
