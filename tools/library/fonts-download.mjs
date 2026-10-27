import {readFile, mkdir} from 'node:fs/promises';
import {gunzipSync} from 'node:zlib';
import {createHash} from 'node:crypto';
import tar from 'tar-stream';
import * as fontkit from 'fontkit';
import {download, atomic, digest} from './network.mjs';

const manifest=JSON.parse(await readFile('resources/library/fonts.json','utf8'));
let lock;
try{lock=JSON.parse(await readFile('resources/library/fonts.lock.json','utf8'));}catch{lock={schemaVersion:1,collection:'popular',families:{}};}
const argument=name=>process.argv.find(x=>x.startsWith(`--${name}=`))?.split('=').slice(1).join('=');
const family=argument('family');
const candidates=manifest.families.filter(x=>!family||x.id===family);
if(!candidates.length)throw Error('Unknown font family.');
if(process.argv.includes('--dry-run')){console.log(JSON.stringify(candidates.map(x=>({family:x.family,pinned:lock.families[x.id]?.version??null})),null,2));process.exit(0);}
const results=[];
const hosts=['registry.npmjs.org','raw.githubusercontent.com','api.github.com'];
if(!lock.upstreamRevision){const revision=await download('https://api.github.com/repos/google/fonts/commits/main',hosts);lock.upstreamRevision=JSON.parse(revision.bytes).sha;}
const required=[...new Set([...Array.from({length:95},(_,i)=>String.fromCodePoint(i+32)),'ÀÂÆÇÈÉÊËÎÏÔŒÙÛÜŸàâæçèéêëîïôœùûüÿ€‘’“”–—…«»'].join(''))].map(x=>x.codePointAt(0));
function inRange(point,range){return range.split(',').some(x=>{const [a,b]=x.trim().slice(2).split('-');return point>=parseInt(a,16)&&point<=parseInt(b??a,16);});}
async function unpack(bytes){
    const files=new Map();let size=0,count=0;
    const stream=tar.extract();
    const done=new Promise((resolve,reject)=>{stream.on('finish',resolve);stream.on('error',reject);});
    stream.on('entry',(header,body,next)=>{
        if(++count>3000||header.type!=='file'&&header.type!=='directory'||!/^package\/[A-Za-z0-9_./-]+$/.test(header.name)||header.name.split('/').includes('..')||header.size>8*1024*1024||(size+=header.size)>64*1024*1024){stream.destroy(Error('Unsafe/excessive font archive.'));body.resume();return;}
        const chunks=[];body.on('data',x=>chunks.push(x));body.on('end',()=>{if(header.type==='file'){if(files.has(header.name))return stream.destroy(Error('Duplicate font archive entry.'));files.set(header.name,Buffer.concat(chunks));}next();});
    });
    stream.end(gunzipSync(bytes,{maxOutputLength:64*1024*1024}));await done;return files;
}
for(const candidate of candidates){
    try{
        let pin=lock.families[candidate.id];
        if(!pin){
            let pkg;
            for(const scope of ['@fontsource-variable','@fontsource']){
                try{const response=await download(`https://registry.npmjs.org/${scope}%2F${candidate.id}/latest`,hosts);pkg=JSON.parse(response.bytes);break;}catch(error){if(!String(error).includes('HTTP 404'))throw error;}
            }
            if(!pkg?.dist?.integrity)throw Error('No eligible Fontsource package.');
            pin={package:pkg.name,version:pkg.version,tarball:pkg.dist.tarball,integrity:pkg.dist.integrity};
        }
        const response=await download(pin.tarball,hosts);
        const [algorithm,expected]=pin.integrity.split('-');
        if(algorithm!=='sha512'||createHash(algorithm).update(response.bytes).digest('base64')!==expected)throw Error('npm tarball integrity mismatch.');
        const files=await unpack(response.bytes);
        const meta=JSON.parse(files.get('package/metadata.json'));
        if(meta.id!==candidate.id||meta.family!==candidate.family||meta.source!=='https://github.com/google/fonts')throw Error('Fontsource/upstream family identity differs.');
        const license=files.get('package/LICENSE');
        if(!license||!['OFL-1.1','Apache-2.0','UFL-1.0'].includes(meta.license.type))throw Error('Missing or unsupported redistribution licence.');
        let upstream,authorityLicense;
        for(const directory of ['ofl','apache','ufl']){
            const url=`https://raw.githubusercontent.com/google/fonts/${lock.upstreamRevision}/${directory}/${candidate.id.replaceAll('-','')}/METADATA.pb`;
            try{const r=await download(url,hosts,{limit:262144});const text=r.bytes.toString();if(!text.includes(`name: "${candidate.family}"`))throw Error('Google Fonts family name differs.');authorityLicense=text.match(/^license:\s*"([A-Z0-9]+)"/m)?.[1];if(({OFL:'OFL-1.1',UFL:'UFL-1.0',APACHE2:'Apache-2.0'})[authorityLicense]!==meta.license.type)throw Error('Fontsource licence differs from Google Fonts licensing authority.');upstream={url,sha256:r.sha256,revision:lock.upstreamRevision};break;}catch(error){if(!String(error).includes('HTTP 404'))throw error;}
        }
        if(!upstream)throw Error('Google Fonts upstream family metadata unavailable.');
        const variable=pin.package.startsWith('@fontsource-variable/');
        const styles=meta.styles.filter(x=>['normal','italic'].includes(x));
        const cssNames=variable?styles.map(x=>`wght${x==='italic'?'-italic':''}.css`):styles.flatMap(style=>meta.weights.filter(x=>[400,500,600,700].includes(x)).map(weight=>`${weight}${style==='italic'?'-italic':''}.css`));
        // Some single-weight variable packages expose their actual axes via index.css.
        if(variable&&!files.has('package/wght.css')){cssNames.splice(0,cssNames.length,...styles.map(x=>x==='normal'?'index.css':'italic.css'));}
        const faces=[];const coverage=new Map();
        for(const cssName of cssNames){
            const css=files.get('package/'+cssName)?.toString();if(!css)throw Error(`Missing declared face stylesheet ${cssName}.`);
            for(const match of css.matchAll(/\/\*\s*([^*]+?)\s*\*\/\s*@font-face\s*\{([^}]+)\}/g)){
                const block=match[2];const source=block.match(/url\(\.\/(files\/[^)]+\.woff2)\)/)?.[1];
                if(!source)continue;
                const subset=[...manifest.subsets].sort((a,b)=>b.length-a.length).find(x=>source.startsWith(`files/${candidate.id}-${x}-`));if(!subset)continue;
                const range=block.match(/unicode-range:\s*([^;]+);/)?.[1];
                const weights=block.match(/font-weight:\s*(\d+)(?:\s+(\d+))?;/);const style=block.match(/font-style:\s*(normal|italic);/)?.[1];
                if(!range||!weights||!style)throw Error('Incomplete font-face metadata.');
                const bytes=files.get('package/'+source);if(!bytes||bytes.length<48||bytes.length>1048576||bytes.readUInt32BE(16)>16777216||bytes.toString('ascii',0,4)!=='wOF2'||bytes.readUInt32BE(8)!==bytes.length)throw Error('Invalid WOFF2 bytes.');
                const parsed=fontkit.create(bytes);const axes=parsed.variationAxes??{};
                const weightMin=Number(weights[1]),weightMax=Number(weights[2]??weights[1]);
                if(axes.wght&&(axes.wght.min!==weightMin||axes.wght.max!==weightMax))throw Error('Font variation weight axis conflicts with stylesheet.');
                if(!axes.wght&&weightMin===weightMax&&parsed['OS/2'].usWeightClass!==weightMin)throw Error('Static font weight conflicts with its OS/2 metadata.');
                if((style==='italic')!==Boolean(parsed['OS/2'].fsSelection.italic||parsed.italicAngle!==0))throw Error('Font style conflicts with its real italic metadata.');
                const key=`${style}:${weightMin}:${weightMax}`;if(!coverage.has(key))coverage.set(key,new Set());
                for(const code of parsed.characterSet)if(inRange(code,range))coverage.get(key).add(code);
                const hash=digest(bytes);const path=`fonts/${hash}.woff2`;
                if(!faces.some(x=>x.sha256===hash))faces.push({path,sha256:hash,bytes:bytes.length,weightMin,weightMax,style,subset,unicodeRange:range,axes,fontFamily:parsed.familyName,fontSubfamily:parsed.subfamilyName});
                await atomic(`var/library-staging/fonts/${candidate.id}/${pin.version}/${path}`,bytes);
            }
        }
        if(!faces.length)throw Error('No suitable WOFF2 faces.');
        const glyphCoverage=[];
        for(const[key,codes]of coverage){const missing=required.filter(x=>!codes.has(x));if(missing.length)throw Error(`Required glyphs missing for ${key}: ${missing.map(x=>'U+'+x.toString(16).toUpperCase()).join(', ')}`);glyphCoverage.push({face:key,requiredCount:required.length,verified:true});}
        const record={ingestionRevision:2,id:candidate.id,family:meta.family,kind:'local',category:candidate.category,tags:candidate.tags,source:`https://fontsource.org/fonts/${candidate.id}`,sourceVersion:pin.version,package:pin.package,upstream,license:meta.license.type,licensePath:`licenses/${candidate.id}.txt`,licenseSha256:digest(license),attribution:meta.license.attribution,faces,glyphCoverage,requiredCodepoints:required,retrievedAt:response.retrievedAt,tarballSha256:response.sha256};
        const destination=`var/library-staging/fonts/${candidate.id}/${pin.version}`;
        await atomic(`${destination}/${record.licensePath}`,license);
        await atomic(`${destination}/family.json`,JSON.stringify(record,null,2)+'\n');
        lock.families[candidate.id]={...pin,authorityLicense,tarballSha256:response.sha256,upstream,faces:faces.map(x=>({path:x.path,sha256:x.sha256,bytes:x.bytes})),licenseSha256:record.licenseSha256,metadataSha256:digest(Buffer.from(JSON.stringify(record,null,2)+'\n'))};
        await atomic('resources/library/fonts.lock.json',JSON.stringify(lock,null,2)+'\n');
        results.push({family:candidate.family,status:'downloaded',cached:response.cached,version:pin.version,faces:faces.length,bytes:faces.reduce((n,x)=>n+x.bytes,0)});
    }catch(error){results.push({family:candidate.family,status:'unavailable',reason:error.message});}
    console.log(JSON.stringify(results.at(-1)));
}
await atomic('var/library-staging/fonts/report.json',JSON.stringify({retrievedAt:new Date().toISOString(),results},null,2)+'\n');
if(results.some(x=>x.status!=='downloaded'))process.exitCode=1;
