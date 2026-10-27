import {test,expect} from '@playwright/test';
import {readFileSync,mkdirSync,writeFileSync} from 'node:fs';
import {randomBytes} from 'node:crypto';
import {login} from './helpers.js';

test.describe.configure({timeout:90000});
test.use({actionTimeout:10000});
const frame=page=>page.frameLocator('iframe[title="Theme component showcase"]');
const card=(page,id)=>frame(page).locator(`[data-component="${id}"]`);
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
async function category(page,value){await frame(page).getByLabel('Component category',{exact:true}).selectOption(value);}
async function open(page,id){await page.goto('/themes/'+id);await expect(frame(page).locator('[data-showcase-target="count"]')).toContainText('65 component examples');}
async function screenshot(page,name){mkdirSync('var/component-showcase-verification',{recursive:true});await page.locator('iframe[title="Theme component showcase"]').screenshot({path:`var/component-showcase-verification/${process.env.APPEARANCE_PRODUCTION?'production':'development'}-${name}.png`});}

test('all Twig examples share the selected palette and real fonts independently of application appearance and variant actions',async({page})=>{
    const errors=[],frameRequests=[];page.on('pageerror',error=>errors.push(error.message));page.on('request',request=>{if(request.frame().parentFrame()&&/^https?:/.test(request.url()))frameRequests.push(request.url());});await login(page);await page.emulateMedia({colorScheme:'light'});await page.getByRole('button',{name:'Restore default appearance',exact:true}).click();await expect(page.locator('[data-appearance-target="status"]')).toHaveText('Applied Studio default.');
    const selected=await theme(page,{fonts:true}),application=await theme(page,{primary:'#64204C',base:'#FFF1FA'});
    await open(page,application.id);await expect(card(page,'buttons').getByRole('button',{name:'Primary',exact:true})).toHaveCSS('background-color',rgb('#64204C'));await expect(frame(page).locator('body')).toHaveCSS('background-color',rgb('#FFF1FA'));await page.getByRole('button',{name:'Apply theme',exact:true}).click();await expect(page.locator('[data-appearance-target="status"]')).toHaveText('Applied '+application.family.label+'.');const saved=await state(page);
    await open(page,selected.id);await expect(page.locator('body')).toHaveCSS('background-color',rgb('#FFF1FA'));await expect(frame(page).locator('html')).toHaveAttribute('data-theme',selected.document.cssName);
    await expect(frame(page).locator('body')).toHaveCSS('background-color',rgb('#F1F8FA'));await expect(card(page,'buttons').getByRole('button',{name:'Primary',exact:true})).toHaveCSS('background-color',rgb('#164E63'));
    await expect(frame(page).locator('body')).toHaveCSS('font-family',/^Inter,/);await expect(frame(page).locator('body')).toHaveCSS('font-weight','472');await expect(frame(page).locator('h1')).toHaveCSS('font-family',/^Lora,/);await expect(frame(page).locator('h1')).toHaveCSS('font-weight','631');await expect(card(page,'code').locator('code').first()).toHaveCSS('font-family',/^"?JetBrains Mono/);
    const faces=await frame(page).locator('body').evaluate(async()=>{await document.fonts.ready;return [...document.fonts].filter(face=>face.status==='loaded').map(face=>({family:face.family.replaceAll('"',''),weight:face.weight,style:face.style}));});for(const name of ['Inter','Lora','JetBrains Mono'])expect(faces.some(face=>face.family===name)).toBe(true);
    const colours=await frame(page).locator('[data-showcase-card]').evaluateAll(cards=>cards.map(card=>getComputedStyle(card).getPropertyValue('--color-primary').trim()));expect(new Set(colours)).toEqual(new Set(['#164E63']));
    const missingClasses=await frame(page).locator('body').evaluate(()=>{const selectors=[];const scan=rules=>{for(const rule of rules){if(rule.selectorText)selectors.push(rule.selectorText);if(rule.cssRules)scan(rule.cssRules);}};for(const sheet of document.styleSheets)scan(sheet.cssRules);return [...new Set([...document.querySelectorAll('[class]')].flatMap(el=>[...el.classList]))].filter(name=>!selectors.some(selector=>selector.includes('.'+CSS.escape(name))));});expect(missingClasses,'Every rendered class must have a compiled or generated CSS rule.').toEqual([]);
    await expect(frame(page).locator('[data-theme]')).toHaveCount(1);await expect(page.locator('iframe[title="Theme component showcase"]')).toHaveAttribute('sandbox','allow-scripts');
    expect(await frame(page).locator('body').evaluate(()=>{try{void parent.document.body;return false;}catch{return true;}})).toBe(true);
    await category(page,'actions');await screenshot(page,'light-actions');
    await page.getByRole('link',{name:'Showcase dark',exact:true}).click();await expect(frame(page).locator('html')).toHaveAttribute('data-color-mode','dark');await expect(frame(page).locator('html')).toHaveCSS('color-scheme','dark');await expect(frame(page).locator('body')).toHaveCSS('background-color',rgb('#16222C'));
    await expect(card(page,'buttons').getByRole('button',{name:'Primary',exact:true})).toHaveCSS('background-color',rgb('#A9D9F0'));expect(await state(page)).toEqual(saved);await category(page,'data');await screenshot(page,'dark-data');
    await page.getByRole('button',{name:'Switch to dark mode',exact:true}).click();await expect(page.locator('html')).toHaveAttribute('data-color-mode','dark');await expect(frame(page).locator('html')).toHaveAttribute('data-theme',selected.darkDocument.cssName);
    await page.getByRole('link',{name:'Showcase light',exact:true}).click();await expect(frame(page).locator('html')).toHaveAttribute('data-color-mode','light');await expect(page.locator('html')).toHaveAttribute('data-color-mode','dark');
    writeFileSync('var/component-showcase-verification/'+(process.env.APPEARANCE_PRODUCTION?'production':'development')+'-fonts.json',JSON.stringify({faces,componentCount:65,allPrimaryTokens:'#164E63',isolated:true,frameRequests},null,2));expect(errors).toEqual([]);expect(frameRequests).toEqual([]);
});

test('category search, all tab styles, dropdown, modal, accordion and swaps work locally with keyboard focus',async({page})=>{
    await login(page);const t=await theme(page);await open(page,t.id);const before=await state(page);
    await frame(page).getByLabel('Find a component',{exact:true}).fill('not a component');await expect(frame(page).locator('[data-showcase-target="empty"]')).toBeVisible();await frame(page).getByLabel('Find a component',{exact:true}).fill('');
    await category(page,'navigation');await expect(card(page,'buttons')).toBeHidden();
    for(const style of ['Underline','Box','Lift']){const tabs=frame(page).getByRole('tablist',{name:style+' sample tabs',exact:true});await tabs.getByRole('tab',{name:'Overview',exact:true}).focus();await page.keyboard.press('ArrowRight');await expect(tabs.getByRole('tab',{name:'Activity',exact:true})).toBeFocused();await expect(tabs.getByRole('tab',{name:'Activity',exact:true})).toHaveAttribute('aria-selected','true');await expect(tabs.locator('..').getByRole('tabpanel')).toHaveText('Today: button documentation was updated.');await page.keyboard.press('End');await expect(tabs.getByRole('tab',{name:'Team',exact:true})).toHaveAttribute('aria-selected','true');}
    await category(page,'actions');await card(page,'dropdowns').getByText('Project actions',{exact:true}).click();await expect(card(page,'dropdowns').getByRole('button',{name:'View activity',exact:true})).toBeVisible();await page.keyboard.press('Escape');await expect(card(page,'dropdowns').locator('details')).not.toHaveAttribute('open','');
    const opener=card(page,'modals').getByRole('button',{name:'Open sample dialog',exact:true});await opener.click();const dialog=frame(page).getByRole('dialog',{name:'Review your sample',exact:true});await expect(dialog).toBeVisible();await expect(dialog.locator('.modal-box')).toHaveCSS('background-color',rgb('#F1F8FA'));await dialog.getByRole('button',{name:'Close sample dialog',exact:true}).click();await expect(dialog).toBeHidden();await expect(opener).toBeFocused();await opener.click();await page.keyboard.press('Escape');await expect(dialog).toBeHidden();
    const swap=card(page,'swaps').getByRole('checkbox',{name:'Swap between play and pause',exact:true});await swap.check();await expect(swap).toBeChecked();await swap.uncheck();await expect(swap).not.toBeChecked();
    await swap.focus();await page.keyboard.press('Space');await expect(swap).toBeChecked();await expect(swap.locator('..')).toHaveCSS('outline-style','solid');
    await card(page,'fab').getByRole('button',{name:'Show quick actions',exact:true}).focus();await card(page,'fab').getByRole('button',{name:'Preview note',exact:true}).click();await expect(frame(page).locator('[data-showcase-target="status"]')).toContainText('Preview action: N.');
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
    await card(page,'file-inputs').getByLabel('Select a local sample file',{exact:false}).setInputFiles({name:'sample.txt',mimeType:'text/plain',buffer:Buffer.from('Only local data.')});await expect(frame(page).locator('[data-showcase-target="status"]')).toHaveText('Selected sample.txt locally. No file was uploaded.');
    const otp=card(page,'otp').getByRole('textbox');await otp.fill('12x456');await expect(otp).toHaveValue('12456');expect(writes).toEqual([]);expect(await state(page)).toEqual(before);
});

test('drawers trap and restore focus, toasts dismiss, carousels move and local navigation selections update',async({page})=>{
    await login(page);const t=await theme(page);await open(page,t.id);const before=await state(page);await category(page,'layout');
    const opener=card(page,'drawers').getByRole('button',{name:'Open sample drawer',exact:true});await opener.click();const drawer=frame(page).getByRole('dialog',{name:'Sample workspace',exact:true});await expect(drawer).toBeVisible();await expect(drawer.locator('aside')).toHaveCSS('background-color',rgb('#F1F8FA'));await expect(drawer.getByRole('button',{name:'Close sample drawer',exact:true})).toBeFocused();await page.keyboard.press('Shift+Tab');await expect(drawer.getByRole('button',{name:'Projects',exact:true})).toBeFocused();await page.keyboard.press('Tab');await expect(drawer.getByRole('button',{name:'Close sample drawer',exact:true})).toBeFocused();await page.keyboard.press('Escape');await expect(drawer).toBeHidden();await expect(opener).toBeFocused();
    await opener.click();await expect(drawer.getByRole('button',{name:'Close sample drawer',exact:true})).toBeFocused();const overlay=drawer.locator('.drawer-overlay'),bounds=await overlay.boundingBox();await overlay.click({position:{x:bounds.width-10,y:20}});await expect(drawer).toBeHidden();await expect(opener).toBeFocused();
    await card(page,'carousels').getByRole('button',{name:'Show slide 3',exact:true}).click();await expect.poll(()=>card(page,'carousels').locator('.carousel').evaluate(el=>el.scrollLeft)).toBeGreaterThan(0);
    await category(page,'feedback');await card(page,'toasts').getByRole('button',{name:'Show sample toast',exact:true}).click();await expect(frame(page).locator('[data-demo-toast]')).toBeVisible();await frame(page).getByRole('button',{name:'Dismiss notification',exact:true}).click();await expect(frame(page).locator('[data-demo-toast]')).toBeHidden();await card(page,'toasts').getByRole('button',{name:'Show sample toast',exact:true}).click();await expect(frame(page).locator('[data-demo-toast]')).toBeHidden({timeout:6500});
    await category(page,'navigation');await card(page,'pagination').getByRole('button',{name:'3',exact:true}).click();await expect(card(page,'pagination').locator('output')).toContainText('3 selected');await card(page,'dock').getByRole('button',{name:'Messages',exact:true}).click();await expect(card(page,'dock').getByRole('button',{name:'Messages',exact:true})).toHaveClass(/dock-active/);expect(await state(page)).toEqual(before);
    await category(page,'data');await card(page,'countdown').getByRole('button',{name:'Start 10-second countdown',exact:true}).click();await expect(frame(page).locator('[data-showcase-target="countdown"]')).toHaveText('9',{timeout:3000});
});

test('all categories stay within mobile and tablet viewports and tooltips, mockups and recovery stay usable',async({page})=>{
    await login(page);const t=await theme(page,{fonts:true});await open(page,t.id);
    for(const width of [375,768]){await page.setViewportSize({width,height:900});for(const name of ['actions','forms','navigation','data','feedback','layout','mockups']){await category(page,name);await expect(frame(page).locator(`section[data-category="${name}"]`)).toBeVisible();expect(await frame(page).locator('html').evaluate(el=>({width:innerWidth,scroll:el.scrollWidth}))).toEqual(expect.objectContaining({width:expect.any(Number)}));const overflow=await frame(page).locator('html').evaluate(el=>el.scrollWidth-innerWidth);expect(overflow,`${name} at ${width}px`).toBeLessThanOrEqual(1);}await screenshot(page,'mockups-'+width);}
    await page.setViewportSize({width:375,height:900});await category(page,'feedback');const tooltip=card(page,'tooltips').getByRole('button',{name:'Primary',exact:true});await tooltip.focus();await expect(tooltip).toBeFocused();await expect.poll(()=>tooltip.locator('..').evaluate(el=>getComputedStyle(el,'::before').opacity)).toBe('1');await expect(page.getByRole('button',{name:'Restore default appearance',exact:true})).toBeInViewport();await screenshot(page,'mobile-feedback');
});

test('all showcase links stay local and cross-category navigation never loads the studio login',async({page})=>{
    await login(page);const t=await theme(page);await open(page,t.id);const before=await state(page),url=page.url(),requests=[];
    page.on('request',request=>{if(request.frame().parentFrame()&&/^https?:/.test(request.url()))requests.push(request.url());});
    await category(page,'navigation');const project=card(page,'menus').getByRole('link',{name:'Design system',exact:true});await project.focus();await project.press('Enter');
    await expect(frame(page).getByLabel('Component category',{exact:true})).toHaveValue('all');await expect(card(page,'cards')).toBeVisible();
    await card(page,'inputs').getByLabel('Primary input',{exact:true}).fill('Keep showcase state');
    const links=frame(page).locator('a[href^="#"]'),count=await links.count();expect(count).toBeGreaterThan(10);
    for(let index=0;index<count;index++){
        await links.nth(index).click();await expect(frame(page).locator('[data-controller="showcase"]')).toBeVisible();
        expect(await frame(page).locator('body').evaluate(()=>location.href)).toMatch(/^about:srcdoc#/);
        await expect(frame(page).getByRole('heading',{name:'Sign in to your studio',exact:true})).toHaveCount(0);
    }
    await expect(card(page,'inputs').getByLabel('Primary input',{exact:true})).toHaveValue('Keep showcase state');
    expect(requests).toEqual([]);expect(page.url()).toBe(url);expect(await state(page)).toEqual(before);expect((await page.request.get('/themes',{maxRedirects:0})).status()).toBe(200);
});
