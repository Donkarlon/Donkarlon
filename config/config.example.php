<?php
return [
    'google_drive' => [
        'credentials_path' => __DIR__ . '/../credentials/google-service-account.json',
        'folder_mappings' => [
            // 'pitch-name' => 'drive-folder-id',
        ],
    ],
    'whisper' => [
        'binary_path' => __DIR__ . '/../bin/whisper.cpp/main',
        'model_path' => __DIR__ . '/../models/ggml-base.en.bin',
        'output_dir' => __DIR__ . '/../storage/transcripts',
    ],
    'gemini' => [
        'api_key' => getenv('GEMINI_API_KEY') ?: '',
        'model' => 'gemini-1.5-pro',
        'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models',
    ],
    'analysis' => [
        'prompt_template' => __DIR__ . '/../resources/prompts/sales_pitch_prompt.txt',
        'report_dir' => __DIR__ . '/../storage/reports',
        'temp_dir' => sys_get_temp_dir() . '/video-audio-cache',
    ],
    'measurements' => [
        'prompt_template' => __DIR__ . '/../resources/prompts/optical_measurement_prompt.txt',
        'report_dir' => __DIR__ . '/../storage/measurements',
    ],
];
