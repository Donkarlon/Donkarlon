<?php

namespace App\Measurement\Data;

use App\Measurement\Support\VectorMath;

/**
 * Represents the pose of a frame relative to the wearer.
 */
class FrameGeometry
{
    /** @var array{0: float, 1: float, 2: float} */
    private array $origin;

    /** @var array{0: float, 1: float, 2: float} */
    private array $normal;

    /** @var array{0: float, 1: float, 2: float} */
    private array $verticalAxis;

    /** @var array{0: float, 1: float, 2: float} */
    private array $horizontalAxis;

    /**
     * @param array{0: float, 1: float, 2: float} $origin
     * @param array{0: float, 1: float, 2: float} $normal
     * @param array{0: float, 1: float, 2: float} $verticalAxis
     * @param array{0: float, 1: float, 2: float} $horizontalAxis
     */
    public function __construct(array $origin, array $normal, array $verticalAxis, array $horizontalAxis)
    {
        $this->origin = $origin;
        $this->normal = VectorMath::normalize($normal);
        $this->verticalAxis = VectorMath::normalize($verticalAxis);
        $this->horizontalAxis = VectorMath::normalize($horizontalAxis);
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    public function getOrigin(): array
    {
        return $this->origin;
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    public function getNormal(): array
    {
        return $this->normal;
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    public function getVerticalAxis(): array
    {
        return $this->verticalAxis;
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    public function getHorizontalAxis(): array
    {
        return $this->horizontalAxis;
    }
}
