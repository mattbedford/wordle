<?php
// env.php - basic .env loader (no dependencies)
function env_load(string $path = __DIR__ . '/../../.env'): void {
    if (!file_exists($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$key, $val] = array_pad(explode('=', $line, 2), 2, '');
        $val = trim($val, "\"'");
        $_ENV[$key] = $val;
        putenv("$key=$val");
    }
}