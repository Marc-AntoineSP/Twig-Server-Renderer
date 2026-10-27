import https from 'node:https';
import {lookup} from 'node:dns/promises';
import {createHash} from 'node:crypto';
import {mkdir, readFile, writeFile, rename} from 'node:fs/promises';
import ipaddr from 'ipaddr.js';

export const digest = bytes => createHash('sha256').update(bytes).digest('hex');
export async function atomic(path, bytes) {
    await mkdir(path.slice(0,path.lastIndexOf('/')), {recursive:true});
    const temporary = `${path}.${process.pid}.tmp`;
    await writeFile(temporary, bytes);
    await rename(temporary, path);
}

// Maintenance only. Pin the resolved public address into the HTTPS connection;
// validate every redirect, never forward credentials, and never execute responses.
export async function download(address, hosts, {limit=16*1024*1024, cache='var/library-cache', refresh=false, redirects=0}={}) {
    const url = new URL(address);
    if (url.protocol !== 'https:' || url.port && url.port !== '443' || url.username || url.password || !hosts.includes(url.hostname)) throw Error(`Source is not allowed: ${url.origin}`);
    const key = `${cache}/${digest(url.href)}`;
    if (!refresh) {
        try {
            const meta = JSON.parse(await readFile(key+'.json','utf8'));
            const bytes = await readFile(key+'.bin');
            if (bytes.length <= limit && digest(bytes) === meta.sha256) return {...meta, bytes, cached:true};
        } catch {}
    }
    const addresses = await lookup(url.hostname,{all:true});
    if (!addresses.length || addresses.some(({address}) => ipaddr.process(address).range() !== 'unicast')) throw Error('Source resolved to a non-public address.');
    const resolved = addresses[0];
    let result;
    for (let attempt=0; attempt<2; attempt++) {
        try {
            result = await new Promise((resolve,reject) => {
                const req = https.get(url, {lookup: (_host, options, callback) => options.all ? callback(null,[resolved]) : callback(null,resolved.address,resolved.family), headers:{'User-Agent':'StyleguideStudio-Research/1.0 (bounded public design-token research)','Accept-Encoding':'identity','Accept':'text/html,application/xhtml+xml,text/css,application/json;q=0.9,*/*;q=0.8'}, timeout:15000}, response => {
                    const chunks=[];let length=0;
                    response.on('data',chunk=>{length+=chunk.length;if(length>limit)response.destroy(Error('Source exceeds byte limit.'));else chunks.push(chunk);});
                    response.on('error',reject);
                    response.on('end',()=>resolve({status:response.statusCode,headers:response.headers,bytes:Buffer.concat(chunks)}));
                });
                req.setTimeout(15000,()=>req.destroy(Error('Source request timed out.')));
                req.on('error',reject);
            });
            break;
        } catch (error) { if(attempt)throw error; }
    }
    if (result.status >= 300 && result.status < 400 && result.headers.location) {
        if(redirects>=3)throw Error('Too many source redirects.');
        return download(new URL(result.headers.location,url).href,hosts,{limit,cache,refresh,redirects:redirects+1});
    }
    if(result.status!==200)throw Error(`HTTP ${result.status}: ${url.href}`);
    const meta={url:url.href,status:result.status,sha256:digest(result.bytes),retrievedAt:new Date().toISOString(),etag:result.headers.etag??null,contentType:result.headers['content-type']??'application/octet-stream'};
    await atomic(key+'.bin',result.bytes);await atomic(key+'.json',JSON.stringify(meta));
    return {...meta,bytes:result.bytes,cached:false};
}
