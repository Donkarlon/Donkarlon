<?php

namespace App\Quiz;

use RuntimeException;

class ScoreRepository
{
    private string $scoreFile;

    public function __construct(?string $scoreFile = null)
    {
        $this->scoreFile = $scoreFile ?? dirname(__DIR__, 2) . '/storage/quizzes/scoreboard.json';
        $this->initialiseStorage();
    }

    public function all(): array
    {
        $contents = file_get_contents($this->scoreFile);
        if ($contents === false || $contents === '') {
            return [];
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Scoreboard data is corrupt.');
        }

        return $decoded;
    }

    public function append(array $entry): void
    {
        $scores = $this->all();
        $scores[] = $entry;
        $this->persist($scores);
    }

    public function exportAsCsv(): string
    {
        $scores = $this->all();
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open temporary stream for CSV export.');
        }

        fputcsv($handle, ['Timestamp', 'Name', 'Email', 'Quiz', 'Score', 'Total Questions', 'Percentage']);

        foreach ($scores as $score) {
            $percentage = isset($score['total']) && $score['total'] > 0
                ? round(($score['score'] / $score['total']) * 100, 2)
                : 0;

            fputcsv($handle, [
                $score['timestamp'] ?? '',
                $score['name'] ?? '',
                $score['email'] ?? '',
                $score['quiz'] ?? '',
                $score['score'] ?? 0,
                $score['total'] ?? 0,
                $percentage,
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }

    private function persist(array $scores): void
    {
        $encoded = json_encode($scores, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode scoreboard data.');
        }

        $this->writeFileAtomically($this->scoreFile, $encoded);
    }

    private function initialiseStorage(): void
    {
        $directory = dirname($this->scoreFile);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('Unable to create scoreboard directory.');
            }
        }

        if (!is_file($this->scoreFile)) {
            $this->writeFileAtomically($this->scoreFile, "[]\n");
        }
    }

    private function writeFileAtomically(string $file, string $contents): void
    {
        $tmpFile = $file . '.tmp';
        if (file_put_contents($tmpFile, $contents) === false) {
            throw new RuntimeException('Failed to write scoreboard data.');
        }

        if (!rename($tmpFile, $file)) {
            throw new RuntimeException('Failed to publish scoreboard data.');
        }
    }
}
