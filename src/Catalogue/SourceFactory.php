<?php

namespace App\Catalogue;

final class SourceFactory
{
    public function initial(CreateItemInput $input): array
    {
        $entry = 'templates/catalogue/'.$input->name.'/component.html.twig';
        $props = (object) ['type' => 'object', 'properties' => (object) ['message' => (object) ['type' => 'string', 'maxLength' => 500]], 'required' => ['message'], 'additionalProperties' => false];
        $manifest = (object) [
            'schemaVersion' => 1, 'name' => $input->name, 'version' => '0.1.0',
            'kind' => $input->kind, 'title' => $input->title, 'description' => $input->description,
            'entry' => $entry, 'propsSchema' => 'meta/props.schema.json',
            'defaults' => (object) ['message' => 'Your component is ready.'],
            'dependencies' => [], 'controllers' => [], 'safelist' => [],
            'compatibility' => (object) ['php' => '^8.5', 'twig' => '^3.0', 'tailwind' => '^4.0', 'daisyui' => '^5.0', 'stimulus' => '^3.0'],
            'files' => [], 'license' => 'UNLICENSED',
        ];
        $sources = (object) [
            $entry => '<section class="rounded-box bg-base-100 p-6"><h2 class="text-xl font-bold">{{ message }}</h2></section>'."\n",
            'meta/props.schema.json' => json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n",
            'meta/examples.json' => json_encode([(object) ['name' => 'Default', 'props' => $manifest->defaults]], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n",
            'meta/LICENSE' => "UNLICENSED\nNo distribution rights are granted by this generated declaration.\n",
            'meta/README.md' => '# '.$input->name."\n\nInclude `".substr($entry, 10)."` with declared props.\nInstall exact dependencies with the local Symfony installer bundle. Compile Tailwind/daisyUI sources.\nReview browser JavaScript before installation into your application.\n",
        ];

        if (in_array($input->kind, ['block', 'layout'], true)) {
            $manifest->defaults = new \stdClass();
            $sources->{$entry} = \App\Composition\CompositionCompiler::EMPTY_TEMPLATE;
            $sources->{'meta/props.schema.json'} = '{"type":"object","properties":{},"additionalProperties":false}';
            $sources->{'meta/examples.json'} = '[{"name":"Default","props":{}}]';
            $sources->{'meta/composition.json'} = '{"schemaVersion":1,"root":{"type":"container","tag":"section","classes":["grid","gap-4"],"children":[]}}';
        }

        return [$manifest, $sources];
    }
}
