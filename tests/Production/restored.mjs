import {chromium,expect} from '@playwright/test';
import {readFileSync,writeFileSync} from 'node:fs';
import {createHash} from 'node:crypto';
import {login} from '../Browser/helpers.js';
const expected=JSON.parse(readFileSync('var/consumer-state.json','utf8'));const browser=await chromium.launch();const page=await browser.newPage({baseURL:process.env.STUDIO_BASE_URL});
try{
 await login(page,'admin');const csrf=(await(await page.request.get('/api/v1/csrf')).json()).token;
 async function api(method,url,data){const r=await page.request.fetch(url,{method,data,headers:{'X-CSRF-Token':csrf}});expect(r.ok(),`${method} ${url}: ${r.status()}`).toBe(true);return r.json();}
 for(const draft of expected.drafts)expect(await api('GET',`/api/v1/items/${draft.id}/draft`)).toEqual(draft.data);
 expect((await api('GET',`/api/v1/themes/${expected.themeId}`)).document).toEqual(expected.themeDocument);
 const job=await api('POST','/api/v1/preview-jobs',{itemId:expected.layoutId,sequence:1,channel:'b'.repeat(32),preset:{themeReleaseId:expected.themeReleaseId,bodyFont:'theme',headingFont:'theme'}});let status;
 for(let i=0;i<250;i++){status=await api('GET',`/api/v1/preview-jobs/${job.id}`);if(['succeeded','failed'].includes(status.status))break;await new Promise(r=>setTimeout(r,100));}expect(status.status,JSON.stringify(status)).toBe('succeeded');const document=await api('GET',`/api/v1/preview-jobs/${job.id}/document`);
 await page.evaluate(html=>{const frame=document.createElement('iframe');frame.title='Restored preview';frame.setAttribute('sandbox','allow-scripts');frame.style.cssText='width:768px;height:700px';frame.srcdoc=html;document.querySelector('main').replaceChildren(frame);},document.html);const frame=page.frameLocator('iframe[title="Restored preview"]');await expect(frame.locator('html')).toHaveAttribute('data-theme',expected.cssName);await expect(frame.locator('body')).toHaveCSS('font-family',/Georgia/);await expect(frame.getByRole('status')).toHaveCount(2);await frame.getByRole('button',{name:'Dismiss notification',exact:true}).first().click();await expect(frame.getByRole('status')).toHaveCount(1);
 const output=await api('POST','/api/v1/exports',{releaseId:expected.layoutReleaseId,format:'collection',themeReleaseId:expected.themeReleaseId});const response=await page.request.get(output.downloadUrl);expect(response.ok()).toBe(true);const bytes=await response.body();expect(createHash('sha256').update(bytes).digest('hex')).toBe(createHash('sha256').update(readFileSync('var/consumer-artifacts/collection.zip')).digest('hex'));writeFileSync('var/consumer-artifacts/restored-collection.zip',bytes,{mode:0o600});
 console.log('PASS: fresh restored DB/blobs preserve exact saved drafts, typed theme and nested render/Stimulus; re-exported collection is byte-identical.');
}finally{await browser.close();}
