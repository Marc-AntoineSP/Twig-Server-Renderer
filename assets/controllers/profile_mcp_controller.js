import {Controller} from '@hotwired/stimulus';

/** Same-origin, read-only MCP diagnostics. Credentials stay only in this page's memory. */
export default class extends Controller {
    static targets=['credential','config','endpoint','feedback','result','checks','runButton','oauthResult','clientId','clientSecret'];
    static values={endpoint:String,expectedResource:String,expectedIssuer:String,profileUrl:String};
    connect(){this.sequence=0;if(location.pathname!==this.profileUrlValue){const fragment=location.pathname.includes('/oauth')?'chatgpt':location.pathname.includes('/password')?'password':location.pathname.includes('/tokens')?'mcp':'account';history.replaceState(history.state,'',this.profileUrlValue+'#'+fragment);}if((this.hasCredentialTarget&&this.credentialTarget.value)||this.hasClientSecretTarget){const oauth=this.hasClientSecretTarget;history.replaceState(history.state,'',this.profileUrlValue+(oauth?'#chatgpt':'#mcp'));requestAnimationFrame(()=>{const input=oauth?this.clientIdTarget:this.credentialTarget;input.focus({preventScroll:true});input.scrollIntoView({block:'center'});});}this.scrubHandler=()=>this.scrub();document.addEventListener('turbo:before-cache',this.scrubHandler);window.addEventListener('pagehide',this.scrubHandler);}
    disconnect(){this.abort?.abort();this.oauthAbort?.abort();document.removeEventListener('turbo:before-cache',this.scrubHandler);window.removeEventListener('pagehide',this.scrubHandler);this.scrub();}
    scrub(){this.abort?.abort();this.oauthAbort?.abort();if(this.hasCredentialTarget){this.credentialTarget.value='';this.credentialTarget.removeAttribute('value');this.credentialTarget.type='password';}if(this.hasClientSecretTarget){this.clientSecretTarget.value='';this.clientSecretTarget.removeAttribute('value');}}
    async copy(event){
        const source=event.params.source;if(!['credential','config','endpoint','clientId','clientSecret'].includes(source)||!this[`has${source[0].toUpperCase()+source.slice(1)}Target`])return;
        const element=this[`${source}Target`];const value='value' in element?element.value:element.textContent;if(!value){this.feedbackTarget.textContent='Enter or create a credential first.';return;}
        try{if(navigator.clipboard?.writeText)await navigator.clipboard.writeText(value);else{const old=document.activeElement;const temp=document.createElement('textarea');temp.value=value;temp.style.position='fixed';temp.style.opacity='0';document.body.append(temp);temp.select();const copied=document.execCommand('copy');temp.remove();old?.focus();if(!copied)throw Error();}this.feedbackTarget.textContent='Copied to clipboard.';}catch{this.feedbackTarget.textContent='Clipboard unavailable. Select and copy the value manually.';element.focus();if('select' in element)element.select();}
    }
    reveal(event){const element=this.credentialTarget;const reveal=element.type==='password';element.type=reveal?'text':'password';event.currentTarget.textContent=reveal?'Hide token':'Show token';event.currentTarget.setAttribute('aria-pressed',String(reveal));}
    async test(){
        if(this.abort)return;const token=this.credentialTarget.value.trim();if(!token){this.resultTarget.textContent='Enter a personal MCP token to test.';this.credentialTarget.focus();return;}
        this.abort=new AbortController();const abort=this.abort;const timer=setTimeout(()=>abort.abort(),20000);this.runButtonTarget.disabled=true;this.checksTarget.replaceChildren();this.checksTarget.hidden=true;this.resultTarget.textContent='Connecting to the authenticated MCP endpoint…';
        let session=null;let protocol='2025-11-25';
        const rpc=async(method,params,notification=false)=>{
            const id=++this.sequence;const headers={'Content-Type':'application/json','Accept':'application/json, text/event-stream','Authorization':`Bearer ${token}`,'Mcp-Protocol-Version':protocol};if(session)headers['Mcp-Session-Id']=session;
            const response=await fetch(this.endpointValue,{method:'POST',credentials:'omit',cache:'no-store',redirect:'error',signal:abort.signal,headers,body:JSON.stringify({jsonrpc:'2.0',...(notification?{}:{id}),method,params})});
            if(!response.ok){if(response.status===401)throw Error('Authentication failed. Check whether the credential expired or was revoked.');if(response.status===403)throw Error('The server rejected this request or its origin.');if(response.status===404)throw Error('MCP is unavailable or disabled on this deployment.');throw Error(`MCP returned HTTP ${response.status}.`);}
            session=response.headers.get('Mcp-Session-Id')||session;if(notification)return;
            const body=await response.text();let message;
            if(response.headers.get('Content-Type')?.includes('text/event-stream')){for(const block of body.split(/\r?\n\r?\n/)){const data=block.split(/\r?\n/).filter(line=>line.startsWith('data:')).map(line=>line.slice(5).trimStart()).join('\n');if(data){const candidate=JSON.parse(data);if(candidate.id===id)message=candidate;}}}else message=JSON.parse(body);
            if(!message||message.id!==id||message.error)throw Error(message?.error?.message||'Unexpected MCP protocol response.');return message.result;
        };
        try{
            const initialized=await rpc('initialize',{protocolVersion:protocol,capabilities:{},clientInfo:{name:'styleguide-profile-check',version:'1.0'}});if(!initialized?.protocolVersion)throw Error('The server did not negotiate an MCP version.');protocol=initialized.protocolVersion;
            await rpc('notifications/initialized',{},true);const inventory=await rpc('tools/list',{});if(!Array.isArray(inventory.tools)||!inventory.tools.length||inventory.tools.some(tool=>!tool.name.startsWith('compose_')))throw Error('Unexpected composer tool discovery response.');
            const capabilities=await rpc('tools/call',{name:'compose_capabilities',arguments:{}});if(capabilities.isError||capabilities.structuredContent?.ok!==true)throw Error('The token authenticated but could not read composer capabilities. Include the compose:read permission.');
            this.checksTarget.replaceChildren(...[`Authenticated bearer request accepted`,`MCP ${protocol} negotiated`,`${inventory.tools.length} composer tools discovered`,'Read-only composer capabilities returned; no drafts changed'].map(text=>{const item=document.createElement('li');item.textContent=text;return item;}));this.checksTarget.hidden=false;
            this.resultTarget.textContent=`Connection verified in this browser: ${inventory.tools.length} composer tools available. Codex and ChatGPT connections must be completed in those clients.`;
        }catch(error){this.resultTarget.textContent=error.name==='AbortError'?'Connection test timed out or was cancelled.':error.message;}
        finally{clearTimeout(timer);this.runButtonTarget.disabled=false;this.abort=null;}
    }
    async testOauth(){
        if(this.oauthAbort)return;const controller=new AbortController();this.oauthAbort=controller;const timer=setTimeout(()=>controller.abort(),15000);this.oauthResultTarget.textContent='Checking authorization discovery…';
        try{
            const [resource,authorization]=await Promise.all(['/.well-known/oauth-protected-resource/mcp/compose','/.well-known/oauth-authorization-server'].map(async url=>{const response=await fetch(url,{credentials:'omit',cache:'no-store',redirect:'error',signal:controller.signal,headers:{Accept:'application/json'}});if(!response.ok)throw Error(`OAuth discovery returned HTTP ${response.status}.`);return response.json();}));
            if(resource.resource!==this.expectedResourceValue||!resource.authorization_servers?.includes(this.expectedIssuerValue)||authorization.issuer!==this.expectedIssuerValue||!authorization.code_challenge_methods_supported?.includes('S256')||authorization.authorization_response_iss_parameter_supported!==true||!authorization.authorization_endpoint||!authorization.token_endpoint)throw Error('OAuth metadata does not match the configured composer resource, issuer or PKCE requirements.');
            this.oauthResultTarget.textContent='OAuth discovery, issuer, resource and S256 PKCE are configured. ChatGPT account linking and external network access are still unverified.';
        }catch(error){this.oauthResultTarget.textContent=error.name==='AbortError'?'OAuth check timed out or was cancelled.':error.message;}
        finally{clearTimeout(timer);this.oauthAbort=null;}
    }
}
