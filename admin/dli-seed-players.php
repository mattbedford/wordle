<?php
declare(strict_types=1);
require_once __DIR__ . '/../env.php';
env_load();

$pdo = new PDO('sqlite:' . $_ENV['DB_PATH']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Add or update players
$players = [
    ['Alice', 'alice@example.com'],
    ['Bob', 'bob@example.com'],
    ['Charlie', 'charlie@example.com'],
];

foreach ($players as [$name, $email]) {
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO players (name, email) VALUES (?, ?)");
    $stmt->execute([$name, $email]);
    echo "✅ Added or skipped $name <$email>\n";
}
