import {chromium,expect} from '@playwright/test';
import {readFileSync,writeFileSync,mkdirSync,chmodSync} from 'node:fs';
import {randomBytes} from 'node:crypto';
import {login,createAlert} from '../Browser/helpers.js';
const browser=await chromium.launch();const page=await browser.newPage({baseURL:process.env.STUDIO_BASE_URL||'http://studio'});page.setDefaultTimeout(30000);
try{
    await login(page,'admin');const csrf=(await(await page.request.get('/api/v1/csrf')).json()).token;
    async function api(method,url,data){const response=await page.request.fetch(url,{method,data,headers:{'X-CSRF-Token':csrf}});if(!response.ok())throw Error(`${method} ${url}: ${response.status()} ${(await response.text()).slice(0,1200)}`);return response.json();}
    const {item,manifest,sources}=await createAlert(page);const leaf=await api('POST',`/api/v1/items/${item.id}/releases`,{version:'1.0.0',expectedRevision:2});
    const entry=manifest.entry;sources[entry]=sources[entry].replace('role="status"','role="status" data-updated="yes"');
    const updated=await page.request.put(`/api/v1/items/${item.id}/draft`,{headers:{'X-CSRF-Token':csrf,'If-Match':'"2"'},data:{title:'Consumer updated alert',manifestData:manifest,sourceMap:sources}});expect(updated.status(),await updated.text()).toBe(200);await api('POST',`/api/v1/items/${item.id}/releases`,{version:'1.1.0',expectedRevision:3});
    const suffix=randomBytes(5).toString('hex');
    async function compose(kind,name,children){const i=await api('POST','/api/v1/items',{kind,name,title:`Consumer ${kind}`});await api('PUT',`/api/v1/items/${i.id}/composition`,{document:{schemaVersion:1,root:{type:'container',tag:'section',classes:['grid','gap-4','md:grid-cols-3'],children}},expectedRevision:1});const r=await api('POST',`/api/v1/items/${i.id}/releases`,{version:'1.0.0',expectedRevision:2});return {item:i,release:r};}
    const block=await compose('block',`consumer/block-${suffix}`,['First consumer instance','Second consumer instance'].map(message=>({type:'reference',name:item.name,version:'1.0.0',props:{...manifest.defaults,message}})));
    const layout=await compose('layout',`consumer/layout-${suffix}`,[{type:'reference',name:block.item.name,version:'1.0.0',props:{}}]);
    const tokens=JSON.parse(readFileSync('agent-kit/examples/packages/ember/meta/theme.json','utf8'));tokens.name=`consumer/theme-${suffix}`;tokens.cssName=`consumer-theme-${suffix}`;tokens.colors.primary='#123456';tokens.typography.bodyFontId='system-serif';
    const theme=await api('POST','/api/v1/themes',{document:tokens});const tr=await api('POST',`/api/v1/themes/${theme.id}/releases`,{expectedRevision:1});await api('POST',`/api/v1/theme-releases/${tr.id}/package`);
    const collection=await api('POST','/api/v1/exports',{releaseId:layout.release.id,format:'collection',themeReleaseId:tr.id});const archive=await page.request.get(collection.downloadUrl);expect(archive.ok()).toBe(true);
    mkdirSync('var/consumer-artifacts',{recursive:true,mode:0o700});writeFileSync('var/consumer-artifacts/collection.zip',await archive.body(),{mode:0o600});
    const source=await page.request.get(`/releases/${leaf.id}/source.zip`);writeFileSync('var/consumer-artifacts/alert.zip',await source.body(),{mode:0o600});
    await page.goto('/registry-tokens');await page.getByLabel('Token label',{exact:true}).fill(`Consumer integration ${suffix}`);await page.getByLabel('Package scopes',{exact:true}).fill('browser/* consumer/*');await page.getByRole('button',{name:'Create read token',exact:true}).click();const token=await page.locator('[data-read-token]').textContent();expect(token).toMatch(/^sgt_[0-9a-f]{64}$/);
    mkdirSync('var/consumer-auth/styleguide',{recursive:true,mode:0o700});chmodSync('var/consumer-auth',0o700);chmodSync('var/consumer-auth/styleguide',0o700);writeFileSync('var/consumer-auth/styleguide/auth.json',JSON.stringify({local:token}),{mode:0o600});chmodSync('var/consumer-auth/styleguide/auth.json',0o600);
    writeFileSync('var/consumer-state.json',JSON.stringify({alert:item.name,block:block.item.name,layout:layout.item.name,theme:tokens.name,cssName:tokens.cssName,entry}),{mode:0o600});console.log('PASS: published alert versions, nested layout and typed theme; private credentials stored outside the consumer.');
}finally{await browser.close();}
