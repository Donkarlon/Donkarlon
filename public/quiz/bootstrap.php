<?php

declare(strict_types=1);

spl_autoload_register(static function ($class): void {
    $prefix = 'App\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', '/', $relativeClass) . '.php';

    $baseDirs = [
        __DIR__ . '/../../src/',
        __DIR__ . '/../../lib/',
    ];

    foreach ($baseDirs as $baseDir) {
        $file = $baseDir . $relativePath;
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

function quiz_repo(): App\Quiz\QuizRepository
{
    static $repo;
    if ($repo === null) {
        $repo = new App\Quiz\QuizRepository();
    }

    return $repo;
}

function score_repo(): App\Quiz\ScoreRepository
{
    static $repo;
    if ($repo === null) {
        $repo = new App\Quiz\ScoreRepository();
    }

    return $repo;
}
