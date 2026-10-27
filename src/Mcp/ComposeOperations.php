<?php
namespace App\Mcp;
use App\Entity\{CompositionOperation,User};
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\{CanonicalJson,CatalogueException};
/** Durable actor/operation/target-scoped receipts, in the same transaction as the write. */
final readonly class ComposeOperations
{
    public function __construct(private EntityManagerInterface $em,private CanonicalJson $json){}
    public function run(User $actor,string $operation,string $target,string $operationId,array $input,callable $authorize,callable $write,?callable $replayAuthorize=null):array
    {
        if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{7,63}$/D',$operationId))throw new CatalogueException(422,'operationId must be an 8–64 character opaque retry key.',[],'VALIDATION_FAILED');
        $authorize();$hash=hash('sha256',$this->json->encode(json_decode(json_encode($input,JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR)));
        return $this->em->wrapInTransaction(function()use($actor,$operation,$target,$operationId,$hash,$authorize,$write,$replayAuthorize):array{
            $db=$this->em->getConnection();
            // Transaction advisory lock serializes retries before their first receipt exists.
            $db->executeQuery('SELECT pg_advisory_xact_lock(hashtextextended(:key, 0))',['key'=>'compose:'.$actor->getId().':'.$operation.':'.$target.':'.$operationId]);
            $receipt=$this->em->getRepository(CompositionOperation::class)->findOneBy(['owner'=>$actor,'operation'=>$operation,'target'=>$target,'operationId'=>$operationId]);
            $authorize();
            if($receipt){
                if(!hash_equals($receipt->getPayloadHash(),$hash))throw new CatalogueException(409,'This operationId was already used with different arguments.',[],'IDEMPOTENCY_CONFLICT');
                $result=$receipt->getResult();if($replayAuthorize)$replayAuthorize($result);$result['replayed']=true;return $result;
            }
            $result=$write();
            $this->em->persist(new CompositionOperation($actor,$operation,$target,$operationId,$hash,json_decode(json_encode($result,JSON_THROW_ON_ERROR),false,64,JSON_THROW_ON_ERROR)));
            return $result;
        });
    }
}
