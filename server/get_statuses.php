<?php
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Metode tidak diizinkan']);
    exit;
}

requireAuth($pdo); // hanya perlu login yang valid, semua user bisa lihat status user lain

// Ambil hanya status TERBARU per user (bukan seluruh riwayat), dan hanya yang
// punya koordinat (lat/lng tidak null) supaya bisa ditampilkan sebagai marker.
// Batasi umur status 24 jam terakhir supaya peta tidak penuh status basi.
$cutoff = date('c', time() - 86400);

$stmt = $pdo->prepare("
    SELECT s.id, s.user_id, u.username, s.lat, s.lng, s.status_text, s.photo_base64, s.created_at
    FROM statuses s
    JOIN users u ON u.id = s.user_id
    WHERE s.id IN (
        SELECT MAX(id) FROM statuses WHERE lat IS NOT NULL AND lng IS NOT NULL AND created_at >= ? GROUP BY user_id
    )
    ORDER BY s.created_at DESC
");
$stmt->execute([$cutoff]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statuses = array_map(function ($r) {
    return [
        'id' => (int) $r['id'],
        'user_id' => (int) $r['user_id'],
        'username' => $r['username'],
        'lat' => (float) $r['lat'],
        'lng' => (float) $r['lng'],
        'status_text' => $r['status_text'],
        'photo_base64' => $r['photo_base64'],
        'created_at' => $r['created_at']
    ];
}, $rows);

echo json_encode(['statuses' => $statuses]);
