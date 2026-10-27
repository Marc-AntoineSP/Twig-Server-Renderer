import {test,expect} from '@playwright/test';
import {readFileSync,mkdirSync,writeFileSync} from 'node:fs';
import {randomBytes} from 'node:crypto';
import {login,createAlert} from './helpers.js';

test.describe.configure({timeout:150000});
const state=async page=>(await page.request.get('/api/v1/appearance')).json();
const root=page=>page.locator('html');
const status=page=>page.locator('[data-appearance-target="status"]');
const rgb=hex=>`rgb(${[1,3,5].map(i=>parseInt(hex.slice(i,i+2),16)).join(', ')})`;
const screenshot=async(page,name)=>{mkdirSync('var/appearance-verification',{recursive:true});await page.screenshot({path:`var/appearance-verification/${process.env.APPEARANCE_PRODUCTION?'production':'development'}-${name}.png`});};
async function csrf(page){return (await(await page.request.get('/api/v1/csrf')).json()).token;}
async function api(page,method,url,data){const response=await page.request.fetch(url,{method,data,headers:{'X-CSRF-Token':await csrf(page)}});expect(response.ok(),(await response.text()).slice(0,1500)).toBe(true);return response.json();}
function font(id){const pin=JSON.parse(readFileSync('resources/library/fonts.lock.json','utf8')).families[id];const f=JSON.parse(readFileSync(`var/library-staging/fonts/${id}/${pin.version}/family.json`,'utf8'));const out={};for(const k of ['id','kind','family','category','source','sourceVersion','license','licensePath','licenseSha256','faces'])out[k]=f[k];out.faces=out.faces.map(({fontFamily,fontSubfamily,...face})=>face);return out;}
async function family(page,{primary='#164E63',base='#F1F8FA',variants='pair',localFonts=false}={}){
    const doc=JSON.parse(readFileSync('agent-kit/examples/packages/ember/meta/theme.json','utf8'));const slug='appearance-'+randomBytes(5).toString('hex');doc.name='browser/'+slug;doc.cssName=slug;doc.default=false;doc.colors.primary=primary;doc.colors['primary-content']='#FFFFFF';doc.colors['base-100']=base;doc.colors['base-200']=base;
    if(localFonts){doc.fonts=[font('inter'),font('lora'),font('jetbrains-mono')];doc.typography={...doc.typography,bodyFontId:'inter',headingFontId:'lora',codeFontId:'jetbrains-mono',bodyWeight:472,bodyStyle:'italic',headingWeight:631,headingStyle:'normal',codeWeight:400,codeStyle:'normal'};}
    const result=await api(page,'POST','/api/v1/themes',{document:doc,variants,familyLabel:'Appearance '+slug});
    if(variants==='pair'){
        const dark=await(await page.request.get('/api/v1/themes/'+result.family.variants.dark.id)).json();dark.document.colors.primary='#A9D9F0';dark.document.colors['base-100']='#16222C';dark.document.colors['base-200']='#101B24';
        await api(page,'PUT','/api/v1/themes/'+dark.id,{document:dark.document,expectedRevision:dark.revision});
    }
    return result;
}
async function apply(page,f){await page.goto('/themes/'+f.id);await page.getByRole('button',{name:'Apply theme',exact:true}).click();await expect(status(page)).toHaveText('Applied '+f.family.label+'.');await expect(root(page)).toHaveAttribute('data-appearance-family',f.family.id);}
async function appearanceMenu(page){const menu=page.locator('[data-appearance-target="menu"]');if(!await menu.evaluate(el=>el.open))await menu.locator('summary').click();return menu;}
async function reset(page){await page.getByRole('button',{name:'Restore default appearance',exact:true}).click();await expect(status(page)).toHaveText('Applied Studio default.');}

test('Apply changes the real shell, fonts, forms, menu and dialog while catalogue presets stay independent',async({page})=>{
    await login(page);await reset(page);await page.emulateMedia({colorScheme:'light'});
    const a=await family(page,{localFonts:true}),b=await family(page,{primary:'#64204C',base:'#FFF1FA'});
    const {item}=await createAlert(page);const before=await(await page.request.get(`/api/v1/items/${item.id}/renderer-preset`)).json();
    await apply(page,a);
    await expect(page.locator('header')).toHaveCSS('background-color',rgb('#F1F8FA'));
    await expect(page.locator('body')).toHaveCSS('background-color',rgb('#F1F8FA'));
    await expect(page.getByRole('button',{name:'Apply theme',exact:true})).toHaveCSS('background-color',rgb('#164E63'));
    await expect(page.locator('body')).toHaveCSS('font-family',/^Inter,/);await expect(page.locator('body')).toHaveCSS('font-weight','472');await expect(page.locator('h1')).toHaveCSS('font-family',/^Lora,/);
    const loaded=await page.locator('body').evaluate(async()=>{await document.fonts.ready;return [...document.fonts].filter(f=>f.status==='loaded').map(f=>({family:f.family,weight:f.weight,style:f.style}));});
    expect(loaded.some(f=>f.family.replaceAll('"','')==='Inter'&&f.style==='italic'&&f.weight==='100 900')).toBe(true);expect(loaded.some(f=>f.family.replaceAll('"','')==='Lora')).toBe(true);
    await appearanceMenu(page);await expect(page.getByRole('complementary',{name:'Appearance options'})).toHaveCSS('background-color',rgb('#F1F8FA'));
    await expect(page.getByLabel('Application theme family',{exact:true})).toHaveCSS('background-color',rgb('#F1F8FA'));
    await page.getByRole('button',{name:'Appearance details',exact:true}).click();await expect(page.locator('#appearance-details-dialog')).toBeVisible();await expect(page.locator('#appearance-details-dialog .modal-box')).toHaveCSS('background-color',rgb('#F1F8FA'));await page.keyboard.press('Escape');await expect(page.locator('#appearance-details-dialog')).toBeHidden();
    await screenshot(page,'light-shell');
    await apply(page,b);await expect(page.locator('header')).toHaveCSS('background-color',rgb('#FFF1FA'));
    const after=await(await page.request.get(`/api/v1/items/${item.id}/renderer-preset`)).json();expect(after).toEqual(before);
    await page.goto(`/catalogue/${item.id}/edit`);await expect(page.locator('[data-controller~="preview"]')).toHaveAttribute('data-preview-state','current',{timeout:30000});
    await expect(page.locator('iframe[title="Component preview"]')).toHaveAttribute('sandbox','allow-scripts');
    await expect(page.frameLocator('iframe[title="Component preview"]').locator('html')).not.toHaveAttribute('data-theme',b.document.cssName);
    await apply(page,a);await page.goto(`/catalogue/${item.id}/edit`);await expect(page.getByLabel('Twig source',{exact:true})).toHaveCSS('font-family',/^"?JetBrains Mono/);await expect.poll(()=>page.evaluate(async()=>{await document.fonts.ready;return [...document.fonts].some(face=>face.status==='loaded'&&face.family.replaceAll('"','')==='JetBrains Mono');})).toBe(true);
    const target=process.env.APPEARANCE_PRODUCTION?'production':'development';mkdirSync('var/appearance-verification',{recursive:true});writeFileSync(`var/appearance-verification/${target}-fonts.json`,JSON.stringify({loaded,rendererPresetUnchanged:true,themeA:a.family.label,themeB:b.family.label},null,2));
});

test('moon and sun preserve family, explicit mode ignores OS and system mode follows it before first paint',async({page})=>{
    await login(page);await page.emulateMedia({colorScheme:'light'});await reset(page);const f=await family(page);await apply(page,f);
    const moon=page.getByRole('button',{name:'Switch to dark mode',exact:true});await expect(moon).toHaveAttribute('title','Switch to dark mode');await moon.click();
    await expect(root(page)).toHaveAttribute('data-color-mode','dark');await expect(root(page)).toHaveAttribute('data-appearance-family',f.family.id);await expect(root(page)).toHaveCSS('color-scheme','dark');
    await expect(page.locator('[data-appearance-target="mode"]')).toHaveCSS('background-color','rgb(21, 25, 30)');
    await expect(page.locator('[data-appearance-target="sun"]')).toBeVisible();await expect(page.locator('[data-appearance-target="moon"]')).toBeHidden();
    await screenshot(page,'dark-shell');
    await page.emulateMedia({colorScheme:'light'});await expect(root(page)).toHaveAttribute('data-color-mode','dark');
    await appearanceMenu(page);await page.getByRole('button',{name:'Use system appearance',exact:true}).click();await expect(root(page)).toHaveAttribute('data-color-mode','light');await expect(root(page)).toHaveAttribute('data-appearance-preference','system');
    await page.emulateMedia({colorScheme:'dark'});await expect(root(page)).toHaveAttribute('data-color-mode','dark');
    await page.addInitScript(()=>requestAnimationFrame(()=>{window.firstAppearanceFrame={mode:document.documentElement.dataset.colorMode,background:getComputedStyle(document.body).backgroundColor};}));
    await page.reload();await expect(root(page)).toHaveAttribute('data-color-mode','dark');await expect.poll(()=>page.evaluate(()=>window.firstAppearanceFrame)).toMatchObject({mode:'dark',background:'rgb(16, 27, 36)'});
    await page.getByRole('button',{name:'Switch to light mode',exact:true}).click();await expect(root(page)).toHaveAttribute('data-appearance-preference','light');await page.emulateMedia({colorScheme:'dark'});await expect(root(page)).toHaveAttribute('data-color-mode','light');
    await page.getByRole('link',{name:'Fonts',exact:true}).click();await expect(root(page)).toHaveAttribute('data-appearance-family',f.family.id);await page.goBack();await expect(root(page)).toHaveAttribute('data-color-mode','light');
});

test('Save freezes applied revisions for both accounts; Save and Apply advances only the current account',async({page,browser})=>{
    await login(page);await page.emulateMedia({colorScheme:'light'});await reset(page);const f=await family(page);await apply(page,f);
    const context=await browser.newContext({baseURL:process.env.STUDIO_BASE_URL||'http://studio',colorScheme:'light'}),other=await context.newPage();
    try{
        await login(other,'admin');await apply(other,f);const otherBefore=await state(other),before=await state(page);
        await page.goto(`/themes/${f.id}/edit`);await page.getByLabel('primary',{exact:true}).fill('#314E80');await page.getByRole('button',{name:'Save theme',exact:true}).click();await expect(page.locator('[data-flash]')).toHaveText('Theme saved.');
        expect((await state(page)).variants.light.id).toBe(before.variants.light.id);expect((await state(other)).variants.light.id).toBe(otherBefore.variants.light.id);await expect(page.locator('body')).toHaveCSS('background-color',rgb('#F1F8FA'));
        await page.getByLabel('primary',{exact:true}).fill('#416E58');await page.getByLabel('Family display name',{exact:true}).fill('Renamed family '+randomBytes(3).toString('hex'));await page.getByRole('button',{name:'Save & Apply',exact:true}).click();await expect(page.locator('[data-theme-editor-target="saveStatus"]')).toHaveText('Theme saved and applied to your application.');
        const after=await state(page);expect(after.familyId).toBe(before.familyId);expect(after.variants.light.id).not.toBe(before.variants.light.id);expect((await state(other)).variants.light.id).toBe(otherBefore.variants.light.id);
        await other.reload();expect((await state(other)).variants.light.id).toBe(otherBefore.variants.light.id);expect(await root(other).evaluate(el=>getComputedStyle(el).getPropertyValue('--color-primary').trim())).toBe('#164E63');
        await page.reload();expect(await root(page).evaluate(el=>getComputedStyle(el).getPropertyValue('--color-primary').trim())).toBe('#416E58');
    }finally{await context.close();}
});

test('unsaved trial edits, invalid input, variant switches and Revert keep the original saved rollback target',async({page})=>{
    await login(page);await page.emulateMedia({colorScheme:'light'});await reset(page);const f=await family(page);await apply(page,f);const original=await state(page);
    await page.goto(`/themes/${f.id}/edit`);await page.getByLabel('primary',{exact:true}).fill('#882233');await page.getByRole('button',{name:'Try on application',exact:true}).click();
    await expect(root(page)).toHaveAttribute('data-appearance-trial','true');await expect(page.locator('[data-appearance-target="trialBanner"] strong')).toHaveText('Trying unsaved changes');expect(await state(page)).toEqual(original);
    await page.getByLabel('primary',{exact:true}).fill('#334488');await expect.poll(()=>root(page).evaluate(el=>getComputedStyle(el).getPropertyValue('--color-primary').trim())).toBe('#334488');
    await page.getByLabel('primary',{exact:true}).fill('#bad');await expect(status(page)).toContainText('Trial not updated');expect(await root(page).evaluate(el=>getComputedStyle(el).getPropertyValue('--color-primary').trim())).toBe('#334488');
    await page.getByLabel('Edit family variant',{exact:true}).selectOption(f.family.variants.dark.id);await expect(root(page)).toHaveAttribute('data-color-mode','dark');
    await page.getByLabel('primary',{exact:true}).fill('#88BBEE');await expect.poll(()=>root(page).evaluate(el=>getComputedStyle(el).getPropertyValue('--color-primary').trim())).toBe('#88BBEE');
    await page.getByRole('button',{name:'Revert trial',exact:true}).first().click();await expect(root(page)).toHaveAttribute('data-appearance-trial','false');await expect(root(page)).toHaveAttribute('data-color-mode','light');expect(await state(page)).toEqual(original);expect(await root(page).evaluate(el=>getComputedStyle(el).getPropertyValue('--color-primary').trim())).toBe('#164E63');
    await page.getByLabel('Edit family variant',{exact:true}).selectOption(f.id);await expect(page.getByLabel('primary',{exact:true})).toHaveValue('#bad');
    await page.getByLabel('primary',{exact:true}).fill('#557733');await page.getByRole('button',{name:'Try on application',exact:true}).click();await expect(root(page)).toHaveAttribute('data-appearance-trial','true');
    await page.getByRole('button',{name:'Save theme',exact:true}).click();await expect(page.locator('[data-flash]')).toHaveText('Theme saved.');await expect(root(page)).toHaveAttribute('data-appearance-trial','false');expect((await state(page)).variants.light.id).toBe(original.variants.light.id);
    await page.getByRole('button',{name:'Try on application',exact:true}).click();await expect(root(page)).toHaveAttribute('data-appearance-trial','true');page.once('dialog',dialog=>dialog.accept());await page.reload();await expect(root(page)).toHaveAttribute('data-appearance-trial','false');expect((await state(page)).variants.light.id).toBe(original.variants.light.id);
});

test('single variant keeps requested mode, explains unavailable toggle and gains a real opposite variant without a rebuild',async({page})=>{
    await login(page);await reset(page);await page.getByRole('button',{name:'Switch to dark mode',exact:true}).click();await expect(root(page)).toHaveAttribute('data-color-mode','dark');
    const f=await family(page,{variants:'single'});await apply(page,f);await expect(root(page)).toHaveAttribute('data-color-mode','light');expect((await state(page)).mode).toBe('dark');await expect(page.getByRole('button',{name:'Switch to dark mode',exact:true})).toBeDisabled();
    await appearanceMenu(page);await expect(page.locator('[data-appearance-target="unavailable"]')).toContainText('Only light is available');
    await page.goto(`/themes/${f.id}/edit`);await page.getByRole('button',{name:'Create dark variant',exact:true}).click();await expect(page.getByLabel('Edit family variant',{exact:true}).locator('option')).toHaveCount(2);
    await page.getByRole('button',{name:'Save & Apply',exact:true}).click();await expect(page.locator('[data-theme-editor-target="saveStatus"]')).toHaveText('Theme saved and applied to your application.');await expect(root(page)).toHaveAttribute('data-color-mode','dark');await expect(root(page)).toHaveAttribute('data-appearance-family',f.family.id);await expect(page.getByRole('button',{name:'Switch to light mode',exact:true})).toBeEnabled();
    await page.setViewportSize({width:375,height:812});await expect(page.getByRole('button',{name:'Restore default appearance',exact:true})).toBeInViewport();await page.getByRole('button',{name:'Switch to light mode',exact:true}).focus();await page.keyboard.press('Enter');await expect(root(page)).toHaveAttribute('data-color-mode','light');
    const focusedToggle=page.getByRole('button',{name:'Switch to dark mode',exact:true});await expect(focusedToggle).toBeFocused();await expect(focusedToggle).toHaveCSS('outline-style','solid');expect(await focusedToggle.evaluate(el=>parseFloat(getComputedStyle(el).outlineWidth))).toBeGreaterThanOrEqual(2);
    await screenshot(page,'mobile-recovery');
    await page.getByRole('button',{name:'Restore default appearance',exact:true}).click();await expect(status(page)).toHaveText('Applied Studio default.');expect((await state(page)).mode).toBe('system');
});

test('failed CSS and preference persistence retain confirmed appearance and saved editor changes remain retryable',async({page})=>{
    await login(page);await reset(page);const a=await family(page),b=await family(page,{primary:'#64204C'});await apply(page,a);const original=await state(page);
    await page.goto('/themes/'+b.id);await page.route('**/appearance/assets/*.css',route=>route.abort());await page.getByRole('button',{name:'Apply theme',exact:true}).click();await expect(status(page)).toContainText('stylesheet could not load');expect(await state(page)).toEqual(original);await expect(root(page)).toHaveAttribute('data-appearance-family',a.family.id);await page.unroute('**/appearance/assets/*.css');
    await page.goto(`/themes/${b.id}/edit`);await page.getByLabel('primary',{exact:true}).fill('#442277');await page.route('**/api/v1/appearance/commit',route=>route.fulfill({status:503,json:{message:'Preference storage unavailable.'}}));await page.getByRole('button',{name:'Save & Apply',exact:true}).click();await expect(page.locator('[data-theme-editor-target="saveError"]')).toContainText('Theme saved, but application appearance was not updated');
    const saved=await(await page.request.get('/api/v1/themes/'+b.id)).json();expect(saved.document.colors.primary).toBe('#442277');expect(await state(page)).toEqual(original);await page.unroute('**/api/v1/appearance/commit');
    await page.getByRole('button',{name:'Retry Apply saved theme',exact:true}).click();await expect(status(page)).toHaveText('Applied '+b.family.label+'.');await expect(root(page)).toHaveAttribute('data-appearance-family',b.family.id);
});

test('reordered A B C preparation responses cannot replace C and a later failure rolls back to C',async({page})=>{
    await login(page);await reset(page);const a=await family(page),b=await family(page,{primary:'#64204C'}),c=await family(page,{primary:'#355E2B'}),d=await family(page,{primary:'#332244'});await page.goto('/themes');
    const releases=[];let observed=0;let notify;const started=()=>new Promise(resolve=>{notify=resolve;});
    await page.route('**/api/v1/appearance/prepare',async route=>{
        const data=route.request().postDataJSON();if([a.id,b.id].includes(data.themeId)){
            const response=await route.fetch();let release;const gate=new Promise(resolve=>{release=resolve;});releases.push(release);observed++;notify?.();await gate;try{await route.fulfill({response});}catch{}
        }else await route.continue();
    });
    let wait=started();await page.locator(`article[data-theme-id="${a.id}"]`).getByRole('button',{name:'Apply theme',exact:true}).click();await wait;
    wait=started();await page.locator(`article[data-theme-id="${b.id}"]`).getByRole('button',{name:'Apply theme',exact:true}).click();await wait;
    await page.locator(`article[data-theme-id="${c.id}"]`).getByRole('button',{name:'Apply theme',exact:true}).click();await expect(status(page)).toHaveText('Applied '+c.family.label+'.');expect(observed).toBe(2);releases.reverse().forEach(release=>release());await page.unroute('**/api/v1/appearance/prepare');
    expect((await state(page)).familyId).toBe(c.family.id);await expect(root(page)).toHaveAttribute('data-appearance-family',c.family.id);
    await page.route('**/appearance/assets/*.css',route=>route.abort());await page.locator(`article[data-theme-id="${d.id}"]`).getByRole('button',{name:'Apply theme',exact:true}).click();await expect(status(page)).toContainText('stylesheet could not load');expect((await state(page)).familyId).toBe(c.family.id);await expect(root(page)).toHaveAttribute('data-appearance-family',c.family.id);
});

test('new theme creation offers pair, trials are temporary and Save and Apply works with already compiled utilities',async({page})=>{
    await login(page);await reset(page);await page.goto('/themes/new');const slug='live-'+randomBytes(5).toString('hex');
    const staticCss=await page.locator('link[rel="stylesheet"][href*="/assets/styles/app-"]').getAttribute('href');
    await page.getByLabel('Theme name (namespace/slug)',{exact:true}).fill('browser/'+slug);await page.getByLabel('CSS theme name',{exact:true}).fill(slug);await page.getByLabel('Family display name',{exact:true}).fill('Live '+slug);await page.getByLabel('primary',{exact:true}).fill('#30548F');
    await page.getByRole('button',{name:'Try on application',exact:true}).click();await expect(root(page)).toHaveAttribute('data-appearance-trial','true');await page.getByRole('button',{name:'Save & Apply',exact:true}).click();await page.waitForURL(/\/themes\/[A-Z0-9]+\/edit$/);
    await expect(root(page)).toHaveAttribute('data-theme',slug);await expect(root(page)).toHaveAttribute('data-appearance-trial','false');await expect(page.locator('[data-flash]')).toHaveText('Theme created.');expect(await root(page).evaluate(el=>getComputedStyle(el).getPropertyValue('--color-primary').trim())).toBe('#30548F');
    await expect(page.getByLabel('Edit family variant',{exact:true}).locator('option')).toHaveCount(2);expect(await page.locator('link[rel="stylesheet"][href*="/assets/styles/app-"]').getAttribute('href')).toBe(staticCss);
    await page.getByRole('button',{name:'Switch to dark mode',exact:true}).click();await expect(root(page)).toHaveAttribute('data-color-mode','dark');
});

test('a real application font decoding failure keeps readable fallbacks and shows honest recovery status',async({page})=>{
    await login(page);await reset(page);const f=await family(page,{localFonts:true});
    const external=[];page.on('request',request=>{if(new URL(request.url()).origin!==new URL(page.url()).origin&&!request.url().startsWith('data:'))external.push(request.url());});
    await page.route('**/fonts/assets/*.woff2',route=>route.fulfill({status:200,contentType:'font/woff2',body:Buffer.from('invalid-woff2')}));
    await page.goto('/themes/'+f.id);await page.getByRole('button',{name:'Apply theme',exact:true}).click();await expect(root(page)).toHaveAttribute('data-appearance-family',f.family.id);
    await expect(status(page)).toContainText('Readable system fallback');
    const fonts=await page.evaluate(async()=>{await document.fonts.ready;return [...document.fonts].map(font=>({family:font.family,status:font.status}));});expect(fonts.some(font=>font.family.replaceAll('"','')==='Inter'&&font.status==='error')).toBe(true);
    expect(external).toEqual([]);await expect(page.getByRole('button',{name:'Restore default appearance',exact:true})).toBeInViewport();await reset(page);
});

test('new theme survives a failed first Save and Apply and the editor offers a real retry after redirect',async({page})=>{
    await login(page);await reset(page);const original=await state(page);await page.goto('/themes/new');const slug='failed-first-'+randomBytes(4).toString('hex');
    await page.getByLabel('Theme name (namespace/slug)',{exact:true}).fill('browser/'+slug);await page.getByLabel('CSS theme name',{exact:true}).fill(slug);await page.getByLabel('primary',{exact:true}).fill('#8E4455');
    await page.route('**/api/v1/appearance/commit',route=>route.fulfill({status:503,json:{message:'Preference storage unavailable.'}}));await page.getByRole('button',{name:'Save & Apply',exact:true}).click();await page.waitForURL(/\/edit\?appearanceApplyFailed=1$/);
    await expect(page.locator('[data-theme-editor-target="saveError"]')).toContainText('Theme saved, but application appearance was not updated');await expect(page.getByRole('button',{name:'Retry Apply saved theme',exact:true})).toBeVisible();expect((await state(page)).familyId).toBe(original.familyId);await expect(page.getByLabel('primary',{exact:true})).toHaveValue('#8E4455');
    await page.unroute('**/api/v1/appearance/commit');await page.getByRole('button',{name:'Retry Apply saved theme',exact:true}).click();await expect(page.locator('[data-theme-editor-target="saveError"]')).toBeHidden();await expect(root(page)).toHaveAttribute('data-theme',slug);expect(await root(page).evaluate(el=>getComputedStyle(el).getPropertyValue('--color-primary').trim())).toBe('#8E4455');
});

test('a lost success response restores the last confirmed saved preference rather than leaving an unseen selection',async({page})=>{
    await login(page);await reset(page);const a=await family(page),b=await family(page,{primary:'#64204C'});await apply(page,a);const original=await state(page);await page.goto('/themes/'+b.id);
    let interrupted=false;
    await page.route('**/api/v1/appearance/commit',async route=>{
        if(!interrupted){interrupted=true;const response=await route.fetch();expect(response.ok()).toBe(true);await route.fulfill({status:503,json:{message:'The success response was lost.'}});}else await route.continue();
    });
    await page.getByRole('button',{name:'Apply theme',exact:true}).click();await expect(status(page)).toContainText('success response was lost');
    await expect.poll(async()=>{const current=await state(page);return {family:current.familyId,light:current.variants.light.id,mode:current.mode};}).toEqual({family:original.familyId,light:original.variants.light.id,mode:original.mode});
    await page.reload();await expect(root(page)).toHaveAttribute('data-appearance-family',a.family.id);
});

test('a saved stylesheet failing during reload reports the fallback and Restore default remains reachable',async({page})=>{
    await login(page);await reset(page);const f=await family(page);await apply(page,f);
    const session=await page.context().newCDPSession(page);await session.send('Network.enable');await session.send('Network.clearBrowserCache');await session.send('Network.setCacheDisabled',{cacheDisabled:true});let failed=0;
    await page.route('**/appearance/assets/*.css',route=>{failed++;return route.fulfill({status:503,contentType:'text/css',body:''});});await page.reload();await expect(status(page)).toContainText('saved appearance stylesheet could not load');expect(failed).toBeGreaterThan(0);await expect(page.getByRole('button',{name:'Restore default appearance',exact:true})).toBeInViewport();await expect(page.locator('[data-appearance-target="applied"]:visible')).toHaveCount(0);
    await page.unroute('**/appearance/assets/*.css');await reset(page);expect((await state(page)).default).toBe(true);
});
