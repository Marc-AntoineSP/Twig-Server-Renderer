import {test,expect} from '@playwright/test';
import {readFileSync,mkdirSync,writeFileSync} from 'node:fs';
import {randomBytes} from 'node:crypto';
import {login} from './helpers.js';

test.describe.configure({timeout:90000});
test.use({actionTimeout:10000});
const showcase=page=>page.locator('#component-showcase');
const card=(page,id)=>showcase(page).locator(`[data-component="${id}"]`);
const state=async page=>(await page.request.get('/api/v1/appearance')).json();
const rgb=hex=>`rgb(${[1,3,5].map(i=>parseInt(hex.slice(i,i+2),16)).join(', ')})`;
async function api(page,method,url,data){const token=(await(await page.request.get('/api/v1/csrf')).json()).token;const response=await page.request.fetch(url,{method,data,headers:{'X-CSRF-Token':token}});expect(response.ok(),(await response.text()).slice(0,1200)).toBe(true);return response.json();}
function localFont(id){const pin=JSON.parse(readFileSync('resources/library/fonts.lock.json','utf8')).families[id];const source=JSON.parse(readFileSync(`var/library-staging/fonts/${id}/${pin.version}/family.json`,'utf8'));const font={};for(const key of ['id','kind','family','category','source','sourceVersion','license','licensePath','licenseSha256','faces'])font[key]=source[key];font.faces=font.faces.map(({fontFamily,fontSubfamily,...face})=>face);return font;}
async function theme(page,{fonts=false,primary='#164E63',base='#F1F8FA'}={}){
    const document=JSON.parse(readFileSync('agent-kit/examples/packages/ember/meta/theme.json','utf8'));const slug='components-'+randomBytes(5).toString('hex');
    document.name='browser/'+slug;document.cssName=slug;document.default=false;document.colors.primary=primary;document.colors['primary-content']='#FFFFFF';document.colors['base-100']=base;document.colors['base-200']=base;
    if(fonts){document.fonts=['inter','lora','jetbrains-mono'].map(localFont);document.typography={...document.typography,bodyFontId:'inter',headingFontId:'lora',codeFontId:'jetbrains-mono',bodyWeight:472,bodyStyle:'italic',headingWeight:631,headingStyle:'normal',codeWeight:400,codeStyle:'normal'};}
    const result=await api(page,'POST','/api/v1/themes',{document,variants:'pair',familyLabel:'Components '+slug});
    const dark=await(await page.request.get('/api/v1/themes/'+result.family.variants.dark.id)).json();dark.document.colors.primary='#A9D9F0';dark.document.colors['base-100']='#16222C';dark.document.colors['base-200']='#101B24';
    await api(page,'PUT','/api/v1/themes/'+dark.id,{document:dark.document,expectedRevision:dark.revision});result.darkDocument=dark.document;return result;
}
async function category(page,value){await showcase(page).getByLabel('Component category',{exact:true}).selectOption(value);}
async function open(page,id){await page.goto('/themes/'+id);await expect(showcase(page).locator('[data-showcase-target="count"]')).toContainText('65 component examples');}
async function screenshot(page,name){mkdirSync('var/theme-layout-verification',{recursive:true});await page.screenshot({path:`var/theme-layout-verification/${process.env.APPEARANCE_PRODUCTION?'production':'development'}-${name}.png`});}

test('all Twig examples share the selected palette and real fonts independently of application appearance and variant actions',async({page})=>{
    const errors=[];page.on('pageerror',error=>errors.push(error.message));await login(page);await page.emulateMedia({colorScheme:'light'});await page.getByRole('button',{name:'Restore default appearance',exact:true}).click();await expect(page.locator('[data-appearance-target="status"]')).toHaveText('Applied Studio default.');
    const selected=await theme(page,{fonts:true}),application=await theme(page,{fonts:true,primary:'#64204C',base:'#FFF1FA'});
    application.document.typography.rootFontSizePx=20;application.document.typography.bodyWeight=584;
    await api(page,'PUT','/api/v1/themes/'+application.id,{document:application.document,expectedRevision:application.revision});
    await open(page,application.id);await expect(card(page,'buttons').getByRole('button',{name:'Primary',exact:true})).toHaveCSS('background-color',rgb('#64204C'));await expect(showcase(page)).toHaveCSS('background-color',rgb('#FFF1FA'));await page.getByRole('button',{name:'Apply theme',exact:true}).click();await expect(page.locator('[data-appearance-target="status"]')).toHaveText('Applied '+application.family.label+'.');const saved=await state(page);
    await open(page,selected.id);await expect(page.locator('body')).toHaveCSS('background-color',rgb('#FFF1FA'));await expect(page.locator('body')).toHaveCSS('font-family',/^Inter,/);await expect(page.locator('body')).toHaveCSS('font-weight','584');await expect(page.locator('html')).toHaveCSS('font-size','20px');await expect(showcase(page)).toHaveCSS('font-size','16px');await expect(showcase(page)).toHaveAttribute('data-color-mode','light');
    await expect(showcase(page)).toHaveCSS('background-color',rgb('#F1F8FA'));await expect(card(page,'buttons').getByRole('button',{name:'Primary',exact:true})).toHaveCSS('background-color',rgb('#164E63'));
    await expect(showcase(page)).toHaveCSS('font-family',/^"?Showcase-[a-f0-9]+ Inter"?,/);await expect(showcase(page)).toHaveCSS('font-weight','472');await expect(showcase(page).locator('#component-showcase-heading')).toHaveCSS('font-family',/^"?Showcase-[a-f0-9]+ Lora"?,/);await expect(showcase(page).locator('#component-showcase-heading')).toHaveCSS('font-weight','631');await expect(card(page,'code').locator('code').first()).toHaveCSS('font-family',/^"?Showcase-[a-f0-9]+ JetBrains Mono/);
    const faces=await showcase(page).evaluate(async()=>{await document.fonts.ready;return [...document.fonts].filter(face=>face.status==='loaded').map(face=>({family:face.family.replaceAll('"',''),weight:face.weight,style:face.style}));});for(const name of ['Inter','Lora','JetBrains Mono'])expect(faces.some(face=>face.family.endsWith(' '+name))).toBe(true);
    const colours=await showcase(page).locator('[data-showcase-card]').evaluateAll(cards=>cards.map(card=>getComputedStyle(card).getPropertyValue('--color-primary').trim()));expect(new Set(colours)).toEqual(new Set(['#164E63']));
    const missingClasses=await showcase(page).evaluate(()=>{const selectors=[];const scan=rules=>{for(const rule of rules){if(rule.selectorText)selectors.push(rule.selectorText);if(rule.cssRules)scan(rule.cssRules);}};for(const sheet of document.styleSheets)scan(sheet.cssRules);return [...new Set([...document.querySelectorAll('#component-showcase, #component-showcase [class]')].flatMap(el=>[...el.classList]))].filter(name=>!selectors.some(selector=>selector.includes('.'+CSS.escape(name))));});expect(missingClasses,'Every rendered class must have a compiled or generated CSS rule.').toEqual([]);
    await expect(page.locator('iframe')).toHaveCount(0);await expect(page.locator('main')).toHaveCount(1);
    await expect(page.locator('[data-theme-sidebar]')).toHaveCSS('width','300px');
    const layout=await page.locator('[data-showcase-column]').boundingBox();expect(layout.x+layout.width).toBeGreaterThan(1200);
    expect(await showcase(page).evaluate(el=>getComputedStyle(el).height)).not.toBe('75vh');
    await category(page,'actions');await screenshot(page,'light-actions');
    await page.getByRole('link',{name:'Showcase dark',exact:true}).click();await expect(showcase(page)).toHaveAttribute('data-color-mode','dark');await expect(showcase(page)).toHaveCSS('color-scheme','dark');await expect(showcase(page)).toHaveCSS('background-color',rgb('#101B24'));
    await expect(card(page,'buttons').getByRole('button',{name:'Primary',exact:true})).toHaveCSS('background-color',rgb('#A9D9F0'));expect(await state(page)).toEqual(saved);await category(page,'data');await screenshot(page,'dark-data');
    await page.getByRole('button',{name:'Switch to dark mode',exact:true}).click();await expect(page.locator('html')).toHaveAttribute('data-color-mode','dark');await expect(showcase(page)).toHaveAttribute('data-color-mode','dark');
    await page.getByRole('link',{name:'Showcase light',exact:true}).click();await expect(showcase(page)).toHaveAttribute('data-color-mode','light');await expect(page.locator('html')).toHaveAttribute('data-color-mode','dark');
    writeFileSync('var/theme-layout-verification/'+(process.env.APPEARANCE_PRODUCTION?'production':'development')+'-fonts.json',JSON.stringify({faces,componentCount:65,allPrimaryTokens:'#164E63',stylesScoped:true},null,2));expect(errors).toEqual([]);
});

test('category search, all tab styles, dropdown, modal, accordion and swaps work locally with keyboard focus',async({page})=>{
    await login(page);const t=await theme(page);await open(page,t.id);const before=await state(page);
    await showcase(page).getByLabel('Find a component',{exact:true}).fill('not a component');await expect(showcase(page).locator('[data-showcase-target="empty"]')).toBeVisible();await showcase(page).getByLabel('Find a component',{exact:true}).fill('');
    await category(page,'navigation');await expect(card(page,'buttons')).toBeHidden();
    for(const style of ['Underline','Box','Lift']){const tabs=showcase(page).getByRole('tablist',{name:style+' sample tabs',exact:true});await tabs.getByRole('tab',{name:'Overview',exact:true}).focus();await page.keyboard.press('ArrowRight');await expect(tabs.getByRole('tab',{name:'Activity',exact:true})).toBeFocused();await expect(tabs.getByRole('tab',{name:'Activity',exact:true})).toHaveAttribute('aria-selected','true');await expect(tabs.locator('..').getByRole('tabpanel')).toHaveText('Today: button documentation was updated.');await page.keyboard.press('End');await expect(tabs.getByRole('tab',{name:'Team',exact:true})).toHaveAttribute('aria-selected','true');}
    await category(page,'actions');await card(page,'dropdowns').getByText('Project actions',{exact:true}).click();await expect(card(page,'dropdowns').getByRole('button',{name:'View activity',exact:true})).toBeVisible();await page.keyboard.press('Escape');await expect(card(page,'dropdowns').locator('details')).not.toHaveAttribute('open','');
    const opener=card(page,'modals').getByRole('button',{name:'Open sample dialog',exact:true});await opener.click();const dialog=showcase(page).getByRole('dialog',{name:'Review your sample',exact:true});await expect(dialog).toBeVisible();await expect(dialog.locator('.modal-box')).toHaveCSS('background-color',rgb('#F1F8FA'));await dialog.getByRole('button',{name:'Close sample dialog',exact:true}).click();await expect(dialog).toBeHidden();await expect(opener).toBeFocused();await opener.click();await page.keyboard.press('Escape');await expect(dialog).toBeHidden();
    const swap=card(page,'swaps').getByRole('checkbox',{name:'Swap between play and pause',exact:true});await swap.check();await expect(swap).toBeChecked();await swap.uncheck();await expect(swap).not.toBeChecked();
    await swap.focus();await page.keyboard.press('Space');await expect(swap).toBeChecked();await expect(swap.locator('..')).toHaveCSS('outline-style','solid');
    await card(page,'fab').getByRole('button',{name:'Show quick actions',exact:true}).focus();await card(page,'fab').getByRole('button',{name:'Preview note',exact:true}).click();await expect(showcase(page).locator('[data-showcase-target="status"]')).toContainText('Preview action: N.');
    await category(page,'layout');await card(page,'accordions').getByText('Can I change the fonts?',{exact:true}).click();await expect(card(page,'accordions').locator('details[open]')).toHaveCount(1);await expect(card(page,'accordions').locator('details[open]')).toContainText('body, heading and code');await card(page,'collapses').getByText('Show sample implementation notes',{exact:true}).click();await expect(card(page,'collapses').locator('details')).toHaveAttribute('open','');expect(await state(page)).toEqual(before);
});

test('form inputs, native validation, independent radio groups and files respond without submissions or uploads',async({page})=>{
    await login(page);const t=await theme(page);await open(page,t.id);const before=await state(page);await category(page,'forms');const writes=[];page.on('request',request=>{if(request.method()!=='GET')writes.push(request.url());});
    await card(page,'inputs').getByLabel('Primary input',{exact:true}).fill('Local workspace');await expect(card(page,'inputs').getByLabel('Primary input',{exact:true})).toHaveValue('Local workspace');
    await card(page,'textareas').getByLabel('Project notes',{exact:false}).fill('Local notes');await card(page,'selects').getByLabel('Sample region',{exact:true}).selectOption({label:'Asia Pacific'});
    await card(page,'checkboxes').getByLabel('Secondary',{exact:true}).check();await card(page,'toggles').getByLabel('Accent',{exact:true}).uncheck();await card(page,'radios').getByLabel('Success',{exact:true}).check();await expect(card(page,'radios').locator('fieldset').getByRole('radio',{checked:true})).toHaveCount(1);
    await card(page,'ratings').getByRole('radio',{name:'2 stars',exact:true}).check();await expect(card(page,'ratings').getByRole('radio',{name:'3 hearts',exact:true})).toBeChecked();
    const range=card(page,'ranges').getByRole('slider').first();await range.fill('84');await expect(card(page,'ranges').locator('output').first()).toHaveText('84%');
    const email=card(page,'validation').getByLabel('Sample email',{exact:true});expect(await email.evaluate(input=>input.validity.valid)).toBe(false);await email.fill('bonjour@example.test');await email.blur();expect(await email.evaluate(input=>input.validity.valid)).toBe(true);
    await card(page,'file-inputs').getByLabel('Select a local sample file',{exact:false}).setInputFiles({name:'sample.txt',mimeType:'text/plain',buffer:Buffer.from('Only local data.')});await expect(showcase(page).locator('[data-showcase-target="status"]')).toHaveText('Selected sample.txt locally. No file was uploaded.');
    const otp=card(page,'otp').getByRole('textbox');await otp.fill('12x456');await expect(otp).toHaveValue('12456');expect(writes).toEqual([]);expect(await state(page)).toEqual(before);
});

test('drawers trap and restore focus, toasts dismiss, carousels move and local navigation selections update',async({page})=>{
    await login(page);const t=await theme(page);await open(page,t.id);const before=await state(page);await category(page,'layout');
    const opener=card(page,'drawers').getByRole('button',{name:'Open sample drawer',exact:true});await opener.click();const drawer=showcase(page).getByRole('dialog',{name:'Sample workspace',exact:true});await expect(drawer).toBeVisible();await expect(drawer.locator('aside')).toHaveCSS('background-color',rgb('#F1F8FA'));await expect(drawer.getByRole('button',{name:'Close sample drawer',exact:true})).toBeFocused();await page.keyboard.press('Shift+Tab');await expect(drawer.getByRole('button',{name:'Projects',exact:true})).toBeFocused();await page.keyboard.press('Tab');await expect(drawer.getByRole('button',{name:'Close sample drawer',exact:true})).toBeFocused();await page.keyboard.press('Escape');await expect(drawer).toBeHidden();await expect(opener).toBeFocused();
    await opener.click();await expect(drawer.getByRole('button',{name:'Close sample drawer',exact:true})).toBeFocused();const bounds=await drawer.boundingBox();await drawer.click({position:{x:bounds.width-10,y:20}});await expect(drawer).toBeHidden();await expect(opener).toBeFocused();
    await card(page,'carousels').getByRole('button',{name:'Show slide 3',exact:true}).click();await expect.poll(()=>card(page,'carousels').locator('.carousel').evaluate(el=>el.scrollLeft)).toBeGreaterThan(0);
    await category(page,'feedback');await card(page,'toasts').getByRole('button',{name:'Show sample toast',exact:true}).click();await expect(showcase(page).locator('[data-demo-toast]')).toBeVisible();await showcase(page).getByRole('button',{name:'Dismiss notification',exact:true}).click();await expect(showcase(page).locator('[data-demo-toast]')).toBeHidden();await card(page,'toasts').getByRole('button',{name:'Show sample toast',exact:true}).click();await expect(showcase(page).locator('[data-demo-toast]')).toBeHidden({timeout:6500});
    await category(page,'navigation');await card(page,'pagination').getByRole('button',{name:'3',exact:true}).click();await expect(card(page,'pagination').locator('output')).toContainText('3 selected');await card(page,'dock').getByRole('button',{name:'Messages',exact:true}).click();await expect(card(page,'dock').getByRole('button',{name:'Messages',exact:true})).toHaveClass(/dock-active/);expect(await state(page)).toEqual(before);
    await category(page,'data');await card(page,'countdown').getByRole('button',{name:'Start 10-second countdown',exact:true}).click();await expect(showcase(page).locator('[data-showcase-target="countdown"]')).toHaveText('9',{timeout:3000});
});

test('all categories stay within mobile and tablet viewports and tooltips, mockups and recovery stay usable',async({page})=>{
    await login(page);const t=await theme(page,{fonts:true});await open(page,t.id);
    for(const width of [375,768]){await page.setViewportSize({width,height:900});for(const name of ['actions','forms','navigation','data','feedback','layout','mockups']){await category(page,name);await expect(showcase(page).locator(`section[data-category="${name}"]`)).toBeVisible();expect(await showcase(page).evaluate(el=>({width:innerWidth,scroll:el.scrollWidth}))).toEqual(expect.objectContaining({width:expect.any(Number)}));const overflow=await showcase(page).evaluate(el=>el.scrollWidth-innerWidth);expect(overflow,`${name} at ${width}px`).toBeLessThanOrEqual(1);}await screenshot(page,'mockups-'+width);}
    await page.setViewportSize({width:375,height:900});await category(page,'feedback');const tooltip=card(page,'tooltips').getByRole('button',{name:'Primary',exact:true});await tooltip.focus();await expect(tooltip).toBeFocused();await expect.poll(()=>tooltip.locator('..').evaluate(el=>getComputedStyle(el,'::before').opacity)).toBe('1');await expect(page.getByRole('button',{name:'Restore default appearance',exact:true})).toBeInViewport();await screenshot(page,'mobile-feedback');
});

test('all showcase links stay local and cross-category navigation never loads the studio login',async({page})=>{
    await login(page);const t=await theme(page);await open(page,t.id);const before=await state(page),url=page.url().split('#')[0],requests=[];
    page.on('request',request=>{if(request.isNavigationRequest())requests.push(request.url());});
    await category(page,'navigation');const project=card(page,'menus').getByRole('link',{name:'Design system',exact:true});await project.focus();await project.press('Enter');
    await expect(showcase(page).getByLabel('Component category',{exact:true})).toHaveValue('all');await expect(card(page,'cards')).toBeVisible();
    await card(page,'inputs').getByLabel('Primary input',{exact:true}).fill('Keep showcase state');
    const links=showcase(page).locator('a[href^="#"]'),count=await links.count();expect(count).toBeGreaterThan(10);
    for(let index=0;index<count;index++){
        await links.nth(index).click();await expect(showcase(page)).toBeVisible();
        expect(page.url().split('#')[0]).toBe(url);
        await expect(showcase(page).getByRole('heading',{name:'Sign in to your studio',exact:true})).toHaveCount(0);
    }
    await expect(card(page,'inputs').getByLabel('Primary input',{exact:true})).toHaveValue('Keep showcase state');
    expect(requests).toEqual([]);expect(page.url().split('#')[0]).toBe(url);expect(await state(page)).toEqual(before);expect((await page.request.get('/themes',{maxRedirects:0})).status()).toBe(200);
});

test('sidebar downloads, immutable release package, edit, duplicate and create keep their original workflows',async({page})=>{
    test.setTimeout(180000);await login(page);await page.setViewportSize({width:1440,height:1000});
    const t=await theme(page),before=await state(page);
    const release=await api(page,'POST',`/api/v1/themes/${t.id}/releases`,{expectedRevision:t.revision});
    await open(page,t.id);const sidebar=page.getByRole('complementary',{name:'Theme details',exact:true});
    await expect(sidebar).toBeVisible();await expect(sidebar.getByRole('heading',{level:1})).toHaveCount(1);
    await sidebar.locator('[data-theme-downloads] > summary').click();
    const downloads=sidebar.locator('[data-theme-downloads] a');expect(await downloads.count()).toBe(7);
    for(const link of await downloads.all()){
        const response=await page.request.get(await link.getAttribute('href'));expect(response.status()).toBe(200);
        expect(response.headers()['content-disposition']).toContain('attachment;');expect((await response.body()).length).toBeGreaterThan(20);
        if((await link.getAttribute('href')).endsWith('/theme.json'))expect((await response.json()).colors).toEqual(t.document.colors);
    }
    const originalUrl=page.url();
    const jsonDownload=page.waitForEvent('download');await sidebar.locator('[data-theme-downloads]').getByRole('link',{name:'theme.json',exact:true}).click();expect((await jsonDownload).suggestedFilename()).toBe('theme.json');expect(page.url()).toBe(originalUrl);
    const bundleDownload=page.waitForEvent('download');await sidebar.locator('[data-theme-downloads]').getByRole('link',{name:'Output bundle ZIP',exact:true}).click();expect((await bundleDownload).suggestedFilename()).toMatch(/\.zip$/);expect(page.url()).toBe(originalUrl);
    await sidebar.locator('[data-theme-releases] > summary').click();
    const history=sidebar.locator('[data-theme-releases]');await expect(history.getByRole('heading',{name:'Version '+t.document.version,exact:true})).toBeVisible();
    const releaseDownloads=history.locator(`a[href^="/theme-releases/${release.id}/export/"]`);expect(await releaseDownloads.count()).toBe(6);
    for(const link of await releaseDownloads.all())expect((await page.request.get(await link.getAttribute('href'))).status()).toBe(200);
    await history.getByRole('button',{name:`Publish package ${t.document.version} to private registry`,exact:true}).click();
    await expect(history.getByRole('status')).toContainText('Theme package is available in the private registry.',{timeout:45000});
    const packageLink=history.getByRole('link',{name:'Download immutable theme package',exact:true});await expect(packageLink).toBeVisible();
    const archive=await page.request.get(await packageLink.getAttribute('href'));expect(archive.status()).toBe(200);expect((await archive.body()).subarray(0,2).toString()).toBe('PK');
    await sidebar.getByRole('button',{name:'Apply to showcase',exact:true}).click();await expect(page).toHaveURL(/\/catalogue\/[0-9A-Z]{26}\/edit$/);
    await expect(page.getByLabel('Published theme',{exact:true})).toHaveValue(release.id);await expect(page.locator('iframe[title="Component preview"]')).toHaveAttribute('sandbox','allow-scripts');expect(await state(page)).toEqual(before);
    await open(page,t.id);
    await sidebar.getByRole('link',{name:'Edit theme',exact:true}).click();await expect(page).toHaveURL(`/themes/${t.id}/edit`);
    await expect(page.locator('#main')).toHaveClass(/max-w-6xl/);await expect(page.locator('iframe[title="Edited theme preview"]')).toHaveAttribute('sandbox','allow-scripts');
    await page.goto('/themes/new');await expect(page.locator('#main')).toHaveClass(/max-w-6xl/);await expect(page.locator('iframe[title="Edited theme preview"]')).toHaveAttribute('sandbox','allow-scripts');
    await open(page,t.id);await page.getByRole('button',{name:'Duplicate/Edit',exact:true}).click();await expect(page).toHaveURL(/\/themes\/[0-9A-Z]{26}\/edit$/);expect(page.url()).not.toContain(t.id);
    const original=await(await page.request.get('/api/v1/themes/'+t.id)).json();expect(original.document).toEqual(t.document);expect(original.revision).toBe(t.revision);expect(await state(page)).toEqual(before);
});

test('mobile details collapse accessibly, category anchors work and desktop uses the available width',async({page})=>{
    await login(page);const t=await theme(page);await page.setViewportSize({width:375,height:900});await open(page,t.id);
    const disclosure=page.locator('[data-theme-sidebar] > details'),summary=disclosure.locator(':scope > summary');
    await expect(disclosure).not.toHaveAttribute('open','');await expect(summary).toBeVisible();await expect(page.getByRole('heading',{name:'Component showcase',exact:true})).toBeInViewport();
    await summary.focus();await page.keyboard.press('Enter');await expect(disclosure).toHaveAttribute('open','');await expect(page.getByRole('button',{name:'Apply theme',exact:true})).toBeVisible();
    await page.getByRole('button',{name:'Apply theme',exact:true}).focus();await page.setViewportSize({width:1440,height:1000});await expect(disclosure).toHaveAttribute('open','');
    await page.setViewportSize({width:768,height:900});await expect(disclosure).not.toHaveAttribute('open','');await expect(summary).toBeFocused();
    await page.setViewportSize({width:1920,height:1080});await expect(page.locator('[data-theme-sidebar]')).toHaveCSS('width','300px');
    const layout=await page.locator('[data-showcase-column]').boundingBox();expect(layout.width).toBeGreaterThan(1450);expect(layout.x+layout.width).toBeGreaterThan(1880);
    await category(page,'data');const table=card(page,'tables');await expect(table).toBeVisible();expect((await table.boundingBox()).width).toBeGreaterThan(1400);
    await category(page,'all');await page.goto('/themes/'+t.id+'#component-showcase');await expect(showcase(page)).toBeVisible();await expect(page.getByRole('heading',{name:'Component showcase',exact:true})).toBeInViewport();
    const scroll=await showcase(page).evaluate(el=>({overflow:getComputedStyle(el).overflowY,maxHeight:getComputedStyle(el).maxHeight,height:el.offsetHeight,viewport:innerHeight}));expect(scroll.overflow).toBe('visible');expect(scroll.maxHeight).toBe('none');expect(scroll.height).toBeGreaterThan(scroll.viewport*3);
});
