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

if ($name === '' || $email === '' || $quizType === '') {
    $_SESSION['flash'] = [
        'type' => 'alert-error',
        'message' => 'Please provide your name, email, and select a quiz to continue.',
    ];
    header('Location: index.php');
    exit;
}

$quizRepository = quiz_repo();
$questions = $quizRepository->getQuiz($quizType);

if ($questions === []) {
    $_SESSION['flash'] = [
        'type' => 'alert-error',
        'message' => 'The selected quiz is not available. Please try another track.',
    ];
    header('Location: index.php');
    exit;
}

$totalQuestions = count($questions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($quizType) ?> &mdash; Lenskart Quiz Arena</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles.css" />
</head>
<body>
    <header class="hero">
        <h1><?= htmlspecialchars($quizType) ?></h1>
        <p>Good luck <?= htmlspecialchars($name) ?>! Curated questions sharpen your confidence for every Lenskart interaction.</p>
        <div class="badge">
            <?= htmlspecialchars((string) $totalQuestions) ?> questions · Smart scoring enabled
        </div>
    </header>
    <main class="container">
        <form action="result.php" method="post">
            <input type="hidden" name="participant_name" value="<?= htmlspecialchars($name) ?>" />
            <input type="hidden" name="participant_email" value="<?= htmlspecialchars($email) ?>" />
            <input type="hidden" name="quiz_type" value="<?= htmlspecialchars($quizType) ?>" />
            <?php foreach ($questions as $index => $question): ?>
                <?php
                $questionNumber = $index + 1;
                $questionText = (string) ($question['question'] ?? 'Untitled question');
                $type = (string) ($question['type'] ?? 'multiple_choice');
                $options = is_array($question['options'] ?? null) ? $question['options'] : [];
                ?>
                <section class="quiz-question">
                    <div class="badge">Question <?= htmlspecialchars((string) $questionNumber) ?></div>
                    <h3><?= htmlspecialchars($questionText) ?></h3>
                    <div class="options">
                        <?php if ($type === 'true_false'): ?>
                            <?php foreach ([
                                'A' => $options['A'] ?? 'True',
                                'B' => $options['B'] ?? 'False',
                            ] as $value => $label): ?>
                                <label class="option">
                                    <input type="radio" name="answers[<?= $index ?>]" value="<?= htmlspecialchars($value) ?>" required />
                                    <span><?= htmlspecialchars($label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach ($options as $value => $label): ?>
                                <label class="option">
                                    <input type="radio" name="answers[<?= $index ?>]" value="<?= htmlspecialchars((string) $value) ?>" required />
                                    <span><?= htmlspecialchars((string) $label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <button class="button" type="submit">Submit Responses</button>
        </form>
    </main>
</body>
</html>
