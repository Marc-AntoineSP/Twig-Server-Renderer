import {pathToFileURL} from 'node:url';
import {validateSources} from '../../catalogue-contracts/tooling/sources.mjs';
const root=process.argv[2];
try{
    const {parse}=await import(pathToFileURL(root+'/node_modules/acorn/dist/acorn.mjs'));
    const {transform}=await import(pathToFileURL(root+'/node_modules/lightningcss/node/index.js'));
    let text='';for await(const chunk of process.stdin){text+=chunk;if(Buffer.byteLength(text)>3145728)throw Error('Source validation exceeds 3 MiB.');}
    validateSources(JSON.parse(text),parse,transform);process.stdout.write(JSON.stringify({ok:true}));
}catch(error){process.stdout.write(JSON.stringify({ok:false,error:String(error.message).slice(0,1200)}));process.exitCode=1;}
