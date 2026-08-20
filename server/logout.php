<?php
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Metode tidak diizinkan']);
    exit;
}

$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = '';
foreach ($headers as $k => $v) {
    if (strtolower($k) === 'authorization') $authHeader = $v;
}
if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
}

if (preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
    $token = trim($m[1]);
    $stmt = $pdo->prepare('DELETE FROM tokens WHERE token = ?');
    $stmt->execute([$token]);
}

// Selalu balas sukses — dari sisi user, logout tetap dianggap berhasil
// walau misalnya tokennya sudah tidak valid/kadaluarsa duluan.
echo json_encode(['status' => 'ok']);
