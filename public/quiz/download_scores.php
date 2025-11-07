<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$csv = score_repo()->exportAsCsv();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="lenskart-quiz-scores.csv"');
header('Content-Length: ' . strlen($csv));

echo $csv;
