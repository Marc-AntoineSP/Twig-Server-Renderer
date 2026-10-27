<?php
namespace App\Mcp;
use App\Mcp\Tool\ComposeTools;
use Mcp\Capability\RegistryInterface;
use Mcp\Capability\Registry\ReferenceHandler;
use Mcp\Schema\JsonRpc\{Error,Request,Response};
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Server\Handler\Request\{CallToolHandler,RequestHandlerInterface};
use Mcp\Server\{RequestContext};
use Mcp\Server\Session\SessionInterface;
use Symfony\Component\DependencyInjection\Attribute\{AutoconfigureTag,Autowire};
use Symfony\Component\DependencyInjection\ServiceLocator;
/** Preserve JSON object/list types before delegating validation/execution to the SDK. */
#[AutoconfigureTag('mcp.request_handler')]
final readonly class ComposeToolHandler implements RequestHandlerInterface
{
    private CallToolHandler $delegate;
    public function __construct(#[Autowire(service:'mcp.server.compose.registry')]RegistryInterface $registry,ComposeTools $tools,private ComposeToolInput $inputs)
    {
        $this->delegate=new CallToolHandler($registry,new ReferenceHandler(new ServiceLocator([ComposeTools::class=>static fn()=>$tools])));
    }
    public function supports(Request $request):bool{return $request instanceof CallToolRequest;}
    public function handle(Request $request,SessionInterface $session):Response|Error
    {
        \assert($request instanceof CallToolRequest);
        $typed=(new CallToolRequest($request->name,$this->inputs->arguments(new RequestContext($session,$request))))->withId($request->getId())->withMeta($request->getMeta()??[]);
        return $this->delegate->handle($typed,$session);
    }
}
