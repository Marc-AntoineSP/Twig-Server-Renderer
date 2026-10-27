<?php
namespace App\Tests\Integration;
use App\Tests\DatabaseTestCase;
use App\Registry\Tokens;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
final class ReadTokenCommandTest extends DatabaseTestCase
{
    public function testCommandsIssueOnlyHashedScopedTokensAndRevokeById():void
    {
        self::bootKernel();$admin=$this->account('ROLE_ADMIN');$app=new Application(self::$kernel);
        $issue=new CommandTester($app->find('styleguide:token:create'));
        self::assertSame(0,$issue->execute(['owner'=>$admin->getEmail(),'--scope'=>['awui/*'],'--days'=>2]));
        self::assertSame(1,preg_match('/sgt_[a-f0-9]{64}/',$issue->getDisplay(),$match));$plain=$match[0];
        $token=self::getContainer()->get(Tokens::class)->verify($plain);self::assertNotNull($token);
        self::assertTrue($token->allows('awui/alert'));self::assertFalse($token->allows('other/alert'));
        $stored=self::getContainer()->get(Connection::class)->fetchOne('SELECT token_hash FROM read_token WHERE id = ?',[$token->getId()]);
        self::assertSame(hash('sha256',$plain),$stored);self::assertNotSame($plain,$stored);
        $revoke=new CommandTester($app->find('styleguide:token:revoke'));self::assertSame(0,$revoke->execute(['id'=>$token->getId()]));self::assertNull(self::getContainer()->get(Tokens::class)->verify($plain));
        $reader=$this->account();self::assertSame(2,$issue->execute(['owner'=>$reader->getEmail(),'--scope'=>['*']]));
    }
}
