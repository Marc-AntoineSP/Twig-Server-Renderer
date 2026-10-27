<?php

namespace Styleguide\Contracts;

use Styleguide\Contracts\FontRegistry;
use Styleguide\Contracts\CanonicalJson;

final readonly class ThemeCssGenerator
{
    public const GEOMETRY = ['radiusSelectorRem' => ['radius-selector', 'rem'], 'radiusFieldRem' => ['radius-field', 'rem'], 'radiusBoxRem' => ['radius-box', 'rem'], 'sizeSelectorRem' => ['size-selector', 'rem'], 'sizeFieldRem' => ['size-field', 'rem'], 'borderPx' => ['border', 'px'], 'depth' => ['depth', ''], 'noise' => ['noise', '']];

    public function __construct(private ThemeValidator $validator, private CanonicalJson $json)
    {
    }

    public function files(\stdClass $theme): array
    {
        $this->validator->validate($theme);
        $marker = '/* styleguide-theme-v1:'.base64_encode($this->json->encode($theme))." */\n";
        $fragment = '@plugin "daisyui/theme" {'."\n".'  name: "'.$theme->cssName.'";'."\n".'  default: '.($theme->default ? 'true' : 'false').";\n".'  prefersdark: '.($theme->prefersDark ? 'true' : 'false').";\n".'  color-scheme: '.$theme->colorScheme.";\n";
        foreach (ThemeValidator::COLORS as $color) { $fragment .= '  --color-'.$color.': '.$theme->colors->$color.";\n"; }
        foreach (self::GEOMETRY as $key => [$variable, $unit]) { $fragment .= '  --'.$variable.': '.$this->number($theme->geometry->$key).$unit.";\n"; }
        $fragment .= "}\n";
        $fontMap = [];
        foreach ($theme->fonts as $font) { $fontMap[$font->id] = FontRegistry::SYSTEM[$font->family]; }
        $sizes = ['text-xs' => '.75rem', 'text-sm' => '.875rem', 'text-base' => '1rem', 'text-lg' => '1.125rem', 'text-xl' => '1.25rem'];
        $name = $theme->cssName;
        $typography = "@theme inline {\n  --font-sans: var(--sg-font-body);\n  --font-heading: var(--sg-font-heading);\n}\n\n";
        $typography .= '[data-theme="'.$name.'"] {'."\n  --sg-font-body: ".$fontMap[$theme->typography->bodyFontId].";\n  --sg-font-heading: ".$fontMap[$theme->typography->headingFontId].";\n  --sg-body-size: ".$sizes[$theme->typography->textClass].";\n  --sg-line-height: ".$this->number($theme->typography->lineHeight).";\n  font-family: var(--sg-font-body);\n}\n\n";
        $typography .= 'html[data-theme="'.$name.'"] { font-size: '.$theme->typography->rootFontSizePx."px; }\n";
        $typography .= ':where([data-theme="'.$name.'"]) body,'."\n".':where([data-theme="'.$name.'"].sg-surface) {'."\n  font-size: var(--sg-body-size);\n  line-height: var(--sg-line-height);\n}\n";
        $typography .= ':where([data-theme="'.$name.'"]) :where(h1, h2, h3, h4, h5, h6) {'."\n  font-family: var(--sg-font-heading);\n}\n";
        $app = '@import "tailwindcss" source(none);'."\n".'@import "./theme.fragment.css";'."\n".'@import "./typography.css";'."\n".'@source "../../../../../templates";'."\n".'@source "../../../../controllers";'."\n".'@source inline("text-xs text-sm text-base text-lg text-xl font-sans font-heading");'."\n".'@plugin "daisyui" { themes: false; }'."\n";

        return ['theme.fragment.css' => $marker.$fragment, 'typography.css' => $marker.$typography, 'app.css' => $marker.$app, 'theme.json' => $this->json->encode($theme, true)."\n"];
    }

    private function number(int|float $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
