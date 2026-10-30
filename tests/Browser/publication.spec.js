import {test,expect} from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import {randomBytes} from 'node:crypto';
import {writeFileSync} from 'node:fs';
import {login} from './helpers.js';

async function fixture(page,examples=8) {
    const csrf=(await(await page.request.get('/api/v1/csrf')).json()).token;
    const name='browser/publication-'+randomBytes(5).toString('hex');
    const created=await page.request.post('/api/v1/items',{headers:{'X-CSRF-Token':csrf},data:{name,kind:'component',title:'Publication request'}});
    expect(created.status()).toBe(201);const draft=await created.json();
    draft.sourceMap['meta/examples.json']=JSON.stringify(Array.from({length:examples},(_,i)=>({name:'Example '+i,props:{message:'Publication example '+i}})));
    const saved=await page.request.put(`/api/v1/items/${draft.id}/draft`,{headers:{'X-CSRF-Token':csrf,'If-Match':'"1"'},data:{title:draft.title,manifestData:draft.manifestData,sourceMap:draft.sourceMap}});
    expect(saved.status()).toBe(200);return {...await saved.json(),csrf};
}
const form=page=>page.locator('form[data-controller="publication"]');
async function open(page,id){await page.goto(`/catalogue/${id}`);await page.locator('#releases > summary').click();await expect(form(page)).toHaveAttribute('data-publication-state','ready');}

for(const width of [390,1440])for(const mode of ['light','dark'])test(`publication: held native submit, real immutable result and keyboard ${width}px ${mode}`,async({page},info)=>{
    await page.setViewportSize({width,height:1000});await page.emulateMedia({colorScheme:mode});await login(page);
    await page.getByRole('button',{name:'Restore default appearance',exact:true}).click();await expect(page.locator('[data-appearance-target="status"]')).toHaveText('Applied Studio default.');
    const item=await fixture(page);await open(page,item.id);
    await expect(form(page)).toContainText('up to 20 examples');await expect(form(page)).toContainText('Leaving the page does not confirm cancellation');
    const version=page.getByLabel('Release version',{exact:true});await version.fill('1.0.0');await version.focus();await page.keyboard.press('Tab');await expect(form(page).getByRole('button',{name:'Publish release',exact:true})).toBeFocused();
    // Playwright waits for native navigation before querying the old document.
    // Hold only its default submit action to inspect the real controller state,
    // then release the unchanged native POST. No worker/result is mocked.
    await form(page).evaluate(el=>el.addEventListener('submit',event=>event.preventDefault(),{once:true}));
    await page.keyboard.press('Enter');await expect(form(page)).toHaveAttribute('data-publication-state','submitting');
    await expect(form(page).getByRole('button',{name:'Publishing…',exact:true})).toBeDisabled();await expect(form(page).getByRole('status')).toContainText('Publishing saved revision 2 as 1.0.0.');
    // A second submit event cannot send another request from this pending document.
    expect(await form(page).evaluate(el=>el.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true})))).toBe(false);
    const report=await new AxeBuilder({page}).include('#releases').withTags(['wcag2a','wcag2aa','wcag21a','wcag21aa']).analyze();
    writeFileSync(info.outputPath('publication-accessibility.json'),JSON.stringify({violations:report.violations,incomplete:report.incomplete},null,2));expect(report.violations).toEqual([]);
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);await page.screenshot({path:info.outputPath(`publication-pending-${width}-${mode}.png`)});
    await form(page).evaluate(el=>setTimeout(()=>HTMLFormElement.prototype.submit.call(el),0));
    await expect(page.locator('[data-flash]')).toHaveText('Release published immutably.',{timeout:20000});await page.locator('#releases > summary').click();
    await expect(page.getByLabel('Installation command for 1.0.0',{exact:true})).toHaveValue(`php bin/console styleguide:require ${item.name}@1.0.0 --registry=local`);
    const duplicate=await page.request.post(`/api/v1/items/${item.id}/releases`,{headers:{'X-CSRF-Token':item.csrf},data:{version:'1.0.0',expectedRevision:2}});expect(duplicate.status()).toBe(409);
    await page.goBack();if(!await page.locator('#releases').evaluate(el=>el.open))await page.locator('#releases > summary').click();await expect(form(page).getByRole('button',{name:'Publish release',exact:true})).toBeEnabled();
});

test('publication: native validation leaves the form usable and no-JavaScript publishing stays synchronous',async({page,browser})=>{
    await login(page);const item=await fixture(page,1);await open(page,item.id);
    const version=page.getByLabel('Release version',{exact:true});await version.fill('not-a-version');await form(page).getByRole('button',{name:'Publish release',exact:true}).click();
    await expect(form(page)).toHaveAttribute('data-publication-state','ready');await expect(form(page).getByRole('button',{name:'Publish release',exact:true})).toBeEnabled();
    const context=await browser.newContext({baseURL:process.env.STUDIO_BASE_URL,javaScriptEnabled:false,storageState:await page.context().storageState()});
    try{const native=await context.newPage();await native.goto(`/catalogue/${item.id}`);await native.locator('#releases > summary').click();await native.getByLabel('Release version',{exact:true}).fill('1.0.0');await native.getByRole('button',{name:'Publish release',exact:true}).click();await expect(native.locator('[data-flash]')).toHaveText('Release published immutably.',{timeout:20000});await native.locator('#releases > summary').click();await expect(native.getByLabel('Installation command for 1.0.0',{exact:true})).toBeVisible();}finally{await context.close();}
});
