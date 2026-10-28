"""Maintain explicit MCP schemas/wrappers from the reviewed composer contract (no runtime generation)."""
import json
from pathlib import Path
S={'type':'string'}
item={'type':'string','pattern':'^[0-9A-HJKMNP-TV-Z]{26}$'}
node={'type':'string','pattern':'^n_[a-f0-9]{32}$'}
alias_node={'type':'string','pattern':'^(n_[a-f0-9]{32}|\\$[a-z][a-z0-9_]{0,31})$'}
rev={'type':'integer','minimum':1,'maximum':2147483647}
opid={'type':'string','pattern':'^[A-Za-z0-9][A-Za-z0-9_-]{7,63}$'}
name={'type':'string','pattern':'^[a-z][a-z0-9-]*/[a-z][a-z0-9-]*$','maxLength':100}
version={'type':'string','pattern':'^(0|[1-9][0-9]*)\\.(0|[1-9][0-9]*)\\.(0|[1-9][0-9]*)$'}
classes={'type':'array','maxItems':100,'items':{'type':'string','minLength':1,'maxLength':120}}
tags={'type':'string','enum':['div','section','article','header','footer','main','aside','nav']}
props={'type':'object'}
def obj(properties,required=None):return {'type':'object',**({'properties':properties} if properties else {}),'required':list(properties) if required is None else required,'additionalProperties':False}
base={'compositionId':item,'expectedRevision':rev,'operationId':opid}
page={'offset':{'type':'integer','minimum':0,'maximum':10000},'limit':{'type':'integer','minimum':1,'maximum':50}}
nodecmds={
'add_container':obj({'type':{'const':'add_container'},'parentId':alias_node,'position':{'type':'integer','minimum':0,'maximum':500},'tag':tags,'classes':classes,'alias':{'type':'string','pattern':'^[a-z][a-z0-9_]{0,31}$'}},['type','parentId','position']),
'add_text':obj({'type':{'const':'add_text'},'parentId':alias_node,'position':{'type':'integer','minimum':0,'maximum':500},'value':{'type':'string','maxLength':10000},'alias':{'type':'string','pattern':'^[a-z][a-z0-9_]{0,31}$'}},['type','parentId','position']),
'add_reference':obj({'type':{'const':'add_reference'},'parentId':alias_node,'position':{'type':'integer','minimum':0,'maximum':500},'name':name,'version':version,'props':props,'alias':{'type':'string','pattern':'^[a-z][a-z0-9_]{0,31}$'}},['type','parentId','position','name','version']),
'update_node':obj({'type':{'const':'update_node'},'nodeId':alias_node,'changes':obj({'tag':tags,'classes':classes,'value':{'type':'string','maxLength':10000},'name':name,'version':version,'props':props},[]) }),
'move_node':obj({'type':{'const':'move_node'},'nodeId':alias_node,'parentId':alias_node,'position':{'type':'integer','minimum':0,'maximum':500}}),
}
for key in ['indent_node','outdent_node','move_up','move_down','duplicate_node','remove_node']:
 p={'type':{'const':key},'nodeId':alias_node}
 if key=='duplicate_node':p['alias']={'type':'string','pattern':'^[a-z][a-z0-9_]{0,31}$'}
 nodecmds[key]=obj(p,['type','nodeId'])
inputs={
'capabilities':obj({}),
'search_catalogue':obj({'favoritesOnly':{'type':'boolean','description':'Only the authenticated user’s personal favorites; catalogue permissions and release eligibility still apply.'},'query':{'type':'string','maxLength':120},'kind':{'type':'string','enum':['component','block','layout']},**page},[]),
'list_preview_themes':obj(page,[]),
'get_reference':obj({'name':name,'version':version}),
'get':obj({'compositionId':item}),
'get_node':obj({'compositionId':item,'nodeId':node,'depth':{'type':'integer','minimum':1,'maximum':20},'maxNodes':{'type':'integer','minimum':1,'maximum':100}},['compositionId','nodeId']),
'create_draft':obj({'name':name,'kind':{'type':'string','enum':['block','layout']},'title':{'type':'string','minLength':1,'maxLength':120},'description':{'type':'string','maxLength':2000},'operationId':opid},['name','kind','title','operationId']),
'apply_batch':obj({**base,'commands':{'type':'array','minItems':1,'maxItems':50,'items':{'oneOf':list(nodecmds.values())}}}),
'validate':obj({'compositionId':item,'revision':rev}),
'update_metadata':obj({**base,'title':{'type':'string','minLength':1,'maxLength':120},'description':{'type':'string','maxLength':2000}},[*base,'title']),
'get_history':obj({'compositionId':item,**page},['compositionId']),
'restore_revision':obj({**base,'restoreRevision':rev}),
'render_preview':obj({'compositionId':item,'revision':rev,'operationId':opid,'themeReleaseId':item,'width':{'type':'integer','minimum':320,'maximum':1920},'scripts':{'type':'boolean'}},['compositionId','revision','operationId']),
'get_preview':obj({'jobId':item,'includeHtml':{'type':'boolean'}},['jobId']),
'cancel_preview':obj({'jobId':item,'operationId':opid}),
}
for key,schema in nodecmds.items():
 properties={k:v for k,v in schema['properties'].items() if k!='type'}
 for k in ['nodeId','parentId']:
  if k in properties:properties[k]=node
 inputs[key]=obj({**base,**properties},[*base,*[k for k in schema['required'] if k!='type']])
def php(v):
 if isinstance(v,dict):return '['+', '.join(php(k)+' => '+php(x) for k,x in v.items())+']'
 if isinstance(v,list):return '['+', '.join(map(php,v))+']'
 if isinstance(v,str):return "'"+v.replace('\\','\\\\').replace("'","\\'")+"'"
 if v is None:return 'null'
 if isinstance(v,bool):return 'true' if v else 'false'
 return str(v)
def schema_php(v,level=0):
 # Keep tiny JSON primitives inline, expand nested contracts for reviewable diffs.
 if isinstance(v,(dict,list)):
  pairs=list(v.items()) if isinstance(v,dict) else list(enumerate(v))
  if not pairs or all(not isinstance(x,(dict,list)) for _,x in pairs) and len(php(v))<160:return php(v)
  indent='    '*(level+1)
  return "[\n"+"\n".join(indent+(php(k)+' => ' if isinstance(v,dict) else '')+schema_php(x,level+1)+',' for k,x in pairs)+"\n"+'    '*level+']'
 return php(v)
result={'type':'object','properties':{'ok':{'type':'boolean'},'compositionId':{'type':['string','null']},'revision':{'type':['integer','null']},'composerUrl':{'type':['string','null']},'summary':S,'warnings':{'type':'array'},'code':S,'message':S,'details':{'type':['object','array']},'document':{'type':'object'},'affectedNodeIds':{'type':'array','items':S},'aliases':{'type':'object'},'replayed':{'type':'boolean'},'items':{'type':'array'},'history':{'type':'array'},'jobId':S,'status':S,'previewRevision':rev,'html':S},'required':['ok','summary','warnings'],'additionalProperties':True}
Path('src/Mcp/Tool/ComposeSchemas.php').write_text('<?php\nnamespace App\\Mcp\\Tool;\n/** Explicit bounded schemas; maintained by tools/mcp/generate-tool-schemas.py. */\nfinal class ComposeSchemas\n{\n    public const INPUT = '+schema_php(inputs,1)+';\n    public const RESULT = '+schema_php(result,1)+';\n}\n')
desc={
'capabilities':'Read composer node types, limits, save/retry rules and supported actions. Source-code authoring and publishing are excluded.',
'search_catalogue':'Search accessible catalogue items with paginated exact released versions and personal favorite status. Set favoritesOnly to filter before pagination. An unpublished draft is not insertable; creation differs from reference insertion.',
'get_reference':'Read the editable props JSON schema and defaults of one accessible immutable release, including transitive permission checks.',
'get':'Read a saved composition tree, stable node IDs, authoritative revision, reference pins and editing permissions.',
'get_node':'Read a bounded subtree with parent/position, legal actions and exact instance property schema.',
'create_draft':'Create a composition-owned block or layout draft. Components belong to the separate source editor. Requires an operationId; repeats with that exact key/input replay the result.',
'apply_batch':'Atomically save 1–50 typed tree commands as one revision. Earlier add/duplicate aliases may be referenced as $alias. Any failure rolls back all edits.',
'validate':'Validate exactly this saved revision through the shared compiler, props schemas and immutable dependency resolver without changing the draft.',
'update_metadata':'Save title/description through the same draft writer/history as the manual composer. expectedRevision and operationId are mandatory.',
'get_history':'List recoverable composition snapshots with paginated revisions; legacy history begins at the first new save.',
'restore_revision':'Restore a recoverable tree/metadata snapshot as a new revision. Preserve revision numbers and check current expectedRevision and permissions.',
'list_preview_themes':'List existing immutable published themes available to the manual composer, with pagination. Does not create or edit themes.',
'render_preview':'Queue an isolated preview of exactly the specified current revision, optionally in an existing published theme/viewport. operationId prevents duplicate jobs. No screenshot inspection is available.',
'get_preview':'Read an owned preview status and captured revision. Optionally return bounded sandbox HTML/diagnostics; a URL does not imply visual inspection.',
'cancel_preview':'Cancel an owned composition preview job. Does not modify the composition draft.',
'add_container':'Append a typed container at an explicit parent/zero-based child position and save one revision.',
'add_text':'Insert escaped literal text at an explicit parent/position and save. Twig syntax remains literal text.',
'add_reference':'Insert an accessible immutable component/block/layout release at an explicit parent/position. Omitted props use that exact release defaults; referenced content is never modified.',
'update_node':'Change only supported fields: container tag/classes, literal text, or reference name/version/props. JSON-schema validation is authoritative.',
'move_node':'Move a non-root subtree to a container and explicit position measured after removing the source; ancestor cycles are forbidden.',
'indent_node':'Match the UI: append the node into its immediately preceding container sibling. Cannot indent the root or first sibling.',
'outdent_node':'Match the UI: insert the node immediately after its parent in the grandparent. Direct children of the root cannot outdent.',
'move_up':'Match the UI: swap with the preceding sibling. Root and boundary moves fail without changing content.',
'move_down':'Match the UI: swap with the following sibling. Root and boundary moves fail without changing content.',
'duplicate_node':'Deep-copy a non-root subtree immediately after itself with fresh stable IDs. Does not alter referenced releases.',
'remove_node':'Remove a non-root subtree and save a recoverable revision. Root removal is forbidden.',
}
read={'capabilities','search_catalogue','get_reference','get','get_node','validate','get_history','list_preview_themes','get_preview'}
wrappers='''<?php
namespace App\\Mcp\\Tool;
use App\\Mcp\\{ComposeApplication,ComposeToolInput};
use Mcp\\Capability\\Attribute\\{McpTool,Schema};
use Mcp\\Schema\\{ToolAnnotations};
use Mcp\\Schema\\Content\\TextContent;
use Mcp\\Schema\\Result\\CallToolResult;
use Mcp\\Server\\RequestContext;
use Styleguide\\Contracts\\CatalogueException;
/** Explicit server allowlist: these are the only exposed capabilities. */
final readonly class ComposeTools
{
    public function __construct(private ComposeApplication $application,private ComposeToolInput $inputs){}
    private function call(string $operation,RequestContext $context):CallToolResult
    {
        try{$result=$this->application->invoke($operation,$this->inputs->arguments($context));return new CallToolResult([new TextContent($result['summary'].(isset($result['revision'])?' Revision '.$result['revision'].'.':'').(isset($result['composerUrl'])?' '.$result['composerUrl']:''))],structuredContent:$result);}
        catch(CatalogueException $e){$code=$e->errorCode;if($code==='revision_mismatch')$code='REVISION_CONFLICT';elseif($code!=='invalid_catalogue_data')$code=strtoupper($code);if($code==='invalid_catalogue_data')$code=match($e->getStatusCode()){403=>'FORBIDDEN',404=>'NOT_FOUND',412=>'REVISION_CONFLICT',default=>str_contains(strtolower($e->getMessage()),'cycle')||str_contains($e->getMessage(),'reference itself')?'REFERENCE_CYCLE':'VALIDATION_FAILED'};$result=['ok'=>false,'summary'=>$e->getMessage(),'warnings'=>[],'code'=>$code,'message'=>$e->getMessage(),'details'=>(object)$e->fieldErrors];return new CallToolResult([new TextContent($code.': '.$e->getMessage())],true,$result);}
    }
'''
for key,schema in inputs.items():
 annotation=f"new ToolAnnotations(readOnlyHint:{'true' if key in read else 'false'}, destructiveHint:{'false' if key in read or key in ['create_draft','add_container','add_text','add_reference','duplicate_node','render_preview'] else 'true'}, idempotentHint:{'false' if key not in read and key != 'cancel_preview' else 'true'}, openWorldHint:false)"
 wrappers+=f"\n    #[McpTool(name:'compose_{key}', description:{php(desc[key])}, annotations:{annotation}, outputSchema:ComposeSchemas::RESULT)]\n    #[Schema(definition:ComposeSchemas::INPUT[{php(key)}])]\n    public function {key}(RequestContext $context):CallToolResult{{return $this->call({php(key)},$context);}}\n"
Path('src/Mcp/Tool/ComposeTools.php').write_text(wrappers+'}\n')
