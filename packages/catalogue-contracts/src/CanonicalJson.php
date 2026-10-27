<?php

namespace Styleguide\Contracts;

final class CanonicalJson
{
    public function encode(mixed $value, bool $pretty = false): string
    {
        return json_encode($this->sort($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | ($pretty ? JSON_PRETTY_PRINT : 0));
    }

    private function sort(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $properties = (array) $value;
            ksort($properties, SORT_STRING);
            foreach ($properties as &$property) { $property = $this->sort($property); }

            return (object) $properties;
        }
        if (is_array($value)) {
            return array_map($this->sort(...), $value);
        }

        return $value;
    }
}
