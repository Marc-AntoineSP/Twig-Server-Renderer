<?php
namespace App\Mcp\Security;
use Mcp\Server\Session\SessionStoreInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Uid\Uuid;
#[AsDecorator('mcp.server.compose.session.store')]
final readonly class ActorSessionStore implements SessionStoreInterface
{
    public function __construct(private SessionStoreInterface $inner,private TokenStorageInterface $security){}
    private function key(Uuid $id):Uuid
    {
        $identity=$this->security->getToken()?->getUser();
        if(!$identity instanceof ComposeIdentity)throw new \LogicException('Authenticated MCP identity is required before session access.');
        return Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL),$identity->getUserIdentifier().':'.$id->toRfc4122());
    }
    public function exists(Uuid $id):bool{return $this->inner->exists($this->key($id));}
    public function read(Uuid $id):string|false{return $this->inner->read($this->key($id));}
    public function write(Uuid $id,string $data):bool{return $this->inner->write($this->key($id),$data);}
    public function destroy(Uuid $id):bool{return $this->inner->destroy($this->key($id));}
    public function gc():array{return $this->inner->gc();}
}
