import {readFileSync,readdirSync} from 'node:fs';
import assert from 'node:assert/strict';
const log=readFileSync('var/production-test/runtime.log','utf8');const secretDir=process.env.TEST_SECRET_DIR;
for(const name of readdirSync(secretDir)){const value=readFileSync(secretDir+'/'+name,'utf8').trim();assert(!log.includes(value),'Runtime secret leaked in container logs: '+name);}
for(const account of Object.values(JSON.parse(readFileSync('var/production-test/accounts.json'))))assert(!log.includes(account.password),'Account password leaked in container logs.');
const auth=JSON.parse(readFileSync('var/consumer-auth/styleguide/auth.json'));assert(!log.includes(auth.local),'Read token leaked in logs.');
const events=log.split('\n').flatMap(line=>{try{return [JSON.parse(line.slice(line.indexOf('{')))];}catch{return [];}}).filter(event=>event.message==='Preview finished.');assert(events.length>0,'No operational job logs emitted.');assert(events.some(event=>/^[a-f0-9]{64}$/.test(event.context.inputHash)&&Number(event.context.durationMs)>=0&&Number(event.context.queueWaitMs)>=0&&event.context.errorCode==='none'),'Missing job timing/hash/category metrics.');
console.log('PASS: actual container logs contain none of the runtime secrets, test passwords or private registry read token.');
