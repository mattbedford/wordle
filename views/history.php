<?php
declare(strict_types=1);
require_once __DIR__ . '/env.php';
env_load();

$dbPath = $_ENV['DB_PATH'] ?? 'data/scores.db';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get last 30 puzzles with scores
$stmt = $pdo->query("
    SELECT DISTINCT puzzle
    FROM scores
    ORDER BY puzzle DESC
    LIMIT 30
");
$puzzleIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

$title = "History";
require __DIR__ . '/partials/header.php';
?>

<h1>Past Games</h1>

<div class="grid">
    <?php foreach ($puzzleIds as $puzzle):
        // Get scores for this puzzle
        $stmt = $pdo->prepare("
        SELECT s.guesses, s.created_at, p.name
        FROM scores s
        JOIN players p ON s.player_id = p.id
        WHERE s.puzzle = ?
        ORDER BY s.guesses ASC, s.created_at ASC
    ");
        $stmt->execute([$puzzle]);
        $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$scores) continue;

        $date = (new DateTime($scores[0]['created_at']))->format('j M Y');
        $winner = $scores[0]['name'];
        ?>
        <div class="card">
            <div class="card-header">
                <strong>Wordle <?= (int)$puzzle ?></strong><br>
                <small><?= $date ?></small><br>
                <em>🏆 <?= htmlspecialchars($winner) ?></em>
            </div>
            <table>
                <thead>
                <tr><th>Player</th><th>Guesses</th></tr>
                </thead>
                <tbody>
                <?php foreach ($scores as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['name']) ?></td>
                        <td><?= $s['guesses'] === 'X' ? 'X' : (int)$s['guesses'] ?>/6</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
