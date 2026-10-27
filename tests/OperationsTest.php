<?php
namespace App\Tests;
use App\Operations\RedactedLogs;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
final class OperationsTest extends TestCase
{
    public function testProductionLogsKeepDiagnosticIdsAndDiscardPrivateInput():void
    {
        $secret='private-token-and-source-content';$record=new LogRecord(new \DateTimeImmutable(),'request',Level::Error,'Uncaught exception '.$secret,['requestId'=>'request-123','exception'=>new \RuntimeException($secret),'headers'=>['Authorization'=>$secret],'payload'=>$secret],['cookie'=>$secret]);
        $safe=(new RedactedLogs())($record);$json=json_encode($safe);self::assertStringNotContainsString($secret,$json);self::assertSame('request-123',$safe->context['requestId']);self::assertSame(\RuntimeException::class,$safe->context['exceptionClass']);self::assertSame([],$safe->extra);
    }
}
