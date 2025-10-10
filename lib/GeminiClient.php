<?php

namespace App\Lib;

class GeminiClient
{
    private string $apiKey;
    private string $model;
    private string $endpoint;

    public function __construct(string $apiKey, string $model, string $endpoint)
    {
        if (empty($apiKey)) {
            throw new \InvalidArgumentException('Gemini API key is required.');
        }

        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->endpoint = rtrim($endpoint, '/');
    }

    public function generateContent(string $prompt, string $content): array
    {
        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    ['text' => $content],
                ],
            ]],
        ];

        $url = sprintf('%s/%s:generateContent?key=%s', $this->endpoint, urlencode($this->model), urlencode($this->apiKey));

        $payloadJson = json_encode($payload);
        if ($payloadJson === false) {
            throw new \RuntimeException('Failed to encode Gemini request payload.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payloadJson,
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('Gemini API request failed: ' . $error);
        }
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode >= 400) {
            throw new \RuntimeException('Gemini API returned status ' . $statusCode . ': ' . $response);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid Gemini API response.');
        }

        return $decoded;
    }

    public static function extractTextResponse(array $response): string
    {
        $candidates = $response['candidates'] ?? [];
        if (!is_array($candidates) || empty($candidates)) {
            throw new \RuntimeException('Gemini response contains no candidates.');
        }

        $parts = $candidates[0]['content']['parts'] ?? [];
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                return $part['text'];
            }
        }

        throw new \RuntimeException('Gemini response did not contain text output.');
    }
}
