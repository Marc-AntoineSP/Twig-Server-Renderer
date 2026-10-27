<?php
namespace App\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted('ROLE_EDITOR')]
final class ComposeOAuthController extends AbstractController
{
    #[Route('/oauth2/compose/approval/{id}',name:'compose_oauth_consent',requirements:['id'=>'[a-f0-9]{48}'],methods:['GET','POST'])]
    public function consent(Request $request,string $id):Response
    {
        $pending=$request->getSession()->get('compose.oauth.pending',[]);$entry=$pending[$id]??null;
        if(!$entry||$entry['owner']!==$this->getUser()->getId()||$entry['expires']<time())throw $this->createNotFoundException('Authorization request expired. Start the connection again.');
        if($request->isMethod('POST')) {
            if(!$this->isCsrfTokenValid('compose-oauth-'.$id,$request->request->getString('_token')))throw $this->createAccessDeniedException('Invalid consent token.');
            if($request->request->getString('decision')!=='approve'){unset($pending[$id]);$request->getSession()->set('compose.oauth.pending',$pending);return $this->render('mcp/denied.html.twig',response:new Response(status:403));}
            $entry['approved']=true;$pending[$id]=$entry;$request->getSession()->set('compose.oauth.pending',$pending);
            return $this->redirectToRoute('oauth2_authorize',$entry['query']+['_compose_consent'=>$id],303);
        }
        return $this->render('mcp/consent.html.twig',['entry'=>$entry,'consentId'=>$id]);
    }
}
