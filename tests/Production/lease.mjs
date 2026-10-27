import {chromium,expect} from '@playwright/test';
import {readFileSync,writeFileSync} from 'node:fs';
import {login} from '../Browser/helpers.js';
const state=JSON.parse(readFileSync('var/consumer-state.json'));const browser=await chromium.launch();const page=await browser.newPage({baseURL:process.env.STUDIO_BASE_URL});const phase=process.argv[2]||'recover';
try{
 await login(page,'admin');const csrf=(await(await page.request.get('/api/v1/csrf')).json()).token;
 const response=await page.request.post('/api/v1/preview-jobs',{headers:{'X-CSRF-Token':csrf},data:{itemId:state.drafts[0].id,sequence:100,channel:'c'.repeat(32)}});expect(response.status()).toBe(202);const job=await response.json();let ready=false;const start=Date.now();
 for(let i=0;i<1000;i++){
  const r=await page.request.get(`/api/v1/preview-jobs/${job.id}`);expect(r.ok()).toBe(true);const status=await r.json();
  if(status.status==='running'&&!ready){ready=true;writeFileSync(`var/production-test/${phase}-ready.json`,JSON.stringify({jobId:job.id}),{mode:0o600});}
  if(['succeeded','failed','expired'].includes(status.status)){
   expect(ready).toBe(true);
   if(phase==='recover'){expect(status.status,JSON.stringify(status)).toBe('succeeded');expect(Date.now()-start).toBeGreaterThan(45000);const document=await page.request.get(`/api/v1/preview-jobs/${job.id}/document`);expect(document.ok()).toBe(true);expect((await document.json()).html).toContain('data-updated="yes"');}
   else expect(status.status,JSON.stringify(status)).toBe('failed');
   writeFileSync(`var/production-test/${phase}-done.json`,JSON.stringify({jobId:job.id,status:status.status,elapsedMs:Date.now()-start}),{mode:0o600});console.log(`PASS: ${phase} actual interrupted preview reached ${status.status} after ${Date.now()-start} ms.`);break;
  }
  await new Promise(r=>setTimeout(r,100));
 }
 expect(JSON.parse(readFileSync(`var/production-test/${phase}-done.json`)).jobId).toBe(job.id);
}finally{await browser.close();}
