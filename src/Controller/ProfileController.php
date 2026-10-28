<?php
namespace App\Controller;

use App\Entity\User;
use App\Mcp\Security\ComposeTokens;
use App\Profile\{AccountProfile,ComposeConnections};
use Doctrine\Persistence\ManagerRegistry;
use Styleguide\Contracts\CatalogueException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\{IsCsrfTokenValid,IsGranted};

#[IsGranted('ROLE_READER')]
final class ProfileController extends AbstractController
{
    public function __construct(private readonly AccountProfile $profiles,private readonly ComposeConnections $connections,private readonly ManagerRegistry $registry){}

    #[Route('/profile',name:'profile_show',methods:['GET'])]
    public function show():Response{return $this->page();}

    #[Route('/profile/details',name:'profile_details',methods:['POST'])]
    #[IsCsrfTokenValid('profile-details',tokenKey:'_token')]
    public function details(Request $request):Response
    {
        $values=['displayName'=>$request->request->getString('displayName'),'email'=>$request->request->getString('email')];
        try{$this->profiles->update($this->account(),$values['displayName'],$values['email'],$request->request->getString('currentPassword'),$request->request->getString('state'));}
        catch(CatalogueException $error){return $this->page('details',$error,$values);}
        $this->addFlash('success','Profile updated.');return $this->redirectToRoute('profile_show',['_fragment'=>'account'],303);
    }

    #[Route('/profile/password',name:'profile_password',methods:['POST'])]
    #[IsCsrfTokenValid('profile-password',tokenKey:'_token')]
    public function password(Request $request):Response
    {
        try{$this->profiles->changePassword($this->account(),$request->request->getString('currentPassword'),$request->request->getString('newPassword'),$request->request->getString('confirmation'));}
        catch(CatalogueException $error){return $this->page('password',$error);}
        $request->getSession()->migrate(true);
        $this->addFlash('success','Password updated. Other browser sessions and existing MCP credentials no longer authorize access.');return $this->redirectToRoute('profile_show',['_fragment'=>'password'],303);
    }

    #[Route('/profile/details',name:'profile_details_return',methods:['GET'])]
    #[Route('/profile/password',name:'profile_password_return',methods:['GET'])]
    #[Route('/profile/mcp/tokens',name:'profile_mcp_token_return',methods:['GET'])]
    #[Route('/profile/mcp/oauth',name:'profile_mcp_oauth_return',methods:['GET'])]
    public function returnToProfile():Response{return $this->redirectToRoute('profile_show',['_fragment'=>'mcp'],303);}

    #[Route('/profile/mcp/tokens',name:'profile_mcp_token_create',methods:['POST'])]
    #[IsGranted('ROLE_EDITOR')]
    #[IsCsrfTokenValid('profile-mcp',tokenKey:'_token')]
    public function createToken(Request $request):Response
    {
        $values=['label'=>$request->request->getString('label'),'patterns'=>$request->request->getString('patterns'),'days'=>$request->request->getString('days'),'scopes'=>$request->request->all('scopes')];
        try{[, $plain]=$this->connections->issue($this->account(),$values['label'],$values['scopes'],$values['patterns'],ctype_digit($values['days'])?(int)$values['days']:0);}
        catch(CatalogueException $error){return $this->page('mcp',$error,tokenValues:$values);}
        return $this->page(plain:$plain);
    }

    #[Route('/profile/mcp/tokens/{id}/revoke',name:'profile_mcp_token_revoke',methods:['POST'])]
    #[IsGranted('ROLE_EDITOR')]
    #[IsCsrfTokenValid('profile-mcp',tokenKey:'_token')]
    public function revokeToken(string $id):Response
    {
        $this->connections->revoke($this->account(),$id);$this->addFlash('success','MCP credential revoked.');return $this->redirectToRoute('profile_show',['_fragment'=>'mcp'],303);
    }

    #[Route('/profile/mcp/codex.toml',name:'profile_mcp_codex',methods:['GET'])]
    #[IsGranted('ROLE_EDITOR')]
    public function codex():Response
    {
        return new Response($this->connections->configuration()['toml'],headers:['Content-Type'=>'text/plain; charset=utf-8','Content-Disposition'=>'attachment; filename="styleguide-compose-mcp.toml"','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }

    #[Route('/profile/mcp/oauth',name:'profile_mcp_oauth_create',methods:['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[IsCsrfTokenValid('profile-oauth',tokenKey:'_token')]
    public function createClient(Request $request):Response
    {
        $values=['label'=>$request->request->getString('label'),'redirect'=>$request->request->getString('redirect')];
        try{[$client,$secret]=$this->connections->createClient($values['label'],$values['redirect']);}
        catch(CatalogueException $error){return $this->page('oauth',$error,oauthValues:$values);}
        return $this->page(newClient:['id'=>$client->getIdentifier(),'secret'=>$secret]);
    }

    #[Route('/profile/mcp/oauth/{id}/disable',name:'profile_mcp_oauth_disable',methods:['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[IsCsrfTokenValid('profile-oauth',tokenKey:'_token')]
    public function disableClient(string $id):Response
    {
        $this->connections->setClientActive($id,false);$this->addFlash('success','OAuth client disabled. Its issued tokens no longer authorize MCP calls.');return $this->redirectToRoute('profile_show',['_fragment'=>'chatgpt'],303);
    }

    #[Route('/profile/mcp/oauth/{id}/enable',name:'profile_mcp_oauth_enable',methods:['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[IsCsrfTokenValid('profile-oauth',tokenKey:'_token')]
    public function enableClient(string $id):Response
    {
        $this->connections->setClientActive($id,true);$this->addFlash('success','OAuth client enabled.');return $this->redirectToRoute('profile_show',['_fragment'=>'chatgpt'],303);
    }

    private function account():User
    {
        $user=$this->getUser();if(!$user instanceof User)throw $this->createAccessDeniedException();return $user;
    }
    private function page(?string $section=null,?CatalogueException $error=null,array $profileValues=[],array $tokenValues=[],array $oauthValues=[],#[\SensitiveParameter]?string $plain=null,#[\SensitiveParameter]?array $newClient=null):Response
    {
        $user=$this->account();
        // Recover the view after a rare concurrent unique-email violation rolls back flush.
        if(!$this->registry->getManager()->isOpen()){$em=$this->registry->resetManager();$user=$em->find(User::class,$user->getId());}
        return $this->render('profile/show.html.twig',[
            'account'=>$user,'profileState'=>$this->profiles->fingerprint($user),'profileValues'=>$profileValues+['displayName'=>$user->getDisplayName()??'','email'=>$user->getEmail()],
            'connection'=>$this->connections->configuration(),'credentials'=>$this->isGranted('ROLE_EDITOR')?$this->connections->owned($user):[],'oauthClients'=>$this->connections->oauthClients(),
            'scopeLabels'=>['compose:read'=>'Read catalogue and schemas','compose:create'=>'Create composition drafts','compose:edit'=>'Edit saved compositions','compose:preview'=>'Render isolated previews'],
            'tokenValues'=>$tokenValues+['label'=>'Codex on my computer','patterns'=>'*','days'=>'7','scopes'=>ComposeTokens::SCOPES],'oauthValues'=>$oauthValues+['label'=>'ChatGPT catalogue composer','redirect'=>''],
            'errorSection'=>$section,'error'=>$error?->getMessage(),'fieldErrors'=>$error?->fieldErrors??[],'plain'=>$plain,'newClient'=>$newClient,
        ],new Response(status:$error?->getStatusCode()??200,headers:['Cache-Control'=>'private, no-store','Referrer-Policy'=>'same-origin','X-Robots-Tag'=>'noindex, nofollow']));
    }
}
