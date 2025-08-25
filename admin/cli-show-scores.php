<?php
declare(strict_types=1);
require_once __DIR__ . '/../env.php';
env_load();

$pdo = new PDO('sqlite:' . $_ENV['DB_PATH']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$res = $pdo->query("
    SELECT p.name, s.puzzle, s.guesses, s.created_at
    FROM scores s
    JOIN players p ON s.player_id = p.id
    ORDER BY s.created_at DESC
    LIMIT 10
");

foreach ($res as $row) {
    printf("%s | Wordle %s | %s guesses | %s\n",
        $row['name'],
        $row['puzzle'],
        $row['guesses'],
        $row['created_at']
    );
}
