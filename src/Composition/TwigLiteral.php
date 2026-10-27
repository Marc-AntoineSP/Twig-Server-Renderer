<?php
namespace App\Composition;
final class TwigLiteral
{
    public static function encode(mixed $value): string
    {
        if (is_string($value)) return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
        if (is_array($value)) return '['.implode(', ', array_map(self::encode(...), $value)).']';
        if ($value instanceof \stdClass) {
            $parts = [];
            $properties = (array) $value;
            ksort($properties, SORT_STRING);
            foreach ($properties as $key => $item) $parts[] = self::encode($key).': '.self::encode($item);
            return '{'.implode(', ', $parts).'}';
        }
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }
}
