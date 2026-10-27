<?php
namespace App\Packaging;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\CanonicalJson;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
final readonly class ArchiveStore
{
    public function __construct(private CanonicalJson $json, #[Autowire('%kernel.project_dir%/var/catalogue')] private string $root) {}
    public function put(\stdClass $manifest, \stdClass $sources): array
    {
        $directory = $this->root.'/releases';
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) throw new \RuntimeException('Archive storage unavailable.');
        $temp = tempnam($directory, '.stage-');
        try {
            $zip = new \ZipArchive();
            if (true !== $zip->open($temp, \ZipArchive::OVERWRITE)) throw new \RuntimeException('Cannot stage archive.');
            $files = ['manifest.json' => $this->json->encode($manifest, true)."\n", ...(array) $sources];
            ksort($files);
            foreach ($files as $path => $content) {
                $zip->addFromString($path, $content);
                // Stored entries avoid self-produced highly repetitive text tripping ratio limits.
                $zip->setCompressionName($path, \ZipArchive::CM_STORE);
                $zip->setMtimeName($path, 315532800);
                $zip->setExternalAttributesName($path, \ZipArchive::OPSYS_UNIX, 0100644 << 16);
            }
            if (!$zip->close() || filesize($temp) > 10485760) throw new CatalogueException(413, 'Release archive exceeds 10 MiB.');
            $hash = hash_file('sha256', $temp);
            $key = 'releases/'.$hash.'.zip';
            // Content-addressed files are never overwritten. Concurrent identical writes converge.
            if (!@link($temp, $this->root.'/'.$key) && (!is_file($this->root.'/'.$key) || !hash_equals($hash, hash_file('sha256', $this->root.'/'.$key)))) throw new \RuntimeException('Cannot install immutable archive.');
            return [$key, $hash];
        } finally { if (is_file($temp)) unlink($temp); }
    }
}
