<?php
namespace App\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class DemoController extends AbstractController
{
    #[Route('/', name: 'demo')]
    public function index(): Response
    {
        $root=$this->getParameter('kernel.project_dir');$packages=[];
        if(is_file($root.'/styleguide.lock.json'))foreach(json_decode(file_get_contents($root.'/styleguide.lock.json'),true,512,JSON_THROW_ON_ERROR)['packages'] as $p){
            $manifest=json_decode(file_get_contents($root.'/.styleguide/packages/'.$p['name'].'/'.$p['version'].'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
            if($manifest['kind']!=='theme')$packages[]=['entry'=>substr($manifest['entry'],10),'props'=>$manifest['defaults'],'name'=>$manifest['name']];
        }
        return $this->render('demo.html.twig',['packages'=>$packages]);
    }
}
