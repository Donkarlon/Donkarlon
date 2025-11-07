<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

session_start();

$quizRepository = quiz_repo();
$quizzes = $quizRepository->getAll();
$quizOptions = array_map(static function ($name, $questions) {
    return [
        'name' => $name,
        'count' => is_array($questions) ? count($questions) : 0,
    ];
}, array_keys($quizzes), $quizzes);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Lenskart Quiz Intelligence Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles.css" />
</head>
<body>
    <header class="hero">
        <h1>Lenskart Quiz Intelligence Hub</h1>
        <p>Coach your teams on product mastery, dispensing excellence, and brand storytelling with adaptive quizzes crafted for the Lenskart experience.</p>
        <div style="margin-top:2rem; display:flex; gap:1rem; flex-wrap:wrap;">
            <a class="button" href="admin.php">Admin Console</a>
            <a class="button" href="download_scores.php">Download Scores</a>
        </div>
    </header>
    <main class="container">
        <?php if ($flash): ?>
            <div class="alert <?= htmlspecialchars($flash['type'] ?? 'alert-success') ?>">
                <?= htmlspecialchars($flash['message'] ?? '') ?>
            </div>
        <?php endif; ?>

        <section class="card-grid">
            <?php foreach ($quizOptions as $quiz): ?>
                <article class="card">
                    <span class="badge">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2L15 8.5L22 9.3L17 14.1L18.5 21L12 17.8L5.5 21L7 14.1L2 9.3L9 8.5L12 2Z" fill="#00b5ad"/>
                        </svg>
                        <?= htmlspecialchars($quiz['count']) ?> questions
                    </span>
                    <h2><?= htmlspecialchars($quiz['name']) ?></h2>
                    <p>Challenge your crew with scenario-based questions and instant analytics.</p>
                    <a class="button" href="#quiz-start">Start quiz</a>
                </article>
            <?php endforeach; ?>
            <?php if ($quizOptions === []): ?>
                <article class="card">
                    <h2>No quizzes yet</h2>
                    <p>Use the admin console to upload your first CSV and unlock the intelligent quiz engine.</p>
                    <a class="button" href="admin.php">Open Admin Console</a>
                </article>
            <?php endif; ?>
        </section>

        <form id="quiz-start" action="quiz.php" method="post">
            <h2>Launch a Quiz Session</h2>
            <label for="participant_name">Participant name</label>
            <input type="text" id="participant_name" name="participant_name" placeholder="Ananya Sharma" required />

            <label for="participant_email">Participant email</label>
            <input type="email" id="participant_email" name="participant_email" placeholder="ananya.sharma@lenskart.com" required />

            <label for="quiz_type">Select quiz track</label>
            <select id="quiz_type" name="quiz_type" required>
                <option value="">Choose a quiz</option>
                <?php foreach ($quizOptions as $quiz): ?>
                    <option value="<?= htmlspecialchars($quiz['name']) ?>">
                        <?= htmlspecialchars($quiz['name']) ?> (<?= htmlspecialchars((string) $quiz['count']) ?> questions)
                    </option>
                <?php endforeach; ?>
            </select>

            <button class="button" type="submit">Enter Quiz Arena</button>
        </form>
    </main>
</body>
</html>
