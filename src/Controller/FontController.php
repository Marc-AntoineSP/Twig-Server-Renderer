<?php

namespace App\Controller;

use App\Font\FontLibrary;
use App\Font\ThemeFontAssets;
use App\Rendering\DocumentAssembler;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\FontRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_READER')]
final class FontController extends AbstractController
{
    #[Route('/fonts', name: 'font_index', methods: ['GET'])]
    public function index(FontLibrary $fonts, \App\Discovery\ListingResponse $listing, #[\Symfony\Component\HttpKernel\Attribute\MapQueryString(validationFailedStatusCode: 400)] \App\Font\FontSearchQuery $query = new \App\Font\FontSearchQuery()): Response
    {
        return $listing->render('font_index', 'font/index.html.twig', $fonts->search($query));
    }

    #[Route('/fonts/assets/{hash}.{extension}', name: 'font_asset', methods: ['GET'], requirements: ['hash' => '[a-f0-9]{64}', 'extension' => 'woff2|txt'])]
    public function asset(string $hash, string $extension, FontLibrary $fonts): Response
    {
        return new Response($fonts->bytes($hash, $extension), headers: ['Content-Type' => 'woff2' === $extension ? 'font/woff2' : 'text/plain; charset=UTF-8', 'Cache-Control' => 'private, max-age=31536000, immutable', 'X-Content-Type-Options' => 'nosniff']);
    }

    #[Route('/api/v1/fonts/{family}/specimen', name: 'font_specimen', methods: ['GET'], requirements: ['family' => '[a-z][a-z0-9-]*'])]
    public function specimen(string $family, Request $request, FontLibrary $fonts, DocumentAssembler $assembler): JsonResponse
    {
        $font = $fonts->definition($family);
        $text = $request->query->getString('text', 'English: The quick brown fox, 0123456789. Français : Élève, cœur, Œuvre, ça coûte 12 € — « Lisibilité ! »');
        if (mb_strlen($text) > 500) throw new CatalogueException(422, 'Specimen text is limited to 500 characters.');
        $weight = $request->query->getInt('weight', 400); $style = $request->query->getString('style', 'normal');
        if ($weight < 100 || $weight > 1000 || !in_array($style, ['normal', 'italic'], true) || ('local' === $font->kind && !array_filter($font->faces, static fn ($face) => $face->style === $style && $face->weightMin <= $weight && $face->weightMax >= $weight))) throw new CatalogueException(422, 'This family has no real face for the selected weight/style.');
        if ('local' === $font->kind) $font->faces = array_values(array_filter($font->faces, static fn ($face) => $face->style === $style && $face->weightMin <= $weight && $face->weightMax >= $weight));
        $theme = (object) ['cssName' => 'font-specimen', 'colorScheme' => 'light', 'fonts' => [$font], 'typography' => (object) ['bodyFontId' => $font->id, 'headingFontId' => $font->id]];
        $css = FontRegistry::faceCss($theme).'[data-theme]{--sg-font-body:'.FontRegistry::cssFamily($font).';}body{color:#17212b;background:#fff;font-size:24px!important;line-height:1.6;font-weight:'.$weight.';font-style:'.$style.';font-synthesis:none;}';
        $html = '<p>'.htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>';

        return new JsonResponse(['html' => $assembler->assemble($html, $css, '', bin2hex(random_bytes(16)), ['bodyFont' => 'theme'], (array) $theme), 'family' => $font->family], headers: ['Cache-Control' => 'private, no-store']);
    }
}
