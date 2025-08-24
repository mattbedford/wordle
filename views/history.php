<?php
declare(strict_types=1);
require_once __DIR__ . '/env.php';
env_load();

$dbPath = $_ENV['DB_PATH'] ?? 'data/scores.db';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Fetch last 30 unique puzzles with scores
$stmt = $pdo->query("
    SELECT DISTINCT puzzle
    FROM scores
    ORDER BY puzzle DESC
    LIMIT 30
");
$puzzles = $stmt->fetchAll(PDO::FETCH_COLUMN);

$title = "Today's Leaderboard";
require __DIR__ . '/partials/header.php';
?>


<h1>Past Wordle Puzzles</h1>
<ul>
    <?php foreach ($puzzles as $puzzle): ?>
        <li><a href="/leaderboard?puzzle=<?= (int)$puzzle ?>">Puzzle <?= (int)$puzzle ?></a></li>
    <?php endforeach; ?>
</ul>


<?php require __DIR__ . '/partials/footer.php'; ?>
