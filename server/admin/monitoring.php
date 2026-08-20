<?php
header('Content-Type: text/html; charset=utf-8');
require __DIR__ . '/../config.php';
require __DIR__ . '/auth.php';
requireAdminLogin();

// Tentukan batas "hari ini" berdasarkan zona waktu Indonesia (WIB), lalu konversi
// ke rentang UTC untuk dibandingkan dengan kolom `date` yang disimpan app dalam UTC
// (hasil toISOString() di JS). Ini supaya "hari ini" sesuai jam Indonesia,
// bukan ikut jam UTC server yang bisa beda ~7 jam.
$tz = new DateTimeZone('Asia/Jakarta');
$utc = new DateTimeZone('UTC');
$todayStart = new DateTime('today', $tz);
$todayEnd = new DateTime('tomorrow', $tz);
$rangeStart = (clone $todayStart)->setTimezone($utc)->format('Y-m-d\TH:i:s.000\Z');
$rangeEnd = (clone $todayEnd)->setTimezone($utc)->format('Y-m-d\TH:i:s.000\Z');

$stmt = $pdo->prepare('
    SELECT t.id, t.user_id, u.username, t.date, t.distance_km, t.duration_ms, t.points_json
    FROM trips t
    JOIN users u ON u.id = t.user_id
    WHERE t.date >= ? AND t.date < ?
    ORDER BY t.date DESC
');
$stmt->execute([$rangeStart, $rangeEnd]);
$todayTrips = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ringkas per user: jumlah trip & total jarak hari ini
$byUser = [];
foreach ($todayTrips as $t) {
    $uid = (int) $t['user_id'];
    if (!isset($byUser[$uid])) {
        $byUser[$uid] = ['username' => $t['username'], 'trip_count' => 0, 'total_km' => 0, 'last_time' => $t['date']];
    }
    $byUser[$uid]['trip_count']++;
    $byUser[$uid]['total_km'] += (float) $t['distance_km'];
    if ($t['date'] > $byUser[$uid]['last_time']) $byUser[$uid]['last_time'] = $t['date'];
}

function colorForUserId($userId)
{
    $hue = ($userId * 137) % 360; // sebaran warna golden-angle biar antar user kontras
    return "hsl($hue, 70%, 58%)";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Monitoring Hari Ini — Admin</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="style.css">
<style>
    .mon-layout { display: grid; grid-template-columns: 300px 1fr; gap: 20px; align-items: start; }
    @media (max-width: 800px) { .mon-layout { grid-template-columns: 1fr; } }
    .mon-side { background: var(--panel); border: 1px solid var(--panel-border); border-radius: 16px; padding: 16px; }
    .mon-user { display: flex; align-items: center; gap: 10px; padding: 10px 6px; border-bottom: 1px solid var(--panel-border); }
    .mon-user:last-child { border-bottom: none; }
    .mon-swatch { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
    .mon-user-info { display: flex; flex-direction: column; gap: 2px; }
    .mon-user-name { font-weight: 600; font-size: 14px; }
    .mon-user-stats { font-size: 12px; color: var(--text-dim); }
    #monMap { width: 100%; height: 560px; border-radius: 16px; border: 1px solid var(--panel-border); }
    .leaflet-tile-pane { filter: invert(1) hue-rotate(180deg) brightness(0.95) contrast(0.9); }
</style>
</head>
<body>
<div class="topbar">
    <div class="brand"><span class="brand-dot"></span> GPS TRIP MONITOR — ADMIN</div>
    <a href="logout.php" class="btn btn-ghost">Keluar</a>
</div>

<div class="container">
    <a href="index.php" class="back-link">← Kembali ke Dashboard</a>
    <h1>Monitoring Hari Ini — <?= (new DateTime('now', $tz))->format('d F Y') ?></h1>

    <div class="stat-grid">
        <div class="stat-card"><div class="label">User Aktif Hari Ini</div><div class="value"><?= count($byUser) ?></div></div>
        <div class="stat-card"><div class="label">Total Trip Hari Ini</div><div class="value"><?= count($todayTrips) ?></div></div>
        <div class="stat-card"><div class="label">Total Jarak Hari Ini</div><div class="value"><?= number_format(array_sum(array_column($todayTrips, 'distance_km')), 1) ?> <span style="font-size:14px">km</span></div></div>
    </div>

    <?php if (!$todayTrips): ?>
        <div class="empty-state">Belum ada aktivitas perjalanan hari ini.</div>
    <?php else: ?>
    <div class="mon-layout">
        <div class="mon-side">
            <h2 style="margin-top:0">User Aktif</h2>
            <?php foreach ($byUser as $uid => $info): ?>
                <div class="mon-user">
                    <span class="mon-swatch" style="background:<?= colorForUserId($uid) ?>"></span>
                    <div class="mon-user-info">
                        <span class="mon-user-name"><?= htmlspecialchars($info['username']) ?></span>
                        <span class="mon-user-stats"><?= $info['trip_count'] ?> trip · <?= number_format($info['total_km'], 2) ?> km · terakhir <?= formatDateAdmin($info['last_time']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div id="monMap"></div>
    </div>
    <?php endif; ?>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
<?php if ($todayTrips): ?>
    var map = L.map('monMap', { zoomControl: true, attributionControl: false });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

    var allBounds = [];
    var trips = <?= json_encode(array_map(function ($t) {
        return [
            'user_id' => (int) $t['user_id'],
            'username' => $t['username'],
            'points' => json_decode($t['points_json'], true) ?: []
        ];
    }, $todayTrips)) ?>;

    trips.forEach(function (trip) {
        if (!trip.points.length) return;
        var hue = (trip.user_id * 137) % 360;
        var color = 'hsl(' + hue + ',70%,58%)';
        var line = L.polyline(trip.points, { color: color, weight: 4, opacity: 0.85 }).addTo(map);
        line.bindPopup(trip.username);
        L.circleMarker(trip.points[trip.points.length - 1], { radius: 5, color: color, fillOpacity: 1 })
            .addTo(map).bindPopup(trip.username + ' (posisi terakhir)');
        allBounds = allBounds.concat(trip.points);
    });

    if (allBounds.length) {
        map.fitBounds(allBounds, { padding: [30, 30] });
    } else {
        map.setView([-6.2, 106.816666], 5);
    }
<?php endif; ?>
</script>
</body>
</html>
