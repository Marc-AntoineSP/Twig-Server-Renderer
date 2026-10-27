import {chromium,expect} from '@playwright/test';
import {readFileSync,writeFileSync,mkdirSync} from 'node:fs';
import {randomBytes} from 'node:crypto';
import {login} from '../Browser/helpers.js';

const browser=await chromium.launch();const page=await browser.newPage({baseURL:process.env.STUDIO_BASE_URL||'http://studio'});
try{
    await login(page);const csrf=(await(await page.request.get('/api/v1/csrf')).json()).token;
    async function api(method,url,data,headers={}){const response=await page.request.fetch(url,{method,data,headers:{'X-CSRF-Token':csrf,...headers}});expect(response.ok(),`${url}: ${(await response.text()).slice(0,1500)}`).toBe(true);return response.json();}
    const suffix=randomBytes(5).toString('hex'),name='font-verification/showcase-'+suffix;
    const item=await api('POST','/api/v1/items',{name,kind:'component',title:'Portable font showcase'});
    const draft=await api('GET',`/api/v1/items/${item.id}/draft`);
    const manifest=draft.manifestData,sources=draft.sourceMap;
    const showcase=readFileSync('src/Theme/ThemeSample.php','utf8').match(/public const TWIG = <<<'TWIG'\n([\s\S]*?)\nTWIG;/)[1];
    const identifier='catalogue--'+name.replace('/','--'),controller=`assets/controllers/catalogue/${name}_controller.js`;
    sources[manifest.entry]=showcase+`<div data-controller="${identifier}"><button type="button" class="btn btn-primary" data-action="${identifier}#increment">Increment portable counter</button><output data-${identifier}-target="count">0</output><p>{{ message }}</p></div>`;
    sources[controller]=`import {Controller} from '@hotwired/stimulus'; export default class extends Controller {static targets=['count'];increment(){this.countTarget.textContent=String(Number(this.countTarget.textContent)+1);}}`;
    manifest.controllers=[{identifier,path:controller}];manifest.files=[];
    await api('PUT',`/api/v1/items/${item.id}/draft`,{title:manifest.title,manifestData:manifest,sourceMap:sources},{'If-Match':'"1"'});
    await api('POST',`/api/v1/items/${item.id}/releases`,{version:'1.0.0',expectedRevision:2});
    const layout=await api('POST','/api/v1/items',{name:'font-verification/layout-'+suffix,kind:'layout',title:'Font export layout'});
    await api('PUT',`/api/v1/items/${layout.id}/composition`,{expectedRevision:1,document:{schemaVersion:1,root:{type:'container',tag:'main',classes:['grid','gap-4','w-full'],children:[{type:'reference',name,version:'1.0.0',props:{message:'Exported Twig, real fonts and working Stimulus.'}}]}}});
    const release=await api('POST',`/api/v1/items/${layout.id}/releases`,{version:'1.0.0',expectedRevision:2});
    const tokens=JSON.parse(readFileSync('agent-kit/examples/packages/ember/meta/theme.json','utf8'));
    tokens.name='font-verification/theme-'+suffix;tokens.cssName='font-verification-'+suffix;tokens.default=true;tokens.geometry.highContrastBorders=true;
    tokens.typography={...tokens.typography,bodyFontId:'inter',headingFontId:'lora',codeFontId:'jetbrains-mono',bodyWeight:472,bodyStyle:'italic',headingWeight:631,headingStyle:'normal',codeWeight:400,codeStyle:'normal'};
    const selected=['inter','lora','jetbrains-mono'];
    for(const id of selected){const pin=JSON.parse(readFileSync('resources/library/fonts.lock.json','utf8')).families[id];const record=JSON.parse(readFileSync(`var/library-staging/fonts/${id}/${pin.version}/family.json`,'utf8'));const font={};for(const key of ['id','kind','family','category','source','sourceVersion','license','licensePath','licenseSha256','faces'])font[key]=record[key];font.faces=font.faces.map(({fontFamily,fontSubfamily,...face})=>face);tokens.fonts.push(font);}
    const theme=await api('POST','/api/v1/themes',{document:tokens});const tr=await api('POST',`/api/v1/themes/${theme.id}/releases`,{expectedRevision:1});
    mkdirSync('var/library-verification',{recursive:true});
    for(const format of ['collection','static']){
        const result=await api('POST','/api/v1/exports',{releaseId:release.id,format,themeReleaseId:tr.id});const archive=await page.request.get(result.downloadUrl);expect(archive.ok()).toBe(true);writeFileSync(`var/library-verification/font-${format}.zip`,await archive.body());
    }
    const bundle=await page.request.get(`/themes/${theme.id}/export/bundle.zip`);expect(bundle.ok()).toBe(true);writeFileSync('var/library-verification/font-theme-bundle.zip',await bundle.body());
    writeFileSync('var/library-verification/consumer-state.json',JSON.stringify({tokens,themeId:theme.id,themeReleaseId:tr.id,layoutId:layout.id,releaseId:release.id},null,2));
    console.log('PASS: exported a real composed layout, three local font families, variable weight/italic selections, licences, collection and offline snapshot.');
}finally{await browser.close();}
