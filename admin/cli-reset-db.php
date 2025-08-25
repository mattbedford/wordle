<?php
declare(strict_types=1);
require_once __DIR__ . '/../views/env.php';
env_load();

$dbPath = $_ENV['DB_PATH'];

if (!file_exists($dbPath)) {
    echo "No existing database to delete.\n";
} else {
    unlink($dbPath);
    echo "🗑️ Deleted existing DB at $dbPath\n";
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("
    CREATE TABLE IF NOT EXISTS players (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE
    );
    CREATE TABLE IF NOT EXISTS scores (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        player_id INTEGER NOT NULL,
        puzzle INTEGER NOT NULL,
        guesses INTEGER NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (player_id) REFERENCES players(id)
    );
");

echo "✅ New DB created and tables ready\n";
