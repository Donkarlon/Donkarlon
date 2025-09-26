<?php

namespace App;

use App\Lib\GeminiClient;
use App\Lib\GoogleDriveClient;
use App\Lib\VideoProcessor;
use App\Lib\WhisperTranscriber;

class SpeechAnalysisService
{
    private Config $config;
    private GoogleDriveClient $driveClient;
    private VideoProcessor $videoProcessor;
    private WhisperTranscriber $transcriber;
    private GeminiClient $geminiClient;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $credentialsPath = $config->get('google_drive.credentials_path');
        $this->driveClient = new GoogleDriveClient($credentialsPath);

        $tempDir = $config->get('analysis.temp_dir', sys_get_temp_dir() . '/video-audio-cache');
        $this->videoProcessor = new VideoProcessor($tempDir);

        $this->transcriber = new WhisperTranscriber(
            $config->get('whisper.binary_path'),
            $config->get('whisper.model_path'),
            $config->get('whisper.output_dir')
        );

        $this->geminiClient = new GeminiClient(
            $config->get('gemini.api_key'),
            $config->get('gemini.model'),
            $config->get('gemini.endpoint')
        );
    }

    public function processPitch(string $pitchKey, int $limit = 1): array
    {
        $folderMappings = $this->config->get('google_drive.folder_mappings', []);
        if (!isset($folderMappings[$pitchKey])) {
            throw new \InvalidArgumentException('Unknown pitch key: ' . $pitchKey);
        }

        $folderId = $folderMappings[$pitchKey];
        $files = $this->driveClient->listFiles($folderId, $limit);

        $results = [];
        foreach ($files as $file) {
            $results[] = $this->processFile($file);
        }

        return $results;
    }

    private function processFile(array $file): array
    {
        $fileId = $file['id'];
        $name = $file['name'];
        $tempDir = sys_get_temp_dir() . '/speech-analysis';
        if (!is_dir($tempDir)) {
            if (!mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
                throw new \RuntimeException('Unable to create temp directory: ' . $tempDir);
            }
        }

        $videoPath = $tempDir . '/' . $name;
        $audioPath = null;

        try {
            $this->driveClient->downloadFile($fileId, $videoPath);

            $audioPath = $this->videoProcessor->extractAudio($videoPath);
            $transcriptData = $this->transcriber->transcribe($audioPath);
            $transcriptText = $transcriptData['text'];
            $transcriptPath = $transcriptData['path'];

            $promptPath = $this->config->get('analysis.prompt_template');
            $prompt = is_file($promptPath) ? file_get_contents($promptPath) : '';
            $response = $this->geminiClient->generateContent($prompt, $transcriptText);
            $analysis = GeminiClient::extractTextResponse($response);

            $reportDir = $this->config->get('analysis.report_dir');
            if (!is_dir($reportDir)) {
                if (!mkdir($reportDir, 0777, true) && !is_dir($reportDir)) {
                    throw new \RuntimeException('Unable to create report directory: ' . $reportDir);
                }
            }

            $reportPath = $reportDir . '/' . pathinfo($name, PATHINFO_FILENAME) . '-analysis.json';
            if (file_put_contents($reportPath, $analysis) === false) {
                throw new \RuntimeException('Failed to write analysis report to ' . $reportPath);
            }

            return [
                'file' => $file,
                'transcript_path' => $transcriptPath,
                'transcript_text' => $transcriptText,
                'report_path' => $reportPath,
                'analysis' => $analysis,
            ];
        } finally {
            if (is_file($videoPath)) {
                @unlink($videoPath);
            }
            if ($audioPath && is_file($audioPath)) {
                @unlink($audioPath);
            }
        }
    }
}
