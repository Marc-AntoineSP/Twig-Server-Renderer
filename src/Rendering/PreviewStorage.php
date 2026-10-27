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

    public function write(string $id, array $result): void
    {
        $this->filesystem->dumpFile($this->path($id), json_encode($result, JSON_THROW_ON_ERROR));
    }

    public function read(string $id): array
    {
        $path = $this->path($id);
        if (!is_file($path) || filesize($path) > 5242880) {
            throw new CatalogueException(410, 'Preview document has expired. Request a new preview.');
        }

        return json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    }

    private function path(string $id): string
    {
        if (!preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/D', $id)) {
            throw new \InvalidArgumentException('Invalid preview identifier.');
        }

        return $this->directory.'/'.$id.'.json';
    }
}
