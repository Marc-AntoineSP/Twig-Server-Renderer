<?php

namespace App\Tests\Integration;

use App\Font\FontLibrary;
use App\Font\ThemeFontAssets;
use App\Packaging\ThemePackager;
use App\Rendering\DocumentAssembler;
use App\Rendering\PreviewRenderer;
use App\Tests\DatabaseTestCase;
use App\Theme\ThemeFactory;
use App\Theme\ThemeWriter;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\FontRegistry;
use Styleguide\Contracts\PackageSetReader;
use Styleguide\Contracts\SourceBytes;
use Styleguide\Contracts\ThemeImporter;
use Styleguide\Contracts\ThemeValidator;
use Symfony\Component\Uid\Ulid;

final class FontLibraryTest extends DatabaseTestCase
{
    private function library(): FontLibrary
    {
        $library = new FontLibrary(sys_get_temp_dir().'/font-test-'.bin2hex(random_bytes(8)));
        $directory = dirname(__DIR__, 2).'/fixtures/fonts/inter';
        $pin = json_decode(file_get_contents($directory.'/pin.json'), true, 24, JSON_THROW_ON_ERROR);
        $result = $library->importStaged($directory, $pin);
        self::assertSame('imported', $result['status']);
        self::assertSame('unchanged', $library->importStaged($directory, $pin)['status']);

        return $library;
    }

    public function testRealFontAssetsRoundTripThroughThePortablePackageAndRenderer(): void
    {
        self::bootKernel(); $this->account();
        $library = $this->library();
        self::getContainer()->set(FontLibrary::class, $library);
        $doc = self::getContainer()->get(ThemeFactory::class)->create('font/theme-'.strtolower((string) new Ulid()));
        $doc->fonts[] = $library->definition('inter');
        $doc->typography->bodyFontId = 'inter'; $doc->typography->headingFontId = 'inter'; $doc->typography->codeFontId = 'system-mono';
        $doc->typography->bodyWeight = 472; $doc->typography->bodyStyle = 'italic';
        $doc->typography->headingWeight = 631;
        $writer = self::getContainer()->get(ThemeWriter::class);
        $theme = $writer->create($doc); $release = $writer->publish($theme, 1);
        $package = self::getContainer()->get(ThemePackager::class)->package($release);
        $path = tempnam(sys_get_temp_dir(), 'font-package-');
        try {
            file_put_contents($path, $package['archiveBytes']);
            $set = self::getContainer()->get(PackageSetReader::class)->read($path, 'zip');
            $sources = $set['packages'][$doc->name]['sources'];
            $parsed = self::getContainer()->get(ThemeImporter::class)->parse($sources->{'meta/theme.json'}, 'json');
            self::assertEquals($doc, $parsed);
            self::assertSame(472, $parsed->typography->bodyWeight);
            self::assertSame('italic', $parsed->typography->bodyStyle);
            $prefix = 'assets/styles/catalogue/'.$doc->name.'/';
            foreach ($parsed->fonts[3]->faces as $face) {
                $raw = SourceBytes::raw($prefix.$face->path, $sources->{$prefix.$face->path});
                self::assertSame('wOF2', substr($raw, 0, 4)); self::assertSame($face->bytes, strlen($raw));
                self::assertSame($face->sha256, hash('sha256', $raw));
            }
            self::assertObjectHasProperty($prefix.'licenses/inter.txt', $sources);
            self::assertStringContainsString('url("./fonts/', $sources->{$prefix.'typography.css'});
            $assembler = self::getContainer()->get(DocumentAssembler::class);
            $html = $assembler->assemble('<p>Élève, Œuvre, 12 €</p>', $release->getFiles()['preview.css'], '', 'font-channel', ['bodyFont' => 'theme', 'headingFont' => 'theme'], (array) $doc);
            self::assertStringContainsString('data:font/woff2;base64,', $html);
            self::assertStringContainsString('font-src data:', $html);
            self::assertStringNotContainsString('allow-same-origin', $html);
            self::assertStringNotContainsString('url("./fonts/', $html);
        } finally { unlink($path); }
    }

    public function testCorruptDownloadCannotReplaceRegisteredFamilyAndUnsupportedFacesAreRejected(): void
    {
        $library = $this->library(); $before = $library->definition('inter');
        $pin = json_decode(file_get_contents(dirname(__DIR__, 2).'/fixtures/fonts/inter/pin.json'), true);
        $pin['metadataSha256'] = str_repeat('0', 64);
        try { $library->importStaged(dirname(__DIR__, 2).'/fixtures/fonts/inter', $pin); self::fail('Corrupt metadata accepted.'); }
        catch (CatalogueException $error) { self::assertStringContainsString('pinned manifest', $error->getMessage()); }
        self::assertEquals($before, $library->definition('inter'));
        self::bootKernel(); $doc = self::getContainer()->get(ThemeFactory::class)->create('font/unsupported');
        $doc->fonts[] = $before; $doc->typography->bodyFontId = 'inter'; $doc->typography->bodyWeight = 950;
        $this->expectException(CatalogueException::class);
        self::getContainer()->get(ThemeValidator::class)->validate($doc);
    }

    public function testStaticFontAxesSurviveTheArrayPayloadUsedByQueuedPreviews(): void
    {
        self::bootKernel(); $library = $this->library();
        $directory = dirname(__DIR__, 2).'/fixtures/fonts/poppins';
        $library->importStaged($directory, json_decode(file_get_contents($directory.'/pin.json'), true));
        self::getContainer()->set(FontLibrary::class, $library);
        $doc = self::getContainer()->get(ThemeFactory::class)->create('font/static-preview');
        $doc->fonts[] = $library->definition('poppins'); $doc->typography->bodyFontId = 'poppins';
        $template = '<p>English &amp; Français : Œuvre, élève, 12 €</p>';
        $entry = 'catalogue/font/static/component.html.twig';
        $result = self::getContainer()->get(PreviewRenderer::class)->render(['entry' => $entry, 'templates' => [$entry => $template], 'sources' => ['templates/'.$entry => $template], 'props' => new \stdClass(), 'controllers' => [], 'safelist' => [], 'channel' => 'static-font-test', 'preset' => ['bodyFont' => 'theme'], 'theme' => json_decode(json_encode($doc), true)]);
        self::assertTrue($result['ok'], json_encode($result['error'] ?? []));
        self::assertStringContainsString('font-family:"Poppins"', $result['html']);
        self::assertStringContainsString('data:font/woff2;base64,', $result['html']);
    }

    public function testFontPackageValidationIsReadOnlyAndApplyingRegistersAssetsInEmptyStorage(): void
    {
        self::bootKernel(); $this->account(); $library = $this->library();
        self::getContainer()->set(FontLibrary::class, $library);
        $doc = self::getContainer()->get(ThemeFactory::class)->create('font/reimport-'.strtolower((string) new Ulid()));
        $doc->fonts[] = $library->definition('inter'); $doc->typography->bodyFontId = 'inter';
        $writer = self::getContainer()->get(ThemeWriter::class); $theme = $writer->create($doc);
        $package = self::getContainer()->get(ThemePackager::class)->package($writer->publish($theme, 1));
        $path = tempnam(sys_get_temp_dir(), 'font-reimport-'); file_put_contents($path, $package['archiveBytes']);
        $emptyRoot = sys_get_temp_dir().'/font-empty-'.bin2hex(random_bytes(8)); $empty = new FontLibrary($emptyRoot);
        try {
            self::ensureKernelShutdown(); self::bootKernel(); self::getContainer()->set(FontLibrary::class, $empty);
            $importer = self::getContainer()->get(\App\Packaging\TransferImporter::class);
            $prepared = $importer->prepare($path, 'zip');
            self::assertFalse(is_dir($emptyRoot), 'Validation/dry-run wrote font assets.');
            $result = $importer->apply($prepared, null, $prepared['planHash']); self::assertSame(1, $result['applied']);
            foreach ($doc->fonts[3]->faces as $face) self::assertSame($face->sha256, hash('sha256', $empty->bytes($face->sha256)));
            $again = $importer->prepare($path, 'zip'); self::assertSame('skip-identical', $again['plan']['packages'][0]['action']);
            self::assertSame(1, $importer->apply($again, null, $again['planHash'])['skipped']);
        } finally { unlink($path); }
    }
}
