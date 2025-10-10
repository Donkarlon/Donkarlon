<?php

namespace App\Measurement\Data;

/**
 * Rich data captured for each measurement pose.
 */
class MeasurementCapture
{
    private string $label;
    private FrameGeometry $frameGeometry;

    /** @var array<string, array{0: float, 1: float, 2: float}> */
    private array $pupilCenters;

    /** @var array<string, array{0: float, 1: float, 2: float}> */
    private array $cornealApexes;

    /** @var array{0: float, 1: float, 2: float} */
    private array $noseBridgePoint;

    /** @var array<string, array{0: float, 1: float, 2: float}> */
    private array $lowerRimPoints;

    /** @var array<string, array{0: float, 1: float, 2: float}> */
    private array $lensInnerSamples;

    /** @var array<string, array{0: float, 1: float, 2: float}> */
    private array $headPoseVectors;

    /** @var array<string, mixed> */
    private array $metadata;

    /**
     * @param array<string, array{0: float, 1: float, 2: float}> $pupilCenters
     * @param array<string, array{0: float, 1: float, 2: float}> $cornealApexes
     * @param array{0: float, 1: float, 2: float} $noseBridgePoint
     * @param array<string, array{0: float, 1: float, 2: float}> $lowerRimPoints
     * @param array<string, array{0: float, 1: float, 2: float}> $lensInnerSamples
     * @param array<string, array{0: float, 1: float, 2: float}> $headPoseVectors
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        string $label,
        FrameGeometry $frameGeometry,
        array $pupilCenters,
        array $cornealApexes,
        array $noseBridgePoint,
        array $lowerRimPoints,
        array $lensInnerSamples,
        array $headPoseVectors,
        array $metadata = []
    ) {
        $this->label = $label;
        $this->frameGeometry = $frameGeometry;
        $this->pupilCenters = $pupilCenters;
        $this->cornealApexes = $cornealApexes;
        $this->noseBridgePoint = $noseBridgePoint;
        $this->lowerRimPoints = $lowerRimPoints;
        $this->lensInnerSamples = $lensInnerSamples;
        $this->headPoseVectors = $headPoseVectors;
        $this->metadata = $metadata;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getFrameGeometry(): FrameGeometry
    {
        return $this->frameGeometry;
    }

    /**
     * @return array<string, array{0: float, 1: float, 2: float}>
     */
    public function getPupilCenters(): array
    {
        return $this->pupilCenters;
    }

    /**
     * @return array<string, array{0: float, 1: float, 2: float}>
     */
    public function getCornealApexes(): array
    {
        return $this->cornealApexes;
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    public function getNoseBridgePoint(): array
    {
        return $this->noseBridgePoint;
    }

    /**
     * @return array<string, array{0: float, 1: float, 2: float}>
     */
    public function getLowerRimPoints(): array
    {
        return $this->lowerRimPoints;
    }

    /**
     * @return array<string, array{0: float, 1: float, 2: float}>
     */
    public function getLensInnerSamples(): array
    {
        return $this->lensInnerSamples;
    }

    /**
     * @return array<string, array{0: float, 1: float, 2: float}>
     */
    public function getHeadPoseVectors(): array
    {
        return $this->headPoseVectors;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
