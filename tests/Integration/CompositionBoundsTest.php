<?php
namespace App\Tests\Integration;
use App\Catalogue\CatalogueException;
use App\Composition\CompositionValidator;
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
}
