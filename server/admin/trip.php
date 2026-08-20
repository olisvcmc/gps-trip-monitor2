<?php
header('Content-Type: text/html; charset=utf-8');
require __DIR__ . '/../config.php';
require __DIR__ . '/auth.php';
requireAdminLogin();

$tripId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $pdo->prepare('SELECT t.*, u.username, u.id AS user_id
    FROM trips t JOIN users u ON u.id = t.user_id WHERE t.id = ?');
$stmt->execute([$tripId]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    header('Location: index.php');
    exit;
}

$points = json_decode($trip['points_json'], true) ?: [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detail Trip — Admin</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="topbar">
    <div class="brand"><span class="brand-dot"></span> GPS TRIP MONITOR — ADMIN</div>
    <a href="logout.php" class="btn btn-ghost">Keluar</a>
</div>

<div class="container">
    <a href="user.php?id=<?= (int) $trip['user_id'] ?>" class="back-link">← Kembali ke riwayat <?= htmlspecialchars($trip['username']) ?></a>
    <h1>Detail Perjalanan</h1>

    <div class="stat-grid">
        <div class="stat-card"><div class="label">Jarak</div><div class="value"><?= number_format((float) $trip['distance_km'], 2) ?> <span style="font-size:14px">km</span></div></div>
        <div class="stat-card"><div class="label">Durasi</div><div class="value" style="font-size:20px"><?= formatDurationAdmin((int) $trip['duration_ms']) ?></div></div>
        <div class="stat-card"><div class="label">Kec. Rata-rata</div><div class="value"><?= number_format((float) $trip['avg_speed_kmh'], 1) ?> <span style="font-size:14px">km/j</span></div></div>
        <div class="stat-card"><div class="label">Titik GPS</div><div class="value"><?= count($points) ?></div></div>
    </div>

    <p style="color:var(--text-dim)">Tanggal: <?= formatDateAdmin($trip['date']) ?></p>

    <div id="tripMap"></div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    var points = <?= json_encode($points) ?>;
    var map = L.map('tripMap', { zoomControl: true, attributionControl: false });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

    if (points.length) {
        var line = L.polyline(points, { color: '#00d9c0', weight: 4 }).addTo(map);
        L.circleMarker(points[0], { radius: 6, color: '#7b61ff', fillOpacity: 1 }).addTo(map).bindPopup('Titik awal');
        L.circleMarker(points[points.length - 1], { radius: 6, color: '#00d9c0', fillOpacity: 1 }).addTo(map).bindPopup('Titik akhir');
        map.fitBounds(line.getBounds(), { padding: [30, 30] });
    } else {
        map.setView([-6.2, 106.816666], 5);
    }
</script>
</body>
</html>
