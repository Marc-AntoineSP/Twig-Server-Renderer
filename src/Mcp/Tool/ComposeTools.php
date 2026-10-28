<?php
namespace App\Mcp\Tool;
use App\Mcp\{ComposeApplication,ComposeToolInput};
use Mcp\Capability\Attribute\{McpTool,Schema};
use Mcp\Schema\{ToolAnnotations};
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\RequestContext;
use Styleguide\Contracts\CatalogueException;
/** Explicit server allowlist: these are the only exposed capabilities. */
final readonly class ComposeTools
{
    public function __construct(private ComposeApplication $application,private ComposeToolInput $inputs){}
    private function call(string $operation,RequestContext $context):CallToolResult
    {
        try{$result=$this->application->invoke($operation,$this->inputs->arguments($context));return new CallToolResult([new TextContent($result['summary'].(isset($result['revision'])?' Revision '.$result['revision'].'.':'').(isset($result['composerUrl'])?' '.$result['composerUrl']:''))],structuredContent:$result);}
        catch(CatalogueException $e){$code=$e->errorCode;if($code==='revision_mismatch')$code='REVISION_CONFLICT';elseif($code!=='invalid_catalogue_data')$code=strtoupper($code);if($code==='invalid_catalogue_data')$code=match($e->getStatusCode()){403=>'FORBIDDEN',404=>'NOT_FOUND',412=>'REVISION_CONFLICT',default=>str_contains(strtolower($e->getMessage()),'cycle')||str_contains($e->getMessage(),'reference itself')?'REFERENCE_CYCLE':'VALIDATION_FAILED'};$result=['ok'=>false,'summary'=>$e->getMessage(),'warnings'=>[],'code'=>$code,'message'=>$e->getMessage(),'details'=>(object)$e->fieldErrors];return new CallToolResult([new TextContent($code.': '.$e->getMessage())],true,$result);}
    }

    #[McpTool(name:'compose_capabilities', description:'Read composer node types, limits, save/retry rules and supported actions. Source-code authoring and publishing are excluded.', annotations:new ToolAnnotations(readOnlyHint:true, destructiveHint:false, idempotentHint:true, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['capabilities'])]
    public function capabilities(RequestContext $context):CallToolResult{return $this->call('capabilities',$context);}

    #[McpTool(name:'compose_search_catalogue', description:'Search accessible catalogue items with paginated exact released versions and personal favorite status. Set favoritesOnly to filter before pagination. An unpublished draft is not insertable; creation differs from reference insertion.', annotations:new ToolAnnotations(readOnlyHint:true, destructiveHint:false, idempotentHint:true, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['search_catalogue'])]
    public function search_catalogue(RequestContext $context):CallToolResult{return $this->call('search_catalogue',$context);}

    #[McpTool(name:'compose_list_preview_themes', description:'List existing immutable published themes available to the manual composer, with pagination. Does not create or edit themes.', annotations:new ToolAnnotations(readOnlyHint:true, destructiveHint:false, idempotentHint:true, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['list_preview_themes'])]
    public function list_preview_themes(RequestContext $context):CallToolResult{return $this->call('list_preview_themes',$context);}

    #[McpTool(name:'compose_get_reference', description:'Read the editable props JSON schema and defaults of one accessible immutable release, including transitive permission checks.', annotations:new ToolAnnotations(readOnlyHint:true, destructiveHint:false, idempotentHint:true, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['get_reference'])]
    public function get_reference(RequestContext $context):CallToolResult{return $this->call('get_reference',$context);}

    #[McpTool(name:'compose_get', description:'Read a saved composition tree, stable node IDs, authoritative revision, reference pins and editing permissions.', annotations:new ToolAnnotations(readOnlyHint:true, destructiveHint:false, idempotentHint:true, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['get'])]
    public function get(RequestContext $context):CallToolResult{return $this->call('get',$context);}

    #[McpTool(name:'compose_get_node', description:'Read a bounded subtree with parent/position, legal actions and exact instance property schema.', annotations:new ToolAnnotations(readOnlyHint:true, destructiveHint:false, idempotentHint:true, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['get_node'])]
    public function get_node(RequestContext $context):CallToolResult{return $this->call('get_node',$context);}

    #[McpTool(name:'compose_create_draft', description:'Create a composition-owned block or layout draft. Components belong to the separate source editor. Requires an operationId; repeats with that exact key/input replay the result.', annotations:new ToolAnnotations(readOnlyHint:false, destructiveHint:false, idempotentHint:false, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['create_draft'])]
    public function create_draft(RequestContext $context):CallToolResult{return $this->call('create_draft',$context);}

    #[McpTool(name:'compose_apply_batch', description:'Atomically save 1–50 typed tree commands as one revision. Earlier add/duplicate aliases may be referenced as $alias. Any failure rolls back all edits.', annotations:new ToolAnnotations(readOnlyHint:false, destructiveHint:true, idempotentHint:false, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['apply_batch'])]
    public function apply_batch(RequestContext $context):CallToolResult{return $this->call('apply_batch',$context);}

    #[McpTool(name:'compose_validate', description:'Validate exactly this saved revision through the shared compiler, props schemas and immutable dependency resolver without changing the draft.', annotations:new ToolAnnotations(readOnlyHint:true, destructiveHint:false, idempotentHint:true, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['validate'])]
    public function validate(RequestContext $context):CallToolResult{return $this->call('validate',$context);}

    #[McpTool(name:'compose_update_metadata', description:'Save title/description through the same draft writer/history as the manual composer. expectedRevision and operationId are mandatory.', annotations:new ToolAnnotations(readOnlyHint:false, destructiveHint:true, idempotentHint:false, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['update_metadata'])]
    public function update_metadata(RequestContext $context):CallToolResult{return $this->call('update_metadata',$context);}

    #[McpTool(name:'compose_get_history', description:'List recoverable composition snapshots with paginated revisions; legacy history begins at the first new save.', annotations:new ToolAnnotations(readOnlyHint:true, destructiveHint:false, idempotentHint:true, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['get_history'])]
    public function get_history(RequestContext $context):CallToolResult{return $this->call('get_history',$context);}

    #[McpTool(name:'compose_restore_revision', description:'Restore a recoverable tree/metadata snapshot as a new revision. Preserve revision numbers and check current expectedRevision and permissions.', annotations:new ToolAnnotations(readOnlyHint:false, destructiveHint:true, idempotentHint:false, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['restore_revision'])]
    public function restore_revision(RequestContext $context):CallToolResult{return $this->call('restore_revision',$context);}

    #[McpTool(name:'compose_render_preview', description:'Queue an isolated preview of exactly the specified current revision, optionally in an existing published theme/viewport. operationId prevents duplicate jobs. No screenshot inspection is available.', annotations:new ToolAnnotations(readOnlyHint:false, destructiveHint:false, idempotentHint:false, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['render_preview'])]
    public function render_preview(RequestContext $context):CallToolResult{return $this->call('render_preview',$context);}

    #[McpTool(name:'compose_get_preview', description:'Read an owned preview status and captured revision. Optionally return bounded sandbox HTML/diagnostics; a URL does not imply visual inspection.', annotations:new ToolAnnotations(readOnlyHint:true, destructiveHint:false, idempotentHint:true, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['get_preview'])]
    public function get_preview(RequestContext $context):CallToolResult{return $this->call('get_preview',$context);}

    #[McpTool(name:'compose_cancel_preview', description:'Cancel an owned composition preview job. Does not modify the composition draft.', annotations:new ToolAnnotations(readOnlyHint:false, destructiveHint:true, idempotentHint:true, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['cancel_preview'])]
    public function cancel_preview(RequestContext $context):CallToolResult{return $this->call('cancel_preview',$context);}

    #[McpTool(name:'compose_add_container', description:'Append a typed container at an explicit parent/zero-based child position and save one revision.', annotations:new ToolAnnotations(readOnlyHint:false, destructiveHint:false, idempotentHint:false, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['add_container'])]
    public function add_container(RequestContext $context):CallToolResult{return $this->call('add_container',$context);}

    #[McpTool(name:'compose_add_text', description:'Insert escaped literal text at an explicit parent/position and save. Twig syntax remains literal text.', annotations:new ToolAnnotations(readOnlyHint:false, destructiveHint:false, idempotentHint:false, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['add_text'])]
    public function add_text(RequestContext $context):CallToolResult{return $this->call('add_text',$context);}

    #[McpTool(name:'compose_add_reference', description:'Insert an accessible immutable component/block/layout release at an explicit parent/position. Omitted props use that exact release defaults; referenced content is never modified.', annotations:new ToolAnnotations(readOnlyHint:false, destructiveHint:false, idempotentHint:false, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['add_reference'])]
    public function add_reference(RequestContext $context):CallToolResult{return $this->call('add_reference',$context);}

    #[McpTool(name:'compose_update_node', description:'Change only supported fields: container tag/classes, literal text, or reference name/version/props. JSON-schema validation is authoritative.', annotations:new ToolAnnotations(readOnlyHint:false, destructiveHint:true, idempotentHint:false, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['update_node'])]
    public function update_node(RequestContext $context):CallToolResult{return $this->call('update_node',$context);}

    #[McpTool(name:'compose_move_node', description:'Move a non-root subtree to a container and explicit position measured after removing the source; ancestor cycles are forbidden.', annotations:new ToolAnnotations(readOnlyHint:false, destructiveHint:true, idempotentHint:false, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['move_node'])]
    public function move_node(RequestContext $context):CallToolResult{return $this->call('move_node',$context);}

    #[McpTool(name:'compose_indent_node', description:'Match the UI: append the node into its immediately preceding container sibling. Cannot indent the root or first sibling.', annotations:new ToolAnnotations(readOnlyHint:false, destructiveHint:true, idempotentHint:false, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['indent_node'])]
    public function indent_node(RequestContext $context):CallToolResult{return $this->call('indent_node',$context);}

    #[McpTool(name:'compose_outdent_node', description:'Match the UI: insert the node immediately after its parent in the grandparent. Direct children of the root cannot outdent.', annotations:new ToolAnnotations(readOnlyHint:false, destructiveHint:true, idempotentHint:false, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['outdent_node'])]
    public function outdent_node(RequestContext $context):CallToolResult{return $this->call('outdent_node',$context);}

    #[McpTool(name:'compose_move_up', description:'Match the UI: swap with the preceding sibling. Root and boundary moves fail without changing content.', annotations:new ToolAnnotations(readOnlyHint:false, destructiveHint:true, idempotentHint:false, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['move_up'])]
    public function move_up(RequestContext $context):CallToolResult{return $this->call('move_up',$context);}

    #[McpTool(name:'compose_move_down', description:'Match the UI: swap with the following sibling. Root and boundary moves fail without changing content.', annotations:new ToolAnnotations(readOnlyHint:false, destructiveHint:true, idempotentHint:false, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['move_down'])]
    public function move_down(RequestContext $context):CallToolResult{return $this->call('move_down',$context);}

    #[McpTool(name:'compose_duplicate_node', description:'Deep-copy a non-root subtree immediately after itself with fresh stable IDs. Does not alter referenced releases.', annotations:new ToolAnnotations(readOnlyHint:false, destructiveHint:false, idempotentHint:false, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['duplicate_node'])]
    public function duplicate_node(RequestContext $context):CallToolResult{return $this->call('duplicate_node',$context);}

    #[McpTool(name:'compose_remove_node', description:'Remove a non-root subtree and save a recoverable revision. Root removal is forbidden.', annotations:new ToolAnnotations(readOnlyHint:false, destructiveHint:true, idempotentHint:false, openWorldHint:false), outputSchema:ComposeSchemas::RESULT)]
    #[Schema(definition:ComposeSchemas::INPUT['remove_node'])]
    public function remove_node(RequestContext $context):CallToolResult{return $this->call('remove_node',$context);}
}
