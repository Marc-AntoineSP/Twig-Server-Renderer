import {chromium,expect} from '@playwright/test';
import {readFileSync,writeFileSync,mkdirSync} from 'node:fs';
import {createHash} from 'node:crypto';
import {login} from '../Browser/helpers.js';

const pins=JSON.parse(readFileSync('resources/library/fonts.lock.json','utf8')).families;
const browser=await chromium.launch(),page=await browser.newPage({baseURL:process.env.STUDIO_BASE_URL||'http://studio'}),results=[];
try{
    await login(page);await page.setContent('<!doctype html><html><body><iframe id="font" title="Font decoder check" sandbox="allow-scripts" style="width:800px;height:240px"></iframe></body></html>');
    const text='English: ABCDEFGHIJKLMNOPQRSTUVWXYZ abcdefghijklmnopqrstuvwxyz 0123456789. Français : ÀÂÆÇÈÉÊËÎÏÔŒÙÛÜŸ àâæçèéêëîïôœùûüÿ € ‘ ’ “ ” – — … « »';
    for(const[id,pin]of Object.entries(pins)){
        const record=JSON.parse(readFileSync(`var/library-staging/fonts/${id}/${pin.version}/family.json`,'utf8'));
        const styles=[...new Set(record.faces.map(face=>face.style))];
        const cases=[...new Map(record.faces.map(face=>[`${face.style}:${face.weightMin}:${face.weightMax}`,{style:face.style,weight:Math.max(face.weightMin,Math.min(face.weightMax,400))}])).values()];
        for(const {style,weight} of cases){
            const response=await page.request.get(`/api/v1/fonts/${id}/specimen?`+new URLSearchParams({text,style,weight:String(weight)}));expect(response.ok(),`${id}: ${await response.text()}`).toBe(true);
            const result=await response.json();
            const hashes=[...result.html.matchAll(/data:font\/woff2;base64,([A-Za-z0-9+/=]+)/g)].map(match=>createHash('sha256').update(Buffer.from(match[1],'base64')).digest('hex'));
            const selected=record.faces.filter(face=>face.style===style&&face.weightMin<=weight&&face.weightMax>=weight);
            expect(new Set(hashes)).toEqual(new Set(selected.map(face=>face.sha256)));
            await page.locator('#font').evaluate((iframe,html)=>{iframe.srcdoc=html;},result.html);
            const frame=page.frameLocator('#font');await expect(frame.locator('html')).toHaveAttribute('data-font-status','loaded');
            const faces=await frame.locator('body').evaluate(async()=>{await document.fonts.ready;return [...document.fonts].filter(face=>face.status==='loaded').map(face=>({family:face.family,style:face.style,weight:face.weight}));});
            expect(faces.length,id+' '+style).toBeGreaterThan(0);expect(faces.every(face=>face.family.replaceAll('"','')===record.family&&face.style===style)).toBe(true);
            results.push({id,family:record.family,style,weight,faces,embeddedBytes:selected.reduce((n,face)=>n+face.bytes,0)});
        }
        console.log(`PASS ${record.family}: real decoded ${styles.join('/')} WOFF2, French/English specimen, matching pinned bytes.`);
    }
    mkdirSync('var/library-verification',{recursive:true});writeFileSync('var/library-verification/fontfaces.json',JSON.stringify({families:Object.keys(pins).length,styleCases:new Set(results.map(x=>x.id+':'+x.style)).size,weightStyleCases:results.length,decodedFaces:results.reduce((n,x)=>n+x.faces.length,0),results},null,2));
}finally{await browser.close();}
