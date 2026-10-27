<?php

namespace App\Theme;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ThemeFactory
{
    public function __construct(#[Autowire('%kernel.project_dir%/agent-kit/examples/packages/ember/meta/theme.json')] private string $example)
    {
    }

    public function create(string $name = 'studio/new-theme'): \stdClass
    {
        $document = json_decode(file_get_contents($this->example), false, 24, JSON_THROW_ON_ERROR);
        $document->name = $name;
        $document->cssName = str_replace('/', '-', $name);

        return $document;
    }
}
