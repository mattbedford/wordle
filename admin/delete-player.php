<?php
// filename: admin/delete-player.php
declare(strict_types=1);

// CLI only
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

require_once __DIR__ . '/../views/env.php';
env_load();

$dbPath = $_ENV['DB_PATH'] ?? '/var/www/webroot/ROOT/data/scores.db';

// --- args ---
$args = $argv;
array_shift($args);
$email = null; $id = null; $purgeRaw = false; $dry = false;

while ($args) {
    $a = array_shift($args);
    if ($a === '--email') { $email = strtolower(trim((string)array_shift($args))); continue; }
    if ($a === '--id')    { $id = (int)(array_shift($args)); continue; }
    if ($a === '--purge-raw') { $purgeRaw = true; continue; }
    if ($a === '--dry-run')   { $dry = true; continue; }
    if (filter_var($a, FILTER_VALIDATE_EMAIL)) { $email = strtolower($a); continue; }
    fwrite(STDERR, "Unknown arg: $a\n"); exit(1);
}
if (!$email && !$id) {
    echo "Usage:\n";
    echo "  php admin/delete-player.php --email [email protected] [--purge-raw] [--dry-run]\n";
    echo "  php admin/delete-player.php --id 123 [--purge-raw] [--dry-run]\n";
    exit(1);
}
if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Invalid email: $email\n"); exit(1);
}

if (!is_file($dbPath)) {
    fwrite(STDERR, "DB not found at $dbPath\n"); exit(2);
}

// safety backup
@copy($dbPath, $dbPath . '.' . date('Ymd-His') . '.bak');

$pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec('PRAGMA foreign_keys = ON');

if ($email) {
    $stmt = $pdo->prepare('SELECT id, email, name FROM players WHERE lower(email)=lower(?)');
    $stmt->execute([$email]);
} else {
    $stmt = $pdo->prepare('SELECT id, email, name FROM players WHERE id=?');
    $stmt->execute([$id]);
}
$player = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$player) {
    fwrite(STDERR, "No matching player found.\n"); exit(3);
}

$pid = (int)$player['id'];
$who = "{$player['name']} <{$player['email']}>";

echo ($dry ? "[DRY RUN] " : "") . "Removing player: $who (id=$pid)\n";

// counts first (for reporting)
$countScores = (int)$pdo->query("SELECT COUNT(*) FROM scores WHERE player_id={$pid}")->fetchColumn();
$countRaw    = 0;
if ($purgeRaw && $player['email']) {
    $q = $pdo->prepare("SELECT COUNT(*) FROM scores_raw WHERE lower(sender)=lower(?)");
    $q->execute([$player['email']]);
    $countRaw = (int)$q->fetchColumn();
}

if ($dry) {
    echo "Would delete: $countScores scores" . ($purgeRaw ? ", $countRaw raw rows" : "") . " and the player row.\n";
    exit(0);
}

$pdo->beginTransaction();
try {
    // delete scores first to avoid orphan logic if FK not set
    $delScores = $pdo->prepare('DELETE FROM scores WHERE player_id=?');
    $delScores->execute([$pid]);

    if ($purgeRaw && $player['email']) {
        $delRaw = $pdo->prepare('DELETE FROM scores_raw WHERE lower(sender)=lower(?)');
        $delRaw->execute([$player['email']]);
    }

    $delPlayer = $pdo->prepare('DELETE FROM players WHERE id=?');
    $delPlayer->execute([$pid]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n"); exit(1);
}

echo "Deleted $countScores scores";
if ($purgeRaw) echo ", $countRaw raw";
echo ". Player removed.\n";
