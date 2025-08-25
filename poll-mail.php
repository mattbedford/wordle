<?php
declare(strict_types=1);
require_once __DIR__ . '/views/env.php';
env_load();

// --- Config ---
$maildir = rtrim($_ENV['MAILDIR_PATH'] ?? '/var/lib/wordle/Maildir', '/');
$dbPath  = $_ENV['DB_PATH'] ?? 'db/scores.db';

// --- DB ---
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// raw log (handy while testing)
$pdo->exec('CREATE TABLE IF NOT EXISTS scores_raw (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  sender TEXT, subject TEXT, body TEXT, sent_at TEXT, created_at TEXT
)');

// ensure idempotency if not already present
$pdo->exec('CREATE TABLE IF NOT EXISTS scores (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  player_id INTEGER NOT NULL,
  puzzle INTEGER NOT NULL,
  guesses INTEGER NOT NULL,
  created_at TEXT NOT NULL,
  UNIQUE(player_id, puzzle)
)');

// --- Your existing parser (unchanged) ---
function parse_wordle_line(string $text): ?array {
    $lines = preg_split('/\r\n|\r|\n/', trim($text));
    foreach ($lines as $line) {
        // remove thousands separators like 1,234
        $line = preg_replace('/(\d),(\d{3})/', '$1$2', $line);
        // Match: Wordle 1527 3/6  or  X/6
        if (preg_match('/Wordle\s+(\d+)\s+([1-6Xx])\/6/', $line, $m)) {
            $puzzle  = (int)$m[1];
            $guesses = strtoupper($m[2]) === 'X' ? 7 : (int)$m[2];
            return [$puzzle, $guesses];
        }
    }
    return null;
}

// --- Minimal RFC822 split: headers + body (no external deps) ---
function parse_headers_and_body(string $raw): array {
    // split on first blank line
    if (preg_match("/\r?\n\r?\n/", $raw, $m, PREG_OFFSET_CAPTURE)) {
        $pos  = $m[0][1];
        $hdrs = substr($raw, 0, $pos);
        $body = substr($raw, $pos + strlen($m[0][0]));
    } else {
        $hdrs = $raw; $body = '';
    }
    // unfold header continuations
    $hdrs = preg_replace("/\r?\n[ \t]+/", ' ', $hdrs);
    $headers = [];
    foreach (preg_split("/\r?\n/", $hdrs) as $line) {
        if (strpos($line, ':') === false) continue;
        [$k, $v] = explode(':', $line, 2);
        $headers[strtolower(trim($k))] = trim($v);
    }
    // subject decode
    $subject = $headers['subject'] ?? '';
    if (function_exists('iconv_mime_decode')) {
        $dec = @iconv_mime_decode($subject, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        if ($dec !== false) $subject = $dec;
    } elseif (function_exists('mb_decode_mimeheader')) {
        $subject = @mb_decode_mimeheader($subject);
    }
    // extract plain email from From:
    $from = strtolower($headers['from'] ?? '');
    if (preg_match('/<([^>]+)>/', $from, $m2)) {
        $from = strtolower($m2[1]);
    } else {
        $from = strtolower(trim($from));
    }
    $date = $headers['date'] ?? gmdate('r');

    // if body is empty and there’s HTML only, strip tags as fallback
    if ($body === '' && isset($headers['content-type']) && stripos($headers['content-type'], 'html') !== false) {
        $body = trim(strip_tags($raw)); // crude but fine for Wordle lines
    }

    return [$from, $subject, $body, $date];
}

// --- Process Maildir/new ---
$newDir = $maildir . '/new';
$curDir = $maildir . '/cur';

if (!is_dir($newDir)) {
    fwrite(STDERR, "No Maildir at $newDir\n");
    exit(1);
}

$files = glob($newDir . '/*') ?: [];
if (!$files) {
    echo "📭 No new messages.\n";
    exit(0);
}

foreach ($files as $path) {
    if (!is_file($path)) continue;

    $raw = file_get_contents($path) ?: '';
    [$fromEmail, $subject, $body, $sentAt] = parse_headers_and_body($raw);

    echo "📧 From: $fromEmail\n";

    // log raw for debugging
    $stmt = $pdo->prepare('INSERT INTO scores_raw (sender, subject, body, sent_at, created_at)
                           VALUES (:s, :subj, :b, :sent, :now)');
    $stmt->execute([
        ':s' => $fromEmail,
        ':subj' => $subject,
        ':b' => $body,
        ':sent' => $sentAt,
        ':now' => gmdate('c'),
    ]);

    // score from body or subject
    $score = parse_wordle_line($body) ?? parse_wordle_line($subject);
    if (!$score) {
        echo "❌ No score found.\n---\n";
        @rename($path, $curDir . '/' . basename($path) . ':2,S');
        continue;
    }
    [$puzzle, $guesses] = $score;

    // lookup player
    $stmt = $pdo->prepare('SELECT id, name FROM players WHERE email = ?');
    $stmt->execute([$fromEmail]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$player) {
        echo "⚠️  Unknown player: $fromEmail (ignored)\n---\n";
        @rename($path, $curDir . '/' . basename($path) . ':2,S');
        continue;
    }

    // insert (idempotent via UNIQUE(player_id,puzzle))
    $stmt = $pdo->prepare('
        INSERT OR IGNORE INTO scores (player_id, puzzle, guesses, created_at)
        VALUES (?, ?, ?, datetime("now"))
    ');
    $stmt->execute([(int)$player['id'], (int)$puzzle, (int)$guesses]);

    echo "✅ Score saved for {$player['name']}: Puzzle $puzzle → " .
        ($guesses === 7 ? 'X/6' : "$guesses/6") . "\n---\n";

    // mark as seen locally
    @rename($path, $curDir . '/' . basename($path) . ':2,S');
}
