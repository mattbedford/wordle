<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title><?= $title ?? 'Wordle Leaderboard' ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <script>
        (function() {
            const stored = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = stored || (prefersDark ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
</head>
<body>
<header>
    <div class="site-title"><?= $_ENV['SITE_NAME'] ?? 'Family Wordle' ?></div>
    <nav>
        <a href="/leaderboard">Today</a>
        <a href="/history">History</a>
        <a href="#" onclick="document.getElementById('rulesDialog').showModal(); return false;">❓ Rules</a>
        <button id="themeToggle" style="margin-left:1em;">🌓</button>
    </nav>
</header>
<main>
