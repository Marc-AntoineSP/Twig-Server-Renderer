<?php

namespace App\Composition;

use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\CompositionValidator;

/** Shared structural commands. Input is typed composition data, never executable code. */
final readonly class CompositionTree
{
    public const MAX_BATCH = 50;
    public const COMMANDS = ['add_container', 'add_text', 'add_reference', 'update_node', 'move_node', 'indent_node', 'outdent_node', 'move_up', 'move_down', 'duplicate_node', 'remove_node'];

    public function __construct(private CompositionValidator $validator) {}

    public function normalize(\stdClass $document, string $compositionId): \stdClass
    {
        $this->validator->validate($document);
        $document = json_decode(json_encode($document, JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR);
        $seen = [];
        $walk = function (object $node, string $path) use (&$walk, &$seen, $compositionId): void {
            $node->id ??= 'n_'.substr(hash('sha256', $compositionId.':'.$path), 0, 32);
            if (isset($seen[$node->id])) $this->fail('VALIDATION_FAILED', 'Node IDs must be unique.', ['nodeId' => $node->id]);
            $seen[$node->id] = true;
            foreach ($node->children ?? [] as $i => $child) $walk($child, $path.'/'.$i);
        };
        $walk($document->root, 'root');
        return $document;
    }

    public function index(\stdClass $document): array
    {
        $index = [];
        $walk = function (object $node, ?string $parent, int $position, int $depth) use (&$walk, &$index): void {
            $index[$node->id] = ['node' => $node, 'parentId' => $parent, 'position' => $position, 'depth' => $depth];
            foreach ($node->children ?? [] as $i => $child) $walk($child, $node->id, $i, $depth + 1);
        };
        $walk($document->root, null, 0, 1);
        return $index;
    }

    /** A failed command/batch cannot mutate the caller's tree. Aliases are batch-local $name. */
    public function apply(\stdClass $document, string $compositionId, array $commands): array
    {
        if (!$commands || count($commands) > self::MAX_BATCH) $this->fail('VALIDATION_FAILED', 'Supply 1–50 typed commands.');
        $working = $this->normalize($document, $compositionId);$aliases = [];$affected = [];$selected = $working->root->id;
        foreach ($commands as $offset => $raw) {
            if (!$raw instanceof \stdClass) $this->fail('VALIDATION_FAILED', 'Each command must be an object.', ['command' => $offset]);
            $command = clone $raw;
            if (!in_array($command->type ?? '', self::COMMANDS, true)) $this->fail('VALIDATION_FAILED', 'Unsupported composer command.', ['command' => $offset]);
            $allowed = match ($command->type) {
                'add_container' => ['parentId','position','selectedId','tag','classes','alias'],
                'add_text' => ['parentId','position','selectedId','value','alias'],
                'add_reference' => ['parentId','position','selectedId','name','version','props','alias'],
                'update_node' => ['nodeId','changes'], 'move_node' => ['nodeId','parentId','position'],
                default => ['nodeId','alias'],
            };
            foreach (array_keys(get_object_vars($command)) as $key) if (!in_array($key, ['type', ...$allowed], true)) $this->fail('VALIDATION_FAILED', 'Unknown command field.', ['field' => $key, 'command' => $offset]);
            foreach (['nodeId','parentId','selectedId'] as $key) if (isset($command->$key) && !is_string($command->$key)) $this->fail('VALIDATION_FAILED', 'Node identifiers must be strings.', ['field'=>$key]);
            foreach (['nodeId','parentId','selectedId'] as $key) if (isset($command->$key) && str_starts_with((string) $command->$key, '$')) $command->$key = $aliases[substr($command->$key, 1)] ?? $this->fail('NOT_FOUND', 'Unknown earlier batch alias.', ['field' => $key]);
            $index = $this->index($working);
            $entry = null;
            if (isset($command->nodeId)) $entry = $index[$command->nodeId] ?? $this->fail('NOT_FOUND', 'Node not found.', ['nodeId' => $command->nodeId]);
            $type = $command->type;
            if (str_starts_with($type, 'add_')) {
                $node = match ($type) {
                    'add_container' => (object) ['type'=>'container','id'=>$this->id(),'tag'=>$command->tag ?? 'div','classes'=>$command->classes ?? ['grid','gap-4'],'children'=>[]],
                    'add_text' => (object) ['type'=>'text','id'=>$this->id(),'value'=>$command->value ?? 'New text'],
                    'add_reference' => (object) ['type'=>'reference','id'=>$this->id(),'name'=>$command->name ?? '', 'version'=>$command->version ?? '', 'props'=>$command->props ?? new \stdClass()],
                };
                if (isset($command->selectedId)) {
                    $chosen = $index[$command->selectedId] ?? $this->fail('NOT_FOUND', 'Selected node not found.');
                    $command->parentId = $chosen['node']->type === 'container' ? $chosen['node']->id : $chosen['parentId'];
                    $command->position = $chosen['node']->type === 'container' ? count($chosen['node']->children) : $chosen['position'] + 1;
                }
                $this->insert($index, $command->parentId ?? '', $command->position ?? null, $node);
                $selected = $node->id;$affected[] = $node->id;
            } else {
                if (!$entry) $this->fail('VALIDATION_FAILED', 'nodeId is required.');
                $node = $entry['node'];$selected = $node->id;$affected[] = $node->id;
                if ($type === 'update_node') {
                    $changes = $command->changes ?? null;
                    if (!$changes instanceof \stdClass || !get_object_vars($changes)) $this->fail('VALIDATION_FAILED', 'changes must be a non-empty object.');
                    $fields = match ($node->type) {'container'=>['tag','classes'], 'text'=>['value'], 'reference'=>['name','version','props']};
                    foreach ($changes as $key=>$value) {if (!in_array($key,$fields,true)) $this->fail('VALIDATION_FAILED','This node property cannot be edited.',['field'=>$key,'nodeId'=>$node->id]);$node->$key=$value;}
                } else {
                    if ($entry['parentId'] === null) $this->fail('INVALID_PARENT', 'The root cannot be moved, duplicated or removed.');
                    $parent = $index[$entry['parentId']]['node'];$i = $entry['position'];
                    switch ($type) {
                        case 'remove_node': array_splice($parent->children,$i,1);$selected=$parent->id;break;
                        case 'duplicate_node':
                            $copy=json_decode(json_encode($node,JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR);
                            $reidentify=function(object $n)use(&$reidentify,&$affected):void{$n->id=$this->id();$affected[]=$n->id;foreach($n->children??[] as $child)$reidentify($child);};$reidentify($copy);
                            array_splice($parent->children,$i+1,0,[$copy]);$selected=$copy->id;break;
                        case 'move_up': case 'move_down':
                            $j=$i+($type==='move_up'?-1:1);
                            if ($j<0||$j>=count($parent->children)) $this->fail('INVALID_PARENT','No sibling exists in that direction.');
                            [$parent->children[$i],$parent->children[$j]]=[$parent->children[$j],$parent->children[$i]];break;
                        case 'indent_node':
                            if ($i<1||$parent->children[$i-1]->type!=='container') $this->fail('INVALID_PARENT','Indent requires an immediately preceding container sibling.');
                            $target=$parent->children[$i-1];array_splice($parent->children,$i,1);$target->children[]=$node;break;
                        case 'outdent_node':
                            $p=$index[$parent->id];if($p['parentId']===null)$this->fail('INVALID_PARENT','A direct child of the root cannot outdent.');
                            array_splice($parent->children,$i,1);array_splice($index[$p['parentId']]['node']->children,$p['position']+1,0,[$node]);break;
                        case 'move_node':
                            $destination=$command->parentId??'';
                            for($p=$destination;isset($index[$p]);$p=$index[$p]['parentId']??'')if($p===$node->id)$this->fail('REFERENCE_CYCLE','Cannot move a node inside its own subtree.');
                            // Position is measured in the destination list after removing the source.
                            array_splice($parent->children,$i,1);$this->insert($index,$destination,$command->position??null,$node);break;
                    }
                }
            }
            if (isset($command->alias)) {
                if (!in_array($type,['add_container','add_text','add_reference','duplicate_node'],true)||!is_string($command->alias)||!preg_match('/^[a-z][a-z0-9_]{0,31}$/D',$command->alias)||isset($aliases[$command->alias])) $this->fail('VALIDATION_FAILED','Aliases must be unique short names on creation/duplication.');
                $aliases[$command->alias]=$selected;
            }
            $this->validator->validate($working);
        }
        return ['document'=>$working,'selectedNodeId'=>$selected,'affectedNodeIds'=>array_values(array_unique($affected)),'aliases'=>(object)$aliases];
    }

    private function insert(array $index, string $parentId, mixed $position, object $node): void
    {
        $parent=$index[$parentId]['node']??null;
        if (!$parent || $parent->type!=='container') $this->fail('INVALID_PARENT','The destination must be a container.',['parentId'=>$parentId]);
        if (!is_int($position)||$position<0||$position>count($parent->children)) $this->fail('INVALID_PARENT','Position must be between zero and the destination child count.',['position'=>$position]);
        array_splice($parent->children,$position,0,[$node]);
    }
    private function id(): string {return 'n_'.bin2hex(random_bytes(16));}
    private function fail(string $code,string $message,array $details=[]): never {throw new CatalogueException($code==='NOT_FOUND'?404:422,$message,$details,$code);}
}
