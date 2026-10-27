<?php

namespace App\Theme;

use App\Entity\Theme;
use App\Rendering\DocumentAssembler;
use Styleguide\Contracts\CanonicalJson;
use Styleguide\Contracts\ThemeCssGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

/** Repository-owned Twig examples, never user templates or a second token generator. */
final readonly class ThemeComponentShowcase
{
    public const CATEGORIES = ['actions' => 'Actions', 'forms' => 'Forms', 'navigation' => 'Navigation', 'data' => 'Data display', 'feedback' => 'Feedback', 'layout' => 'Layout', 'mockups' => 'Mockups'];

    public function __construct(
        private Environment $twig,
        private ThemeCssGenerator $css,
        private CanonicalJson $json,
        private DocumentAssembler $documents,
        #[Autowire('%kernel.project_dir%')] private string $project,
    ) {}

    public function document(Theme $theme): string
    {
        $tokens = $theme->getDocument();
        $scope = hash('sha256', $this->json->encode($tokens));
        $html = $this->twig->render('theme/showcase/_catalogue.html.twig', [
            'prefix' => 'sg-'.strtolower($theme->getId()), 'categories' => self::CATEGORIES,
            'themeName' => $tokens->provenance->title ?? $theme->getName(), 'mode' => $tokens->colorScheme,
        ]);
        $css = $this->asset('assets/styles/showcase.css')."\n".$this->css->applicationCss($tokens, $scope);
        return $this->documents->assemble($html, $css, $this->asset('assets/showcase/showcase.js'), bin2hex(random_bytes(16)), [
            'rootSize' => $tokens->typography->rootFontSizePx, 'textClass' => $tokens->typography->textClass,
            'bodyFont' => 'theme', 'headingFont' => 'theme', 'codeFont' => 'theme', 'colorScheme' => 'theme',
        ], (array) $tokens, themeScope: $scope);
    }

    private function asset(string $relative): string
    {
        $path = $this->project.'/'.$relative;
        if (!is_file($path) || filesize($path) > 1048576) throw new \RuntimeException('Showcase assets are unavailable. Run make assets before serving the application.');
        return file_get_contents($path);
    }
}
