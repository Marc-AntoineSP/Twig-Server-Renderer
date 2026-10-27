<?php
namespace App\Controller;
use App\Entity\ReadToken;
use App\Registry\Tokens;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted('ROLE_ADMIN')]
final class TokenController extends AbstractController
{
    #[Route('/registry-tokens',name:'registry_tokens',methods:['GET'])]
    public function index(EntityManagerInterface $em):Response{return $this->page($em);}
    #[Route('/registry-tokens',name:'registry_token_create',methods:['POST'])]
    #[IsCsrfTokenValid('registry-token',tokenKey:'_token')]
    public function create(Request $request,Tokens $tokens,EntityManagerInterface $em):Response
    {
        [$token,$plain]=$tokens->issue($this->getUser(),$request->request->getString('label'),preg_split('/[\s,]+/',trim($request->request->getString('scopes')),-1,PREG_SPLIT_NO_EMPTY),$request->request->getInt('days',30));
        return $this->page($em,$plain);
    }
    #[Route('/registry-tokens/{id}/revoke',name:'registry_token_revoke',methods:['POST'])]
    #[IsCsrfTokenValid('registry-token',tokenKey:'_token')]
    public function revoke(#[MapEntity(id:'id')] ReadToken $token,Tokens $tokens):Response
    {
        $tokens->revoke($token);$this->addFlash('success','Registry token revoked.');return $this->redirectToRoute('registry_tokens',status:303);
    }
    private function page(EntityManagerInterface $em,?string $plain=null):Response
    {
        return $this->render('registry/tokens.html.twig',['tokens'=>$em->getRepository(ReadToken::class)->findBy([],['createdAt'=>'DESC'],200),'plain'=>$plain],new Response(headers:['Cache-Control'=>'private, no-store']));
    }
}
