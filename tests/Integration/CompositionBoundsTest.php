<?php
namespace App\Tests\Integration;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\CompositionValidator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
final class CompositionBoundsTest extends KernelTestCase
{
    public function testBoundsAndUnsafeClassesAreRejected(): void
    {
        self::bootKernel();$validator=self::getContainer()->get(CompositionValidator::class);
        $node=(object)['type'=>'text','value'=>'Leaf'];
        $cases=[(object)['type'=>'container','tag'=>'script','classes'=>[],'children'=>[]],(object)['type'=>'container','tag'=>'div','classes'=>['x" onmouseover="x'],'children'=>[]],(object)['type'=>'container','tag'=>'div','classes'=>[],'children'=>array_fill(0,501,$node)]];
        for($i=0;$i<21;$i++) $node=(object)['type'=>'container','tag'=>'div','classes'=>[],'children'=>[$node]];
        $cases[]=$node;
        foreach($cases as $root) { try{$validator->validate((object)['schemaVersion'=>1,'root'=>$root]);self::fail('Invalid tree accepted.');}catch(CatalogueException $e){self::assertSame(422,$e->getStatusCode());} }
    }
    public function testStableIdsAndCommandBoundsPreserveTheOriginalWorkingTree():void
    {
        self::bootKernel();$trees=self::getContainer()->get(\App\Composition\CompositionTree::class);
        $original=(object)['schemaVersion'=>1,'root'=>(object)['type'=>'container','tag'=>'section','classes'=>[],'children'=>[]]];
        $doc=$trees->normalize($original,'stable-composition');self::assertEquals($doc,$trees->normalize($original,'stable-composition'));self::assertObjectNotHasProperty('id',$original->root);
        $root=$doc->root->id;
        $created=$trees->apply($doc,'stable-composition',[(object)['type'=>'add_container','parentId'=>$root,'position'=>0,'alias'=>'one'],(object)['type'=>'add_container','parentId'=>'$one','position'=>0,'alias'=>'two'],(object)['type'=>'add_text','parentId'=>'$two','position'=>0,'value'=>'Text','alias'=>'text']]);
        $duplicated=$trees->apply($created['document'],'stable-composition',[(object)['type'=>'duplicate_node','nodeId'=>$created['aliases']->one]]);
        $ids=array_keys($trees->index($duplicated['document']));self::assertCount(7,$ids);self::assertCount(7,array_unique($ids));self::assertCount(1,$created['document']->root->children);
        foreach([
            [(object)['type'=>'add_text','parentId'=>$root,'position'=>5]],
            [(object)['type'=>'add_text','parentId'=>$created['aliases']->text,'position'=>0]],
            [(object)['type'=>'outdent_node','nodeId'=>$created['aliases']->one]],
            [(object)['type'=>'move_node','nodeId'=>$created['aliases']->one,'parentId'=>$created['aliases']->two,'position'=>0]],
            [(object)['type'=>'add_text','parentId'=>'$missing','position'=>0]],
            array_fill(0,51,(object)['type'=>'add_text','parentId'=>$root,'position'=>0]),
        ] as $commands){try{$trees->apply($created['document'],'stable-composition',$commands);self::fail('Invalid command accepted.');}catch(CatalogueException $error){self::assertContains($error->getStatusCode(),[422,404]);}self::assertCount(1,$created['document']->root->children);self::assertSame('Text',$created['document']->root->children[0]->children[0]->children[0]->value);}
        $moved=$trees->apply($created['document'],'stable-composition',[(object)['type'=>'move_node','nodeId'=>$created['aliases']->text,'parentId'=>$root,'position'=>1],(object)['type'=>'move_node','nodeId'=>$created['aliases']->text,'parentId'=>$root,'position'=>0],(object)['type'=>'update_node','nodeId'=>$root,'changes'=>(object)['tag'=>'article','classes'=>['grid','md:grid-cols-2']]]]);
        self::assertSame($created['aliases']->text,$moved['document']->root->children[0]->id);self::assertSame($created['aliases']->one,$moved['document']->root->children[1]->id);self::assertSame('article',$moved['document']->root->tag);self::assertSame(['grid','md:grid-cols-2'],$moved['document']->root->classes);self::assertSame('section',$created['document']->root->tag);
        $invalid=json_decode(json_encode($created['document']));$invalid->root->children[0]->id=$root;
        try{$trees->normalize($invalid,'stable-composition');self::fail('Duplicate node IDs accepted.');}catch(CatalogueException $error){self::assertSame(422,$error->getStatusCode());}
    }

}
