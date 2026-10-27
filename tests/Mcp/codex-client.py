#!/usr/bin/env python3
"""Real installed Codex app-server MCP client; no model turn or global config change."""
import json, os, queue, subprocess, threading, time

endpoint=os.environ.get('STYLEGUIDE_COMPOSE_MCP_URL','http://127.0.0.1:8095/mcp/compose')
if not os.environ.get('STYLEGUIDE_COMPOSE_MCP_TOKEN'): raise SystemExit('Export STYLEGUIDE_COMPOSE_MCP_TOKEN before running this client.')
command=['codex','app-server','--stdio','-c','mcp_servers.styleguide_compose.url='+json.dumps(endpoint),'-c','mcp_servers.styleguide_compose.bearer_token_env_var="STYLEGUIDE_COMPOSE_MCP_TOKEN"','-c','mcp_servers.styleguide_compose.startup_timeout_sec=30']
p=subprocess.Popen(command,stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True,bufsize=1)
messages=queue.Queue()
def read():
    for line in p.stdout:
        try: messages.put(json.loads(line))
        except json.JSONDecodeError: pass
    messages.put(None)
threading.Thread(target=read,daemon=True).start()
# Drain stderr without retaining credentials or unrelated client configuration.
threading.Thread(target=lambda: [None for _ in p.stderr],daemon=True).start()
sequence=0

def rpc(method,params):
    global sequence
    sequence+=1; id=sequence
    p.stdin.write(json.dumps({'id':id,'method':method,'params':params})+'\n');p.stdin.flush()
    deadline=time.monotonic()+90
    while time.monotonic()<deadline:
        try: result=messages.get(timeout=max(.1,deadline-time.monotonic()))
        except queue.Empty: break
        if result is None: raise RuntimeError('Codex app-server exited.')
        if result.get('id')==id:
            if 'error' in result: raise RuntimeError(method+': '+json.dumps(result['error']))
            return result['result']
    raise RuntimeError(method+' timed out.')

try:
    initialized=rpc('initialize',{'clientInfo':{'name':'styleguide-compose-verification','version':'1.0'},'capabilities':{'experimentalApi':True}})
    p.stdin.write(json.dumps({'method':'initialized','params':{}})+'\n');p.stdin.flush()
    thread=rpc('thread/start',{'cwd':os.getcwd(),'ephemeral':True,'approvalPolicy':'never'})
    thread_id=thread['thread']['id']
    inventory=rpc('mcpServerStatus/list',{'threadId':thread_id,'serverName':'styleguide_compose','detail':'toolsAndAuthOnly'})
    servers=inventory.get('data',inventory.get('servers',[]))
    server=next((s for s in servers if s['name']=='styleguide_compose'),None)
    if not server or len(server['tools'])!=26: raise RuntimeError('Codex did not discover the composition allowlist: '+json.dumps(server))
    def call(name,args):
        result=rpc('mcpServer/tool/call',{'threadId':thread_id,'server':'styleguide_compose','tool':'compose_'+name,'arguments':args})
        if result.get('isError'): raise RuntimeError('Codex tool error: '+json.dumps(result))
        return result.get('structuredContent') or result
    capabilities=call('capabilities',{})
    if capabilities.get('ok') is not True: raise RuntimeError('Missing capabilities structured result: '+json.dumps(capabilities))
    created=call('create_draft',{'name':'mcp-codex/layout-'+os.urandom(5).hex(),'kind':'layout','title':'Real Codex client draft','operationId':'codex-create-'+os.urandom(8).hex()})
    changed=call('add_text',{'compositionId':created['compositionId'],'expectedRevision':1,'operationId':'codex-add-'+os.urandom(8).hex(),'parentId':created['document']['root']['id'],'position':0,'value':'Edited through the installed Codex MCP client.'})
    readback=call('get',{'compositionId':created['compositionId']})
    assert readback['revision']==2 and readback['document']['root']['children'][0]['value']=='Edited through the installed Codex MCP client.'
    print(json.dumps({'client':'installed Codex CLI app-server','userAgent':initialized.get('userAgent'),'toolCount':len(server['tools']),'compositionId':created['compositionId'],'revision':readback['revision'],'composerUrl':readback['composerUrl'],'verified':['discovery','capabilities','create_draft','add_text','get'],'modelTurn':False,'globalConfigChanged':False},indent=2))
finally:
    p.terminate()
    try:p.wait(timeout=5)
    except subprocess.TimeoutExpired:p.kill()
