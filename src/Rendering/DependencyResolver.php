<?php
namespace App\Rendering;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\PackageGraph;
use App\Entity\Release;
use App\Repository\CatalogueRepository;
use Doctrine\ORM\EntityManagerInterface;
final readonly class DependencyResolver
{
    public function __construct(private CatalogueRepository $catalogue,private EntityManagerInterface $entityManager,private ReleaseSourceReader $releases,private PackageGraph $graphs) {}
    public function resolve(\stdClass $manifest,\stdClass $sources):array
    {
        $closure=$this->graphs->resolve($manifest,$sources,function(string $name,string $version):array {
            $item=$this->catalogue->named($name);$release=$item?$this->entityManager->getRepository(Release::class)->findOneBy(['item'=>$item,'version'=>$version]):null;
            if(!$release) throw new CatalogueException(422,'Missing immutable dependency '.$name.'@'.$version.'.');
            return [$release->getManifestData(),$this->releases->read($release)];
        });
        unset($closure['packages']);
        return $closure;
    }
}
