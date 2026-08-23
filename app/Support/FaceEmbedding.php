<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class FaceEmbedding
{
    public const LENGTH = 128;

    /**
     * @return array<int, string>
     */
    public static function rules(string $field = 'embedding'): array
    {
        return [
            $field => ['required', 'array', 'size:'.self::LENGTH],
            "{$field}.*" => ['numeric'],
        ];
    }

    /**
     * @param  mixed  $value
     * @return list<float>
     */
    public static function normalize(mixed $value, string $field = 'embedding'): array
    {
        if (! is_array($value) || count($value) !== self::LENGTH) {
            throw ValidationException::withMessages([
                $field => ['A valid 128-D face embedding is required.'],
            ]);
        }

        return array_map('floatval', array_values($value));
    }
}
