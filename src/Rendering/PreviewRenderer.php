<?php

namespace App\Rendering;

use App\Catalogue\CatalogueException;

final readonly class PreviewRenderer
{
    public function __construct(private WorkerClient $workers, private DocumentAssembler $documents)
    {
    }

    public function render(array $payload): array
    {
        $render = $this->workers->render(['entry' => $payload['entry'], 'templates' => $payload['templates'], 'props' => $payload['props']]);
        if (!$render['ok']) {
            return $render;
        }
        $sources = $payload['sources'];
        $sources['rendered/preview.html'] = $render['html'];
        $build = $this->workers->build(['sources' => $sources, 'controllers' => $payload['controllers'], 'safelist' => $payload['safelist']]);
        if (!$build['ok']) {
            return $build;
        }

        return ['ok' => true, 'html' => $this->documents->assemble($render['html'], $build['css'], $build['js'], $payload['channel'], $payload['preset'] ?? []), 'css' => $build['css'], 'js' => $build['js']];
    }
}
