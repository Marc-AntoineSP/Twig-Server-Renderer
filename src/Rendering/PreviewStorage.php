<?php

namespace App\Rendering;

use Styleguide\Contracts\CatalogueException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

final readonly class PreviewStorage
{
    public function __construct(private Filesystem $filesystem, #[Autowire('%kernel.project_dir%/var/catalogue/previews')] private string $directory)
    {
    }

    public function write(string $id, array $result, ?string $token = null): void
    {
        $encoded=json_encode($result,JSON_THROW_ON_ERROR);if(strlen($encoded)>5242880)throw new CatalogueException(413,'Preview output exceeds storage limits.');
        if(!is_dir($this->directory))$this->filesystem->mkdir($this->directory,0770);
        $lock=fopen($this->directory.'/.quota.lock','c+');if(!$lock||!flock($lock,LOCK_EX))throw new \RuntimeException('Preview storage unavailable.');
        try{$size=0;foreach(glob($this->directory.'/*.json')as$file)$size+=filesize($file);if($size+strlen($encoded)>536870912)throw new CatalogueException(429,'Preview storage is full; wait for scheduled cleanup.');
        $this->filesystem->dumpFile($this->path($id, $token), $encoded);
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }

    public function read(string $id, ?string $token = null): array
    {
        $path = $this->path($id, $token);
        if (!is_file($path) || filesize($path) > 5242880) {
            throw new CatalogueException(410, 'Preview document has expired. Request a new preview.');
        }

        return json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    }

    private function path(string $id, ?string $token): string
    {
        if (!preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/D', $id)) {
            throw new \InvalidArgumentException('Invalid preview identifier.');
        }

        if(null!==$token&&!preg_match('/^[a-f0-9]{32}$/D',$token))throw new \InvalidArgumentException('Invalid preview attempt.');
        return $this->directory.'/'.$id.($token?'-'.$token:'').'.json';
    }
}
