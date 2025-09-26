<?php

namespace App\Lib;

class WhisperTranscriber
{
    private string $binaryPath;
    private string $modelPath;
    private string $outputDir;

    public function __construct(string $binaryPath, string $modelPath, string $outputDir)
    {
        $this->binaryPath = $binaryPath;
        $this->modelPath = $modelPath;
        $this->outputDir = rtrim($outputDir, '/');
    }

    public function transcribe(string $audioPath): array
    {
        if (!is_file($this->binaryPath)) {
            throw new \RuntimeException('Whisper binary not found at ' . $this->binaryPath);
        }
        if (!is_file($this->modelPath)) {
            throw new \RuntimeException('Whisper model not found at ' . $this->modelPath);
        }
        if (!is_file($audioPath)) {
            throw new \RuntimeException('Audio file not found at ' . $audioPath);
        }
        if (!is_dir($this->outputDir)) {
            if (!mkdir($concurrentDirectory = $this->outputDir, 0777, true) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException('Unable to create Whisper output directory: ' . $this->outputDir);
            }
        }

        $baseName = pathinfo($audioPath, PATHINFO_FILENAME) . '-' . uniqid('transcript', true);
        $outputPath = $this->outputDir . '/' . $baseName . '.txt';

        $command = sprintf(
            '%s -m %s -f %s -otxt -of %s 2>&1',
            escapeshellcmd($this->binaryPath),
            escapeshellarg($this->modelPath),
            escapeshellarg($audioPath),
            escapeshellarg($this->outputDir . '/' . $baseName)
        );

        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            throw new \RuntimeException('Whisper transcription failed: ' . implode("\n", $output));
        }

        if (!is_file($outputPath)) {
            throw new \RuntimeException('Expected Whisper transcript not found: ' . $outputPath);
        }

        return [
            'text' => file_get_contents($outputPath) ?: '',
            'path' => $outputPath,
        ];
    }
}
