<?php
header('Content-Type: text/html; charset=utf-8');
require __DIR__ . '/../config.php'; // menyediakan $pdo (MySQL)
require __DIR__ . '/auth.php';
requireAdminLogin();

$totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalTrips = (int) $pdo->query('SELECT COUNT(*) FROM trips')->fetchColumn();
$totalDistance = (float) $pdo->query('SELECT COALESCE(SUM(distance_km), 0) FROM trips')->fetchColumn();

$users = $pdo->query('
    SELECT u.id, u.username, u.created_at,
        (SELECT COUNT(*) FROM trips t WHERE t.user_id = u.id) AS trip_count,
        (SELECT COALESCE(SUM(distance_km), 0) FROM trips t WHERE t.user_id = u.id) AS total_km,
        (SELECT MAX(date) FROM trips t WHERE t.user_id = u.id) AS last_trip_date,
        (SELECT MAX(created_at) FROM tokens tok WHERE tok.user_id = u.id) AS last_login,
        (SELECT COUNT(*) FROM tokens tok WHERE tok.user_id = u.id) AS active_sessions
    FROM users u
    ORDER BY u.created_at DESC
')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Admin — GPS Trip Monitor</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="topbar">
    <div class="brand"><span class="brand-dot"></span> GPS TRIP MONITOR — ADMIN</div>
    <div style="display:flex; gap:10px;">
        <a href="monitoring.php" class="btn btn-ghost">📍 Monitoring Hari Ini</a>
        <a href="logout.php" class="btn btn-ghost">Keluar</a>
    </div>
</div>

<div class="container">
    <h1>Ringkasan</h1>
    <div class="stat-grid">
        <div class="stat-card"><div class="label">Total User</div><div class="value"><?= $totalUsers ?></div></div>
        <div class="stat-card"><div class="label">Total Perjalanan</div><div class="value"><?= $totalTrips ?></div></div>
        <div class="stat-card"><div class="label">Total Jarak</div><div class="value"><?= number_format($totalDistance, 1) ?> <span style="font-size:14px">km</span></div></div>
    </div>

    <h2>Semua User</h2>
    <?php if (!$users): ?>
        <div class="empty-state">Belum ada user yang mendaftar.</div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Username</th>
                <th>Daftar Sejak</th>
                <th>Jumlah Trip</th>
                <th>Total Jarak</th>
                <th>Trip Terakhir</th>
                <th>Login Terakhir</th>
                <th>Sesi Aktif</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= formatDateAdmin($u['created_at']) ?></td>
                <td><?= (int) $u['trip_count'] ?></td>
                <td><?= number_format((float) $u['total_km'], 2) ?> km</td>
                <td><?= $u['last_trip_date'] ? formatDateAdmin($u['last_trip_date']) : '—' ?></td>
                <td><?= $u['last_login'] ? formatDateAdmin($u['last_login']) : '<span style="color:var(--text-dim)">Belum pernah</span>' ?></td>
                <td><?php if ((int) $u['active_sessions'] > 0): ?><span class="badge badge-synced"><?= (int) $u['active_sessions'] ?> aktif</span><?php else: ?><span style="color:var(--text-dim)">—</span><?php endif; ?></td>
                <td><a href="user.php?id=<?= (int) $u['id'] ?>" class="btn btn-ghost">Lihat Riwayat</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
</body>
</html>
