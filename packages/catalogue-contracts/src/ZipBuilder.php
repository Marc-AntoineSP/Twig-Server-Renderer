<?php
namespace Styleguide\Contracts;
final class ZipBuilder
{
    public static function bytes(array $files):string
    {
        if(count($files)>500) throw new CatalogueException(413,'Too many export files.');
        $path=tempnam(sys_get_temp_dir(),'catalogue-zip-');$total=0;
        try {
            $zip=new \ZipArchive();if(true!==$zip->open($path,\ZipArchive::OVERWRITE)) throw new \RuntimeException('Cannot stage archive.');
            ksort($files,SORT_STRING);
            foreach($files as $name=>$content) {
                PortablePath::check($name);$total+=strlen($content);if($total>52428800) throw new CatalogueException(413,'Export exceeds 50 MiB.');
                $zip->addFromString($name,$content);$zip->setCompressionName($name,\ZipArchive::CM_STORE);$zip->setMtimeName($name,315532800);$zip->setExternalAttributesName($name,\ZipArchive::OPSYS_UNIX,0100644<<16);
            }
            if(!$zip->close() || filesize($path)>10485760) throw new CatalogueException(413,'Export exceeds 10 MiB.');
            return file_get_contents($path);
        }finally{if(is_file($path))unlink($path);}
    }
}
