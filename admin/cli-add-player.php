<?php
// filename: admin/cli-add-player.php
declare(strict_types=1);

// Always run from CLI
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

require_once __DIR__ . '/../views/env.php';
env_load();

$dbPath = $_ENV['DB_PATH'] ?? '/var/www/webroot/ROOT/data/scores.db';
if (!is_file($dbPath)) {
    fwrite(STDERR, "DB not found at $dbPath\n");
    exit(2);
}

$pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// ensure table exists (harmless if it already does)
$pdo->exec('CREATE TABLE IF NOT EXISTS players (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL UNIQUE,
  name  TEXT NOT NULL
)');

$email = strtolower(trim($argv[1] ?? ''));
$name  = trim(implode(' ', array_slice($argv, 2))); // allow spaces in name

if (!$email || !$name) {
    echo "Usage: php admin/cli-add-player.php EMAIL \"NAME\"\n";
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Invalid email: $email\n");
    exit(1);
}

// check if exists
$sel = $pdo->prepare('SELECT id, name FROM players WHERE lower(email)=lower(?)');
$sel->execute([$email]);
$existing = $sel->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    if ($existing['name'] === $name) {
        echo "ℹ️  Player already exists: {$existing['name']} <$email>\n";
        exit(0);
    }
    // update name if different
    $upd = $pdo->prepare('UPDATE players SET name=? WHERE id=?');
    $upd->execute([$name, (int)$existing['id']]);
    echo "🔁 Name updated: $name <$email>\n";
    exit(0);
}

// insert new
$ins = $pdo->prepare('INSERT INTO players (email, name) VALUES (?, ?)');
$ins->execute([$email, $name]);
echo "✅ Player added: $name <$email>\n";
