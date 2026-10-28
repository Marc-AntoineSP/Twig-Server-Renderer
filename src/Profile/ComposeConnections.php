<?php
namespace App\Profile;

use App\Entity\{ComposeToken,User};
use App\Mcp\Security\ComposeTokens;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\ValueObject\{Grant,RedirectUri,Scope};
use Styleguide\Contracts\CatalogueException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/** Browser setup reuses the same credential services as the existing CLI. */
final readonly class ComposeConnections
{
    public function __construct(private ComposeTokens $tokens,private EntityManagerInterface $em,private ClientManagerInterface $clients,#[Autowire(service:'league.oauth2_server.password_hasher')]private PasswordHasherInterface $hasher,private AuthorizationCheckerInterface $auth,#[Autowire('%env(MCP_PUBLIC_BASE_URL)%')]private string $base,#[Autowire('%env(bool:MCP_COMPOSE_ENABLED)%')]private bool $enabled){}
    public function configuration():array
    {
        $base=rtrim($this->base,'/');$endpoint=$base.'/mcp/compose';
        return ['enabled'=>$this->enabled,'endpoint'=>$endpoint,'issuer'=>$base,'publicHttps'=>str_starts_with($base,'https://'),'toml'=>"[mcp_servers.styleguide_compose]\nurl = ".json_encode($endpoint,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\nbearer_token_env_var = \"STYLEGUIDE_COMPOSE_MCP_TOKEN\"\n"];
    }
    public function issue(User $owner,string $label,array $scopes,string $patterns,int $days):array
    {
        if(preg_match('/[\x00-\x1F\x7F]/u',$label))throw new CatalogueException(422,'Choose a credential name without control characters.');
        $active=$this->em->createQuery('SELECT COUNT(t.id) FROM App\Entity\ComposeToken t WHERE t.owner = :owner AND t.revokedAt IS NULL AND t.expiresAt > :now')->setParameters(['owner'=>$owner,'now'=>new \DateTimeImmutable()])->getSingleScalarResult();
        if($active>=20)throw new CatalogueException(422,'Revoke an unused credential before creating another. Up to 20 may be active.');
        foreach($scopes as $scope)if(!is_string($scope))throw new CatalogueException(422,'Choose valid composer permissions.');
        $patterns=preg_split('/[\s,]+/',trim($patterns),-1,PREG_SPLIT_NO_EMPTY);
        return $this->tokens->issue($owner,$label,$scopes,$patterns,$days);
    }
    public function revoke(User $owner,string $id):void
    {
        $token=$this->em->getRepository(ComposeToken::class)->findOneBy(['id'=>$id,'owner'=>$owner]);
        if(!$token)throw new CatalogueException(404,'Personal MCP credential not found.');
        $token->revoke();$this->em->flush();
    }
    public function owned(User $owner):array{return $this->em->getRepository(ComposeToken::class)->findBy(['owner'=>$owner],['createdAt'=>'DESC'],100);}
    public function oauthClients():array
    {
        if(!$this->auth->isGranted('ROLE_ADMIN'))return [];
        return $this->em->getRepository(Client::class)->findBy([],['name'=>'ASC'],100);
    }
    public function createClient(string $label,string $redirect):array
    {
        $this->admin();$label=trim($label);$redirect=trim($redirect);
        if($label===''||mb_strlen($label)>100||preg_match('/[\x00-\x1F\x7F]/u',$label))throw new CatalogueException(422,'Choose a client name of 1–100 characters.');
        // The profile exposes ChatGPT registration only, with exact callbacks from its UI.
        if(!preg_match('~^https://chatgpt\.com/(?:connector_platform_oauth_redirect|connector/oauth/[A-Za-z0-9_-]{1,128})$~D',$redirect))throw new CatalogueException(422,'Copy the exact HTTPS callback URL from your ChatGPT MCP connection.');
        $secret=bin2hex(random_bytes(32));$client=new Client($label,bin2hex(random_bytes(16)),$this->hasher->hash($secret));
        $client->setRedirectUris(new RedirectUri($redirect))->setGrants(new Grant('authorization_code'),new Grant('refresh_token'))->setScopes(...array_map(static fn($scope)=>new Scope($scope),ComposeTokens::SCOPES));
        $this->clients->save($client);return [$client,$secret];
    }
    public function setClientActive(string $id,bool $active):void
    {
        $this->admin();$client=$this->clients->find($id)??throw new CatalogueException(404,'OAuth client not found.');
        $client->setActive($active);$this->clients->save($client);
    }
    private function admin():void{if(!$this->auth->isGranted('ROLE_ADMIN'))throw new CatalogueException(403,'Only a studio administrator can manage OAuth clients.');}
}
