<?php
declare(strict_types=1);
require_once __DIR__ . '/../views/env.php';
env_load();

$pdo = new PDO('sqlite:' . $_ENV['DB_PATH']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Add or update players
$players = json_decode(file_get_contents(__DIR__ . '/../../players.json'), true);

foreach ($players as $info) {
    $name = $info['name'];
    $email = $info['email'];

    $stmt = $pdo->prepare("INSERT OR IGNORE INTO players (name, email) VALUES (?, ?)");
    $stmt->execute([$name, $email]);
    echo "✅ Added or skipped $name <$email>\n";
}
