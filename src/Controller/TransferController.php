<?php
namespace App\Controller;
use App\Entity\Release;
use App\Entity\ThemeRelease;
use App\Packaging\ExportArtifacts;
use App\Packaging\ExportInput;
use App\Packaging\ThemePackager;
use App\Packaging\TransferExporter;
use App\Packaging\TransferImporter;
use App\Rendering\ReleaseSourceReader;
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\CatalogueException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
#[IsGranted('ROLE_READER')]
final class TransferController extends AbstractController
{
    #[Route('/imports',name:'package_import',methods:['GET'])]
    #[IsGranted('ROLE_EDITOR')]
    public function page():Response{return $this->render('transfer/import.html.twig');}
    #[Route('/api/v1/imports',name:'package_import_api',methods:['POST'],format:'json')]
    #[IsGranted('ROLE_EDITOR')]
    #[IsCsrfTokenValid('api',tokenKey:'X-CSRF-Token',tokenSource:IsCsrfTokenValid::SOURCE_HEADER)]
    public function import(Request $request,TransferImporter $importer):JsonResponse
    {
        if(array_diff($request->request->keys(),['mode','expectedPlan','format','content']) || array_diff($request->files->keys(),['file']))throw new CatalogueException(400,'Unknown import fields.');
        $mode=$request->request->getString('mode','validate');if(!in_array($mode,['validate','dry-run','apply'],true))throw new CatalogueException(400,'Choose validate, dry-run or apply.');
        $file=$request->files->get('file');$temp=null;
        try{
            if($file instanceof UploadedFile){if(!$file->isValid() || $file->getSize()>10485760)throw new CatalogueException(413,'Upload must be within 10 MiB.');$path=$file->getPathname();$format=strtolower($file->getClientOriginalExtension());}
            else{$content=$request->request->getString('content');if(''===$content || strlen($content)>3145728)throw new CatalogueException(413,'Choose a complete ZIP or JSON/YAML envelope within limits.');$format=$request->request->getString('format','json');$temp=tempnam(sys_get_temp_dir(),'studio-import-');file_put_contents($temp,$content);$path=$temp;}
            $prepared=$importer->prepare($path,$format);
            if('apply'===$mode){$expected=$request->request->getString('expectedPlan');if(''===$expected)throw new CatalogueException(428,'Validate or dry-run first and supply its plan hash.');return new JsonResponse(['plan'=>$prepared['plan'],'result'=>$importer->apply($prepared,$this->getUser(),$expected)]);}
            return new JsonResponse(['valid'=>true,'plan'=>$prepared['plan'],'planHash'=>$prepared['planHash'],'applied'=>false],headers:['Cache-Control'=>'private, no-store']);
        }finally{if($temp && is_file($temp))unlink($temp);}
    }
    #[Route('/api/v1/exports',name:'package_export_api',methods:['POST'],format:'json')]
    #[IsCsrfTokenValid('api',tokenKey:'X-CSRF-Token',tokenSource:IsCsrfTokenValid::SOURCE_HEADER)]
    public function export(#[MapRequestPayload(acceptFormat:'json',serializationContext:[JsonDecode::ASSOCIATIVE=>false,AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES=>false])]ExportInput $input,EntityManagerInterface $em,TransferExporter $exporter,ExportArtifacts $artifacts):JsonResponse
    {
        $release=$em->find(Release::class,$input->releaseId)??throw new CatalogueException(404,'Release not found.');
        $theme=$input->themeReleaseId?($em->find(ThemeRelease::class,$input->themeReleaseId)??throw new CatalogueException(404,'Theme release not found.')):null;
        $artifact=$artifacts->create($this->getUser(),$exporter->export($release,$input->format,$theme,$input->props));
        return new JsonResponse(['status'=>'ready','downloadUrl'=>$this->generateUrl('package_export_download',['id'=>$artifact['id']]),'sha256'=>$artifact['sha256'],'expiresAt'=>$artifact['expiresAt']],201,['Cache-Control'=>'private, no-store']);
    }
    #[Route('/api/v1/exports/{id}/download',name:'package_export_download',methods:['GET'])]
    public function download(string $id,ExportArtifacts $artifacts):Response
    {
        $artifact=$artifacts->read($this->getUser(),$id);$m=$artifact['metadata'];$response=new BinaryFileResponse($artifact['path'],headers:['Content-Type'=>$m->type,'Cache-Control'=>'private, no-store','X-Content-SHA256'=>$m->sha256,'X-Content-Type-Options'=>'nosniff'],public:false);$response->setContentDisposition('attachment',$m->name);return $response;
    }
    #[Route('/releases/{id}/source.zip',name:'release_source_download',methods:['GET'])]
    public function source(#[MapEntity(id:'id')]Release $release,ReleaseSourceReader $reader):Response
    {
        $response=new BinaryFileResponse($reader->path($release),headers:['Content-Type'=>'application/zip','Cache-Control'=>'private, no-store','X-Content-SHA256'=>$release->getArchiveHash()],public:false);$response->setContentDisposition('attachment',str_replace('/','-',$release->getItem()->getName()).'-'.$release->getVersion().'.zip');return $response;
    }
    #[Route('/api/v1/theme-releases/{id}/package',name:'theme_package_publish',methods:['POST'],format:'json')]
    #[IsGranted('ROLE_EDITOR')]
    #[IsCsrfTokenValid('api',tokenKey:'X-CSRF-Token',tokenSource:IsCsrfTokenValid::SOURCE_HEADER)]
    public function themePackage(#[MapEntity(id:'id')]ThemeRelease $theme,ThemePackager $packager):JsonResponse
    {
        $release=$packager->publish($theme);return new JsonResponse(['id'=>$release->getId(),'version'=>$release->getVersion(),'archiveHash'=>$release->getArchiveHash()],201);
    }
}
