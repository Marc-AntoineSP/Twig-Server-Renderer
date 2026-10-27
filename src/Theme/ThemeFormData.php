<?php

namespace App\Theme;

final class ThemeFormData
{
    public function array(\stdClass $document): array
    {
        return json_decode(json_encode($document, JSON_THROW_ON_ERROR), true, 24, JSON_THROW_ON_ERROR);
    }

    public function document(array $data, array $fonts): \stdClass
    {
        unset($data['revision']);
        $data['schemaVersion'] = 1;
        $data['fonts'] = $fonts;

        return json_decode(json_encode($data, JSON_THROW_ON_ERROR), false, 24, JSON_THROW_ON_ERROR);
    }
}
