<?php
namespace App\Mcp\Security;
use App\Entity\{ComposeToken,User};
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\CatalogueException;
final readonly class ComposeTokens
{
    public const SCOPES=['compose:read','compose:create','compose:edit','compose:preview'];
    public function __construct(private EntityManagerInterface $em){}
    public function issue(User $owner,string $label,array $scopes,array $patterns=['*'],int $days=7):array
    {
        if(!$owner->isEnabled()||!array_intersect($owner->getRoles(),['ROLE_EDITOR','ROLE_ADMIN']))throw new CatalogueException(403,'Composer credentials require an enabled editor.');
        if(trim($label)===''||mb_strlen($label)>100||$days<1||$days>90||!$scopes||array_diff($scopes,self::SCOPES)||!$patterns||count($patterns)>20)throw new CatalogueException(422,'Choose composer scopes, catalogue patterns and 1–90 days.');
        foreach($patterns as $p)if(!is_string($p)||!preg_match('~^(?:\*|[a-z][a-z0-9-]*/(?:\*|[a-z][a-z0-9-]*))$~D',$p))throw new CatalogueException(422,'Catalogue patterns use namespace/slug, namespace/* or *.');
        $plain='sgc_'.bin2hex(random_bytes(32));$token=new ComposeToken($owner,trim($label),hash('sha256',$plain),array_values(array_unique($scopes)),array_values(array_unique($patterns)),new \DateTimeImmutable('+'.$days.' days'));
        $this->em->persist($token);$this->em->flush();return [$token,$plain];
    }
    public function verify(#[\SensitiveParameter] string $plain):?ComposeIdentity
    {
        if(!preg_match('/^sgc_[a-f0-9]{64}$/D',$plain))return null;
        $token=$this->em->getRepository(ComposeToken::class)->findOneBy(['tokenHash'=>hash('sha256',$plain)]);
        return $token?->isActive()?new ComposeIdentity($token->getOwner(),$token->getScopes(),$token->getCataloguePatterns(),'pat:'.$token->getId()):null;
    }
}
