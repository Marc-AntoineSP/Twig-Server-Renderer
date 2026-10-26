<?php

namespace App\Catalogue;

final class FormInput
{
    public function create(array $data): CreateItemInput
    {
        return new CreateItemInput($data['name'], $data['kind'], $data['title'], $data['description'], $this->tags($data['tagsText']));
    }

    public function draft(array $data): DraftInput
    {
        return new DraftInput($this->object($data['manifestJson'], 'manifestJson'), $this->object($data['sourceMapJson'], 'sourceMapJson'), $data['title'], $data['description'], $this->tags($data['tagsText']));
    }

    private function tags(string $tags): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $tags)), static fn (string $tag): bool => '' !== $tag));
    }

    private function object(string $json, string $field): \stdClass
    {
        try {
            $value = json_decode($json, false, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new CatalogueException(422, 'Invalid JSON.', [$field => ['Enter valid JSON within the nesting limit.']]);
        }
        if (!$value instanceof \stdClass) {
            throw new CatalogueException(422, 'Expected a JSON object.', [$field => ['Enter a JSON object, not an array or scalar.']]);
        }

        return $value;
    }
}
