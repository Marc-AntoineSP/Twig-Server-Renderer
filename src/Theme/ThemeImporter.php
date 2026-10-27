<?php

namespace App\Theme;

use App\Catalogue\CatalogueException;
use Symfony\Component\Yaml\Yaml;

final readonly class ThemeImporter
{
    public function __construct(private ThemeValidator $validator, private ThemeCssGenerator $css)
    {
    }

    public function parse(string $content, string $format): \stdClass
    {
        if (strlen($content) > 262144) {
            throw new CatalogueException(413, 'Theme import exceeds 256 KiB.');
        }
        try {
            $document = match ($format) {
                'json' => json_decode($content, false, 24, JSON_THROW_ON_ERROR),
                'yaml' => $this->yaml($content),
                'css' => $this->css($content),
                default => throw new CatalogueException(422, 'Choose JSON, YAML or this application’s generated CSS.'),
            };
        } catch (\JsonException|\Symfony\Component\Yaml\Exception\ParseException) {
            throw new CatalogueException(422, 'Invalid theme document.');
        }
        if (!$document instanceof \stdClass) {
            throw new CatalogueException(422, 'A theme must be an object.');
        }
        $this->validator->validate($document);

        return $document;
    }

    private function yaml(string $content): mixed
    {
        // V1 has no use for aliases, anchors, custom tags or YAML objects.
        if (preg_match('/(^|[\s\[\]{},])(?:[&*][A-Za-z0-9_-]+|!!|!php)/m', $content)) {
            throw new CatalogueException(422, 'YAML anchors, aliases and custom types are not supported.');
        }
        $value = Yaml::parse($content, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);

        return json_decode(json_encode($value, JSON_THROW_ON_ERROR), false, 24, JSON_THROW_ON_ERROR);
    }

    private function css(string $content): \stdClass
    {
        if (1 !== preg_match_all('~/\* styleguide-theme-v1:([A-Za-z0-9+/=]+) \*/~', $content, $matches)) {
            throw new CatalogueException(422, 'CSS has no supported format marker. Import theme.json for arbitrary CSS.');
        }
        $match = [$matches[0][0], $matches[1][0]];
        $decoded = base64_decode($match[1], true);
        if (false === $decoded) { throw new CatalogueException(422, 'Invalid CSS theme metadata.'); }
        $document = json_decode($decoded, false, 24, JSON_THROW_ON_ERROR);
        if (!$document instanceof \stdClass) { throw new CatalogueException(422, 'Invalid CSS theme metadata.'); }
        $this->validator->validate($document);
        $normalized = $this->normalizeCss($content);
        foreach ($this->css->files($document) as $name => $candidate) {
            if (str_ends_with($name, '.css') && $this->normalizeCss($candidate) === $normalized) { return $document; }
        }
        throw new CatalogueException(422, 'CSS declarations differ from their embedded theme data. Reconcile edited CSS with theme.json; no changes were imported.', [], 'css_token_conflict');
    }

    private function normalizeCss(string $css): string
    {
        return preg_replace_callback('~"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|/\*.*?\*/|\s+~s', static fn (array $part): string => in_array($part[0][0], ['"', "'"], true) ? $part[0] : '', $css);
    }
}
