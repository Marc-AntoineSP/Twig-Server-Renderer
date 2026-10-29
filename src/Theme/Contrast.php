<?php

namespace App\Theme;

final class Contrast
{
    public const PAIRS = ['base-100' => 'base-content', 'base-200' => 'base-content', 'base-300' => 'base-content', 'primary' => 'primary-content', 'secondary' => 'secondary-content', 'accent' => 'accent-content', 'neutral' => 'neutral-content', 'info' => 'info-content', 'success' => 'success-content', 'warning' => 'warning-content', 'error' => 'error-content'];

    public function ratios(\stdClass $theme): array
    {
        return array_map(static fn ($result) => $result['ratio'], $this->evaluate($theme->colors));
    }

    /** The theme contract supports opaque #RRGGBB only; never guess a backdrop. */
    public function evaluate(object $colors): array
    {
        $results = [];
        foreach (self::PAIRS as $background => $foreground) {
            $invalid = array_values(array_filter([$background, $foreground], static fn ($key) => !is_string($colors->$key ?? null) || !preg_match('/^#[0-9a-f]{6}$/iD', $colors->$key)));
            $ratio = null;
            if (!$invalid) {
                $a = $this->luminance($colors->$background);
                $b = $this->luminance($colors->$foreground);
                $ratio = (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
            }
            // Compare full precision before rounding for display (WCAG 2.2).
            $results[$background] = ['foreground' => $foreground, 'ratio' => null === $ratio ? null : round($ratio, 2), 'ordinary' => null === $ratio ? null : $ratio >= 4.5, 'large' => null === $ratio ? null : $ratio >= 3, 'invalid' => $invalid];
        }
        return $results;
    }

    private function luminance(string $hex): float
    {
        $rgb = array_map(static function (string $channel): float {
            $value = hexdec($channel) / 255;
            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($hex, 1), 2));
        return $rgb[0] * 0.2126 + $rgb[1] * 0.7152 + $rgb[2] * 0.0722;
    }
}
