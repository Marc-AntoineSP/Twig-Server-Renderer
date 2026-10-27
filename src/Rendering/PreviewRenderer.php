<?php

namespace App\Rendering;

use Styleguide\Contracts\CatalogueException;

final readonly class PreviewRenderer
{
    public function __construct(private WorkerClient $workers, private DocumentAssembler $documents)
    {
    }

    public function render(array $payload): array
    {
        if (isset($payload['theme'])) {
            $theme = json_decode(json_encode($payload['theme'], JSON_THROW_ON_ERROR), false, 24, JSON_THROW_ON_ERROR);
            // JSONB payloads are read as arrays; retain the canonical empty axes object.
            foreach ($theme->fonts as $font) foreach ($font->faces ?? [] as $face) if ([] === $face->axes) $face->axes = new \stdClass();
            $payload['theme'] = $theme;
        }
        $render = $this->workers->render(['entry' => $payload['entry'], 'templates' => $payload['templates'], 'props' => $payload['props']]);
        if (!$render['ok']) {
            return $render;
        }
        $sources = (array) $payload['sources'];
        $sources['rendered/preview.html'] = $render['html'];
        $build = $this->workers->build(['sources' => $sources, 'controllers' => $payload['controllers'], 'safelist' => $payload['safelist'], 'theme' => $payload['theme'] ?? null]);
        if (!$build['ok']) {
            return $build;
        }

        return ['ok' => true, 'html' => $this->documents->assemble($render['html'], $build['css'], $build['js'], $payload['channel'], $payload['preset'] ?? [], isset($payload['theme']) ? (array) $payload['theme'] : null, $payload['fontFiles'] ?? []), 'css' => $build['css'], 'js' => $build['js']];
    }
}
