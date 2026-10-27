<?php

namespace App\Font;

use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\FontRegistry;
use Styleguide\Contracts\SourceBytes;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class FontLibrary
{
    public function __construct(#[Autowire('%kernel.project_dir%/var/catalogue/fonts')] private string $root)
    {
    }

    public function catalogue(): array
    {
        $families = [];
        foreach (glob($this->root.'/families/*/current.json') ?: [] as $path) {
            $record = json_decode(file_get_contents($path), true, 24, JSON_THROW_ON_ERROR);
            $families[$record['id']] = $record;
        }
        uasort($families, static fn ($a, $b) => strcasecmp($a['family'], $b['family']));

        return $families;
    }

    public function definition(string $id): \stdClass
    {
        if (isset(FontRegistry::SYSTEM[$id])) return (object) ['id' => $id, 'kind' => 'system', 'family' => $id];
        $record = $this->catalogue()[$id] ?? throw new CatalogueException(422, 'Font family is not installed: '.$id);
        $font = array_intersect_key($record, array_flip(['id', 'kind', 'family', 'category', 'source', 'sourceVersion', 'license', 'licensePath', 'licenseSha256', 'faces']));
        $font['faces'] = array_map(static function ($face) {
            $face = array_diff_key($face, array_flip(['fontFamily', 'fontSubfamily']));
            $face['axes'] = (object) $face['axes'];
            return $face;
        }, $font['faces']);

        return json_decode(json_encode($font, JSON_THROW_ON_ERROR), false, 24, JSON_THROW_ON_ERROR);
    }

    public function importStaged(string $directory, array $pin, bool $dryRun = false): array
    {
        $path = $directory.'/family.json';
        if (!is_file($path) || is_link($path) || filesize($path) > 65536 || !hash_equals($pin['metadataSha256'], hash_file('sha256', $path))) throw new CatalogueException(422, 'Staged font metadata differs from its pinned manifest. Download the family again.');
        $record = json_decode(file_get_contents($path), true, 24, JSON_THROW_ON_ERROR);
        if (!preg_match('/^[a-z][a-z0-9-]*$/D', $record['id']) || $record['sourceVersion'] !== $pin['version'] || $record['tarballSha256'] !== $pin['tarballSha256']) throw new CatalogueException(422, 'Staged font identity differs.');
        $staged = [];
        foreach ($record['faces'] as $face) {
            if ($face['path'] !== 'fonts/'.$face['sha256'].'.woff2') throw new CatalogueException(422, 'Invalid staged font path.');
            $bytes = $this->staged($directory.'/'.$face['path'], $face['sha256'], $face['bytes']);
            SourceBytes::validateFont($bytes);
            $staged[] = [$face['sha256'], $bytes, 'woff2'];
        }
        if (!preg_match('~^licenses/[a-z][a-z0-9-]*\.txt$~D', $record['licensePath'])) throw new CatalogueException(422, 'Invalid font licence path.');
        $license = $this->staged($directory.'/'.$record['licensePath'], $record['licenseSha256']);
        if (!mb_check_encoding($license, 'UTF-8') || '' === trim($license)) throw new CatalogueException(422, 'Invalid staged font licence.');
        if ($dryRun) return ['family' => $record['family'], 'status' => 'validated', 'faces' => count($record['faces']), 'bytes' => array_sum(array_column($record['faces'], 'bytes'))];
        foreach ($staged as [$hash, $bytes, $extension]) $this->store($hash, $bytes, $extension);
        $this->store($record['licenseSha256'], $license, 'txt');
        $encoded = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        $version = $this->root.'/families/'.$record['id'].'/'.$pin['version'].'-r'.($record['ingestionRevision'] ?? 1).'.json';
        if (is_file($version) && file_get_contents($version) !== $encoded) throw new CatalogueException(409, 'Installed font version is immutable.');
        $current = $this->root.'/families/'.$record['id'].'/current.json';
        $changed = !is_file($current) || file_get_contents($current) !== $encoded;
        $this->atomic($version, $encoded);
        if ($changed) $this->atomic($current, $encoded);

        return ['family' => $record['family'], 'status' => $changed ? 'imported' : 'unchanged', 'faces' => count($record['faces']), 'bytes' => array_sum(array_column($record['faces'], 'bytes'))];
    }

    private function staged(string $path, string $hash, ?int $size = null): string
    {
        if (!is_file($path) || is_link($path) || filesize($path) > 1048576 || (null !== $size && filesize($path) !== $size) || !hash_equals($hash, hash_file('sha256', $path))) throw new CatalogueException(422, 'Missing or corrupt staged font/licence. Previous installed assets are preserved.');

        return file_get_contents($path);
    }

    public function store(string $hash, string $bytes, string $extension): void
    {
        $path = $this->blobPath($hash, $extension);
        if (!hash_equals($hash, hash('sha256', $bytes)) || strlen($bytes) > 1048576) throw new CatalogueException(422, 'Font asset checksum/size mismatch.');
        if ('woff2' === $extension) SourceBytes::validateFont($bytes);
        elseif (!mb_check_encoding($bytes, 'UTF-8') || '' === trim($bytes)) throw new CatalogueException(422, 'Font licence must be nonempty UTF-8 text.');
        if (is_file($path)) {
            if (!hash_equals($hash, hash_file('sha256', $path))) throw new CatalogueException(422, 'Stored font asset is corrupt. Restore it before importing.');
            return;
        }
        $this->atomic($path, $bytes);
    }

    public function bytes(string $hash, string $extension = 'woff2'): string
    {
        $path = $this->blobPath($hash, $extension);
        if (!is_file($path) || is_link($path) || filesize($path) > 1048576 || !hash_equals($hash, hash_file('sha256', $path))) throw new CatalogueException(422, 'Font asset unavailable or corrupt; using the last good preview. Run fonts:sync or reimport its theme bundle.');

        return file_get_contents($path);
    }

    private function blobPath(string $hash, string $extension): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $hash) || !in_array($extension, ['woff2', 'txt'], true)) throw new CatalogueException(422, 'Invalid font asset reference.');

        return $this->root.'/blobs/'.$hash.'.'.$extension;
    }

    private function atomic(string $path, string $bytes): void
    {
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0770, true) && !is_dir(dirname($path))) throw new \RuntimeException('Font storage is unavailable.');
        $temporary = tempnam(dirname($path), '.font-');
        try {
            if (file_put_contents($temporary, $bytes) !== strlen($bytes) || !rename($temporary, $path)) throw new \RuntimeException('Cannot register font asset.');
        } finally { if (is_file($temporary)) unlink($temporary); }
    }
}
