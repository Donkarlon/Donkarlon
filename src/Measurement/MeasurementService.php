<?php

namespace App\Measurement;

use App\Config;
use App\Lib\GeminiClient;
use App\Measurement\Data\MeasurementResult;
use App\Measurement\Data\MeasurementSession;

class MeasurementService
{
    private Config $config;
    private GeminiClient $geminiClient;

    public function __construct(Config $config, GeminiClient $geminiClient)
    {
        $this->config = $config;
        $this->geminiClient = $geminiClient;
    }

    public function processSession(MeasurementSession $session): MeasurementResult
    {
        $calculator = new MeasurementCalculator($session);
        $metrics = $calculator->calculate();

        $promptPath = $this->config->get('measurements.prompt_template');
        $prompt = is_string($promptPath) && is_file($promptPath) ? file_get_contents($promptPath) : '';

        $payload = [
            'session_id' => $session->getSessionId(),
            'calibration' => $session->getCalibration(),
            'patient_profile' => $session->getPatientProfile(),
            'metrics' => $metrics,
            'captures' => array_map(static function ($capture) {
                return $capture->getMetadata();
            }, $session->getCaptures()),
        ];

        $input = json_encode($payload, JSON_PRETTY_PRINT);
        if ($input === false) {
            throw new \RuntimeException('Failed to encode measurement payload.');
        }

        $response = $this->geminiClient->generateContent($prompt, $input);
        $qaSummary = GeminiClient::extractTextResponse($response);

        $reportDir = $this->config->get('measurements.report_dir');
        if (!is_dir($reportDir)) {
            if (!mkdir($reportDir, 0777, true) && !is_dir($reportDir)) {
                throw new \RuntimeException('Unable to create measurement report directory: ' . $reportDir);
            }
        }

        $reportPath = $reportDir . '/' . $session->getSessionId() . '-progressive-measurements.json';
        $reportPayload = [
            'session' => $payload,
            'qa_summary' => $qaSummary,
        ];

        $reportJson = json_encode($reportPayload, JSON_PRETTY_PRINT);
        if ($reportJson === false) {
            throw new \RuntimeException('Failed to encode measurement report payload.');
        }

        if (file_put_contents($reportPath, $reportJson) === false) {
            throw new \RuntimeException('Failed to write measurement report to ' . $reportPath);
        }

        return new MeasurementResult($metrics, $qaSummary, $reportPath);
    }
}
