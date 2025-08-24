<?php

declare(strict_types=1);
require_once __DIR__ . '/env.php';
env_load();

$dbPath = $_ENV['DB_PATH'] ?? 'db/scores.db';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create tables
$pdo->exec('
CREATE TABLE IF NOT EXISTS players (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL
)');

$pdo->exec('
CREATE TABLE IF NOT EXISTS scores (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    player_id INTEGER NOT NULL,
    puzzle INTEGER NOT NULL,
    guesses INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(player_id, puzzle),
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
)');

echo "✅ SQLite schema created at $dbPath\n";
