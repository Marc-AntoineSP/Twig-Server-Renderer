<?php
namespace Styleguide\Contracts;
final class SafeArchive
{
    public function read(string $file): array
    {
        if(!is_file($file) || filesize($file)>10485760) throw new CatalogueException(413,'Archive exceeds 10 MiB or is unavailable.');
        $zip=new \ZipArchive();
        if(true!==$zip->open($file,\ZipArchive::RDONLY) || $zip->numFiles>500) throw new CatalogueException(422,'Invalid archive or too many files.');
        $files=[];$seen=[];$total=0;
        try {
            for($index=0;$index<$zip->numFiles;$index++) {
                $stat=$zip->statIndex($index);$name=$stat['name'];PortablePath::check($name);
                $zip->getExternalAttributesIndex($index,$system,$attributes);$type=($attributes>>16)&0170000;
                if(isset($seen[strtolower($name)]) || ($type && 0100000!==$type) || ($stat['encryption_method']??0)!==0 || $stat['size']>10485760 || $stat['size']>100*max(1,$stat['comp_size'])) throw new CatalogueException(422,'Unsafe, duplicate, encrypted or excessive archive entry.');
                $seen[strtolower($name)]=true;
                $stream=$zip->getStream($name);if(false===$stream) throw new CatalogueException(422,'Cannot read archive entry.');
                $content='';
                try {
                    while(!feof($stream)) {
                        $chunk=fread($stream,65536);if(false===$chunk) throw new CatalogueException(422,'Cannot read archive data.');
                        $content.=$chunk;$total+=strlen($chunk);
                        if(strlen($content)>10485760 || $total>52428800) throw new CatalogueException(413,'Archive exceeds inflated limits.');
                    }
                } finally {fclose($stream);}
                if(strlen($content)!==$stat['size']) throw new CatalogueException(422,'Archive entry length mismatch.');
                $files[$name]=$content;
            }
        } finally {$zip->close();}
        return $files;
    }
}
