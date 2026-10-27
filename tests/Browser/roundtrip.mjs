import {chromium,expect} from '@playwright/test';
import {readFileSync,writeFileSync,mkdirSync} from 'node:fs';
import {randomBytes,createHash} from 'node:crypto';
import {login,createAlert} from './helpers.js';
const browser=await chromium.launch();const page=await browser.newPage({baseURL:process.env.STUDIO_BASE_URL||'http://localhost:8095'});page.setDefaultTimeout(30000);
const root='var/roundtrip';mkdirSync(root,{recursive:true,mode:0o700});
try{
    await login(page);let csrf=(await(await page.request.get('/api/v1/csrf')).json()).token;
    async function api(method,url,data){const r=await page.request.fetch(url,{method,data,headers:{'X-CSRF-Token':csrf}});if(!r.ok())throw Error(`${method} ${url}: ${r.status()} ${(await r.text()).slice(0,1500)}`);return r.json();}
    async function publish(item,revision){return api('POST',`/api/v1/items/${item.id}/releases`,{version:'1.0.0',expectedRevision:revision});}
    async function rendered(itemId,themeReleaseId){
        const job=await api('POST','/api/v1/preview-jobs',{itemId,sequence:1,channel:'a'.repeat(32),preset:{themeReleaseId,bodyFont:'theme',headingFont:'theme'}});let status;
        for(let i=0;i<200;i++){status=await api('GET',`/api/v1/preview-jobs/${job.id}`);if(['succeeded','failed'].includes(status.status))break;await new Promise(r=>setTimeout(r,100));}
        expect(status.status,JSON.stringify(status)).toBe('succeeded');return api('GET',`/api/v1/preview-jobs/${job.id}/document`);
    }
    async function mount(html){
        await page.goto('/');await page.addStyleTag({content:'.sf-toolbar { display: none !important; }'});await page.evaluate(html=>{const frame=document.createElement('iframe');frame.title='Round trip preview';frame.setAttribute('sandbox','allow-scripts');frame.style.cssText='width:768px;height:800px;border:0;display:block';frame.srcdoc=html;document.querySelector('main').replaceChildren(frame);},html);
        const frame=page.frameLocator('iframe[title="Round trip preview"]');await expect(frame.getByRole('status')).toHaveCount(2);await frame.locator('body').evaluate(()=>document.fonts.ready);return frame;
    }
    async function composed(kind,name,reference){const item=await api('POST','/api/v1/items',{kind,name,title:`Round-trip ${kind}`});const document={schemaVersion:1,root:{type:'container',tag:'section',classes:['grid','gap-4','md:grid-cols-2'],children:reference}};await api('PUT',`/api/v1/items/${item.id}/composition`,{document,expectedRevision:1});return {item,release:await publish(item,2)};}
    if(process.argv[2]==='prepare'){
        const {item:alert,manifest}=await createAlert(page);const leaf=await publish(alert,2);const suffix=randomBytes(5).toString('hex');
        const refs=['First portable instance','Second portable instance'].map(message=>({type:'reference',name:alert.name,version:'1.0.0',props:{...manifest.defaults,message}}));refs.push({type:'text',value:'{{ literal }} <safe text>'});
        const block=await composed('block',`roundtrip/block-${suffix}`,refs);const layout=await composed('layout',`roundtrip/layout-${suffix}`,[{type:'reference',name:block.item.name,version:'1.0.0',props:{}}]);
        const tokens=JSON.parse(readFileSync('agent-kit/examples/packages/ember/meta/theme.json','utf8'));tokens.name=`roundtrip/theme-${suffix}`;tokens.cssName=`roundtrip-theme-${suffix}`;tokens.colors.primary='#123456';tokens.typography.bodyFontId='system-serif';
        const theme=await api('POST','/api/v1/themes',{document:tokens});const tr=await api('POST',`/api/v1/themes/${theme.id}/releases`,{expectedRevision:1});
        const archive=await api('POST','/api/v1/exports',{releaseId:layout.release.id,format:'collection',themeReleaseId:tr.id});const response=await page.request.get(archive.downloadUrl);expect(response.ok()).toBe(true);writeFileSync(`${root}/collection.zip`,await response.body(),{mode:0o600});
        const expected={tokens,rootName:layout.item.name,themeName:tokens.name,packages:{}};
        for(const item of [alert,block.item,layout.item]){const draft=await api('GET',`/api/v1/items/${item.id}/draft`);expected.packages[item.name]={manifestData:draft.manifestData,sourceMap:draft.sourceMap};}
        const document=await rendered(layout.item.id,tr.id);await mount(document.html);const png=await page.locator('iframe[title="Round trip preview"]').screenshot({animations:'disabled'});expected.previewSha256=createHash('sha256').update(png).digest('hex');writeFileSync(`${root}/source-preview.png`,png,{mode:0o600});
        writeFileSync(`${root}/expected.json`,JSON.stringify(expected),{mode:0o600});console.log('PASS: created, composed, themed and exported a real complete collection.');
    }else if(process.argv[2]==='verify'){
        const expected=JSON.parse(readFileSync(`${root}/expected.json`,'utf8'));const buffer=readFileSync(`${root}/collection.zip`);
        async function importMode(mode,plan){const r=await page.request.post('/api/v1/imports',{headers:{'X-CSRF-Token':csrf},multipart:{mode,...(plan?{expectedPlan:plan}:{}),file:{name:'collection.zip',mimeType:'application/zip',buffer}}});if(!r.ok())throw Error(`Import ${r.status()}: ${await r.text()}`);return r.json();}
        const plan=await importMode('dry-run');expect(plan.plan.packages).toHaveLength(4);expect(plan.plan.packages.every(p=>p.action==='create-release')).toBe(true);const imported=await importMode('apply',plan.planHash);expect(imported.result.applied).toBe(4);
        for(const [name,data] of Object.entries(expected.packages)){const draft=await api('GET',`/api/v1/items/${imported.result.items[name]}/draft`);expect(draft.sourceMap).toEqual(data.sourceMap);const normalize=m=>({...m,version:'1.0.0'});expect(normalize(draft.manifestData)).toEqual(normalize(data.manifestData));}
        const theme=await api('GET',`/api/v1/themes/${imported.result.themes[expected.themeName]}`);expect(theme.document).toEqual(expected.tokens);
        const itemId=imported.result.items[expected.rootName];const document=await rendered(itemId,imported.result.themeReleases[expected.themeName]);const frame=await mount(document.html);
        await expect(frame.locator('html')).toHaveAttribute('data-theme',expected.tokens.cssName);await expect(frame.locator('body')).toHaveCSS('font-family',/Georgia/);await expect(frame.getByRole('status')).toHaveText([/First portable instance/,/Second portable instance/]);await expect(frame.locator('body')).toContainText('{{ literal }} <safe text>');
        const png=await page.locator('iframe[title="Round trip preview"]').screenshot({animations:'disabled'});writeFileSync(`${root}/imported-preview.png`,png,{mode:0o600});expect(createHash('sha256').update(png).digest('hex')).toBe(expected.previewSha256);
        await frame.getByRole('button',{name:'Dismiss notification',exact:true}).first().click();await expect(frame.getByRole('status')).toHaveCount(1);
        console.log('PASS: clean studio imported all exact dependencies/theme, normalized sources match and nested Stimulus renders correctly.');
    }else throw Error('Use prepare or verify.');
}finally{await browser.close();}
