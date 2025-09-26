<?php

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', '/', $relativeClass) . '.php';

    $namespaceRoots = [
        'Lib\\' => __DIR__ . '/../lib/',
    ];

    foreach ($namespaceRoots as $namespacePrefix => $baseDir) {
        if (strncmp($relativeClass, $namespacePrefix, strlen($namespacePrefix)) === 0) {
            $trimmedClass = substr($relativeClass, strlen($namespacePrefix));
            $mappedPath = str_replace('\\', '/', $trimmedClass) . '.php';
            $mappedFile = $baseDir . $mappedPath;

            if (file_exists($mappedFile)) {
                require $mappedFile;
                return;
            }
        }
    }

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

$options = getopt('', ['config:', 'pitch::', 'limit::', 'local-dir::']);

$localDir = $options['local-dir'] ?? null;

if (!$options || empty($options['config']) || ($localDir === null && empty($options['pitch']))) {
    fwrite(
        STDERR,
        "Usage: php analyze_sales_pitch.php --config=/path/to/config.php [--pitch=key] [--limit=1] [--local-dir=/videos]\n"
    );
    exit(1);
}

$configPath = $options['config'];
$pitchKey = $options['pitch'] ?? 'local';
$limit = isset($options['limit']) ? (int) $options['limit'] : 1;

try {
    $config = new Config($configPath);
    $service = new SpeechAnalysisService($config);
    $results = $service->processPitch($pitchKey, $limit, $localDir);
    echo json_encode($results, JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
