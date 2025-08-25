<?php
require_once __DIR__ . './../env.php';
env_load();
?>


<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title><?php echo $title ?? 'Wordle Leaderboard'; ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="favicon" href="/favicon.ico">

    <!--manifest etc-->
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#111">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="apple-touch-icon" href="/icon-192.png">

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
    <div class="site-title"><?php echo $_ENV['SITE_NAME'] ?? 'Family Wordle'; ?></div>
    <nav>
        <a href="/leaderboard">Today</a>
        <a href="/history">History</a>
        <a href="#" onclick="document.getElementById('rulesDialog').showModal(); return false;">
            <svg xmlns="http://www.w3.org/2000/svg" class="help-rules" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01"/></svg>
        </a>
        <button id="themeToggle" style="margin-left:1em;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" class="lucide lucide-moon-star-icon lucide-moon-star" viewBox="0 0 24 24"><path d="M18 5h4M20 3v4M20.985 12.486a9 9 0 1 1-9.473-9.472c.405-.022.617.46.402.803a6 6 0 0 0 8.268 8.268c.344-.215.825-.004.803.401"/></svg>
        </button>
    </nav>
</header>
<main>
