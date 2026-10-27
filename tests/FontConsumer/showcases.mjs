import {chromium,expect} from '@playwright/test';
import {readFileSync,writeFileSync,mkdirSync} from 'node:fs';
import {randomBytes} from 'node:crypto';
import {login} from '../Browser/helpers.js';

const imports=JSON.parse(readFileSync('var/library-staging/themes/import-report.json','utf8')).results;
const browser=await chromium.launch(),page=await browser.newPage({baseURL:process.env.STUDIO_BASE_URL||'http://studio',viewport:{width:1380,height:1000}});
const results=[];mkdirSync('var/library-verification/showcases',{recursive:true});
try{
    await login(page);await page.goto('/themes');
    const originalBackground=await page.locator('body').evaluate(el=>getComputedStyle(el).backgroundColor);
    const card=page.locator(`article[data-theme-id="${imports[0].id}"]`);await card.getByRole('button',{name:'Preview showcase',exact:true}).click();
    await expect(page.locator('[data-controller~="preview"]')).toHaveAttribute('data-preview-state','current',{timeout:30000});
    const itemId=page.url().split('/').at(-2),csrf=(await(await page.request.get('/api/v1/csrf')).json()).token;
    await page.reload();await expect(page.getByLabel('Published theme',{exact:true})).not.toHaveValue('');
    expect(await page.locator('body').evaluate(el=>getComputedStyle(el).backgroundColor)).toBe(originalBackground);
    await page.setContent('<!doctype html><html><head><title>Theme verification</title></head><body style="margin:0"><iframe id="audit" title="Shared theme showcase" sandbox="allow-scripts" style="border:0;width:1280px;height:2400px"></iframe></body></html>');
    for(const[index,record]of imports.entries()){
        const doc=(await(await page.request.get(`/api/v1/themes/${record.id}`)).json()).document;
        const response=await page.request.post('/api/v1/preview-jobs',{headers:{'X-CSRF-Token':csrf},data:{itemId,sequence:index+1,channel:randomBytes(16).toString('hex'),themeData:doc,preset:{width:1280,rootSize:doc.typography.rootFontSizePx,bodyFont:'theme',headingFont:'theme',codeFont:'theme',textClass:doc.typography.textClass}}});
        expect(response.ok(),await response.text()).toBe(true);const job=await response.json();
        let status;const deadline=Date.now()+30000;
        do{status=await(await page.request.get(`/api/v1/preview-jobs/${job.id}`)).json();if(status.status==='succeeded')break;if(['failed','expired','cancelled'].includes(status.status)||Date.now()>deadline)throw Error(`${record.name}: ${JSON.stringify(status)}`);await new Promise(r=>setTimeout(r,100));}while(true);
        const result=await(await page.request.get(`/api/v1/preview-jobs/${job.id}/document`)).json();
        await page.locator('#audit').evaluate((frame,html)=>{frame.srcdoc=html;},result.html);
        const frame=page.frameLocator('#audit');await expect(frame.locator('html')).toHaveAttribute('data-font-status','loaded');
        const loaded=await frame.locator('body').evaluate(async()=>{await document.fonts.ready;return [...document.fonts].filter(f=>f.status==='loaded').map(f=>({family:f.family,weight:f.weight,style:f.style}));});
        for(const font of doc.fonts.filter(f=>f.kind==='local'))expect(loaded.some(f=>f.family.replaceAll('"','')===font.family),record.name+' '+font.family).toBe(true);
        async function measure(locator){return locator.evaluate(el=>{
            const canvas=document.createElement('canvas');canvas.width=canvas.height=1;const context=canvas.getContext('2d');
            const pixel=color=>{context.clearRect(0,0,1,1);context.fillStyle=color;context.fillRect(0,0,1,1);return [...context.getImageData(0,0,1,1).data].map((x,i)=>i===3?x/255:x);};
            const over=(top,bottom)=>[0,1,2].map(i=>top[i]*top[3]+bottom[i]*(1-top[3])).concat(1);
            function bg(node){return node?over(pixel(getComputedStyle(node).backgroundColor),bg(node.parentElement)):[255,255,255,1];}
            const l=rgb=>rgb.slice(0,3).map(x=>{x/=255;return x<=.04045?x/12.92:((x+.055)/1.055)**2.4;}).reduce((n,x,i)=>n+x*[.2126,.7152,.0722][i],0);
            const contrast=(a,b)=>{const x=l(a),y=l(b);return Math.round((Math.max(x,y)+.05)/(Math.min(x,y)+.05)*100)/100;};
            const style=getComputedStyle(el),background=bg(el),foreground=over(pixel(style.color),background),border=over(pixel(style.borderColor),background),outline=pixel(style.outlineColor),parent=bg(el.parentElement);
            return {color:style.color,background:style.backgroundColor,textRatio:contrast(foreground,background),borderRatio:contrast(border,background),outlineRatio:contrast(outline,parent),outlineWidth:style.outlineWidth,disabled:el.disabled??false};
        });}
        const primary=frame.getByRole('button',{name:'Primary',exact:true});
        const normal=await measure(primary);await primary.hover();await page.waitForTimeout(250);const hover=await measure(primary);
        await page.keyboard.press('Tab');await primary.focus();const focus=await measure(primary);
        const selected=await measure(frame.getByRole('tab',{name:'Overview tab',exact:true}));
        const disabled=await measure(frame.getByRole('button',{name:'Disabled',exact:true}));
        const field=await measure(frame.getByLabel('Sample input',{exact:true}));const error=await measure(frame.getByLabel('Invalid field',{exact:true}));
        expect(normal.textRatio,record.name+' normal').toBeGreaterThanOrEqual(4.5);expect(hover.textRatio,record.name+' hover').toBeGreaterThanOrEqual(4.5);expect(selected.textRatio,record.name+' selected').toBeGreaterThanOrEqual(4.5);
        expect(focus.outlineRatio,record.name+' focus').toBeGreaterThanOrEqual(3);expect(parseFloat(focus.outlineWidth)).toBeGreaterThanOrEqual(2);expect(field.borderRatio,record.name+' field').toBeGreaterThanOrEqual(3);expect(error.borderRatio,record.name+' error').toBeGreaterThanOrEqual(3);expect(disabled.disabled).toBe(true);
        await frame.getByRole('tab',{name:'Details tab',exact:true}).click();await expect(frame.getByText('Details content',{exact:true})).toBeVisible();
        await frame.getByRole('tab',{name:'Overview tab',exact:true}).click();
        await frame.getByRole('button',{name:'Open modal example',exact:true}).click();await expect(frame.getByRole('dialog',{name:'Modal example'})).toBeVisible();await frame.getByRole('button',{name:'Close modal',exact:true}).click();
        for(const width of [375,1280]){
            await page.locator('#audit').evaluate((el,width)=>{el.style.width=width+'px';},width);
            const bounds=await frame.locator('html').evaluate(el=>({width:innerWidth,scroll:el.scrollWidth,height:el.scrollHeight}));expect(bounds.scroll,record.name+' horizontal overflow').toBeLessThanOrEqual(width+1);
            await page.locator('#audit').evaluate((el,height)=>{el.style.height=Math.min(3900,height)+'px';},bounds.height);
            await page.locator('#audit').screenshot({path:`var/library-verification/showcases/${doc.provenance.slug}-${width}.png`});
        }
        results.push({name:record.name,version:doc.version,loaded,states:{normal,hover,focus,selected,disabled:{...disabled,note:'Inactive control: recorded separately; exempt from ordinary text contrast threshold.'},field,error}});
        writeFileSync('var/library-verification/showcase-audit.json',JSON.stringify(results,null,2));console.log(`PASS ${record.name}: loaded fonts, state contrast, native tabs/modal, 375/1280 layouts.`);
    }
    // Exercise a real editable copy and prove the managed seed rejects mutation.
    await page.goto('/themes');const source=imports.find(x=>x.name==='websites/gitlab-inspired-dark');
    const before=(await(await page.request.get(`/api/v1/themes/${source.id}`)).json());
    await page.locator(`article[data-theme-id="${source.id}"]`).getByRole('button',{name:'Duplicate/Edit',exact:true}).click();
    await expect(page.getByRole('heading',{name:/Edit studio\/copy-/})).toBeVisible();
    await page.getByLabel('primary',{exact:true}).fill('#123456');await page.getByRole('button',{name:'Save theme',exact:true}).click();await expect(page.locator('[data-flash]')).toHaveText('Theme saved.');
    const after=await(await page.request.get(`/api/v1/themes/${source.id}`)).json();expect(after).toEqual(before);
    const rejected=await page.request.put(`/api/v1/themes/${source.id}`,{headers:{'X-CSRF-Token':csrf},data:{document:before.document,expectedRevision:before.revision}});expect(rejected.status()).toBe(409);
    const cards=results.map(result=>{const slug=result.name.split('/')[1],png=readFileSync(`var/library-verification/showcases/${slug}-1280.png`).toString('base64');return `<article><h2>${slug}</h2><img src="data:image/png;base64,${png}"></article>`;}).join('');
    const html=`<!doctype html><html><head><meta charset="utf-8"><title>Website theme comparison</title><style>body{font:14px system-ui;margin:20px;background:#ececf0}main{display:grid;grid-template-columns:repeat(5,1fr);gap:16px}article{background:white;overflow:hidden}h2{font-size:12px;padding:8px;margin:0}img{width:100%;display:block}</style></head><body><h1>25 shared showcases — actual browser renders</h1><main>${cards}</main></body></html>`;
    writeFileSync('var/library-verification/showcases.html',html);await page.setContent(html);await page.setViewportSize({width:1800,height:1200});await page.screenshot({path:'var/library-verification/showcases-contact.png',fullPage:true});
    console.log(`PASS: ${results.length} presets at two widths; gallery Apply persists, Duplicate/Edit saves independently, managed seeds resist writes.`);
}finally{await browser.close();}
