<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Quiz\CsvImporter;

session_start();

$quizRepository = quiz_repo();
$scoreRepository = score_repo();
$importer = new CsvImporter($quizRepository);

$alert = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_FILES['questions_csv']) || !is_uploaded_file($_FILES['questions_csv']['tmp_name'])) {
            throw new RuntimeException('Please upload a CSV file.');
        }

        $replace = isset($_POST['replace_existing']) && $_POST['replace_existing'] === '1';
        $result = $importer->import($_FILES['questions_csv']['tmp_name'], $replace);

        $alert = [
            'type' => 'alert-success',
            'message' => sprintf('Imported %d questions across %d quiz tracks.', $result['imported'], count($result['quizzes'])),
        ];
    } catch (Throwable $exception) {
        $alert = [
            'type' => 'alert-error',
            'message' => $exception->getMessage(),
        ];
    }
}

$quizzes = $quizRepository->getAll();
$quizSummaries = [];
foreach ($quizzes as $name => $questions) {
    $counts = [
        'multiple_choice' => 0,
        'true_false' => 0,
        'other' => 0,
    ];

    foreach ($questions as $question) {
        $type = strtolower((string) ($question['type'] ?? 'multiple_choice'));
        if (isset($counts[$type])) {
            $counts[$type]++;
        } else {
            $counts['other']++;
        }
    }

    $quizSummaries[] = [
        'name' => $name,
        'total' => is_array($questions) ? count($questions) : 0,
        'counts' => $counts,
    ];
}

$scores = array_reverse($scoreRepository->all());
$recentScores = array_slice($scores, 0, 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Console — Lenskart Quiz Intelligence Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles.css" />
</head>
<body>
    <header class="hero">
        <h1>Admin Console</h1>
        <p>Upload new content, monitor performance, and keep every Lenskart team aligned on excellence.</p>
        <div style="margin-top:2rem; display:flex; gap:1rem; flex-wrap:wrap;">
            <a class="button" href="index.php">Back to participant hub</a>
            <a class="button" href="download_scores.php">Download all scores</a>
        </div>
    </header>
    <main class="container">
        <?php if ($alert): ?>
            <div class="alert <?= htmlspecialchars($alert['type']) ?>">
                <?= htmlspecialchars($alert['message']) ?>
            </div>
        <?php endif; ?>

        <div class="admin-layout">
            <form method="post" enctype="multipart/form-data">
                <h2>Upload questions</h2>
                <p style="color:var(--text-muted);">Supply a CSV with columns such as <strong>quiz_type</strong>, <strong>question</strong>, <strong>answer</strong>, <strong>type</strong>, <strong>option_a</strong>…</p>
                <input type="file" name="questions_csv" accept=".csv" required />

                <label style="display:flex; align-items:center; gap:0.5rem; margin-top:1rem;">
                    <input type="checkbox" name="replace_existing" value="1" /> Replace quizzes with uploaded data
                </label>

                <button class="button" type="submit" style="margin-top:1.5rem;">Import CSV</button>

                <p style="font-size:0.9rem; color:var(--text-muted); margin-top:1.5rem;">
                    Accepted question types: <strong>multiple_choice</strong> (requires options A–D) and <strong>true_false</strong>.
                    Answers may use option letters or literal values like “True”.
                </p>
            </form>

            <section class="card">
                <h2>Quiz catalogue</h2>
                <ul style="list-style:none; padding:0; margin:0; display:grid; gap:1rem;">
                    <?php foreach ($quizSummaries as $summary): ?>
                        <li style="padding:1rem; border-radius:18px; background:var(--surface-muted);">
                            <h3 style="margin:0 0 0.4rem; color:var(--lenskart-navy);">
                                <?= htmlspecialchars($summary['name']) ?>
                            </h3>
                            <p style="margin:0; color:var(--text-muted);">
                                <?= htmlspecialchars((string) $summary['total']) ?> questions ·
                                <?= htmlspecialchars((string) $summary['counts']['multiple_choice']) ?> MCQ ·
                                <?= htmlspecialchars((string) $summary['counts']['true_false']) ?> True/False
                            </p>
                        </li>
                    <?php endforeach; ?>
                    <?php if ($quizSummaries === []): ?>
                        <li style="padding:1rem; border-radius:18px; background:var(--surface-muted); color:var(--text-muted);">
                            No quizzes yet. Upload a CSV to get started.
                        </li>
                    <?php endif; ?>
                </ul>
            </section>
        </div>

        <section class="table-wrapper" style="margin-top:3rem;">
            <h2 style="margin-top:0;">Latest scores</h2>
            <table>
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Participant</th>
                        <th>Quiz</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentScores as $score): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($score['timestamp'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($score['name'] ?? '')) ?><?= isset($score['email']) && $score['email'] !== '' ? ' · ' . htmlspecialchars((string) $score['email']) : '' ?></td>
                            <td><?= htmlspecialchars((string) ($score['quiz'] ?? '')) ?></td>
                            <td>
                                <?php
                                $scoreValue = (int) ($score['score'] ?? 0);
                                $totalValue = max(1, (int) ($score['total'] ?? 0));
                                $pct = round(($scoreValue / $totalValue) * 100);
                                ?>
                                <?= htmlspecialchars((string) $scoreValue) ?>/<?= htmlspecialchars((string) $totalValue) ?> (<?= htmlspecialchars((string) $pct) ?>%)
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($recentScores === []): ?>
                        <tr>
                            <td colspan="4">No attempts recorded yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
