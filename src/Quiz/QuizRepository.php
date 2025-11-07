<?php

namespace App\Quiz;

use RuntimeException;

class QuizRepository
{
    private string $questionsFile;

    public function __construct(?string $questionsFile = null)
    {
        $this->questionsFile = $questionsFile ?? dirname(__DIR__, 2) . '/storage/quizzes/questions.json';
        $this->initialiseStorage();
    }

    public function getAll(): array
    {
        $json = file_get_contents($this->questionsFile);
        if ($json === false || $json === '') {
            return [];
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new RuntimeException('Quiz data is invalid JSON.');
        }

        return $data;
    }

    public function getQuiz(string $quizName): array
    {
        $quizzes = $this->getAll();
        return $quizzes[$quizName] ?? [];
    }

    public function saveAll(array $quizzes): void
    {
        $encoded = json_encode($quizzes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode quiz data.');
        }

        $this->writeFileAtomically($this->questionsFile, $encoded);
    }

    public function replaceQuiz(string $quizName, array $questions): void
    {
        $quizzes = $this->getAll();
        $quizzes[$quizName] = array_values($questions);
        $this->saveAll($quizzes);
    }

    public function appendQuestions(string $quizName, array $newQuestions): void
    {
        $quizzes = $this->getAll();
        $existing = $quizzes[$quizName] ?? [];
        $quizzes[$quizName] = array_merge($existing, array_values($newQuestions));
        $this->saveAll($quizzes);
    }

    public function getQuizNames(): array
    {
        return array_keys($this->getAll());
    }

    private function initialiseStorage(): void
    {
        $directory = dirname($this->questionsFile);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('Unable to create quiz storage directory.');
            }
        }

        if (!is_file($this->questionsFile)) {
            $this->writeFileAtomically($this->questionsFile, "{}\n");
        }
    }

    private function writeFileAtomically(string $file, string $contents): void
    {
        $tempFile = $file . '.tmp';
        if (file_put_contents($tempFile, $contents) === false) {
            throw new RuntimeException('Failed to write temporary quiz data file.');
        }

        if (!rename($tempFile, $file)) {
            throw new RuntimeException('Failed to move quiz data into place.');
        }
    }
}
