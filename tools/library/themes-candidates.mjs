import {readFile} from 'node:fs/promises';
import {parse,converter,formatHex,wcagContrast} from 'culori';
import {atomic,digest} from './network.mjs';

const manifest=JSON.parse(await readFile('resources/library/websites.json','utf8'));
const status=JSON.parse(await readFile('resources/library/source-status.json','utf8'));
const rgb=converter('rgb'),oklch=converter('oklch');
const clamp=x=>Math.max(0,Math.min(1,x));
function hex(value,backdrop='#ffffff'){
    const color=rgb(parse(value));if(!color||[color.r,color.g,color.b].some(x=>!Number.isFinite(x)))throw Error('Unsupported observed colour: '+value);
    const alpha=color.alpha??1;if(alpha===0)throw Error('Transparent colour needs an observed solid ancestor.');
    const bg=rgb(parse(backdrop));
    return formatHex({mode:'rgb',r:clamp(color.r)*alpha+bg.r*(1-alpha),g:clamp(color.g)*alpha+bg.g*(1-alpha),b:clamp(color.b)*alpha+bg.b*(1-alpha)});
}
const defaults={info:'#1769aa',success:'#187543',warning:'#a85d00',error:'#bc243c'};
const candidates=[],results=[];
for(const reference of manifest.references){
    if(status[reference.id]?.status!=='collected'){results.push({reference:reference.id,status:'pending',reason:status[reference.id]?.reason??'No source evidence.'});continue;}
    try{
        const raw=await readFile(`resources/library/evidence/${reference.id}.json`);
        const evidence=JSON.parse(raw),seenModes=new Set();
        for(const observation of evidence.modes){
            const o=observation.observed,mode=observation.mode;
            const signature=JSON.stringify({tokens:o.tokens,background:o.elements.body?.background,color:o.elements.body?.color});
            if(seenModes.has(signature)){results.push({reference:reference.id,mode,status:'omitted',reason:'No distinct mode was observed; no inversion generated.'});continue;}seenModes.add(signature);
            const values={},colors={};
            function observed(pointer){
                if(pointer.startsWith('token:')){const name=pointer.slice(6);return {value:o.tokens[name]??o.availableTokens?.[name],source:`CSS custom property ${name}`};}
                if(pointer.startsWith('control:')){const[,label,property]=pointer.split(':');const c=o.controls?.find(x=>x.text===label||x.text.startsWith(label));return{value:c?.[property],source:`${c?.tag??'control'} ${JSON.stringify(label)} · ${property}`};}
                if(pointer.startsWith('documented:')){const label=pointer.slice(11);const row=o.documentedColors?.find(x=>x.text.toLowerCase().startsWith(label.toLowerCase()+' '));return{value:row?.colors[0],source:`Documented token row ${label}`};}
                const[,element,property]=pointer.split(':');return {value:o.elements[element]?.[property],source:`${o.elements[element]?.selector??element} · ${property}`};
            }
            function assign(token,pointers,fallback){
                for(const pointer of pointers??[]){const item=observed(pointer);if(!item.value)continue;try{
                    const converted=hex(item.value,colors['base-100']??'#ffffff');colors[token]=converted;
                    values[`colors.${token}`]={kind:'observed',source:item.source,observed:item.value,value:converted,reason:'Parsed with Culori 4.0.2, converted to sRGB, out-of-gamut channels clipped, alpha composited on base-100, rounded to #RRGGBB.'};return;
                }catch{}}
                colors[token]=fallback;values[`colors.${token}`]={kind:'derived',source:null,observed:null,value:fallback,reason:'Completes the studio semantic palette; this value was not observed on the reference surface.'};
            }
            const map=reference.mapping??{};
            assign('base-100',map['base-100']??['element:body:background','element:surface:background','element:card:background'],mode==='dark'?'#18191d':'#ffffff');
            assign('base-content',map['base-content']??['element:body:color','element:heading:color'],mode==='dark'?'#f4f5f7':'#202124');
            assign('base-200',map['base-200'],hex(mode==='dark'?'#ffffff12':'#00000008',colors['base-100']));
            assign('base-300',map['base-300'],hex(mode==='dark'?'#ffffff20':'#00000014',colors['base-100']));
            assign('primary',map.primary,mode==='dark'?'#a0a8f8':'#4a42b5');
            assign('secondary',map.secondary,mode==='dark'?'#b6a4dc':'#625182');
            assign('accent',map.accent,mode==='dark'?'#83cbd2':'#166d78');
            assign('neutral',map.neutral,mode==='dark'?'#c4c5cd':'#35353f');
            for(const[token,fallback]of Object.entries(defaults))assign(token,map[token],fallback);
            function adapt(token,value,reason){const old=values[`colors.${token}`];colors[token]=value;values[`colors.${token}`]={...old,kind:old.kind==='derived'?'derived':'adapted',value,reason:old.reason+' '+reason};}
            for(const token of ['primary','secondary','accent','neutral','info','success','warning','error']){
                // Text-bearing actions and selected labels need margin for daisyUI hover mixing.
                const target=mode==='dark'?'#000000':'#ffffff';let color=colors[token];
                for(let step=0;step<160&&(wcagContrast(color,colors['base-100'])<4.5||wcagContrast(color,target)<5.5);step++){
                    const c=oklch(parse(color));c.l=clamp(c.l+(mode==='dark'?.008:-.008));color=hex(formatHex(rgb(c)));
                }
                if(color!==colors[token])adapt(token,color,'Adjusted OKLCH lightness for readable action labels, selected text and visible focus against the base surface; source colour retained above.');
                const content=token+'-content';assign(content,map[content],target);
                if(wcagContrast(colors[content],colors[token])<5.5)adapt(content,target,'Uses a solid content colour with contrast margin for hover states.');
            }
            for(const base of ['base-100','base-200','base-300'])if(wcagContrast(colors['base-content'],colors[base])<4.5)adapt('base-content',mode==='dark'?'#ffffff':'#111111','Adjusted body text to at least 4.5:1 across the three base surfaces.');
            const slug=`${reference.id}-inspired-${mode}`;
            const geometry={radiusSelectorRem:.375,radiusFieldRem:.375,radiusBoxRem:.75,sizeSelectorRem:.25,sizeFieldRem:.25,borderPx:1,depth:0,noise:0,highContrastBorders:true};
            for(const[key,value]of Object.entries(geometry))values['geometry.'+key]={kind:'derived',source:null,observed:null,value,reason:'Bounded studio geometry; source shadows and gradients are omitted.'};
            for(const[key,element]of [['radiusFieldRem','field'],['radiusBoxRem','card'],['radiusSelectorRem','action']]){
                const observed=o.elements[element]?.borderRadius;
                if(observed&&/^\d+(?:\.\d+)?px$/.test(observed)){geometry[key]=Math.min(4,parseFloat(observed)/16);values['geometry.'+key]={kind:parseFloat(observed)/16>4?'adapted':'observed',source:`${o.elements[element].selector} · border-radius`,observed,value:geometry[key],reason:'Converted CSS px to rem at the 16px preset root; radii capped at supported 4rem.'};}
            }
            const typography={bodyFontId:reference.fonts.body,headingFontId:reference.fonts.heading,codeFontId:reference.fonts.code,textClass:'text-base',rootFontSizePx:16,lineHeight:1.5,bodyWeight:400,headingWeight:700,codeWeight:400,bodyStyle:'normal',headingStyle:'normal',codeStyle:'normal'};
            for(const[key,value]of Object.entries(typography))values['typography.'+key]={kind:'derived',source:null,observed:null,value,reason:key.endsWith('FontId')?'Deliberate installed font or system fallback; see substitutions.':'Studio text defaults; real supported font faces only.'};
            const fontSubstitutions=['body','heading','code'].map(role=>({role,observed:o.elements[role==='body'?'body':role==='heading'?'heading':'code']?.fontFamily??'No family observed for this role',selected:reference.fonts[role],reason:'Use the identified locally redistributable family or system fallback. Proprietary/system binaries are not downloaded from the website.'}));
            const sources=[...new Map(observation.requests.filter(x=>x.sha256).map(x=>[x.url,{url:x.url,sha256:x.sha256,revision:new URL(x.url).pathname.match(/[a-f0-9]{8,}/)?.[0]??null,method:observation.method,attribution:evidence.attribution}])).values()].slice(0,12);
            const document={schemaVersion:1,name:'websites/'+slug,version:manifest.version,cssName:slug,colorScheme:mode,default:false,prefersDark:false,colors,geometry,typography,fonts:[],provenance:{managed:true,collection:'website-inspired',slug,title:`${reference.name}-inspired ${mode[0].toUpperCase()+mode.slice(1)}`,reference:reference.name,surface:evidence.surface,modeOrigin:'observed',retrievedAt:observation.retrievedAt,sources,values,fontSubstitutions,omitted:['Source logos, illustrations, gradients, shadows and proprietary fonts.','Hover, selected, focus and disabled styles use the installed daisyUI 5 component implementation; audited separately on the shared showcase.']}};
            const ratios={};for(const[bg,fg]of [['base-100','base-content'],['base-200','base-content'],['base-300','base-content'],...['primary','secondary','accent','neutral','info','success','warning','error'].map(x=>[x,x+'-content'])]){ratios[bg]=Math.round(wcagContrast(colors[bg],colors[fg])*100)/100;if(ratios[bg]<4.5)throw Error(`Contrast fails for ${slug} ${bg}: ${ratios[bg]}`);}
            candidates.push({document,ratios,evidenceSha256:digest(raw)});results.push({reference:reference.id,mode,status:'candidate',slug,ratios});
        }
    }catch(error){results.push({reference:reference.id,status:'pending',reason:error.message});}
}
await atomic('resources/library/theme-candidates.json',JSON.stringify({schemaVersion:1,collection:'website-inspired',version:manifest.version,candidates,results},null,2)+'\n');
console.log(JSON.stringify({families:new Set(candidates.map(x=>x.document.provenance.reference)).size,modes:candidates.length,results:results.filter(x=>x.status!=='candidate')},null,2));
