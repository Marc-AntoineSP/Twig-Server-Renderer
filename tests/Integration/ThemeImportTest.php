<?php

namespace App\Tests\Integration;

use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\CanonicalJson;
use Styleguide\Contracts\ThemeCssGenerator;
use App\Theme\ThemeFactory;
use Styleguide\Contracts\ThemeImporter;
use Styleguide\Contracts\ThemeValidator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Yaml\Yaml;

final class ThemeImportTest extends KernelTestCase
{
    public function testCanonicalJsonYamlAndGeneratedCssRoundTripExactly(): void
    {
        self::bootKernel();
        $theme = self::getContainer()->get(ThemeFactory::class)->create();
        $json = self::getContainer()->get(CanonicalJson::class);
        $importer = self::getContainer()->get(ThemeImporter::class);
        $expected = $json->encode($theme);
        foreach (['json' => $json->encode($theme, true), 'yaml' => Yaml::dump(json_decode($expected, true), 8, 2)] as $format => $input) {
            self::assertSame($expected, $json->encode($importer->parse($input, $format)));
        }
        foreach (self::getContainer()->get(ThemeCssGenerator::class)->files($theme) as $name => $contents) {
            if (str_ends_with($name, '.css')) self::assertSame($expected, $json->encode($importer->parse($contents, 'css')));
        }
        $fragment = self::getContainer()->get(ThemeCssGenerator::class)->files($theme)['theme.fragment.css'];
        $this->expectException(CatalogueException::class);
        $importer->parse(str_replace('--radius-box: 1rem', '--radius-box: 3rem', $fragment), 'css');
    }

    public function testInvalidTokensAndUnsafeImportsAreRejected(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(ThemeFactory::class);
        $validator = self::getContainer()->get(ThemeValidator::class);
        foreach ([
            static function ($t) { $t->colors->primary = '#123456;@import "https://bad"'; },
            static function ($t) { $t->cssName = 'evil"]{color:red}'; },
            static function ($t) { $t->geometry->radiusBoxRem = 999; },
            static function ($t) { $t->typography->bodyFontId = 'unknown'; },
            static function ($t) { $t->fonts[0]->kind = 'local'; $t->fonts[0]->path = '/etc/passwd'; },
        ] as $change) {
            $theme = $factory->create(); $change($theme);
            try { $validator->validate($theme); self::fail('Unsafe theme accepted.'); }
            catch (CatalogueException $e) { self::assertSame(422, $e->getStatusCode()); }
        }
        $importer = self::getContainer()->get(ThemeImporter::class);
        foreach (['x: &x [*x]', 'x: !php/object evil', "name: a/b\nname: c/d"] as $yaml) {
            try { $importer->parse($yaml, 'yaml'); self::fail('Unsafe YAML accepted.'); }
            catch (CatalogueException $e) { self::assertSame(422, $e->getStatusCode()); }
        }
    }
}
