<?php
header('Content-Type: text/html; charset=utf-8');
require __DIR__ . '/../config.php';
require __DIR__ . '/auth.php';
requireAdminLogin();

$userId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $pdo->prepare('SELECT id, username, created_at FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, device_trip_id, date, distance_km, duration_ms, avg_speed_kmh, created_at
    FROM trips WHERE user_id = ? ORDER BY date DESC');
$stmt->execute([$userId]);
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Riwayat <?= htmlspecialchars($user['username']) ?> — Admin</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="topbar">
    <div class="brand"><span class="brand-dot"></span> GPS TRIP MONITOR — ADMIN</div>
    <a href="logout.php" class="btn btn-ghost">Keluar</a>
</div>

<div class="container">
    <a href="index.php" class="back-link">← Kembali ke daftar user</a>
    <h1>Riwayat perjalanan: <?= htmlspecialchars($user['username']) ?></h1>
    <p style="color:var(--text-dim); margin-top:-10px;">Daftar sejak <?= formatDateAdmin($user['created_at']) ?> · <?= count($trips) ?> perjalanan tercatat</p>

    <?php if (!$trips): ?>
        <div class="empty-state">User ini belum punya riwayat perjalanan.</div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Jarak</th>
                <th>Durasi</th>
                <th>Kec. Rata-rata</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($trips as $t): ?>
            <tr>
                <td><?= formatDateAdmin($t['date']) ?></td>
                <td><?= number_format((float) $t['distance_km'], 2) ?> km</td>
                <td><?= formatDurationAdmin((int) $t['duration_ms']) ?></td>
                <td><?= number_format((float) $t['avg_speed_kmh'], 1) ?> km/j</td>
                <td><a href="trip.php?id=<?= (int) $t['id'] ?>" class="btn btn-ghost">Lihat Rute</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
</body>
</html>
