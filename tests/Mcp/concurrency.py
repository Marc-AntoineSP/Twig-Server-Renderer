#!/usr/bin/env python3
"""Two real concurrent HTTP requests; uses a dedicated credential supplied in env."""
import concurrent.futures, json, os, urllib.request
endpoint=os.environ.get('STYLEGUIDE_COMPOSE_MCP_URL','http://127.0.0.1:8095/mcp/compose')
token=os.environ['STYLEGUIDE_COMPOSE_MCP_TOKEN']
def rpc(method,params,session=None):
    headers={'Content-Type':'application/json','Accept':'application/json, text/event-stream','Authorization':'Bearer '+token,'Mcp-Protocol-Version':'2025-11-25'}
    if session:headers['Mcp-Session-Id']=session
    request=urllib.request.Request(endpoint,json.dumps({'jsonrpc':'2.0','id':1,'method':method,'params':params}).encode(),headers)
    with urllib.request.urlopen(request,timeout=40) as response:
        body=response.read().decode();session=response.headers.get('Mcp-Session-Id',session)
    if body.startswith('event:') or body.startswith('data:'):body=[l[6:] for l in body.splitlines() if l.startswith('data: ')][-1]
    result=json.loads(body)
    if 'error' in result:raise RuntimeError(json.dumps(result['error']))
    return result['result'],session

def connect():return rpc('initialize',{'protocolVersion':'2025-11-25','capabilities':{},'clientInfo':{'name':'studio-concurrent-acceptance','version':'1'}})[1]
def call(name,args,session):return rpc('tools/call',{'name':'compose_'+name,'arguments':args},session)[0]
sessions=[connect(),connect()]
created=call('create_draft',{'name':'mcp-codex/concurrent-'+os.urandom(5).hex(),'kind':'layout','title':'Concurrent MCP test','operationId':'concurrent-create-'+os.urandom(8).hex()},sessions[0])['structuredContent'];id=created['compositionId'];root=created['document']['root']['id']
base={'compositionId':id,'expectedRevision':1,'parentId':root,'position':0,'value':'Exactly one winner'}
with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
    results=list(pool.map(lambda i:call('add_text',dict(base,operationId='parallel-'+os.urandom(8).hex()),sessions[i]),range(2)))
assert sum(not r.get('isError',False) for r in results)==1,results
assert [r['structuredContent']['code'] for r in results if r.get('isError')]==['REVISION_CONFLICT'],results
retry=dict(base,expectedRevision=2,position=1,operationId='lost-response-'+os.urandom(8).hex())
with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
    replay=list(pool.map(lambda i:call('add_text',retry,sessions[i]),range(2)))
assert all(not r.get('isError',False) and r['structuredContent']['revision']==3 for r in replay),replay
assert sum(r['structuredContent'].get('replayed',False) for r in replay)==1,replay
read=call('get',{'compositionId':id},sessions[0])['structuredContent'];assert read['revision']==3 and len(read['document']['root']['children'])==2,read
print(json.dumps({'client':'two concurrent HTTP sessions','compositionId':id,'revision':3,'verified':['one concurrent winner','one stale revision conflict','one receipt replay','no duplicate node or revision']},indent=2))
