<?php
// filename: admin/seed-players.php
declare(strict_types=1);

// CLI only
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

require_once __DIR__ . '/../views/env.php';
env_load();

$dbPath = $_ENV['DB_PATH'] ?? '/var/www/webroot/ROOT/data/scores.db';
$jsonPath = $argv[1] ?? __DIR__ . '/players.json';

if (!is_file($jsonPath)) {
    fwrite(STDERR, "players.json not found: $jsonPath\n");
    exit(1);
}

$raw = file_get_contents($jsonPath);
$data = json_decode($raw, true);
if (!is_array($data)) {
    fwrite(STDERR, "Invalid JSON in $jsonPath\n");
    exit(1);
}

$pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// Ensure table exists
$pdo->exec('CREATE TABLE IF NOT EXISTS players (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL UNIQUE,
  name  TEXT NOT NULL
)');

// UPSERT statement (SQLite 3.24+)
$upsert = $pdo->prepare(
    'INSERT INTO players (email, name) VALUES (?, ?)
   ON CONFLICT(email) DO UPDATE SET name=excluded.name'
);

$added = 0; $updated = 0; $skipped = 0;
foreach ($data as $i => $row) {
    $email = strtolower(trim($row['email'] ?? ''));
    $name  = trim((string)($row['name'] ?? ''));

    if (!$email || !$name || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $skipped++;
        fwrite(STDERR, "Skip[$i]: invalid email/name\n");
        continue;
    }

    // Detect if exists to count added vs updated (optional, for reporting)
    $sel = $pdo->prepare('SELECT name FROM players WHERE lower(email)=lower(?)');
    $sel->execute([$email]);
    $existing = $sel->fetchColumn();

    $upsert->execute([$email, $name]);

    if ($existing === false) $added++; elseif ($existing !== $name) $updated++; else $skipped++;
}

echo "Done. added=$added, updated=$updated, unchanged=$skipped\n";
