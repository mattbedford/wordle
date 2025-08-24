</main>
<footer>
    <p>&copy; <?= date('Y') ?> Wordle Tracker · Made with ❤️ for friends & family</p>
</footer>

<dialog id="rulesDialog">
    <article>
        <h2>Scoring Rules</h2>
        <ul>
            <li><strong>Daily Score:</strong> Based on your result. 1/6 = 6 pts, 2/6 = 5 pts, … X/6 = 0 pts</li>
            <li><strong>Rolling Leaderboard:</strong> Shows your <em>average points</em> × <em>participation rate</em></li>
            <li><strong>Participation:</strong> Play more days, rank higher. No cherry-picking allowed 😉</li>
        </ul>
        <button onclick="document.getElementById('rulesDialog').close()">Close</button>
    </article>
</dialog>

<script>
    const btn = document.getElementById('themeToggle');
    if (btn) {
        btn.addEventListener('click', () => {
            const current = document.documentElement.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
        });
    }
</script>
</body>
</html>