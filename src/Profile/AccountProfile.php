<?php
namespace App\Profile;

use App\Entity\{ComposeToken,User};
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\CatalogueException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Personal account changes never accept an actor, role, or enabled flag from a form. */
final readonly class AccountProfile
{
    public function __construct(private EntityManagerInterface $em,private UserPasswordHasherInterface $hasher,private ValidatorInterface $validator,#[Autowire(service:'limiter.profile_password')]private RateLimiterFactoryInterface $limiter){}

    public function update(User $user,string $name,string $email,#[\SensitiveParameter]string $password,string $expected):void
    {
        $name=trim($name);$email=mb_strtolower(trim($email));$errors=[];
        if(mb_strlen($name)>100||preg_match('/[\x00-\x1F\x7F]/u',$name))$errors['displayName']=['Use at most 100 characters without control characters.'];
        if(count($this->validator->validate($email,[new Assert\NotBlank(),new Assert\Email(),new Assert\Length(max:180)])))$errors['email']=['Enter a valid email address (maximum 180 characters).'];
        if($errors)throw new CatalogueException(422,'Check your account details.',$errors);
        $other=$this->em->getRepository(User::class)->findOneBy(['email'=>$email]);
        if($other&&$other->getId()!==$user->getId())throw new CatalogueException(422,'This email address is already in use.',['email'=>['Choose a different email address.']]);
        $previous=[$user->getDisplayName(),$user->getEmail()];
        try{
            $this->em->getConnection()->transactional(function()use($user,$name,$email,$password,$expected):void{
                $this->em->refresh($user,LockMode::PESSIMISTIC_WRITE);
                if(!hash_equals($this->fingerprint($user),$expected))throw new CatalogueException(412,'Your profile changed in another tab. Reload before saving again.');
                if($email!==$user->getEmail())$this->checkPassword($user,$password);
                $user->updateProfile($name===''?null:$name,$email);$this->em->flush();
            });
        }catch(UniqueConstraintViolationException){$user->updateProfile(...$previous);throw new CatalogueException(422,'This email address is already in use.',['email'=>['Choose a different email address.']]);}
    }

    public function changePassword(User $user,#[\SensitiveParameter]string $current,#[\SensitiveParameter]string $new,#[\SensitiveParameter]string $confirmation):void
    {
        if(mb_strlen($new)<12||strlen($new)>4096)throw new CatalogueException(422,'Use a password of 12–4096 characters.',['newPassword'=>['Use at least 12 characters and at most 4096 bytes.']]);
        if(!hash_equals($new,$confirmation))throw new CatalogueException(422,'The new passwords do not match.',['confirmation'=>['Repeat the same new password.']]);
        $this->em->getConnection()->transactional(function()use($user,$current,$new):void{
            $this->em->refresh($user,LockMode::PESSIMISTIC_WRITE);$this->checkPassword($user,$current);
            $user->setPassword($this->hasher->hashPassword($user,$new));
            foreach($this->em->getRepository(ComposeToken::class)->findBy(['owner'=>$user]) as $token)$token->revoke();
            // OAuth subjects have used stable IDs since M13. Include legacy email subjects.
            $db=$this->em->getConnection();$subjects=['id'=>$user->getId(),'email'=>$user->getEmail()];
            $db->executeStatement('UPDATE oauth2_access_token SET revoked = true WHERE user_identifier = :id OR user_identifier = :email',$subjects);
            $db->executeStatement('UPDATE oauth2_authorization_code SET revoked = true WHERE user_identifier = :id OR user_identifier = :email',$subjects);
            $db->executeStatement('UPDATE oauth2_refresh_token SET revoked = true WHERE access_token IN (SELECT identifier FROM oauth2_access_token WHERE user_identifier = :id OR user_identifier = :email)',$subjects);
            $this->em->flush();
        });
    }

    public function fingerprint(User $user):string{return hash('sha256',json_encode([$user->getEmail(),$user->getDisplayName()],JSON_THROW_ON_ERROR));}
    private function checkPassword(User $user,#[\SensitiveParameter]string $password):void
    {
        if(!$this->limiter->create($user->getId())->consume()->isAccepted())throw new CatalogueException(429,'Too many password attempts. Try again in five minutes.');
        if(!$user->isEnabled()||strlen($password)>4096||!$this->hasher->isPasswordValid($user,$password))throw new CatalogueException(422,'Your current password is incorrect.',['currentPassword'=>['Enter your current account password.']]);
    }
}
