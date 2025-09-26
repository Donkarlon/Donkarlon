<?php

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', '/', $relativeClass) . '.php';
    $locations = [
        __DIR__ . '/../src/' . $relativePath,
        __DIR__ . '/../lib/' . $relativePath,
    ];

    foreach ($locations as $file) {
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

use App\Config;
use App\SpeechAnalysisService;

$options = getopt('', ['config:', 'pitch:', 'limit::']);

if (!$options || empty($options['config']) || empty($options['pitch'])) {
    fwrite(STDERR, "Usage: php analyze_sales_pitch.php --config=/path/to/config.php --pitch=key [--limit=1]\n");
    exit(1);
}

$configPath = $options['config'];
$pitchKey = $options['pitch'];
$limit = isset($options['limit']) ? (int) $options['limit'] : 1;

try {
    $config = new Config($configPath);
    $service = new SpeechAnalysisService($config);
    $results = $service->processPitch($pitchKey, $limit);
    echo json_encode($results, JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
