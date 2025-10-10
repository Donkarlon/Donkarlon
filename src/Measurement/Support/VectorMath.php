<?php

namespace App\Measurement\Support;

/**
 * Lightweight vector math helper for working with 3D measurement data.
 */
class VectorMath
{
    /**
     * @param array{0: float, 1: float, 2: float} $a
     * @param array{0: float, 1: float, 2: float} $b
     */
    public static function add(array $a, array $b): array
    {
        return [$a[0] + $b[0], $a[1] + $b[1], $a[2] + $b[2]];
    }

    /**
     * @param array{0: float, 1: float, 2: float} $a
     * @param array{0: float, 1: float, 2: float} $b
     */
    public static function subtract(array $a, array $b): array
    {
        return [$a[0] - $b[0], $a[1] - $b[1], $a[2] - $b[2]];
    }

    /**
     * @param array{0: float, 1: float, 2: float} $a
     * @param float $scalar
     */
    public static function scale(array $a, float $scalar): array
    {
        return [$a[0] * $scalar, $a[1] * $scalar, $a[2] * $scalar];
    }

    /**
     * @param array{0: float, 1: float, 2: float} $a
     * @param array{0: float, 1: float, 2: float} $b
     */
    public static function dot(array $a, array $b): float
    {
        return $a[0] * $b[0] + $a[1] * $b[1] + $a[2] * $b[2];
    }

    /**
     * @param array{0: float, 1: float, 2: float} $a
     * @param array{0: float, 1: float, 2: float} $b
     */
    public static function cross(array $a, array $b): array
    {
        return [
            $a[1] * $b[2] - $a[2] * $b[1],
            $a[2] * $b[0] - $a[0] * $b[2],
            $a[0] * $b[1] - $a[1] * $b[0],
        ];
    }

    /**
     * @param array{0: float, 1: float, 2: float} $a
     */
    public static function magnitude(array $a): float
    {
        return sqrt(self::dot($a, $a));
    }

    /**
     * @param array{0: float, 1: float, 2: float} $a
     */
    public static function normalize(array $a): array
    {
        $magnitude = self::magnitude($a);
        if ($magnitude === 0.0) {
            return [0.0, 0.0, 0.0];
        }

        return [$a[0] / $magnitude, $a[1] / $magnitude, $a[2] / $magnitude];
    }

    /**
     * @param array{0: float, 1: float, 2: float} $a
     * @param array{0: float, 1: float, 2: float} $b
     */
    public static function angleBetween(array $a, array $b): float
    {
        $normA = self::normalize($a);
        $normB = self::normalize($b);

        $dot = max(-1.0, min(1.0, self::dot($normA, $normB)));

        return acos($dot);
    }

    /**
     * Project vector a onto vector b.
     *
     * @param array{0: float, 1: float, 2: float} $a
     * @param array{0: float, 1: float, 2: float} $b
     */
    public static function projectVector(array $a, array $b): array
    {
        $normB = self::normalize($b);
        $scalar = self::dot($a, $normB);

        return [$normB[0] * $scalar, $normB[1] * $scalar, $normB[2] * $scalar];
    }

    /**
     * @param array{0: float, 1: float, 2: float} $point
     * @param array{0: float, 1: float, 2: float} $planeOrigin
     * @param array{0: float, 1: float, 2: float} $planeNormal
     */
    public static function projectPointOntoPlane(array $point, array $planeOrigin, array $planeNormal): array
    {
        $planeNormal = self::normalize($planeNormal);
        $vector = self::subtract($point, $planeOrigin);
        $distance = self::dot($vector, $planeNormal);

        return self::subtract($point, self::scale($planeNormal, $distance));
    }

    /**
     * @param array{0: float, 1: float, 2: float} $point
     * @param array{0: float, 1: float, 2: float} $planeOrigin
     * @param array{0: float, 1: float, 2: float} $planeNormal
     */
    public static function signedDistanceToPlane(array $point, array $planeOrigin, array $planeNormal): float
    {
        $planeNormal = self::normalize($planeNormal);
        $vector = self::subtract($point, $planeOrigin);

        return self::dot($vector, $planeNormal);
    }
}
