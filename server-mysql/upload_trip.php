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
    // sempat gagal separuh jalan), data di server di-UPDATE dengan yang terbaru.
    // (Versi sebelumnya pakai "ON DUPLICATE KEY UPDATE user_id = user_id" yang
    // ternyata no-op — tidak pernah benar-benar memperbarui data lama.)
    $stmt = $pdo->prepare('INSERT INTO trips
        (user_id, device_trip_id, date, distance_km, duration_ms, avg_speed_kmh, points_json, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            date = VALUES(date),
            distance_km = VALUES(distance_km),
            duration_ms = VALUES(duration_ms),
            avg_speed_kmh = VALUES(avg_speed_kmh),
            points_json = VALUES(points_json)');
    $stmt->execute([
        $userId, $deviceTripId, $date, $distanceKm, $durationMs, $avgSpeedKmh,
        json_encode($points), date('Y-m-d H:i:s')
    ]);

    echo json_encode(['status' => 'ok', 'server_id' => (int) $pdo->lastInsertId(), 'points_saved' => count($points)]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Gagal menyimpan trip: ' . $e->getMessage()]);
}
