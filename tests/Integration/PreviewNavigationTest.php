<?php

namespace App\Tests\Integration;

use App\Rendering\DocumentAssembler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class PreviewNavigationTest extends KernelTestCase
{
    public function testNativeFragmentBaseIsLocalWithoutRewritingUserMarkupOrAddingPermissions(): void
    {
        self::bootKernel();
        $markup = '<nav><a href="#overview">Overview</a><a href="#missing">Settings</a></nav><h2 id="overview">Élève &amp; Œuvre</h2><svg><use href="#icon"/></svg>';
        $html = self::getContainer()->get(DocumentAssembler::class)->assemble($markup, '', '', 'fragment-test');
        $document = new Crawler($html);
        self::assertSame('about:srcdoc', $document->filter('head base')->attr('href'));
        self::assertSame('#overview', $document->filter('nav a')->first()->attr('href'));
        self::assertSame('#missing', $document->filter('nav a')->last()->attr('href'));
        self::assertStringContainsString($markup, $html, 'User HTML, SVG references and JS-visible href attributes remain unchanged.');
        $policy = $document->filter('meta[http-equiv="Content-Security-Policy"]')->attr('content');
        foreach (['base-uri about:', "default-src 'none'", "connect-src 'none'", "frame-src 'none'", "form-action 'none'", 'font-src data:'] as $directive) self::assertStringContainsString($directive, $policy);
        self::assertStringNotContainsString('allow-same-origin', $html);
        self::assertStringNotContainsString('base-uri https:', $policy);
    }
}
