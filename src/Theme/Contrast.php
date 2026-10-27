<?php

namespace App\Theme;

final class Contrast
{
    public function ratios(\stdClass $theme): array
    {
        $pairs = ['base-100' => 'base-content', 'base-200' => 'base-content', 'base-300' => 'base-content', 'primary' => 'primary-content', 'secondary' => 'secondary-content', 'accent' => 'accent-content', 'neutral' => 'neutral-content', 'info' => 'info-content', 'success' => 'success-content', 'warning' => 'warning-content', 'error' => 'error-content'];
        $ratios = [];
        foreach ($pairs as $background => $foreground) {
            $a = $this->luminance($theme->colors->$background);
            $b = $this->luminance($theme->colors->$foreground);
            $ratios[$background] = round((max($a, $b) + 0.05) / (min($a, $b) + 0.05), 2);
        }

        return $ratios;
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
