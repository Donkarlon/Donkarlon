<?php

namespace App\Measurement;

use App\Config;
use App\Lib\GeminiClient;
use App\Measurement\Data\MeasurementResult;
use App\Measurement\Data\MeasurementSession;

use Throwable;

class MeasurementService
{
    private Config $config;
    private ?GeminiClient $geminiClient;
    private bool $qaEnabled = false;
    private ?string $qaDisabledReason;

    public function __construct(Config $config, ?GeminiClient $geminiClient = null, ?string $qaDisabledReason = null)
    {
        $this->config = $config;
        $this->geminiClient = $geminiClient;
        $this->qaEnabled = $geminiClient !== null;
        $this->qaDisabledReason = $qaDisabledReason;

        if (!$this->qaEnabled && $this->qaDisabledReason === null) {
            $this->qaDisabledReason = 'Gemini QA disabled because no client was provided.';
        }
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

        $qaSummary = $this->resolveQaSummary($prompt, $input, $metrics);

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

    /**
     * @param array<string, mixed> $metrics
     */
    private function resolveQaSummary(string $prompt, string $input, array $metrics): string
    {
        if ($this->qaEnabled && $this->geminiClient !== null) {
            try {
                $response = $this->geminiClient->generateContent($prompt, $input);
                return GeminiClient::extractTextResponse($response);
            } catch (Throwable $exception) {
                error_log('Gemini QA failed: ' . $exception->getMessage());

                return $this->buildFallbackSummary(
                    $metrics,
                    'Gemini QA failed: ' . $exception->getMessage()
                );
            }
        }

        $reason = $this->qaDisabledReason ?? 'Gemini QA disabled.';

        return $this->buildFallbackSummary($metrics, $reason);
    }

    /**
     * @param array<string, mixed> $metrics
     */
    private function buildFallbackSummary(array $metrics, string $reason): string
    {
        $lines = [$reason, '', 'Key metrics snapshot:'];

        $pd = $metrics['pupillary_distance'] ?? [];
        if (is_array($pd)) {
            $lines[] = sprintf(
                '- Pupillary distance: L %.2f mm / R %.2f mm (Binocular %.2f mm)',
                $pd['left'] ?? 0.0,
                $pd['right'] ?? 0.0,
                $pd['binocular'] ?? 0.0
            );
        }

        $fitting = $metrics['fitting_height'] ?? [];
        if (is_array($fitting)) {
            $lines[] = sprintf(
                '- Fitting height (primary): L %.2f mm / R %.2f mm',
                $fitting['primary_gaze_left'] ?? 0.0,
                $fitting['primary_gaze_right'] ?? 0.0
            );
            $lines[] = sprintf(
                '- Fitting height (reading): L %.2f mm / R %.2f mm',
                $fitting['reading_gaze_left'] ?? 0.0,
                $fitting['reading_gaze_right'] ?? 0.0
            );
        }

        $vertex = $metrics['vertex_distance'] ?? [];
        if (is_array($vertex)) {
            $lines[] = sprintf(
                '- Vertex distance: L %.2f mm / R %.2f mm (Δ %.2f mm)',
                $vertex['left'] ?? 0.0,
                $vertex['right'] ?? 0.0,
                $vertex['symmetry_delta'] ?? 0.0
            );
        }

        $pantoscopic = $metrics['pantoscopic_tilt']['angle_degrees'] ?? null;
        if (is_numeric($pantoscopic)) {
            $lines[] = sprintf('- Pantoscopic tilt: %.2f°', $pantoscopic);
        }

        $wrap = $metrics['frame_wrap']['angle_degrees'] ?? null;
        if (is_numeric($wrap)) {
            $lines[] = sprintf('- Frame wrap: %.2f°', $wrap);
        }

        $size = $metrics['frame_size'] ?? [];
        if (is_array($size)) {
            $lines[] = sprintf(
                '- Frame size (A/B): %.2f mm / %.2f mm',
                $size['a_dimension'] ?? 0.0,
                $size['b_dimension'] ?? 0.0
            );
        }

        $posture = $metrics['posture']['forward_angle_degrees'] ?? null;
        if (is_numeric($posture)) {
            $tilt = $metrics['posture']['tilt_angle_degrees'] ?? 0.0;
            $lines[] = sprintf('- Posture shift (forward/tilt): %.2f° / %.2f°', $posture, $tilt);
        }

        return implode(PHP_EOL, $lines);
    }
}
