<?php
namespace App\Packaging;
use Styleguide\Contracts\CatalogueException;
final class ExportFile
{
    public function write(string $output,string $bytes):void
    {
        if(''===$output || !is_dir(dirname($output)))throw new CatalogueException(422,'Choose an output file in an existing directory.');
        if(file_exists($output) || is_link($output))throw new CatalogueException(409,'Output already exists; choose another path.');
        $temp=tempnam(dirname($output),'.styleguide-export-');
        try{if(file_put_contents($temp,$bytes)!==strlen($bytes) || !@link($temp,$output))throw new CatalogueException(409,'Could not install output without overwriting an existing file.');}finally{if(is_file($temp))unlink($temp);}
    }
}
