<?php
namespace App\Rendering;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\PackageArchive;
use Styleguide\Contracts\PortablePath;
use App\Entity\Release;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
final readonly class ReleaseSourceReader
{
    public function __construct(private PackageArchive $archives,#[Autowire('%kernel.project_dir%/var/catalogue')] private string $directory) {}
    public function path(Release $release):string
    {
        PortablePath::check($release->getArchiveKey());$path=realpath($this->directory.'/'.$release->getArchiveKey());$root=realpath($this->directory);
        if(!$root || !$path || !str_starts_with($path,$root.'/') || !is_file($path) || filesize($path)>10485760 || !hash_equals($release->getArchiveHash(),hash_file('sha256',$path))) throw new CatalogueException(422,'Dependency archive is missing or fails integrity verification.');
        return $path;
    }
    public function read(Release $release):\stdClass
    {
        [$manifest,$sources]=$this->archives->read($this->path($release));
        if($manifest!=$release->getManifestData()) throw new CatalogueException(422,'Dependency archive manifest does not match its immutable release.');
        return $sources;
    }
}
