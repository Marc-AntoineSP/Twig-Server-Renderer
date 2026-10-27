<?php

namespace App\Theme;

final class ThemeFormData
{
    public function __construct(private readonly \App\Font\FontLibrary $library) {}

    public function array(\stdClass $document): array
    {
        $data = json_decode(json_encode($document, JSON_THROW_ON_ERROR), true, 24, JSON_THROW_ON_ERROR);
        $data['geometry'] += ['highContrastBorders' => false];
        $data['typography'] += ['codeFontId' => 'system-mono', 'bodyWeight' => 400, 'headingWeight' => 700, 'codeWeight' => 400, 'bodyStyle' => 'normal', 'headingStyle' => 'normal', 'codeStyle' => 'normal'];
        return $data;
    }

    public function document(array $data, array $fonts, ?\stdClass $original = null): \stdClass
    {
        unset($data['revision']);
        $data['schemaVersion'] = 1;
        $known = [];
        foreach ($fonts as $font) $known[$font->id] = $font;
        $ids = array_unique([$data['typography']['bodyFontId'], $data['typography']['headingFontId'], $data['typography']['codeFontId'] ?? 'system-mono']);
        $selected = [];
        foreach ($ids as $id) $selected[] = $known[$id] ?? $this->library->definition($id);
        foreach ($fonts as $font) if ('system' === $font->kind && !in_array($font->id, $ids, true)) $selected[] = $font;
        $data['fonts'] = $selected;
        if (isset($original->provenance)) $data['provenance'] = $original->provenance;

        return json_decode(json_encode($data, JSON_THROW_ON_ERROR), false, 24, JSON_THROW_ON_ERROR);
    }
}
