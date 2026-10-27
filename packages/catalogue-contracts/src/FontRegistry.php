<?php

namespace Styleguide\Contracts;

final class FontRegistry
{
    public const SYSTEM = [
        'system-sans' => 'ui-sans-serif, system-ui, sans-serif',
        'system-serif' => 'ui-serif, Georgia, serif',
        'system-mono' => 'ui-monospace, SFMono-Regular, Menlo, monospace',
    ];

    public function family(string $id): string
    {
        return self::SYSTEM[$id] ?? throw new \InvalidArgumentException('Unknown system font.');
    }

    public static function cssFamily(object $font): string
    {
        return 'system' === $font->kind ? self::SYSTEM[$font->family] : '"'.$font->family.'", '.self::SYSTEM[($font->category ?? '') === 'mono' ? 'system-mono' : (in_array($font->category ?? '', ['serif', 'display'], true) ? 'system-serif' : 'system-sans')];
    }

    public static function selected(object $theme): array
    {
        $ids = [$theme->typography->bodyFontId, $theme->typography->headingFontId, $theme->typography->codeFontId ?? 'system-mono'];

        return array_values(array_filter($theme->fonts, static fn ($font) => in_array($font->id, $ids, true)));
    }

    public static function faceCss(object $theme): string
    {
        $css = '';
        foreach (self::selected($theme) as $font) {
            if ('local' !== $font->kind) continue;
            foreach ($font->faces as $face) {
                $weight = $face->weightMin.($face->weightMax !== $face->weightMin ? ' '.$face->weightMax : '');
                $css .= '@font-face{font-family:"'.$font->family.'";font-style:'.$face->style.';font-weight:'.$weight.';font-display:swap;src:url("./'.$face->path.'") format("woff2");unicode-range:'.$face->unicodeRange.";}\n";
            }
        }

        return $css;
    }
}
