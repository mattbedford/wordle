<?php
declare(strict_types=1);
require_once __DIR__ . '/env.php';
env_load();

// Connect to IMAP
$host = $_ENV['IMAP_HOST'] ?? '';
$user = $_ENV['IMAP_USER'] ?? '';
$pass = $_ENV['IMAP_PASS'] ?? '';
if (!$host || !$user || !$pass) {
    die("❌ Missing IMAP credentials.\n");
}
$mbox = @imap_open($host, $user, $pass);
if (!$mbox) {
    die("❌ IMAP connection failed: " . imap_last_error() . "\n");
}

// Connect to SQLite
$dbPath = $_ENV['DB_PATH'] ?? 'db/scores.db';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Email parser
function parse_wordle_line(string $text): ?array {
    // Normalize line breaks, just in case
    $lines = preg_split('/\r\n|\r|\n/', trim($text));
    foreach ($lines as $line) {
        // Clean up thousands separator commas
        $line = preg_replace('/(\d),(\d{3})/', '$1$2', $line);

        // Match Wordle 1527 3/6 or X/6
        if (preg_match('/Wordle\s+(\d+)\s+([1-6Xx])\/6/', $line, $m)) {
            $puzzle = (int)$m[1];
            $guesses = strtoupper($m[2]) === 'X' ? 7 : (int)$m[2];
            return [$puzzle, $guesses];
        }
    }
    return null;
}


// Process new emails
$emails = imap_search($mbox, 'UNSEEN');
if (!$emails) {
    echo "📭 No new messages.\n";
    imap_close($mbox);
    exit;
}

foreach ($emails as $msgno) {
    $header = imap_headerinfo($mbox, $msgno);
    $from = $header->from[0];
    $from_email = strtolower(($from->mailbox ?? '') . '@' . ($from->host ?? ''));
    $subject = imap_utf8($header->subject ?? '');
    $structure = imap_fetchstructure($mbox, $msgno);
    $body = '';

    if (!empty($structure->parts)) {
        foreach ($structure->parts as $i => $part) {
            $isPlainText = strtolower($part->subtype ?? '') === 'plain';
            if ($isPlainText) {
                $partBody = imap_fetchbody($mbox, $msgno, (string)($i + 1), FT_PEEK);

                if ($part->encoding == 3) { // base64
                    $body = base64_decode($partBody);
                } elseif ($part->encoding == 4) { // quoted-printable
                    $body = quoted_printable_decode($partBody);
                } else {
                    $body = $partBody;
                }

                break; // use the first text/plain part only
            }
        }
    } else {
        $body = imap_body($mbox, $msgno, FT_PEEK);
    }


    echo "📧 From: $from_email\n";

    $score = parse_wordle_line($body) ?? parse_wordle_line($subject);
    if (!$score) {
        imap_setflag_full($mbox, (string)$msgno, "\\Seen");
        echo "---\n";
        echo "❌ No score found in:\n";
        echo "--- Body ---\n$body\n";
        echo "---\n";
        continue;
    }

    if (!$score) {

    }

    [$puzzle, $guesses] = $score;

    // Match to player
    $stmt = $pdo->prepare('SELECT id, name FROM players WHERE email = ?');
    $stmt->execute([$from_email]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$player) {
        echo "⚠️  Unknown player: $from_email (ignored)\n";
        imap_setflag_full($mbox, (string)$msgno, "\\Seen");
        echo "---\n";
        continue;
    }

    // Insert score (if not already recorded)
    $stmt = $pdo->prepare('
        INSERT OR IGNORE INTO scores (player_id, puzzle, guesses, created_at)
        VALUES (?, ?, ?, datetime("now"))
    ');
    $stmt->execute([(int)$player['id'], $puzzle, $guesses]);

    echo "✅ Score saved for {$player['name']}: Puzzle $puzzle → " .
        ($guesses === 7 ? 'X/6' : "$guesses/6") . "\n";

    imap_setflag_full($mbox, (string)$msgno, "\\Seen");
    echo "---\n";
}

imap_close($mbox);
