<?php
namespace App\Controller;
use App\Catalogue\Duplicator;
use App\Entity\CatalogueItem;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted('ROLE_EDITOR')]
final class DuplicateController extends AbstractController
{
    #[Route('/catalogue/{id}/duplicate',name:'catalogue_duplicate',methods:['POST'])]
    #[IsCsrfTokenValid('duplicate',tokenKey:'_token')]
    public function duplicate(Request $request,#[MapEntity(id:'id')]CatalogueItem $item,Duplicator $duplicator):Response
    {
        $draft=$duplicator->duplicate($item,$request->request->getString('name'),$this->getUser());$this->addFlash('success','Draft duplicated. Original source and releases preserved.');return $this->redirectToRoute('catalogue_edit',['id'=>$draft->getItem()->getId()],303);
    }
}
