import {posix} from 'node:path';
// Pure parsing: no source module is imported, evaluated or given a filesystem loader.
export function validateSources(sources,parse,transform) {
    for(const [path,source] of Object.entries(sources)) {
        if(path.endsWith('.css')) {
            if(/@(import|plugin|config)\b/i.test(source))throw Error('Imported CSS configuration and plugins are forbidden.');
            try{transform({filename:path,code:Buffer.from(source),errorRecovery:false});}catch(error){throw Error(`CSS syntax error in ${path}:${error.loc?.line||1}: ${error.message}`);}
        }
        if(!path.endsWith('.js'))continue;
        let ast;try{ast=parse(source,{ecmaVersion:'latest',sourceType:'module',locations:true});}catch(error){throw Error(`JavaScript syntax error in ${path}:${error.loc?.line||1}: ${error.message}`);}
        const queue=[ast];let count=0;
        while(queue.length){const node=queue.pop();if(++count>100000)throw Error('JavaScript syntax exceeds its node budget.');
            if(node.type==='ImportExpression')throw Error(`Dynamic imports are not supported in ${path}:${node.loc.start.line}.`);
            if(['ImportDeclaration','ExportNamedDeclaration','ExportAllDeclaration'].includes(node.type)&&node.source){
                const target=node.source.value;
                if(target!=='@hotwired/stimulus'){
                    const resolved=posix.normalize(posix.join(posix.dirname(path),target));
                    if(!/^\.{1,2}\//.test(target)||!resolved.endsWith('.js')||!Object.hasOwn(sources,resolved))throw Error(`Forbidden or unknown compiler import in ${path}:${node.loc.start.line}: ${String(target).slice(0,160)}`);
                }
            }
            for(const value of Object.values(node)){if(Array.isArray(value)){for(const child of value)if(child&&typeof child==='object'&&child.type)queue.push(child);}else if(value&&typeof value==='object'&&value.type)queue.push(value);}
        }
    }
}
