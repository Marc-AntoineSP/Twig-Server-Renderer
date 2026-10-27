<?php

namespace Styleguide\Contracts;

final class FontRegistry
{
    public const SYSTEM = [
        'system-sans' => 'ui-sans-serif, system-ui, sans-serif',
        'system-serif' => 'ui-serif, Georgia, serif',
        'system-mono' => 'ui-monospace, SFMono-Regular, Menlo, monospace',
    ];

    public function family(string $id): string
    {
        return self::SYSTEM[$id] ?? throw new \InvalidArgumentException('Unknown system font.');
    }
}
