<?php

namespace App\Appearance;

/** Complete, versioned starting palettes; mode changes never synthesize an inverse. */
final class DefaultThemes
{
    public static function document(string $mode, string $name): \stdClass
    {
        $light = ['#FFFFFF','#F2F2F2','#E5E6E6','#1F2937','#422AD5','#FFFFFF','#CF267B','#FFFFFF','#007F73','#FFFFFF','#2A323C','#FFFFFF','#0076A8','#FFFFFF','#007848','#FFFFFF','#F3C100','#1F2937','#BD2539','#FFFFFF'];
        $dark = ['#1D232A','#191E24','#15191E','#ECF9FF','#A6A3FF','#171538','#FF8AC4','#371126','#55EACD','#052B24','#2A323C','#ECF9FF','#63CEFF','#052A3A','#5AECA2','#072C19','#F8D36A','#322500','#FF9DAB','#390911'];
        $colors = array_combine(\Styleguide\Contracts\ThemeValidator::COLORS, 'dark' === $mode ? $dark : $light);
        return json_decode(json_encode([
            'schemaVersion' => 1, 'name' => $name, 'version' => '1.0.0', 'cssName' => str_replace('/', '-', $name), 'colorScheme' => $mode, 'default' => false, 'prefersDark' => false,
            'colors' => $colors,
            'geometry' => ['radiusSelectorRem' => 1, 'radiusFieldRem' => .5, 'radiusBoxRem' => 1, 'sizeSelectorRem' => .25, 'sizeFieldRem' => .25, 'borderPx' => 1, 'depth' => 1, 'noise' => 0],
            'typography' => ['bodyFontId' => 'system-sans', 'headingFontId' => 'system-sans', 'codeFontId' => 'system-mono', 'textClass' => 'text-base', 'rootFontSizePx' => 16, 'lineHeight' => 1.5, 'bodyWeight' => 400, 'headingWeight' => 700, 'codeWeight' => 400, 'bodyStyle' => 'normal', 'headingStyle' => 'normal', 'codeStyle' => 'normal'],
            'fonts' => array_map(static fn ($id) => ['id' => $id, 'kind' => 'system', 'family' => $id], ['system-sans', 'system-serif', 'system-mono']),
        ], JSON_THROW_ON_ERROR), false, 24, JSON_THROW_ON_ERROR);
    }
}
