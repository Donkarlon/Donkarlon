<?php

namespace App\Measurement\Data;

/**
 * Container for all captures and calibration metadata for a session.
 */
class MeasurementSession
{
    private string $sessionId;

    /** @var array<string, mixed> */
    private array $calibration;

    /** @var array<string, MeasurementCapture> */
    private array $captures;

    /** @var array<string, mixed> */
    private array $patientProfile;

    /**
     * @param array<string, mixed> $calibration
     * @param array<string, MeasurementCapture> $captures
     * @param array<string, mixed> $patientProfile
     */
    public function __construct(string $sessionId, array $calibration, array $captures, array $patientProfile = [])
    {
        $this->sessionId = $sessionId;
        $this->calibration = $calibration;
        $this->captures = $captures;
        $this->patientProfile = $patientProfile;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCalibration(): array
    {
        return $this->calibration;
    }

    public function getCapture(string $key): MeasurementCapture
    {
        if (!isset($this->captures[$key])) {
            throw new \InvalidArgumentException('Capture not found: ' . $key);
        }

        return $this->captures[$key];
    }

    /**
     * @return array<string, MeasurementCapture>
     */
    public function getCaptures(): array
    {
        return $this->captures;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPatientProfile(): array
    {
        return $this->patientProfile;
    }
}
