<?php
namespace App\Controller;
use App\Entity\Release;
use App\Registry\RegistryIdentity;
use App\Rendering\ReleaseSourceReader;
use App\Repository\CatalogueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\CanonicalJson;
use Styleguide\Contracts\CatalogueException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted('ROLE_READER')]
#[Route('/api/v1/registry/{namespace}/{slug}',requirements:['namespace'=>'[a-z][a-z0-9-]*','slug'=>'[a-z][a-z0-9-]*'],format:'json')]
final class RegistryController extends AbstractController
{
    private function item(string $namespace,string $slug,CatalogueRepository $catalogue):\App\Entity\CatalogueItem
    {
        $name=$namespace.'/'.$slug;$identity=$this->getUser();
        if(!$identity instanceof RegistryIdentity || !$identity->token->allows($name)) throw new CatalogueException(403,'Registry token does not cover this package.');
        return $catalogue->named($name)??throw new CatalogueException(404,'Package not found.');
    }
    private function release(string $namespace,string $slug,string $version,CatalogueRepository $catalogue,EntityManagerInterface $em):Release
    {
        $item=$this->item($namespace,$slug,$catalogue);
        return $em->getRepository(Release::class)->findOneBy(['item'=>$item,'version'=>$version])??throw new CatalogueException(404,'Immutable version not found.');
    }
    private function cachedJson(Request $request,mixed $data,CanonicalJson $json):Response
    {
        $body=$json->encode($data);$response=new Response($body,headers:['Content-Type'=>'application/json','Cache-Control'=>'private, no-cache, must-revalidate','X-Content-Type-Options'=>'nosniff']);
        $response->setEtag(hash('sha256',$body));$response->isNotModified($request);return $response;
    }
    #[Route('',name:'registry_package',methods:['GET'])]
    public function package(Request $request,string $namespace,string $slug,CatalogueRepository $catalogue,EntityManagerInterface $em,CanonicalJson $json):Response
    {
        $item=$this->item($namespace,$slug,$catalogue);$versions=[];
        foreach($em->getRepository(Release::class)->findBy(['item'=>$item],['createdAt'=>'DESC'],1000) as $release) $versions[]=['version'=>$release->getVersion(),'archiveSha256'=>$release->getArchiveHash()];
        usort($versions,static fn($a,$b)=>version_compare($b['version'],$a['version']));
        return $this->cachedJson($request,['schemaVersion'=>1,'name'=>$item->getName(),'kind'=>$item->getKind(),'versions'=>$versions],$json);
    }
    #[Route('/{version}/manifest',name:'registry_manifest',methods:['GET'],requirements:['version'=>'[0-9]+\.[0-9]+\.[0-9]+'])]
    public function manifest(Request $request,string $namespace,string $slug,string $version,CatalogueRepository $catalogue,EntityManagerInterface $em,CanonicalJson $json):Response
    {
        return $this->cachedJson($request,$this->release($namespace,$slug,$version,$catalogue,$em)->getManifestData(),$json);
    }
    #[Route('/{version}/archive',name:'registry_archive',methods:['GET'],requirements:['version'=>'[0-9]+\.[0-9]+\.[0-9]+'])]
    public function archive(Request $request,string $namespace,string $slug,string $version,CatalogueRepository $catalogue,EntityManagerInterface $em,ReleaseSourceReader $reader):Response
    {
        $release=$this->release($namespace,$slug,$version,$catalogue,$em);$path=$reader->path($release);
        $response=new BinaryFileResponse($path,headers:['Content-Type'=>'application/zip','Cache-Control'=>'private, no-cache, must-revalidate','X-Content-SHA256'=>$release->getArchiveHash(),'X-Content-Type-Options'=>'nosniff'],public:false);
        $response->setContentDisposition('attachment',$namespace.'-'.$slug.'-'.$version.'.zip');$response->setEtag($release->getArchiveHash());$response->isNotModified($request);return $response;
    }
}
