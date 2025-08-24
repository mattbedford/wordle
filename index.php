<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch ($path) {
    case '/':
    case '/leaderboard':
        require __DIR__ . '/views/leaderboard.php';
        break;

    case '/history':
        require __DIR__ . '/views/history.php';
        break;

    case '/api/today':
        require __DIR__ . '/api/today.php';
        break;

    case '/admin':
        require __DIR__ . '/admin/players.php';
        break;

    default:
        http_response_code(404);
        echo "404 – Not Found";
}
