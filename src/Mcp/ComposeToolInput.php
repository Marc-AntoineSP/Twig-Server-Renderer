<?php
namespace App\Mcp;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Server\RequestContext;
use Styleguide\Contracts\StrictJson;
use Symfony\Component\HttpFoundation\RequestStack;
/** SDK dispatch/validation remains authoritative; preserve {} vs [] in JSON prop values. */
final readonly class ComposeToolInput
{
    public function __construct(private RequestStack $requests){}
    public function arguments(RequestContext $context):array
    {
        $tool=$context->getRequest();
        if(!$tool instanceof CallToolRequest)throw new \LogicException('Expected the SDK tool-call context.');
        $http=$this->requests->getCurrentRequest();
        if($http){$raw=StrictJson::decode($http->getContent(),262144,64);$messages=is_array($raw)?$raw:[$raw];foreach($messages as $message)if($message instanceof \stdClass&&($message->id??null)===$tool->getId()&&($message->params->name??null)===$tool->name)return get_object_vars($message->params->arguments??new \stdClass());}
        return $tool->arguments;
    }
}
