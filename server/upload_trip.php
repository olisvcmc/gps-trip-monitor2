<?php
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Metode tidak diizinkan']);
    exit;
}

$userId = requireAuth($pdo);
$body = readJsonBody();

$deviceTripId = isset($body['device_trip_id']) ? (string) $body['device_trip_id'] : null;
$date = isset($body['date']) ? (string) $body['date'] : date('c');
$distanceKm = isset($body['distance_km']) ? (float) $body['distance_km'] : 0;
$durationMs = isset($body['duration_ms']) ? (int) $body['duration_ms'] : 0;
$avgSpeedKmh = isset($body['avg_speed_kmh']) ? (float) $body['avg_speed_kmh'] : 0;
$points = isset($body['points']) && is_array($body['points']) ? $body['points'] : [];

try {
    // UPSERT: kalau device_trip_id sudah ada (misal percobaan upload sebelumnya
    // sempat gagal separuh jalan), data di server di-UPDATE dengan yang terbaru —
    // bukan diabaikan. Ini penting supaya koordinat/jarak tidak "nyangkut" kosong
    // kalau baris lama sempat tersimpan tanpa points_json yang lengkap.
    $stmt = $pdo->prepare('INSERT INTO trips
        (user_id, device_trip_id, date, distance_km, duration_ms, avg_speed_kmh, points_json, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON CONFLICT(user_id, device_trip_id) DO UPDATE SET
            date = excluded.date,
            distance_km = excluded.distance_km,
            duration_ms = excluded.duration_ms,
            avg_speed_kmh = excluded.avg_speed_kmh,
            points_json = excluded.points_json');
    $stmt->execute([
        $userId, $deviceTripId, $date, $distanceKm, $durationMs, $avgSpeedKmh,
        json_encode($points), date('c')
    ]);

    echo json_encode(['status' => 'ok', 'server_id' => (int) $pdo->lastInsertId(), 'points_saved' => count($points)]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Gagal menyimpan trip: ' . $e->getMessage()]);
}
