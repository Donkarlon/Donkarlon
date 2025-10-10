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
use App\Measurement\Data\FrameGeometry;
use App\Measurement\Data\MeasurementCapture;
use App\Measurement\Data\MeasurementSession;
use App\Measurement\MeasurementService;

$inputPath = $argv[1] ?? __DIR__ . '/../resources/examples/measurement-session-sample.json';

if (!is_file($inputPath)) {
    fwrite(STDERR, "Sample session JSON not found at {$inputPath}\n");
    exit(1);
}

try {
    $payload = json_decode(file_get_contents($inputPath), true, 512, JSON_THROW_ON_ERROR);
} catch (\JsonException $exception) {
    fwrite(STDERR, 'Invalid JSON payload: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

$tempDir = sys_get_temp_dir() . '/progressive-measurement-test-' . bin2hex(random_bytes(6));
if (!mkdir($tempDir) && !is_dir($tempDir)) {
    fwrite(STDERR, "Unable to create temporary directory at {$tempDir}\n");
    exit(1);
}

$configPath = $tempDir . '/config.php';
$reportDir = $tempDir . '/reports';

$configData = [
    'gemini' => [
        'api_key' => '',
        'model' => 'gemini-1.5-pro',
        'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models',
    ],
    'measurements' => [
        'prompt_template' => __DIR__ . '/../resources/prompts/optical_measurement_prompt.txt',
        'report_dir' => $reportDir,
    ],
];

$configTemplate = "<?php\nreturn " . var_export($configData, true) . ";\n";
file_put_contents($configPath, $configTemplate);

$config = new Config($configPath);

$session = buildMeasurementSession($payload);

$service = new MeasurementService($config, null, 'Gemini QA disabled for automated pipeline test.');
$result = $service->processSession($session);

$reportPath = $result->getReportPath();
if (!is_file($reportPath)) {
    cleanup($tempDir);
    fwrite(STDERR, 'Measurement report was not generated.' . PHP_EOL);
    exit(1);
}

try {
    $report = json_decode(file_get_contents($reportPath), true, 512, JSON_THROW_ON_ERROR);
} catch (\JsonException $exception) {
    cleanup($tempDir);
    fwrite(STDERR, 'Measurement report JSON is invalid: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

$requiredKeys = [
    'session.metrics.pupillary_distance.left',
    'session.metrics.pupillary_distance.right',
    'session.metrics.pupillary_distance.binocular',
    'session.metrics.fitting_height.primary_gaze_left',
    'session.metrics.fitting_height.primary_gaze_right',
    'session.metrics.vertex_distance.left',
    'session.metrics.vertex_distance.right',
    'session.metrics.pantoscopic_tilt.angle_degrees',
    'session.metrics.frame_wrap.angle_degrees',
    'session.metrics.frame_size.a_dimension',
];

$missing = [];
foreach ($requiredKeys as $key) {
    $value = data_get($report, $key);
    if ($value === null) {
        $missing[] = $key;
    }
}

if ($missing !== []) {
    cleanup($tempDir);
    fwrite(STDERR, 'Missing expected measurement keys: ' . implode(', ', $missing) . PHP_EOL);
    exit(1);
}

$expectedMetrics = [
    'session.metrics.pupillary_distance.left' => [30.4759, 0.05],
    'session.metrics.pupillary_distance.right' => [30.9158, 0.05],
    'session.metrics.pupillary_distance.binocular' => [61.4024, 0.05],
    'session.metrics.fitting_height.primary_gaze_left' => [26.9164, 0.05],
    'session.metrics.fitting_height.primary_gaze_right' => [26.8964, 0.05],
    'session.metrics.fitting_height.reading_gaze_left' => [14.6117, 0.05],
    'session.metrics.fitting_height.reading_gaze_right' => [14.3920, 0.05],
    'session.metrics.vertex_distance.left' => [2.4075, 0.1],
    'session.metrics.vertex_distance.right' => [1.3877, 0.1],
    'session.metrics.pantoscopic_tilt.angle_degrees' => [88.8314, 0.1],
    'session.metrics.frame_wrap.angle_degrees' => [57.9949, 0.1],
    'session.metrics.frame_size.a_dimension' => [92.5775, 0.1],
];

$mismatched = [];
foreach ($expectedMetrics as $key => [$expectedValue, $tolerance]) {
    $value = data_get($report, $key);
    if ($value === null) {
        $mismatched[] = "$key (missing)";
        continue;
    }

    if (!is_numeric($value)) {
        $mismatched[] = "$key (non-numeric value)";
        continue;
    }

    if (abs($value - $expectedValue) > $tolerance) {
        $mismatched[] = sprintf(
            '%s (expected %.4f ± %.3f, got %.4f)',
            $key,
            $expectedValue,
            $tolerance,
            $value
        );
    }
}

if ($mismatched !== []) {
    cleanup($tempDir);
    fwrite(STDERR, 'Measurement values outside expected tolerance: ' . implode('; ', $mismatched) . PHP_EOL);
    exit(1);
}

$qaSummary = $report['qa_summary'] ?? '';
if (!is_string($qaSummary) || trim($qaSummary) === '') {
    cleanup($tempDir);
    fwrite(STDERR, 'QA summary was empty.' . PHP_EOL);
    exit(1);
}

echo "Measurement pipeline test passed.\n";
cleanup($tempDir);

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

function data_get(array $data, string $key)
{
    $segments = explode('.', $key);
    $value = $data;

    foreach ($segments as $segment) {
        if (is_array($value) && array_key_exists($segment, $value)) {
            $value = $value[$segment];
        } else {
            return null;
        }
    }

    return $value;
}

function cleanup(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $items = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($directory);
}
