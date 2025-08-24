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
    $subject = imap_utf8($header->subject ?? '(no subject)');

    echo "From: $from_email\n";
    echo "Subject: $subject\n";
    echo "---\n";

    imap_setflag_full($mbox, $msgno, "\\Seen"); // Mark as read
}

imap_close($mbox);
