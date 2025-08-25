<?php
declare(strict_types=1);
require_once __DIR__ . '/env.php';
env_load();

$dbPath = $_ENV['DB_PATH'] ?? 'data/scores.db';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get today’s puzzle number (we assume latest puzzle used today)
$stmt = $pdo->prepare("SELECT puzzle FROM scores WHERE DATE(created_at) = DATE('now') ORDER BY puzzle DESC LIMIT 1");
$stmt->execute();
$puzzle = $stmt->fetchColumn();

$title = "Today's Leaderboard";
require __DIR__ . '/partials/header.php';
?>

<h1>Leaderboard – Wordle</h1>

<?php if (!$puzzle): ?>
    <p>No scores yet today. Be the first to post yours!</p>
<?php else: ?>

    <?php
// Fetch all scores for that puzzle
    $stmt = $pdo->prepare("
    SELECT s.guesses, s.created_at, p.name
    FROM scores s
    JOIN players p ON s.player_id = p.id
    WHERE s.puzzle = ?
    ORDER BY s.guesses ASC, s.created_at ASC
");
    $stmt->execute([$puzzle]);
    $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <table>
        <thead>
        <tr>
            <th>Rank</th>
            <th>Player</th>
            <th>Guesses</th>
            <th>Submitted</th>
        </tr>
        </thead>
        <tbody>
        <?php $rank = 1; foreach ($scores as $row): ?>
            <tr>
                <td><?= $rank++ ?></td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= $row['guesses'] === 7 ? 'X/6' : $row['guesses'] . '/6' ?></td>
                <td><?= date('H:i', strtotime($row['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php endif; ?>


<?php
// Rolling leaderboard (last 30 days)
$windowDays = 30;
$since = (new DateTimeImmutable())->modify("-{$windowDays} days")->format('Y-m-d 00:00:00');

// Get all players
$players = $pdo->query("SELECT id, name FROM players ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$rolling = [];

foreach ($players as $player) {
    $stmt = $pdo->prepare("
        SELECT guesses FROM scores
        WHERE player_id = ? AND created_at >= ?
    ");
    $stmt->execute([$player['id'], $since]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $submissions = count($rows);
    $points = array_map(fn($g) => max(0, 7 - (int)$g), $rows);
    $avg = $submissions ? array_sum($points) / $submissions : 0;
    $rate = min(1.0, $submissions / $windowDays);
    $rollingScore = round($avg * $rate, 2);

    $rolling[] = [
        'name' => $player['name'],
        'subs' => $submissions,
        'avg' => round($avg, 2),
        'rate' => round($rate * 100),
        'score' => $rollingScore,
    ];
}

usort($rolling, fn($a, $b) => $b['score'] <=> $a['score']);
?>

<h2 style="margin-top:3em;">Rolling Leaderboard (Last <?= $windowDays ?> Days)</h2>
<table>
    <thead>
    <tr>
        <th>Rank</th>
        <th>Player</th>
        <th>Avg Points</th>
        <th>Participation</th>
        <th>Submissions</th>
        <th>Rolling Score</th>
    </tr>
    </thead>
    <tbody>
    <?php $rank = 1; foreach ($rolling as $r): ?>
        <tr>
            <td><?= $rank++ ?></td>
            <td><?= htmlspecialchars($r['name']) ?></td>
            <td><?= $r['avg'] ?></td>
            <td><?= $r['rate'] ?>%</td>
            <td><?= $r['subs'] ?></td>
            <td><strong><?= $r['score'] ?></strong></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/partials/footer.php'; ?>
