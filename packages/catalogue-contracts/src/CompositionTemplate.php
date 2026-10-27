<?php
namespace Styleguide\Contracts;
final readonly class CompositionTemplate
{
    public function __construct(private CompositionValidator $validator,private PropsValidator $props) {}
    public function compile(\stdClass $document,string $owner,callable $lookup): array
    {
        $this->validator->validate($document);$dependencies=[];$targets=[];
        $render=function(\stdClass $node) use(&$render,&$dependencies,&$targets,$lookup,$owner):string {
            if('text'===$node->type) return '{{ '.TwigLiteral::encode($node->value).'|escape }}';
            if('container'===$node->type) return '<'.$node->tag.' class="'.implode(' ',$node->classes).'">'."\n".implode("\n",array_map($render,$node->children))."\n</".$node->tag.'>';
            if($node->name===$owner) throw new CatalogueException(422,'A composition cannot reference itself.');
            if(isset($dependencies[$node->name]) && $dependencies[$node->name]->version!==$node->version) throw new CatalogueException(409,'Conflicting exact versions for '.$node->name.'.');
            $key=$node->name.'@'.$node->version;
            $targets[$key]??=$lookup($node->name,$node->version);
            [$manifest,$files]=$targets[$key];
            if('theme'===$manifest->kind) throw new CatalogueException(422,'Themes cannot be composition references.');
            $this->props->validateValue($node->props,StrictJson::decode($files->{$manifest->propsSchema},65536,24),'reference.'.$key);
            $dependencies[$node->name]=(object)['name'=>$node->name,'version'=>$node->version];
            return '{% include '.TwigLiteral::encode(substr($manifest->entry,10)).' with '.TwigLiteral::encode($node->props).' only %}';
        };
        $twig=$render($document->root)."\n";
        return [$twig,array_values($dependencies)];
    }
}
