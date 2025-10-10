<?php
return [
    'gemini' => [
        'api_key' => getenv('GEMINI_API_KEY') ?: '',
        'model' => 'gemini-1.5-pro',
        'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models',
    ],
    'measurements' => [
        'prompt_template' => __DIR__ . '/../resources/prompts/optical_measurement_prompt.txt',
        'report_dir' => __DIR__ . '/../storage/measurements',
    ],
];
