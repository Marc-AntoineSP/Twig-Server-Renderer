<?php

namespace App\Catalogue\Validation;

use App\Catalogue\CatalogueException;
use Opis\JsonSchema\CompliantValidator;
use Opis\JsonSchema\Errors\ErrorFormatter;

final class PropsValidator
{
    private const KEYWORDS = ['$schema', '$defs', '$ref', 'type', 'properties', 'required', 'additionalProperties', 'enum', 'const', 'default', 'minimum', 'maximum', 'minLength', 'maxLength', 'minItems', 'maxItems', 'items', 'title', 'description'];

    public function validateSchema(\stdClass $schema): void
    {
        $this->bounded($schema, 'propsSchema');
        if (($schema->type ?? null) !== 'object') {
            $this->fail('propsSchema', 'The root props schema must have type object.');
        }
        $nodes = 0;
        $this->walk($schema, $schema, 0, [], $nodes);
    }

    public function validateValue(mixed $value, \stdClass $schema, string $field = 'props'): void
    {
        $this->bounded($value, $field);
        $validator = new CompliantValidator(max_errors: 10, stop_at_first_error: false);
        try {
            $result = $validator->validate($value, $schema);
        } catch (\Throwable) {
            $this->fail($field, 'Invalid or unsupported props schema.');
        }
        if (!$result->isValid()) {
            $errors = (new ErrorFormatter())->format($result->error());
            $fields = [];
            foreach ($errors as $path => $messages) {
                $fields[$field.$path] = (array) $messages;
            }
            throw new CatalogueException(422, 'Props do not satisfy their schema.', $fields);
        }
        $this->validateUrls($value, $field);
    }

    private function walk(\stdClass $schema, \stdClass $root, int $depth, array $references, int &$nodes): void
    {
        if ($depth > 20 || ++$nodes > 500) {
            $this->fail('propsSchema', 'Schema exceeds depth/node limits.');
        }
        foreach ($schema as $key => $value) {
            if (!in_array($key, self::KEYWORDS, true)) {
                $this->fail('propsSchema', 'Unsupported schema keyword: '.$key);
            }
        }
        if (isset($schema->{'$schema'}) && $schema->{'$schema'} !== 'https://json-schema.org/draft/2020-12/schema') {
            $this->fail('propsSchema', 'Only JSON Schema draft 2020-12 is supported.');
        }
        if (isset($schema->type)) {
            $types = is_array($schema->type) ? $schema->type : [$schema->type];
            if (!$types || array_filter($types, static fn ($type): bool => !is_string($type) || !in_array($type, ['object', 'string', 'number', 'integer', 'boolean', 'array', 'null'], true))) {
                $this->fail('propsSchema', 'Invalid schema type.');
            }
        }
        foreach (['minLength', 'maxLength', 'minItems', 'maxItems'] as $key) {
            if (isset($schema->$key) && (!is_int($schema->$key) || $schema->$key < 0 || $schema->$key > 10000)) {
                $this->fail('propsSchema', $key.' must be an integer between 0 and 10000.');
            }
        }
        foreach (['minimum', 'maximum'] as $key) {
            if (isset($schema->$key) && !is_int($schema->$key) && !is_float($schema->$key)) {
                $this->fail('propsSchema', $key.' must be numeric.');
            }
        }
        if (isset($schema->enum) && (!is_array($schema->enum) || !array_is_list($schema->enum) || !$schema->enum || count($schema->enum) > 100)) {
            $this->fail('propsSchema', 'enum must be a nonempty list of at most 100 values.');
        }
        if (isset($schema->required)) {
            if (!is_array($schema->required) || !array_is_list($schema->required) || count($schema->required) > 100) {
                $this->fail('propsSchema', 'required must be a list.');
            }
            foreach ($schema->required as $required) {
                if (!is_string($required) || !isset($schema->properties->$required)) {
                    $this->fail('propsSchema', 'Required properties must be declared.');
                }
            }
        }
        foreach (['properties', '$defs'] as $key) {
            if (!isset($schema->$key)) {
                continue;
            }
            if (!$schema->$key instanceof \stdClass || count((array) $schema->$key) > 100) {
                $this->fail('propsSchema', $key.' must be a bounded object.');
            }
            foreach ($schema->$key as $name => $child) {
                if (!$child instanceof \stdClass || strlen($name) > 100) {
                    $this->fail('propsSchema', 'Child schemas must be objects with bounded names.');
                }
                $this->walk($child, $root, $depth + 1, $references, $nodes);
            }
        }
        foreach (['items', 'additionalProperties'] as $key) {
            if (!isset($schema->$key) || ('additionalProperties' === $key && is_bool($schema->$key))) {
                continue;
            }
            if (!$schema->$key instanceof \stdClass) {
                $this->fail('propsSchema', $key.' must be a schema object.');
            }
            $this->walk($schema->$key, $root, $depth + 1, $references, $nodes);
        }
        if (isset($schema->{'$ref'})) {
            $ref = $schema->{'$ref'};
            if (!is_string($ref) || !preg_match('~^#/\$defs/([A-Za-z0-9_-]+)$~D', $ref, $match) || !isset($root->{'$defs'}->{$match[1]})) {
                $this->fail('propsSchema', 'Only resolvable local #/$defs/name references are supported.');
            }
            if (isset($references[$ref])) {
                $this->fail('propsSchema', 'Recursive schema references are not supported.');
            }
            $references[$ref] = true;
            $this->walk($root->{'$defs'}->{$match[1]}, $root, $depth + 1, $references, $nodes);
        }
        if (property_exists($schema, 'default')) {
            $defaultSchema = clone $schema;
            unset($defaultSchema->default);
            if (isset($root->{'$defs'})) {
                $defaultSchema->{'$defs'} = $root->{'$defs'};
            }
            $this->validateValue($schema->default, $defaultSchema, 'propsSchema.default');
        }
    }

    private function bounded(mixed $value, string $field): void
    {
        try {
            $json = json_encode($value, JSON_THROW_ON_ERROR, 24);
        } catch (\JsonException) {
            $this->fail($field, 'JSON exceeds nesting limits or contains invalid data.');
        }
        if (strlen($json) > 65536) {
            $this->fail($field, 'JSON exceeds 64 KiB.');
        }
    }

    private function validateUrls(mixed $value, string $field): void
    {
        if (!is_array($value) && !$value instanceof \stdClass) {
            return;
        }
        foreach ($value as $name => $child) {
            if (is_string($child) && preg_match('/(?:^|_)(?:url|href|src|action)$|(?:Url|Href|Src)$/', (string) $name)) {
                $url = trim($child);
                if (preg_match('/[\x00-\x20\x7f\\\\]/', $url) || str_starts_with($url, '//') || (preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) && !preg_match('/^(https?:|mailto:|tel:)/i', $url))) {
                    $this->fail($field.'.'.$name, 'Unsafe URL scheme or characters.');
                }
            }
            $this->validateUrls($child, $field.'.'.$name);
        }
    }

    private function fail(string $field, string $message): never
    {
        throw new CatalogueException(422, $message, [$field => [$message]]);
    }
}
