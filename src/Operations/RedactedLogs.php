<?php
namespace App\Operations;
use Monolog\LogRecord;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\When;
#[When(env:'prod')]
#[AutoconfigureTag('monolog.processor')]
final class RedactedLogs
{
    public function __invoke(LogRecord $record):LogRecord
    {
        $context=[];foreach(['requestId','jobId','inputHash','durationMs','queueWaitMs','errorCode','exceptionClass'] as $key)if(isset($record->context[$key])&&is_scalar($record->context[$key]))$context[$key]=substr((string)$record->context[$key],0,128);
        if(($record->context['exception']??null) instanceof \Throwable)$context['exceptionClass']=$record->context['exception']::class;
        $known=['API request failed.','Preview finished.','Preview queued.','Cleanup completed.'];
        $message=in_array($record->message,$known,true)?$record->message:match($record->channel){'request'=>'HTTP request event.','security'=>'Security event.','console'=>'Console operation event.',default=>'Application event.'};
        return $record->with(message:$message,context:$context,extra:[]);
    }
}
