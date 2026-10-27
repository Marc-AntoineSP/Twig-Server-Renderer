import {chromium} from '@playwright/test';
import {readFile} from 'node:fs/promises';
import {download,atomic,digest} from './network.mjs';

const manifest=JSON.parse(await readFile('resources/library/websites.json','utf8'));
const only=process.argv.find(x=>x.startsWith('--reference='))?.slice(12);
if(process.argv.includes('--dry-run')){console.log(JSON.stringify(manifest.references.filter(x=>!only||x.id===only),null,2));process.exit(0);}
const browser=await chromium.launch({headless:true});
const results=[];let statuses={};try{statuses=JSON.parse(await readFile('resources/library/source-status.json','utf8'));}catch{}
const robots=new Map();
async function permitted(url,hosts){
    const parsed=new URL(url);
    if(!robots.has(parsed.origin))robots.set(parsed.origin,(async()=>{
        let text='';
        try{text=(await download(parsed.origin+'/robots.txt',hosts,{limit:524288})).bytes.toString();}catch(error){if(!/HTTP (400|401|403|404|410)/.test(String(error)))throw Error('robots.txt unavailable: '+error.message);}
        const groups=[];let agents=[],rules=[];
        for(const line of text.split('\n')){
            const match=line.replace(/#.*$/,'').match(/^\s*(User-agent|Disallow|Allow):\s*(.*?)\s*$/i);if(!match)continue;
            if(match[1].toLowerCase()==='user-agent'){
                if(rules.length){groups.push({agents,rules});agents=[];rules=[];}agents.push(match[2].toLowerCase());
            }else if(match[2])rules.push({allow:match[1].toLowerCase()==='allow',pattern:match[2]});
        }
        groups.push({agents,rules});
        const specific=groups.filter(g=>g.agents.some(a=>a!=='*'&&'styleguidestudio-research'.includes(a)));
        return (specific.length?specific:groups.filter(g=>g.agents.includes('*'))).flatMap(g=>g.rules);
    })());
    const rules=await robots.get(parsed.origin);
    const matches=rules.filter(rule=>new RegExp('^'+rule.pattern.replace(/[.+?^{}()|[\]\\]/g,'\\$&').replaceAll('*','.*').replace(/\$$/,'$')).test(parsed.pathname+parsed.search)).sort((a,b)=>b.pattern.length-a.pattern.length||Number(b.allow)-Number(a.allow));
    return !matches.length||matches[0].allow;
}
let active=0;const waiting=[];
async function acquire(){if(active>=3)await new Promise(resolve=>waiting.push(resolve));active++;}
function release(){active--;waiting.shift()?.();}
for(const source of manifest.references.filter(x=>!only||x.id===only)){
    const contexts=[];const diagnostics=[];
    try{
        if(!await permitted(source.url,source.hosts))throw Error('robots.txt disallows the reference surface for this maintenance client.');
        const modes=[];
        for(const mode of source.modes){
            const context=await browser.newContext({javaScriptEnabled:source.browserScripts===true,serviceWorkers:'block',colorScheme:mode,viewport:{width:1280,height:900},userAgent:'StyleguideStudio-Research/1.0'});contexts.push(context);
            await context.routeWebSocket('**/*', socket=>socket.close());
            const requests=[];diagnostics.push({mode,requests});let count=0,total=0;
            await context.route('**/*',async route=>{
                const req=route.request();
                if(req.method()!=='GET'||![...['document','stylesheet'],...(source.browserScripts?['script']:[])].includes(req.resourceType()))return route.abort();
                await acquire();
                try{
                    if(++count>(source.requestLimit??24))throw Error('Reference request budget reached.');
                    if(!await permitted(req.url(),source.hosts))throw Error('robots.txt disallows subrequest.');
                    const result=await download(req.url(),source.hosts,{limit:4194304,refresh:process.argv.includes('--refresh')});
                    if((total+=result.bytes.length)>12582912)throw Error('Reference exceeds total byte budget.');
                    requests.push({url:result.url,sha256:result.sha256,retrievedAt:result.retrievedAt,contentType:result.contentType});
                    await route.fulfill({status:200,headers:{'content-type':result.contentType,'access-control-allow-origin':'*'},body:result.bytes});
                }catch(error){requests.push({url:req.url(),error:error.message});await route.abort();}finally{release();}
            });
            const page=await context.newPage();
            await page.goto(source.url,{waitUntil:'load',timeout:45000});
            if(source.browserScripts)await page.waitForTimeout(2500);
            if(source.bodyAttributes?.[mode])await page.evaluate(attrs=>{for(const[name,value]of Object.entries(attrs))document.body.setAttribute(name,value);},source.bodyAttributes[mode]);
            if(source.attributes?.[mode])await page.evaluate(attrs=>{for(const[name,value]of Object.entries(attrs))value===null?document.documentElement.removeAttribute(name):document.documentElement.setAttribute(name,value);},source.attributes[mode]);
            const observed=await page.evaluate(({selectors,variables})=>{
                const sample=selector=>{const el=document.querySelector(selector);if(!el)return null;const style=getComputedStyle(el);return {selector,tag:el.tagName,href:el.getAttribute('href'),text:el.textContent.trim().slice(0,100),background:style.backgroundColor,color:style.color,fontFamily:style.fontFamily,fontSize:style.fontSize,lineHeight:style.lineHeight,borderRadius:style.borderRadius,borderColor:style.borderColor,borderWidth:style.borderWidth,boxShadow:style.boxShadow,backgroundImage:style.backgroundImage};};
                const root=getComputedStyle(document.documentElement);
                const tokens={};const availableTokens={};
                for(const key of root){if(key.startsWith('--')&&/(color|background|bg|text|radius|font|brand|accent|label|surface)/i.test(key)&&Object.keys(availableTokens).length<180){const val=root.getPropertyValue(key).trim();if(val.length<200)availableTokens[key]=val;}}
                for(const variable of variables??[]){const value=root.getPropertyValue(variable).trim();if(value)tokens[variable]=value;}
                const elements={};for(const[key,selector]of Object.entries(selectors))elements[key]=sample(selector);
                const documentedColors=[...document.querySelectorAll('tr')].map(row=>row.innerText.trim().replace(/\s+/g,' ')).filter(text=>/#[0-9a-f]{6}/i.test(text)).slice(0,60).map(text=>({text:text.slice(0,250),colors:[...text.matchAll(/#[0-9a-f]{6}/gi)].map(x=>x[0])}));
                const controls=[...document.querySelectorAll('button,a[href]')].filter(el=>{const r=el.getBoundingClientRect(),s=getComputedStyle(el);return r.width>40&&r.height>20&&s.backgroundColor!=='rgba(0, 0, 0, 0)';}).slice(0,14).map(el=>{const id='sample-'+[...document.querySelectorAll('button,a[href]')].indexOf(el);el.setAttribute('data-styleguide-sample',id);return sample('[data-styleguide-sample="'+id+'"]');});
                return {documentedColors,controls,inlineStyleBytes:[...document.querySelectorAll('style')].reduce((n,s)=>n+s.textContent.length,0),title:document.title,lang:document.documentElement.lang,tokens,availableTokens,elements,text:document.body.innerText.slice(0,500)};
            },source);
            if(/(just a moment|access denied|verify you are human|robot or human|security checkpoint)/i.test(observed.title+' '+observed.text))throw Error('Access challenge encountered; no challenge bypass attempted.');
            if(!requests.some(x=>x.contentType?.includes('html')&&!x.error))throw Error('Reference document unavailable.');
            if(!observed.inlineStyleBytes&&!requests.some(x=>x.contentType?.includes('css')))throw Error('No permitted reference stylesheet loaded; unstyled fallback is not evidence.');
            if(!Object.keys(observed.tokens).length&&!Object.keys(observed.availableTokens).length&&['\"Times New Roman\"','serif'].includes(observed.elements.body?.fontFamily))throw Error('Required styles are inaccessible; refusing browser default styling as design evidence.');
            delete observed.text;
            modes.push({mode,observed,requests,retrievedAt:requests.find(x=>!x.error).retrievedAt,method:source.browserScripts?'Isolated Chromium computed styles; website scripts run only in browser; all outbound document/CSS/script requests checked; fonts/images/API requests blocked.':'Passive Chromium computed styles and CSS custom properties; website scripts/fonts/images blocked; allowed HTTPS sources only.'});
            await context.close();
        }
        const record={schemaVersion:1,reference:source.id,name:source.name,url:source.url,surface:source.surface,attribution:source.attribution,modes};
        await atomic(`resources/library/evidence/${source.id}.json`,JSON.stringify(record,null,2)+'\n');
        results.push({reference:source.id,status:'collected',modes:modes.map(x=>x.mode)});
    }catch(error){results.push({reference:source.id,status:'pending',reason:error.message});await atomic(`resources/library/evidence/unavailable/${source.id}.json`,JSON.stringify({url:source.url,reason:error.message,retrievedAt:new Date().toISOString(),diagnostics},null,2)+'\n');}
    finally{for(const context of contexts)await context.close().catch(()=>{});}
    console.log(JSON.stringify(results.at(-1)));
}
await browser.close();
for(const result of results){const previous=statuses[result.reference];statuses[result.reference]=result.status==='pending'&&previous?.status==='collected'?{...previous,lastAttempt:{...result,checkedAt:new Date().toISOString()}}:{...result,checkedAt:new Date().toISOString()};}
await atomic('resources/library/source-status.json',JSON.stringify(statuses,null,2)+'\n');
await atomic('var/library-staging/themes/collection-report.json',JSON.stringify({retrievedAt:new Date().toISOString(),results},null,2)+'\n');
