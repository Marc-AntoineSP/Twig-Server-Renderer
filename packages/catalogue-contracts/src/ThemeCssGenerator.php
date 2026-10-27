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
        foreach ($theme->fonts as $font) { $fontMap[$font->id] = FontRegistry::cssFamily($font); }
        $sizes = ['text-xs' => '.75rem', 'text-sm' => '.875rem', 'text-base' => '1rem', 'text-lg' => '1.125rem', 'text-xl' => '1.25rem'];
        $name = $theme->cssName;
        $extended = isset($theme->typography->codeFontId) || (bool) array_filter($theme->fonts, static fn ($font) => 'local' === $font->kind);
        $typography = "@theme inline {\n  --font-sans: var(--sg-font-body);\n  --font-heading: var(--sg-font-heading);\n".($extended ? "  --font-mono: var(--sg-font-code, ui-monospace, monospace);\n" : '')."}\n\n";
        $typography .= '[data-theme="'.$name.'"] {'."\n  --sg-font-body: ".$fontMap[$theme->typography->bodyFontId].";\n  --sg-font-heading: ".$fontMap[$theme->typography->headingFontId].";\n  --sg-body-size: ".$sizes[$theme->typography->textClass].";\n  --sg-line-height: ".$this->number($theme->typography->lineHeight).";\n  font-family: var(--sg-font-body);\n}\n\n";
        $typography .= 'html[data-theme="'.$name.'"] { font-size: '.$theme->typography->rootFontSizePx."px; }\n";
        $typography .= ':where([data-theme="'.$name.'"]) body,'."\n".':where([data-theme="'.$name.'"].sg-surface) {'."\n  font-size: var(--sg-body-size);\n  line-height: var(--sg-line-height);\n}\n";
        $typography .= ':where([data-theme="'.$name.'"]) :where(h1, h2, h3, h4, h5, h6) {'."\n  font-family: var(--sg-font-heading);\n}\n";
        if (isset($theme->typography->codeFontId)) $typography .= '[data-theme="'.$name.'"] { --sg-font-code: '.$fontMap[$theme->typography->codeFontId]."; }\n";
        foreach ($extended ? ['body' => 'body', 'heading' => ':where(h1,h2,h3,h4,h5,h6)', 'code' => ':where(code,pre,kbd,samp)'] : [] as $role => $selector) {
            $declarations = 'font-synthesis:none;';
            if ('code' === $role) $declarations .= 'font-family:var(--sg-font-code,ui-monospace,monospace);';
            if (isset($theme->typography->{$role.'Weight'})) $declarations .= 'font-weight:'.$theme->typography->{$role.'Weight'}.';';
            if (isset($theme->typography->{$role.'Style'})) $declarations .= 'font-style:'.$theme->typography->{$role.'Style'}.';';
            $typography .= ':where([data-theme="'.$name.'"]) '.$selector.'{'.$declarations."}\n";
        }
        $typography .= FontRegistry::faceCss($theme);
        if ($theme->geometry->highContrastBorders ?? false) {
            $typography .= '[data-theme="'.$name.'"] :is(.input,.select,.textarea){border-color:var(--color-base-content)}'."\n";
            $typography .= '[data-theme="'.$name.'"] :is(.input-error,.select-error,.textarea-error,[aria-invalid="true"]){border-color:var(--color-error)}'."\n";
        }
        $app = '@import "tailwindcss" source(none);'."\n".'@import "./theme.fragment.css";'."\n".'@import "./typography.css";'."\n".'@source "../../../../../templates";'."\n".'@source "../../../../controllers";'."\n".'@source inline("text-xs text-sm text-base text-lg text-xl font-sans font-heading");'."\n".'@plugin "daisyui" { themes: false; }'."\n";

        return ['theme.fragment.css' => $marker.$fragment, 'typography.css' => $marker.$typography, 'app.css' => $marker.$app, 'theme.json' => $this->json->encode($theme, true)."\n"];
    }

    /** Application tokens need no utility rebuild. The only accepted scope is a snapshot hash. */
    public function applicationCss(\stdClass $theme, string $snapshot): string
    {
        $this->validator->validate($theme);
        if (!preg_match('/^[a-f0-9]{64}$/D', $snapshot)) throw new \InvalidArgumentException('Invalid application snapshot scope.');
        $root = 'html[data-appearance-'.$theme->colorScheme.'="'.$snapshot.'"][data-color-mode="'.$theme->colorScheme.'"]';
        $css = $root.'{color-scheme:'.$theme->colorScheme.';font-size:'.$theme->typography->rootFontSizePx.'px;';
        foreach (ThemeValidator::COLORS as $color) $css .= '--color-'.$color.':'.$theme->colors->$color.';';
        foreach (self::GEOMETRY as $key => [$variable, $unit]) $css .= '--'.$variable.':'.$this->number($theme->geometry->$key).$unit.';';
        $map = []; $definitions = []; $weights = [];
        foreach ($theme->fonts as $font) { $map[$font->id] = FontRegistry::cssFamily($font); $definitions[$font->id] = $font; }
        foreach (['body', 'heading', 'code'] as $role) {
            $font = $definitions[$theme->typography->{$role.'FontId'} ?? 'system-mono'] ?? null;
            $weights[$role] = $theme->typography->{$role.'Weight'} ?? ('heading' === $role && ($font->kind ?? 'system') === 'system' ? 700 : 400);
        }
        foreach (['body', 'heading', 'code'] as $role) $css .= '--sg-font-'.$role.':'.($map[$theme->typography->{$role.'FontId'} ?? 'system-mono'] ?? FontRegistry::SYSTEM['system-mono']).';';
        $sizes = ['text-xs' => '.75rem', 'text-sm' => '.875rem', 'text-base' => '1rem', 'text-lg' => '1.125rem', 'text-xl' => '1.25rem'];
        $css .= '--font-sans:var(--sg-font-body);--font-heading:var(--sg-font-heading);--font-mono:var(--sg-font-code);font-family:var(--sg-font-body);}'."\n";
        foreach (['body' => 'body', 'heading' => ':is(h1,h2,h3,h4,h5,h6,.font-heading)', 'code' => ':is(code,pre,kbd,samp,.font-mono)'] as $role => $selector) {
            $css .= $root.' '.$selector.'{font-family:var(--sg-font-'.$role.');font-synthesis:none;font-weight:'.$weights[$role].';font-style:'.($theme->typography->{$role.'Style'} ?? 'normal').';';
            if ('body' === $role) $css .= 'font-size:'.$sizes[$theme->typography->textClass].';line-height:'.$this->number($theme->typography->lineHeight).';';
            $css .= "}\n";
        }
        if ($theme->geometry->highContrastBorders ?? false) $css .= $root.' :is(.input,.select,.textarea){border-color:var(--color-base-content)}'.$root.' :is(.input-error,.select-error,.textarea-error,[aria-invalid="true"]){border-color:var(--color-error)}'."\n";
        $selected = json_decode(json_encode($theme, JSON_THROW_ON_ERROR), false, 24, JSON_THROW_ON_ERROR);
        foreach ($selected->fonts as $font) {
            if ('local' !== $font->kind) continue;
            $font->faces = array_values(array_filter($font->faces, static function ($face) use ($font, $theme, $weights): bool {
                foreach (['body', 'heading', 'code'] as $role) {
                    if (($theme->typography->{$role.'FontId'} ?? null) !== $font->id) continue;
                    $weight = $weights[$role];
                    if ($face->style === ($theme->typography->{$role.'Style'} ?? 'normal') && $weight >= $face->weightMin && $weight <= $face->weightMax) return true;
                }
                return false;
            }));
        }
        return $css.FontRegistry::faceCss($selected);
    }

    private function number(int|float $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
