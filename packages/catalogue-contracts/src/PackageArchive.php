<?php
namespace Styleguide\Contracts;
final readonly class PackageArchive
{
    public function __construct(private SafeArchive $archives,private PackageValidator $validator) {}
    public function read(string $file): array
    {
        $files=$this->archives->read($file);
        if(!isset($files['manifest.json'])) throw new CatalogueException(422,'Missing manifest.json.');
        $manifest=StrictJson::decode($files['manifest.json']);unset($files['manifest.json']);
        if(!$manifest instanceof \stdClass) throw new CatalogueException(422,'Manifest must be an object.');
        foreach($files as $path=>$bytes) $files[$path]=SourceBytes::encode($path,$bytes);
        $sources=(object)$files;$manifest=$this->validator->validate($manifest,$sources);
        return [$manifest,$sources];
    }
}
