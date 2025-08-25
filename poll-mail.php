<?php
declare(strict_types=1);
require_once __DIR__ . '/views/env.php';
env_load();

// --- Config ---
$maildir = rtrim($_ENV['MAILDIR_PATH'] ?? '/var/lib/wordle/Maildir', '/');
$dbPath  = $_ENV['DB_PATH'] ?? 'db/scores.db';

// --- DB ---
$pdo = new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec('CREATE TABLE IF NOT EXISTS scores_raw (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  sender TEXT, subject TEXT, body TEXT, sent_at TEXT, created_at TEXT
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS scores (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  player_id INTEGER NOT NULL,
  puzzle INTEGER NOT NULL,
  guesses INTEGER NOT NULL,
  created_at TEXT NOT NULL,
  UNIQUE(player_id, puzzle)
)');

// --- Your existing Wordle parser (widened for hard-mode star) ---
function parse_wordle_line(string $text): ?array {
    $text  = str_replace(["\xC2\xA0", "\xE2\x80\x8B"], ' ', $text); // NBSP, zero-width
    $lines = preg_split('/\r\n|\r|\n/', trim($text));
    foreach ($lines as $line) {
        $line = preg_replace('/(\d),(\d{3})/', '$1$2', $line); // 1,234 -> 1234
        if (preg_match('/Wordle\s+(\d+)\s+([1-6Xx])\/6(?:\*|⭐)?/i', $line, $m)) {
            $puzzle  = (int)$m[1];
            $guesses = strtoupper($m[2]) === 'X' ? 7 : (int)$m[2];
            return [$puzzle, $guesses];
        }
    }
    return null;
}

// --- Minimal MIME helpers (no extensions) ---
function parse_headers(string $hdrs): array {
    // unfold continuations
    $hdrs = preg_replace("/\r?\n[ \t]+/", ' ', $hdrs);
    $headers = [];
    foreach (preg_split("/\r?\n/", trim($hdrs)) as $line) {
        $pos = strpos($line, ':');
        if ($pos === false) continue;
        $k = strtolower(trim(substr($line, 0, $pos)));
        $v = trim(substr($line, $pos + 1));
        $headers[$k] = $v;
    }
    return $headers;
}
function header_params(string $v): array {
    $out = ['value' => strtolower(trim(strtok($v, ';')))];
    foreach (preg_split('/;\s*/', $v) as $part) {
        if (strpos($part, '=') === false) continue;
        if (preg_match('/^([a-zA-Z0-9_-]+)\s*=\s*"?([^";]+)"?$/', trim($part), $m)) {
            $out[strtolower($m[1])] = $m[2];
        }
    }
    return $out;
}
function rfc2047(string $s): string {
    if (function_exists('iconv_mime_decode')) {
        $d = @iconv_mime_decode($s, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        if ($d !== false) return $d;
    }
    if (function_exists('mb_decode_mimeheader')) {
        $d = @mb_decode_mimeheader($s);
        if ($d !== false) return $d;
    }
    return $s;
}
function decode_cte(string $body, string $cte): string {
    $cte = strtolower(trim($cte));
    if ($cte === 'base64') return base64_decode($body) ?: '';
    if ($cte === 'quoted-printable') return quoted_printable_decode($body);
    return $body; // 7bit/8bit/binary
}
function to_utf8(string $text, ?string $charset): string {
    $cs = $charset ? strtoupper($charset) : 'UTF-8';
    if ($cs === 'UTF-8') return $text;
    if (function_exists('mb_convert_encoding')) {
        return @mb_convert_encoding($text, 'UTF-8', $cs) ?: $text;
    }
    if (function_exists('iconv')) {
        $conv = @iconv($cs, 'UTF-8//IGNORE', $text);
        return $conv !== false ? $conv : $text;
    }
    return $text;
}
function split_multipart(string $body, string $boundary): array {
    $b = preg_quote($boundary, '/');
    // Ensure boundary lines are detectable even if body doesn't start with a newline
    $chunks = preg_split('/\R--' . $b . '(?:--)?\R/', "\n" . $body);
    // Remove preamble (first) and epilogue (last), keep middles
    if (count($chunks) > 2) {
        array_shift($chunks);
        array_pop($chunks);
    } else {
        $chunks = [];
    }
    return $chunks;
}
function extract_text_plain(string $raw): array {
    // Split headers/body
    if (preg_match("/\r?\n\r?\n/", $raw, $m, PREG_OFFSET_CAPTURE)) {
        $pos  = $m[0][1];
        $hdrs = substr($raw, 0, $pos);
        $body = substr($raw, $pos + strlen($m[0][0]));
    } else { $hdrs = $raw; $body = ''; }

    $H = parse_headers($hdrs);
    $from = strtolower($H['from'] ?? '');
    if (preg_match('/<([^>]+)>/', $from, $m2)) $from = strtolower($m2[1]); else $from = strtolower(trim($from));
    $subject = rfc2047($H['subject'] ?? '');
    $date    = $H['date'] ?? gmdate('r');

    $ct = header_params($H['content-type'] ?? 'text/plain; charset=UTF-8');
    $cte = $H['content-transfer-encoding'] ?? '7bit';

    // Multipart?
    if (strpos($ct['value'], 'multipart/') === 0 && !empty($ct['boundary'])) {
        foreach (split_multipart($body, $ct['boundary']) as $part) {
            // Part headers/body
            if (preg_match("/\r?\n\r?\n/", $part, $m, PREG_OFFSET_CAPTURE)) {
                $ppos = $m[0][1];
                $ph   = substr($part, 0, $ppos);
                $pb   = substr($part, $ppos + strlen($m[0][0]));
            } else { $ph = $part; $pb = ''; }

            $PH  = parse_headers($ph);
            $pct = header_params($PH['content-type'] ?? 'text/plain; charset=UTF-8');
            $pcte= $PH['content-transfer-encoding'] ?? '7bit';
            $disp= strtolower($PH['content-disposition'] ?? 'inline');

            if ($pct['value'] === 'text/plain' && strpos($disp, 'attachment') === false) {
                $decoded = decode_cte($pb, $pcte);
                $decoded = to_utf8($decoded, $pct['charset'] ?? null);
                return [$from, $subject, $decoded, $date];
            }

            // Nested multipart/alternative inside multipart/mixed
            if (strpos($pct['value'], 'multipart/') === 0 && !empty($pct['boundary'])) {
                foreach (split_multipart($pb, $pct['boundary']) as $sub) {
                    if (preg_match("/\r?\n\r?\n/", $sub, $m3, PREG_OFFSET_CAPTURE)) {
                        $spos = $m3[0][1];
                        $sh   = substr($sub, 0, $spos);
                        $sb   = substr($sub, $spos + strlen($m3[0][0]));
                    } else { $sh = $sub; $sb = ''; }
                    $SH   = parse_headers($sh);
                    $sct  = header_params($SH['content-type'] ?? 'text/plain; charset=UTF-8');
                    $scte = $SH['content-transfer-encoding'] ?? '7bit';
                    $sdisp= strtolower($SH['content-disposition'] ?? 'inline');
                    if ($sct['value'] === 'text/plain' && strpos($sdisp, 'attachment') === false) {
                        $decoded = decode_cte($sb, $scte);
                        $decoded = to_utf8($decoded, $sct['charset'] ?? null);
                        return [$from, $subject, $decoded, $date];
                    }
                }
            }
        }
        // No text/plain found; fallback: strip HTML if present
        return [$from, $subject, trim(strip_tags($body)), $date];
    }

    // Single-part
    $decoded = decode_cte($body, $cte);
    $decoded = to_utf8($decoded, $ct['charset'] ?? null);
    return [$from, $subject, $decoded, $date];
}

// --- Process Maildir/new ---
$newDir = $maildir . '/new';
$curDir = $maildir . '/cur';
if (!is_dir($newDir)) { fwrite(STDERR, "No Maildir at $newDir\n"); exit(1); }

$files = glob($newDir . '/*') ?: [];
if (!$files) { echo "📭 No new messages.\n"; exit(0); }

foreach ($files as $path) {
    if (!is_file($path)) continue;

    $raw = file_get_contents($path) ?: '';
    [$fromEmail, $subject, $body, $sentAt] = extract_text_plain($raw);

    echo "📧 From: $fromEmail\n";

    // Log raw/plain snapshot
    $stmt = $pdo->prepare('INSERT INTO scores_raw (sender, subject, body, sent_at, created_at)
                           VALUES (:s, :subj, :b, :sent, :now)');
    $stmt->execute([
        ':s' => $fromEmail, ':subj' => $subject, ':b' => $body,
        ':sent' => $sentAt, ':now' => gmdate('c'),
    ]);

    $score = parse_wordle_line($body) ?? parse_wordle_line($subject);
    if (!$score) {
        echo "❌ No score found (subject: $subject)\n---\n";
        @rename($path, $curDir . '/' . basename($path) . ':2,S');
        continue;
    }
    [$puzzle, $guesses] = $score;

    // Match to player
    $stmt = $pdo->prepare('SELECT id, name FROM players WHERE email = ?');
    $stmt->execute([$fromEmail]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$player) {
        echo "⚠️  Unknown player: $fromEmail (ignored)\n---\n";
        @rename($path, $curDir . '/' . basename($path) . ':2,S');
        continue;
    }

    // Insert (idempotent)
    $stmt = $pdo->prepare('
        INSERT OR IGNORE INTO scores (player_id, puzzle, guesses, created_at)
        VALUES (?, ?, ?, datetime("now"))
    ');
    $stmt->execute([(int)$player['id'], (int)$puzzle, (int)$guesses]);

    echo "✅ Score saved for {$player['name']}: Puzzle $puzzle → " .
        ($guesses === 7 ? 'X/6' : "$guesses/6") . "\n---\n";

    @rename($path, $curDir . '/' . basename($path) . ':2,S');
}
