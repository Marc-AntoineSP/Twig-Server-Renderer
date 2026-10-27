<?php
namespace App\Tests\Integration;
use App\Mcp\Security\ComposeTokens;
use App\Tests\DatabaseTestCase;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class ComposeTokenCommandTest extends DatabaseTestCase
{
    public function testCliNarrowScopesReplaceDefaultsStoreOnlyHashAndRevoke():void
    {
        self::bootKernel();$editor=$this->account();$app=new Application(self::$kernel);$issue=new CommandTester($app->find('styleguide:mcp:token:issue'));
        self::assertSame(0,$issue->execute(['--email'=>$editor->getEmail(),'--scope'=>['compose:read'],'--catalogue'=>['cli-test/*'],'--days'=>'1','--token-only'=>true]));
        $plain=trim($issue->getDisplay());self::assertMatchesRegularExpression('/^sgc_[a-f0-9]{64}$/D',$plain);$identity=self::getContainer()->get(ComposeTokens::class)->verify($plain);self::assertNotNull($identity);
        self::assertTrue($identity->permits('compose:read'));self::assertFalse($identity->permits('compose:edit'));self::assertTrue($identity->matches('cli-test/item'));self::assertFalse($identity->matches('other/item'));
        $db=self::getContainer()->get(Connection::class);$record=$db->fetchAssociative('SELECT id,token_hash FROM compose_token WHERE token_hash = ?',[hash('sha256',$plain)]);self::assertSame(hash('sha256',$plain),$record['token_hash']);self::assertNotSame($plain,$record['token_hash']);
        $revoke=new CommandTester($app->find('styleguide:mcp:token:revoke'));self::assertSame(0,$revoke->execute(['id'=>$record['id']]));self::assertNull(self::getContainer()->get(ComposeTokens::class)->verify($plain));
    }
}
