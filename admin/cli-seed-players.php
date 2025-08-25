<?php
declare(strict_types=1);
require_once __DIR__ . '/../views/env.php';
env_load();

$pdo = new PDO('sqlite:' . $_ENV['DB_PATH']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Add or update players
$players = [
    $_ENV['PLAYER_MATT'],
    $_ENV['PLAYER_SOPHIE'],
    $_ENV['PLAYER_SARAH'],
    $_ENV['PLAYER_NICK'],
    $_ENV['PLAYER_NONNA'],
    $_ENV['PLAYER_LUKE'],
    $_ENV['PLAYER_POPPA'],
];

foreach ($players as [$name, $email]) {
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO players (name, email) VALUES (?, ?)");
    $stmt->execute([$name, $email]);
    echo "✅ Added or skipped $name <$email>\n";
}
