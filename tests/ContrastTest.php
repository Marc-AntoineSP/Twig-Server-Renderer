<?php

namespace App\Tests;

use App\Theme\Contrast;
use PHPUnit\Framework\TestCase;

final class ContrastTest extends TestCase
{
    public function testSavedAndDraftUseTheSameOpaquePairsAndThresholds(): void
    {
        $engine = new Contrast();
        $colors = new \stdClass();
        foreach (Contrast::PAIRS as $background => $foreground) {$colors->$background = '#FFFFFF'; $colors->$foreground = '#000000';}
        $colors->primary = '#777777'; $colors->{'primary-content'} = '#FFFFFF';
        $colors->secondary = '#767676'; $colors->{'secondary-content'} = '#FFFFFF';
        $result = $engine->evaluate($colors);
        self::assertSame(21.0, $result['base-100']['ratio']);
        self::assertSame(4.48, $result['primary']['ratio']);
        self::assertFalse($result['primary']['ordinary']); self::assertTrue($result['primary']['large']);
        self::assertTrue($result['secondary']['ordinary']);
        $boundary = $engine->evaluate((object) ['primary' => '#1B8577', 'primary-content' => '#FFFFFF']);
        self::assertSame(4.5, $boundary['primary']['ratio']);
        self::assertFalse($boundary['primary']['ordinary'], 'A displayed rounded 4.50 must not pass when the full ratio is below 4.5.');
        self::assertSame(array_map(static fn ($row) => $row['ratio'], $result), $engine->ratios((object) ['colors' => $colors]));
    }

    public function testIncompleteAlphaAndContextDependentColorsNeverInventRatios(): void
    {
        $engine = new Contrast();
        foreach (['#FFF', '#12345680', 'rgba(0,0,0,.5)', 'transparent', 'var(--color-primary)', 'oklch(60% .2 20)', '</style><script>alert(1)</script>', null, ['#000000']] as $value) {
            $results = $engine->evaluate((object) ['primary' => $value, 'primary-content' => '#FFFFFF']);
            self::assertNull($results['primary']['ratio']); self::assertNull($results['primary']['ordinary']);
            self::assertSame(['primary'], $results['primary']['invalid']);
        }
        $same = $engine->evaluate((object) ['primary' => '#ffffff', 'primary-content' => '#FFFFFF']);
        self::assertSame(1.0, $same['primary']['ratio']); self::assertFalse($same['primary']['large']);
    }
}
