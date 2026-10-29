import {test, expect} from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import {readFileSync,writeFileSync} from 'node:fs';
import {randomBytes} from 'node:crypto';
import {login} from './helpers.js';

async function family(page) {
    const document=JSON.parse(readFileSync('agent-kit/examples/packages/ember/meta/theme.json','utf8'));
    document.name='browser/read-choice-'+randomBytes(6).toString('hex');document.cssName=document.name.replace('/','-');
    const csrf=(await(await page.request.get('/api/v1/csrf')).json()).token;
    const response=await page.request.post('/api/v1/themes',{headers:{'X-CSRF-Token':csrf},data:{document,variants:'pair',familyLabel:document.name}});
    expect(response.status()).toBe(201);return response.json();
}
const menu=page=>page.locator('[data-appearance-target="menu"]');
const summary=page=>menu(page).locator('summary');
const select=page=>page.getByLabel('Application theme family',{exact:true});
async function open(page) {await summary(page).focus();await page.keyboard.press('Enter');await expect(menu(page)).toHaveAttribute('open','');}
async function close(page) {await summary(page).focus();await page.keyboard.press('Enter');await expect(menu(page)).not.toHaveAttribute('open','');}

for(const width of [390,1440])for(const mode of ['light','dark'])test(`read performance: lazy appearance choices and keyboard ${width}px ${mode}`,async({page},info)=>{
    await page.setViewportSize({width,height:1000});await page.emulateMedia({colorScheme:mode});await login(page);
    await page.getByRole('button',{name:'Restore default appearance',exact:true}).click();await expect(page.locator('[data-appearance-target="status"]')).toHaveText('Applied Studio default.');
    const f=await family(page);let requests=0;page.on('request',r=>{if(new URL(r.url()).pathname==='/api/v1/appearance/families')requests++;});
    await page.goto('/catalogue');await expect(select(page).locator('option')).toHaveCount(1);expect(requests).toBe(0);
    const initial=await select(page).inputValue();await open(page);await expect(select(page).locator(`option[value="${f.family.id}"]`)).toHaveCount(1);expect(requests).toBe(1);await expect(select(page)).toHaveValue(initial);
    await select(page).selectOption(f.family.id);await select(page).focus();await page.keyboard.press('Tab');await expect(page.getByRole('button',{name:'Apply selected theme',exact:true})).toBeFocused();await page.keyboard.press('Enter');
    await expect(page.locator('html')).toHaveAttribute('data-appearance-family',f.family.id);await page.reload();await expect(select(page).locator('option')).toHaveCount(1);await expect(select(page)).toHaveValue(f.family.id);
    await open(page);await expect(select(page)).not.toHaveAttribute('aria-busy');await expect(select(page)).toHaveValue(f.family.id);
    const report=await new AxeBuilder({page}).include('[data-appearance-controls]').withTags(['wcag2a','wcag2aa','wcag21a','wcag21aa']).analyze();
    writeFileSync(info.outputPath('read-performance-accessibility.json'),JSON.stringify({violations:report.violations,incomplete:report.incomplete},null,2));expect(report.violations).toEqual([]);
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);await page.screenshot({path:info.outputPath(`read-choices-${width}-${mode}.png`)});
});

test('read performance: failed and obsolete family loads keep current choice and retry on reopen',async({page})=>{
    await login(page);const f=await family(page);await page.goto('/catalogue');const initial=await select(page).inputValue();
    await page.route('**/api/v1/appearance/families',route=>route.fulfill({status:503,json:{message:'Choices temporarily unavailable.'}}));
    await open(page);await expect(page.locator('[data-appearance-target="status"]')).toHaveText('Choices temporarily unavailable.');await expect(select(page)).toHaveValue(initial);await expect(select(page).locator('option')).toHaveCount(1);
    await close(page);await page.unroute('**/api/v1/appearance/families');await open(page);await expect(select(page).locator(`option[value="${f.family.id}"]`)).toHaveCount(1);await close(page);
    let release,started;const held=new Promise(resolve=>release=resolve),fetched=new Promise(resolve=>started=resolve);
    await page.route('**/api/v1/appearance/families',async route=>{const response=await route.fetch();started();await held;await route.fulfill({response}).catch(()=>{});});
    await open(page);await fetched;await select(page).selectOption(f.family.id);await close(page);release();await page.waitForTimeout(150);await expect(select(page)).toHaveValue(f.family.id);await expect(select(page)).not.toHaveAttribute('aria-busy');
    await page.unroute('**/api/v1/appearance/families');await open(page);await expect(select(page)).not.toHaveAttribute('aria-busy');await expect(select(page)).toHaveValue(f.family.id);
});
