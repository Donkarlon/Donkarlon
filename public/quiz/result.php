<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$name = trim((string) ($_POST['participant_name'] ?? ''));
$email = trim((string) ($_POST['participant_email'] ?? ''));
$quizType = trim((string) ($_POST['quiz_type'] ?? ''));
$submittedAnswers = $_POST['answers'] ?? [];

if ($name === '' || $email === '' || $quizType === '') {
    $_SESSION['flash'] = [
        'type' => 'alert-error',
        'message' => 'Your session expired. Please start again.',
    ];
    header('Location: index.php');
    exit;
}

$quizRepository = quiz_repo();
$questions = $quizRepository->getQuiz($quizType);
if ($questions === []) {
    $_SESSION['flash'] = [
        'type' => 'alert-error',
        'message' => 'Quiz configuration missing. Reach out to the admin team.',
    ];
    header('Location: index.php');
    exit;
}

$score = 0;
$breakdown = [];

foreach ($questions as $index => $question) {
    $questionText = (string) ($question['question'] ?? 'Untitled question');
    $options = is_array($question['options'] ?? null) ? $question['options'] : [];
    $correctRaw = (string) ($question['answer'] ?? '');
    $userRaw = (string) ($submittedAnswers[$index] ?? '');

    $correctKey = normaliseAnswerKey($correctRaw, $options);
    $userKey = normaliseAnswerKey($userRaw, $options);

    $isCorrect = $correctKey !== '' && $correctKey === $userKey;
    if ($isCorrect) {
        $score++;
    }

    $breakdown[] = [
        'question' => $questionText,
        'selected' => $userKey !== '' && isset($options[$userKey]) ? $options[$userKey] : 'No response',
        'correct' => $correctKey !== '' && isset($options[$correctKey]) ? $options[$correctKey] : 'Not configured',
        'is_correct' => $isCorrect,
        'explanation' => (string) ($question['explanation'] ?? ''),
    ];
}

$totalQuestions = count($questions);
$percentage = $totalQuestions > 0 ? round(($score / $totalQuestions) * 100, 1) : 0.0;

score_repo()->append([
    'timestamp' => date(DATE_ATOM),
    'name' => $name,
    'email' => $email,
    'quiz' => $quizType,
    'score' => $score,
    'total' => $totalQuestions,
]);

function normaliseAnswerKey(string $value, array $options): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $upper = strtoupper($value);

    if (array_key_exists($upper, $options)) {
        return $upper;
    }

    foreach ($options as $key => $label) {
        if (strtoupper((string) $label) === $upper) {
            return strtoupper((string) $key);
        }
    }

    if ($upper === 'TRUE') {
        return 'A';
    }

    if ($upper === 'FALSE') {
        return 'B';
    }

    return $upper;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Results — <?= htmlspecialchars($quizType) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles.css" />
</head>
<body>
    <header class="hero">
        <h1>Quiz Performance</h1>
        <p><?= htmlspecialchars($name) ?>, here's how you scored on <strong><?= htmlspecialchars($quizType) ?></strong>.</p>
    </header>
    <main class="container">
        <section class="card summary-card">
            <h2><?= htmlspecialchars((string) $score) ?>/<?= htmlspecialchars((string) $totalQuestions) ?></h2>
            <p>You achieved <strong><?= htmlspecialchars((string) $percentage) ?>%</strong>. Keep honing your expertise to stay ahead.</p>
            <div style="margin-top:2rem; display:flex; gap:1rem; flex-wrap:wrap; justify-content:center;">
                <a class="button" href="index.php">Back to hub</a>
            </div>
        </section>

        <section class="table-wrapper" style="margin-top:2.5rem;">
            <h2 style="margin-top:0;">Answer intelligence</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width:45%;">Question</th>
                        <th style="width:20%;">Your response</th>
                        <th style="width:20%;">Correct response</th>
                        <th>Insights</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($breakdown as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['question']) ?></td>
                            <td style="color: <?= $item['is_correct'] ? '#00b5ad' : '#d14343' ?>; font-weight:600;">
                                <?= htmlspecialchars($item['selected']) ?>
                            </td>
                            <td><?= htmlspecialchars($item['correct']) ?></td>
                            <td><?= htmlspecialchars($item['explanation']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
