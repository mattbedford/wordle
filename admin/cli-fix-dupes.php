<?php
declare(strict_types=1);
require_once __DIR__ . '/../env.php';
env_load();

$pdo = new PDO('sqlite:' . $_ENV['DB_PATH']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Find duplicate scores for same player & puzzle
$stmt = $pdo->query("
    SELECT player_id, puzzle, COUNT(*) as c
    FROM scores
    GROUP BY player_id, puzzle
    HAVING c > 1
");

$dupes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$fixed = 0;

foreach ($dupes as $dupe) {
    $stmt = $pdo->prepare("
        SELECT id FROM scores
        WHERE player_id = ? AND puzzle = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$dupe['player_id'], $dupe['puzzle']]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

    array_shift($rows); // keep first
    if ($rows) {
        $in = implode(',', array_map('intval', $rows));
        $pdo->exec("DELETE FROM scores WHERE id IN ($in)");
        echo "🧹 Removed ".count($rows)." duplicate(s) for player_id {$dupe['player_id']} puzzle {$dupe['puzzle']}\n";
        $fixed += count($rows);
    }
}

echo "✅ Done. $fixed duplicates removed.\n";
