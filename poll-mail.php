<?php
declare(strict_types=1);
require_once __DIR__ . '/env.php';


env_load();

$host = $_ENV['IMAP_HOST'] ?? '';
$user = $_ENV['IMAP_USER'] ?? '';
$pass = $_ENV['IMAP_PASS'] ?? '';

if (!$host || !$user || !$pass) {
    die("Missing IMAP credentials.\n");
}

$mbox = @imap_open($host, $user, $pass);
if (!$mbox) {
    die("IMAP connection failed: " . imap_last_error() . "\n");
}

$emails = imap_search($mbox, 'UNSEEN');
if (!$emails) {
    echo "No new messages.\n";
    imap_close($mbox);
    exit;
}

foreach ($emails as $msgno) {
    $header = imap_headerinfo($mbox, $msgno);
    $from = $header->from[0];
    $from_email = strtolower(($from->mailbox ?? '') . '@' . ($from->host ?? ''));
    $subject = imap_utf8($header->subject ?? '');
    $body = imap_body($mbox, $msgno, FT_PEEK);

    echo "From: $from_email\n";

    $score = parse_wordle_line($subject) ?? parse_wordle_line($body);

    if (!$score) {
        echo "❌ No Wordle score found.\n";
    } else {
        [$puzzle, $guess] = $score;
        echo "✅ Puzzle: $puzzle, Guesses: " . ($guess === 7 ? 'X/6' : "$guess/6") . "\n";
        // We'll insert into DB later here
    }

    imap_setflag_full($mbox, (string)$msgno, "\\Seen");
    echo "---\n";
}

imap_close($mbox);


function parse_wordle_line(string $text): ?array {
    $pattern = '/Wordle\s+(\d+)\s+([1-6Xx])\/6/';
    if (preg_match($pattern, $text, $m)) {
        $puzzle = (int)$m[1];
        $guess = strtoupper($m[2]) === 'X' ? 7 : (int)$m[2];  // 7 = fail
        return [$puzzle, $guess];
    }
    return null;
}