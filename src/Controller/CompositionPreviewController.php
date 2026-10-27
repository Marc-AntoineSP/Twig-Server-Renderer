<?php
namespace App\Controller;
use App\Entity\CompositionPreview;
use App\Rendering\PreviewStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted('ROLE_EDITOR')]
final class CompositionPreviewController extends AbstractController
{
    #[Route('/catalogue/composition-previews/{id}',name:'composition_preview_page',methods:['GET'])]
    public function show(string $id,EntityManagerInterface $em,PreviewStorage $storage):Response
    {
        $binding=$em->find(CompositionPreview::class,$id);
        if(!$binding||$binding->getJob()->getOwner()->getId()!==$this->getUser()->getId())throw $this->createNotFoundException('Owned preview not found.');
        $this->denyAccessUnlessGranted('COMPOSE_READ',$binding->getItem());$job=$binding->getJob();
        $html=$job->getStatus()==='succeeded'?$storage->read($job->getId(),$job->getRunToken())['html']:null;
        return $this->render('composition/result.html.twig',['binding'=>$binding,'html'=>$html,'width'=>$job->getPayload()['preset']['width']??768,'scripts'=>$job->getPayload()['preset']['scripts']??true],new Response(headers:['Cache-Control'=>'private, no-store']));
    }
}
