import {test,expect} from '@playwright/test';
import {writeFileSync,unlinkSync} from 'node:fs';
import {randomBytes} from 'node:crypto';
import {login,createAlert} from './helpers.js';
function entry(zip,wanted){let offset=0;while(zip.readUInt32LE(offset)===0x04034b50){const method=zip.readUInt16LE(offset+8),size=zip.readUInt32LE(offset+18),names=zip.readUInt16LE(offset+26),extra=zip.readUInt16LE(offset+28);const name=zip.subarray(offset+30,offset+30+names).toString();const start=offset+30+names+extra;if(name===wanted){expect(method).toBe(0);return zip.subarray(start,start+size);}offset=start+size;}throw Error('Missing ZIP entry '+wanted);}
test('reviewed package import and static download use real immutable bytes and work offline',async({page})=>{
    test.setTimeout(90000);await login(page);const {item}=await createAlert(page);const csrf=(await(await page.request.get('/api/v1/csrf')).json()).token;
    const published=await page.request.post(`/api/v1/items/${item.id}/releases`,{headers:{'X-CSRF-Token':csrf},data:{version:'1.0.0',expectedRevision:2}});expect(published.status()).toBe(201);const release=await published.json();
    await page.goto(`/catalogue/${item.id}`);await page.getByLabel('Export format for 1.0.0',{exact:true}).selectOption('static');await page.getByRole('button',{name:'Build export 1.0.0',exact:true}).click();
    const link=page.getByRole('link',{name:'Download ready export',exact:true});await expect(link).toBeVisible({timeout:20000});const download=await page.request.get(await link.getAttribute('href'));expect(download.status()).toBe(200);const bytes=await download.body();const html=entry(bytes,'index.html');
    const random=randomBytes(6).toString('hex');const offline=`var/browser/offline-${random}.html`;const source=`var/browser/source-${random}.zip`;
    try{
        writeFileSync(offline,html,{mode:0o600});const studioRequests=[];page.on('request',r=>{if(/^https?:/.test(r.url()))studioRequests.push(r.url());});
        await page.goto(`file:///app/${offline}`);await expect(page.getByRole('status')).toContainText('Your component is ready.');await expect(page.getByRole('status')).toHaveCSS('display','flex');await page.getByRole('button',{name:'Dismiss notification',exact:true}).click();await expect(page.getByRole('status')).toHaveCount(0);expect(studioRequests).toEqual([]);
        const sourceResponse=await page.request.get(`/releases/${release.id}/source.zip`);expect(sourceResponse.status()).toBe(200);writeFileSync(source,await sourceResponse.body(),{mode:0o600});
        await page.goto('/imports');await page.getByLabel('Complete local package (10 MiB maximum)',{exact:true}).setInputFiles(source);await page.getByRole('button',{name:'Dry-run import',exact:true}).click();await expect(page.locator('[data-package-import-target="status"]')).toContainText('Validation passed',{timeout:20000});await expect(page.getByLabel('Import plan',{exact:true})).toContainText('skip-identical');
        await page.getByRole('button',{name:'Apply reviewed import',exact:true}).click();await expect(page.locator('[data-package-import-target="status"]')).toContainText('Imported 0 release(s); 1 identical release(s) preserved.',{timeout:20000});await expect(page.getByRole('link',{name:`Open ${item.name}`,exact:true})).toBeVisible();
    }finally{try{unlinkSync(offline);}catch{}try{unlinkSync(source);}catch{}}
});
