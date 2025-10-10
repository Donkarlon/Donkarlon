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
use App\Lib\GeminiClient;
use App\Measurement\Data\FrameGeometry;
use App\Measurement\Data\MeasurementCapture;
use App\Measurement\Data\MeasurementSession;
use App\Measurement\MeasurementService;

$options = getopt('', ['config:', 'input:', 'skip-qa']);

$configPath = $options['config'] ?? __DIR__ . '/../config/config.php';
$inputPath = $options['input'] ?? null;

if ($inputPath === null) {
    fwrite(STDERR, "--input path to session JSON is required\n");
    exit(1);
}

if (!is_file($configPath)) {
    fwrite(STDERR, "Config file not found at {$configPath}\n");
    exit(1);
}

if (!is_file($inputPath)) {
    fwrite(STDERR, "Input JSON not found at {$inputPath}\n");
    exit(1);
}

$config = new Config($configPath);

$skipQa = array_key_exists('skip-qa', $options);
$apiKey = (string) $config->get('gemini.api_key', '');

$geminiClient = null;
$qaDisabledReason = null;

if ($skipQa) {
    $qaDisabledReason = 'Gemini QA skipped via --skip-qa flag.';
} elseif ($apiKey !== '') {
    $geminiClient = new GeminiClient(
        $apiKey,
        (string) $config->get('gemini.model'),
        (string) $config->get('gemini.endpoint')
    );
} else {
    $qaDisabledReason = 'Gemini QA disabled because no API key was provided.';
}

try {
    $payload = json_decode(file_get_contents($inputPath), true, 512, JSON_THROW_ON_ERROR);
} catch (\JsonException $exception) {
    fwrite(STDERR, 'Invalid JSON payload: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

$session = buildMeasurementSession($payload);

$service = new MeasurementService($config, $geminiClient, $qaDisabledReason);
$result = $service->processSession($session);

echo "Measurement report stored at: " . $result->getReportPath() . PHP_EOL;
echo "QA Summary:\n" . $result->getQaSummary() . PHP_EOL;

/**
 * @param array<string, mixed> $payload
 */
function buildMeasurementSession(array $payload): MeasurementSession
{
    $captures = [];
    foreach ($payload['captures'] as $label => $captureData) {
        $geometryData = $captureData['frame_geometry'];
        $geometry = new FrameGeometry(
            $geometryData['origin'],
            $geometryData['normal'],
            $geometryData['vertical_axis'],
            $geometryData['horizontal_axis']
        );

        $captures[$label] = new MeasurementCapture(
            $label,
            $geometry,
            $captureData['pupil_centers'],
            $captureData['corneal_apexes'],
            $captureData['nose_bridge_point'],
            $captureData['lower_rim_points'],
            $captureData['lens_inner_samples'],
            $captureData['head_pose_vectors'],
            $captureData['metadata'] ?? []
        );
    }

    return new MeasurementSession(
        $payload['session_id'],
        $payload['calibration'] ?? [],
        $captures,
        $payload['patient_profile'] ?? []
    );
}
