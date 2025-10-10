<?php

namespace App\Measurement\Data;

class MeasurementResult
{
    /** @var array<string, mixed> */
    private array $metrics;
    private string $qaSummary;
    private string $reportPath;

    /**
     * @param array<string, mixed> $metrics
     */
    public function __construct(array $metrics, string $qaSummary, string $reportPath)
    {
        $this->metrics = $metrics;
        $this->qaSummary = $qaSummary;
        $this->reportPath = $reportPath;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function getQaSummary(): string
    {
        return $this->qaSummary;
    }

    public function getReportPath(): string
    {
        return $this->reportPath;
    }
}
