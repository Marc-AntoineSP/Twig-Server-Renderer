<?php

namespace App\Theme;

use App\Entity\Theme;
use App\Font\ThemeFontAssets;
use Styleguide\Contracts\CanonicalJson;
use Styleguide\Contracts\ThemeCssGenerator;

/** Repository-owned Twig examples, never user templates or a second token generator. */
final readonly class ThemeComponentShowcase
{
    public const CATEGORIES = ['actions' => 'Actions', 'forms' => 'Forms', 'navigation' => 'Navigation', 'data' => 'Data display', 'feedback' => 'Feedback', 'layout' => 'Layout', 'mockups' => 'Mockups'];

    public function __construct(private ThemeCssGenerator $css, private CanonicalJson $json, private ThemeFontAssets $fonts) {}

    public function presentation(Theme $theme): array
    {
        $tokens = $theme->getDocument();
        $scope = hash('sha256', $this->json->encode($tokens));
        $css = $this->fonts->embed($this->css->showcaseCss($tokens, $scope), $tokens);
        return [
            'scope' => $scope, 'css' => $css, 'hash' => hash('sha256', $css),
            'integrity' => 'sha256-'.base64_encode(hash('sha256', $css, true)),
            'prefix' => 'sg-'.strtolower($theme->getId()), 'categories' => self::CATEGORIES,
            'mode' => $tokens->colorScheme,
        ];
    }
}
