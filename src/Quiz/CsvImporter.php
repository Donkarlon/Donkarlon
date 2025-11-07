<?php

namespace App\Quiz;

use RuntimeException;

class CsvImporter
{
    private QuizRepository $quizRepository;

    public function __construct(QuizRepository $quizRepository)
    {
        $this->quizRepository = $quizRepository;
    }

    /**
     * @return array{imported:int, quizzes:array<int,string>}
     */
    public function import(string $filePath, bool $replaceExisting = false): array
    {
        if (!is_readable($filePath)) {
            throw new RuntimeException('Uploaded CSV file is not readable.');
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new RuntimeException('Failed to open uploaded CSV file.');
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            throw new RuntimeException('CSV file is empty.');
        }

        $normalisedHeader = array_map(static function ($column) {
            return strtolower(trim((string) $column));
        }, $header);

        $required = ['quiz_type', 'question', 'answer'];
        foreach ($required as $column) {
            if (!in_array($column, $normalisedHeader, true)) {
                fclose($handle);
                throw new RuntimeException(sprintf('Missing required column "%s" in CSV.', $column));
            }
        }

        $quizzes = $replaceExisting ? [] : $this->quizRepository->getAll();
        $imported = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }

            $record = [];
            foreach ($normalisedHeader as $index => $column) {
                $record[$column] = $row[$index] ?? null;
            }

            $quizName = trim((string) ($record['quiz_type'] ?? ''));
            $questionText = trim((string) ($record['question'] ?? ''));
            $answer = strtoupper(trim((string) ($record['answer'] ?? '')));

            if ($quizName === '' || $questionText === '' || $answer === '') {
                continue;
            }

            $type = strtolower(trim((string) ($record['type'] ?? 'multiple_choice')));
            if ($type === '') {
                $type = 'multiple_choice';
            }

            $options = $this->buildOptions($record, $type);

            $quizzes[$quizName] = $quizzes[$quizName] ?? [];
            $quizzes[$quizName][] = [
                'question' => $questionText,
                'type' => $type,
                'options' => $options,
                'answer' => $answer,
                'explanation' => trim((string) ($record['explanation'] ?? '')),
            ];

            $imported++;
        }

        fclose($handle);

        if ($imported === 0) {
            if ($replaceExisting) {
                throw new RuntimeException('No questions detected in the CSV. Existing quizzes were left unchanged.');
            }

            return [
                'imported' => 0,
                'quizzes' => array_keys($quizzes),
            ];
        }

        $this->quizRepository->saveAll($quizzes);

        return [
            'imported' => $imported,
            'quizzes' => array_keys($quizzes),
        ];
    }

    private function buildOptions(array $record, string $type): array
    {
        if ($type === 'true_false') {
            return [
                'A' => 'True',
                'B' => 'False',
            ];
        }

        $options = [];
        foreach (['option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D'] as $key => $label) {
            if (!array_key_exists($key, $record)) {
                continue;
            }

            $value = trim((string) $record[$key]);
            if ($value !== '') {
                $options[$label] = $value;
            }
        }

        if ($options === []) {
            throw new RuntimeException('Multiple choice questions require at least one option.');
        }

        return $options;
    }
}
