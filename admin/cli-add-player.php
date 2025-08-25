<?php
declare(strict_types=1);
require_once __DIR__ . '/../views/env.php';
env_load();

$dbPath = $_ENV['DB_PATH'] ?? 'db/scores.db';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$email = strtolower(trim($argv[1] ?? ''));
$name  = trim($argv[2] ?? '');

if (!$email || !$name) {
    echo "Usage: php admin/cli-add-player.php EMAIL NAME\n";
    exit(1);
}

$stmt = $pdo->prepare('INSERT OR IGNORE INTO players (email, name) VALUES (?, ?)');
$stmt->execute([$email, $name]);

echo "✅ Player added: $name <$email>\n";
