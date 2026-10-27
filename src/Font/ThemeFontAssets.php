<?php

namespace App\Font;

use Styleguide\Contracts\FontRegistry;
use Styleguide\Contracts\SourceBytes;
use Styleguide\Contracts\CatalogueException;

final readonly class ThemeFontAssets
{
    public function __construct(private FontLibrary $library) {}

    public function files(object $theme): array
    {
        $files = [];
        foreach (FontRegistry::selected($theme) as $font) {
            if ('local' !== $font->kind) continue;
            foreach ($font->faces as $face) $files[$face->path] = SourceBytes::encode($face->path, $this->library->bytes($face->sha256));
            $files[$font->licensePath] = $this->library->bytes($font->licenseSha256, 'txt');
        }

        return $files;
    }

    public function register(object $theme, object $sources): void
    {
        $prefix = 'assets/styles/catalogue/'.$theme->name.'/';
        foreach (FontRegistry::selected($theme) as $font) {
            if ('local' !== $font->kind) continue;
            foreach ($font->faces as $face) {
                $path = $prefix.$face->path;
                $this->library->store($face->sha256, SourceBytes::raw($path, $sources->$path), 'woff2');
            }
            $path = $prefix.$font->licensePath;
            $this->library->store($font->licenseSha256, $sources->$path, 'txt');
        }
    }

    public function fromSources(object $theme, object $sources): array
    {
        $files = []; $prefix = 'assets/styles/catalogue/'.$theme->name.'/';
        foreach (FontRegistry::selected($theme) as $font) {
            if ('local' !== $font->kind) continue;
            foreach ($font->faces as $face) $files[$face->path] = $sources->{$prefix.$face->path};
            $files[$font->licensePath] = $sources->{$prefix.$font->licensePath};
        }
        return $files;
    }

    public function embed(string $css, object $theme, array $provided = []): string
    {
        $faces = [];
        foreach (FontRegistry::selected($theme) as $font) if ('local' === $font->kind) foreach ($font->faces as $face) $faces[$face->sha256] = $face;

        return preg_replace_callback('~url\(([\'"]?)(?:\./)?fonts/([a-f0-9]{64})\.woff2\1\)~', function ($match) use ($faces, $provided) {
            if (!isset($faces[$match[2]])) throw new CatalogueException(422, 'Compiled CSS references an undeclared font.');
            $path = 'fonts/'.$match[2].'.woff2';
            $bytes = isset($provided[$path]) ? SourceBytes::raw($path, $provided[$path]) : $this->library->bytes($match[2]);
            return 'url("data:font/woff2;base64,'.base64_encode($bytes).'")';
        }, $css);
    }
}
