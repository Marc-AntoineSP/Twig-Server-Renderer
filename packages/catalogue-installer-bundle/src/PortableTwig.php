<?php
namespace Styleguide\Installer;
use Styleguide\Contracts\CatalogueException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\Node;
use Twig\Sandbox\SecurityPolicy;
final class PortableTwig
{
    public function validate(array $packages):void
    {
        $templates=[];foreach($packages as $p)foreach($p['sources'] as $path=>$source)if(str_starts_with($path,'templates/'))$templates[substr($path,10)]=$source;
        $twig=new Environment(new ArrayLoader($templates),['strict_variables'=>true,'autoescape'=>'html','cache'=>false]);
        $policy=new SecurityPolicy(allowedTags:['if','for','set','include'],allowedFilters:['escape','e','default','length','upper','lower','join','json_encode'],allowedFunctions:['range'],allowedTests:['defined','empty','null','none','true','false','same as','even','odd','iterable','sequence','mapping']);$policy->setStrict(true);
        $edges=[];$count=0;
        $walk=function(Node $node,string $name)use(&$walk,&$edges,&$count,$templates,$policy):void{
            if(++$count>20000)throw new CatalogueException(422,'Twig syntax exceeds its node budget.');
            $policy->checkSecurity($node->getNodeTag()?[$node->getNodeTag()]:[],$node instanceof \Twig\Node\Expression\FilterExpression?[$node->getAttribute('name')]:[],$node instanceof \Twig\Node\Expression\FunctionExpression?[$node->getAttribute('name')]:[],$node instanceof \Twig\Node\Expression\TestExpression?[$node->getAttribute('name')]:[]);
            if($node instanceof \Twig\Node\Expression\Variable\ContextVariable && in_array($node->getAttribute('name'),['app','request','user','_self','_context','_charset'],true))throw new CatalogueException(422,'Application globals are forbidden in portable Twig.');
            if($node instanceof \Twig\Node\Expression\GetAttrExpression && $node->getAttribute('type')===\Twig\Template::METHOD_CALL)throw new CatalogueException(422,'Object method calls are forbidden in portable Twig.');
            if($node instanceof \Twig\Node\IncludeNode){$expr=$node->getNode('expr');if(!$expr instanceof \Twig\Node\Expression\ConstantExpression||!is_string($expr->getAttribute('value'))||!array_key_exists($expr->getAttribute('value'),$templates))throw new CatalogueException(422,'Include is outside the exact installed template closure.');$edges[$name][]=$expr->getAttribute('value');}
            foreach($node as $child)$walk($child,$name);
        };
        try{foreach($templates as $name=>$source)$walk($twig->parse($twig->tokenize(new \Twig\Source($source,$name))),$name);}catch(\Twig\Error\Error $e){throw new CatalogueException(422,'Portable Twig '.$e->getSourceContext()?->getName().':'.$e->getTemplateLine().' '.$e->getRawMessage());}
        $visit=function(string $name,array $stack=[])use(&$visit,$edges):void{if(isset($stack[$name])||count($stack)>20)throw new CatalogueException(422,'Twig include cycle/depth limit.');$stack[$name]=true;foreach($edges[$name]??[] as $target)$visit($target,$stack);};foreach(array_keys($templates) as $name)$visit($name);
    }
}
