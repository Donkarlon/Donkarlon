<?php

namespace App\Lib;

class VideoProcessor
{
    private string $workingDir;

    public function __construct(string $workingDir)
    {
        $this->workingDir = rtrim($workingDir, '/');
        if (!is_dir($this->workingDir)) {
            if (!mkdir($concurrentDirectory = $this->workingDir, 0777, true) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException('Unable to create working directory: ' . $this->workingDir);
            }
        }
    }

    public function extractAudio(string $videoPath, string $format = 'wav'): string
    {
        if (!is_file($videoPath)) {
            throw new \RuntimeException('Video file not found at ' . $videoPath);
        }

        $baseName = pathinfo($videoPath, PATHINFO_FILENAME) . '-' . uniqid('audio', true);
        $audioPath = $this->workingDir . '/' . $baseName . '.' . $format;

        $command = sprintf(
            'ffmpeg -y -i %s -vn -acodec pcm_s16le -ar 16000 -ac 1 %s 2>&1',
            escapeshellarg($videoPath),
            escapeshellarg($audioPath)
        );

        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            throw new \RuntimeException('FFmpeg audio extraction failed: ' . implode("\n", $output));
        }

        if (!is_file($audioPath)) {
            throw new \RuntimeException('Expected audio file not created: ' . $audioPath);
        }

        return $audioPath;
    }
}
