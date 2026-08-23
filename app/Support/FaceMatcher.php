<?php

namespace App\Support;

class FaceMatcher
{
    /**
     * Compute Euclidean distance between two face embeddings.
     *
     * @param  list<float|int>  $descriptorA
     * @param  list<float|int>  $descriptorB
     */
    public static function euclideanDistance(array $descriptorA, array $descriptorB): float
    {
        if (count($descriptorA) !== count($descriptorB)) {
            throw new \InvalidArgumentException('Descriptor dimensions must match.');
        }

        $sum = 0.0;

        foreach ($descriptorA as $index => $value) {
            $delta = (float) $value - (float) $descriptorB[$index];
            $sum += $delta * $delta;
        }

        return sqrt($sum);
    }

    /**
     * Check if two face embeddings match within a distance threshold.
     *
     * @param  list<float|int>  $descriptorA
     * @param  list<float|int>  $descriptorB
     */
    public static function matches(array $descriptorA, array $descriptorB, ?float $threshold = null): bool
    {
        $threshold ??= (float) config('services.face.match_threshold', 0.6);

        return self::euclideanDistance($descriptorA, $descriptorB) <= $threshold;
    }
}
