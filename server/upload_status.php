<?php
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Metode tidak diizinkan']);
    exit;
}

$userId = requireAuth($pdo);
$body = readJsonBody();

$lat = isset($body['lat']) ? (float) $body['lat'] : null;
$lng = isset($body['lng']) ? (float) $body['lng'] : null;
$statusText = isset($body['status_text']) ? trim((string) $body['status_text']) : '';
$photoBase64 = isset($body['photo_base64']) ? (string) $body['photo_base64'] : '';

if ($statusText === '' && $photoBase64 === '') {
    http_response_code(400);
    echo json_encode(['message' => 'Status atau foto wajib diisi.']);
    exit;
}

// Batas ukuran wajar untuk foto base64 (~3MB) supaya tidak membebani database/koneksi.
if (strlen($photoBase64) > 4 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['message' => 'Ukuran foto terlalu besar. Coba lagi dengan kompresi lebih tinggi.']);
    exit;
}

try {
    $stmt = $pdo->prepare('INSERT INTO statuses (user_id, lat, lng, status_text, photo_base64, created_at)
        VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $lat, $lng, $statusText, $photoBase64, date('c')]);

    echo json_encode(['status' => 'ok', 'id' => (int) $pdo->lastInsertId()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Gagal menyimpan status: ' . $e->getMessage()]);
}
