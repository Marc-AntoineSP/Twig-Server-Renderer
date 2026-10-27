import {chromium,expect} from '@playwright/test';
import {readFileSync,writeFileSync} from 'node:fs';
import {createHash} from 'node:crypto';

const browser=await chromium.launch();const state=JSON.parse(readFileSync('var/library-verification/consumer-state.json','utf8'));
const reports=[];
try{
    for(const target of ['http://font-consumer:8080/','file:///app/var/library-verification/static/index.html']){
        const context=await browser.newContext({viewport:{width:1280,height:1000}}),page=await context.newPage(),external=[],fonts=[];
        await context.route('**/*',async route=>{const url=route.request().url();if(url.startsWith('http://font-consumer:8080/')||url.startsWith('file:')||url.startsWith('data:'))return route.continue();external.push(url);return route.abort();});
        const pending=[];page.on('response',response=>{if(response.request().resourceType()==='font')pending.push(response.body().then(bytes=>fonts.push({url:response.url(),bytes:bytes.length,sha256:createHash('sha256').update(bytes).digest('hex')})));});
        await page.goto(target);await page.locator('body').evaluate(()=>document.fonts.ready);
        const surface=page.locator('main').first();
        await expect(surface.getByRole('button',{name:'Increment portable counter',exact:true})).toBeVisible();
        await surface.getByRole('button',{name:'Increment portable counter',exact:true}).click();await expect(surface.locator('output')).toHaveText('1');
        await expect(page.locator('body')).toHaveCSS('font-weight','472');await expect(page.locator('body')).toHaveCSS('font-style','italic');
        const loaded=await page.locator('body').evaluate(async()=>{await document.fonts.ready;return [...document.fonts].filter(face=>face.status==='loaded').map(face=>({family:face.family,weight:face.weight,style:face.style}));});
        for(const family of ['Inter','Lora','JetBrains Mono'])expect(loaded.some(face=>face.family.replaceAll('"','')===family),`${target}: ${family} did not decode`).toBe(true);
        expect(loaded.some(face=>face.family.replaceAll('"','')==='Inter'&&face.style==='italic'&&face.weight==='100 900')).toBe(true);
        await surface.getByRole('button',{name:'Open modal example',exact:true}).click();await expect(surface.getByRole('dialog',{name:'Modal example'})).toBeVisible();await surface.getByRole('button',{name:'Close modal',exact:true}).click();await expect(surface.getByRole('dialog',{name:'Modal example'})).toBeHidden();
        await page.screenshot({path:`var/library-verification/${target.startsWith('file:')?'static':'consumer'}-desktop.png`,fullPage:true});
        await page.setViewportSize({width:375,height:900});expect(await page.locator('html').evaluate(el=>el.scrollWidth<=innerWidth+1)).toBe(true);await page.screenshot({path:`var/library-verification/${target.startsWith('file:')?'static':'consumer'}-mobile.png`,fullPage:true});
        await Promise.all(pending);expect(external).toEqual([]);
        const allowed=new Set(state.tokens.fonts.flatMap(font=>font.faces??[]).map(face=>face.sha256));for(const face of fonts)expect(allowed.has(face.sha256)).toBe(true);
        reports.push({target,loaded,fonts,externalRequests:external});await context.close();
    }
    writeFileSync('var/library-verification/consumer-browser.json',JSON.stringify(reports,null,2));
    console.log('PASS: offline consumer and file snapshot decoded real fonts, preserved variable italic weight 472, ran Stimulus/modal controls, and made zero external/studio requests at desktop/mobile widths.');
}finally{await browser.close();}
