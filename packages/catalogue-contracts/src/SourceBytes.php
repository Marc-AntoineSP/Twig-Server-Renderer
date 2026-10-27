<?php

namespace Styleguide\Contracts;

/** JSON source maps use an explicit encoding only for validated WOFF2 assets. */
final class SourceBytes
{
    public static function encode(string $path, string $bytes): string
    {
        return str_ends_with($path, '.woff2') ? 'base64:'.base64_encode($bytes) : $bytes;
    }

    public static function raw(string $path, string $source): string
    {
        if (!str_ends_with($path, '.woff2')) return $source;
        if (!str_starts_with($source, 'base64:') || false === ($bytes = base64_decode(substr($source, 7), true)) || 'base64:'.base64_encode($bytes) !== $source) {
            throw new CatalogueException(422, 'WOFF2 source maps require canonical base64 encoding.');
        }
        self::validateFont($bytes);

        return $bytes;
    }

    public static function validateFont(string $bytes): void
    {
        if (strlen($bytes) < 48 || strlen($bytes) > 1048576 || substr($bytes, 0, 4) !== 'wOF2') throw new CatalogueException(422, 'Invalid or excessive WOFF2 asset.');
        $header = unpack('Nlength/ntables/nreserved/Nsfnt/Ncompressed', substr($bytes, 8, 16));
        if ($header['length'] !== strlen($bytes) || $header['reserved'] !== 0 || $header['tables'] < 1 || $header['tables'] > 100 || $header['sfnt'] > 16777216 || $header['compressed'] > strlen($bytes) - 48) throw new CatalogueException(422, 'Invalid WOFF2 header or decompression bounds.');
    }
}
