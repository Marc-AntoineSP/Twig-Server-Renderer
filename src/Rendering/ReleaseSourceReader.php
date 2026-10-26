<?php

namespace App\Rendering;

use App\Catalogue\CatalogueException;
use App\Catalogue\Validation\PackageValidator;
use App\Entity\Release;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ReleaseSourceReader
{
    public function __construct(
        private PackageValidator $validator,
        #[Autowire('%kernel.project_dir%/var/catalogue')]
        private string $directory,
    ) {
    }

    public function read(Release $release): \stdClass
    {
        $this->validator->path($release->getArchiveKey());
        $path = realpath($this->directory.'/'.$release->getArchiveKey());
        $root = realpath($this->directory);
        if (!$root || !$path || !str_starts_with($path, $root.'/') || !is_file($path) || filesize($path) > 10485760 || !hash_equals($release->getArchiveHash(), hash_file('sha256', $path))) {
            throw new CatalogueException(422, 'Dependency archive is missing or fails integrity verification.');
        }
        $zip = new \ZipArchive();
        if (true !== $zip->open($path, \ZipArchive::RDONLY) || $zip->numFiles > 101) {
            throw new CatalogueException(422, 'Dependency archive is invalid or exceeds file limits.');
        }
        $sources = new \stdClass();
        $seen = [];
        $bytes = 0;
        $archiveManifest = null;
        try {
            for ($index = 0; $index < $zip->numFiles; ++$index) {
                $stat = $zip->statIndex($index);
                $name = $stat['name'];
                $this->validator->path($name);
                $zip->getExternalAttributesIndex($index, $system, $attributes);
                $type = ($attributes >> 16) & 0170000;
                if (isset($seen[strtolower($name)]) || ($type && 0100000 !== $type) || $stat['size'] > 1048576 || ($stat['size'] > 0 && $stat['size'] > 100 * max(1, $stat['comp_size']))) {
                    throw new CatalogueException(422, 'Dependency archive has unsafe entries or exceeds expansion limits.');
                }
                $seen[strtolower($name)] = true;
                $stream = $zip->getStream($name);
                if (false === $stream) {
                    throw new CatalogueException(422, 'Cannot read dependency file.');
                }
                $content = stream_get_contents($stream, 1048577);
                fclose($stream);
                if (strlen($content) !== $stat['size'] || ($bytes += strlen($content)) > 2359296) {
                    throw new CatalogueException(422, 'Dependency archive exceeds expanded byte limits.');
                }
                if ('manifest.json' === $name) {
                    $archiveManifest = json_decode($content, false, 32, JSON_THROW_ON_ERROR);
                } else {
                    $sources->$name = $content;
                }
            }
        } catch (\JsonException) {
            throw new CatalogueException(422, 'Dependency manifest JSON is invalid.');
        } finally {
            $zip->close();
        }
        if ($archiveManifest != $release->getManifestData()) {
            throw new CatalogueException(422, 'Dependency archive manifest does not match its immutable release.');
        }
        $this->validator->validate($archiveManifest, $sources);

        return $sources;
    }
}
