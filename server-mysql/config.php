<?php
/**
 * config.php — versi MySQL (untuk XAMPP: database via phpMyAdmin).
 *
 * SEBELUM PAKAI:
 * 1. Buka http://localhost/phpmyadmin
 * 2. Buat database baru bernama: gpstracker  (collation: utf8mb4_general_ci)
 * 3. Sesuaikan $DB_USER / $DB_PASS di bawah kalau MySQL Anda pakai password
 *    (default XAMPP: user "root", password kosong)
 *
 * Endpoint lain (register.php, login.php, dst) TIDAK perlu diubah —
 * semuanya cukup include config.php ini seperti versi SQLite sebelumnya.
 */

// PENTING: set zona waktu ke Indonesia (WIB) supaya semua tampilan jam
// (di panel admin, dsb) sesuai jam HP Anda, bukan ikut default server.
date_default_timezone_set('Asia/Jakarta');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$DB_HOST = 'localhost';
$DB_NAME = 'gpstracker';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Tidak bisa konek ke database MySQL: ' . $e->getMessage() .
        ' — pastikan database "gpstracker" sudah dibuat di phpMyAdmin dan MySQL service XAMPP jalan.']);
    exit;
}

// Buat skema jika belum ada (aman dipanggil berulang)
$pdo->exec('CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$pdo->exec('CREATE TABLE IF NOT EXISTS tokens (
    token VARCHAR(64) PRIMARY KEY,
    user_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$pdo->exec('CREATE TABLE IF NOT EXISTS trips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    device_trip_id VARCHAR(64),
    date VARCHAR(64),
    distance_km DOUBLE,
    duration_ms BIGINT,
    avg_speed_kmh DOUBLE,
    points_json LONGTEXT,
    created_at DATETIME NOT NULL,
    UNIQUE KEY unique_device_trip (user_id, device_trip_id),
    INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$pdo->exec('CREATE TABLE IF NOT EXISTS statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    lat DOUBLE,
    lng DOUBLE,
    status_text TEXT,
    photo_base64 LONGTEXT,
    created_at DATETIME NOT NULL,
    INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

/**
 * Ambil user_id dari header Authorization: Bearer <token>.
 */
function requireAuth(PDO $pdo)
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = '';
    foreach ($headers as $k => $v) {
        if (strtolower($k) === 'authorization') $authHeader = $v;
    }
    if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (!preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
        http_response_code(401);
        echo json_encode(['message' => 'Token tidak ditemukan. Silakan login ulang.']);
        exit;
    }
    $token = trim($m[1]);
    $stmt = $pdo->prepare('SELECT user_id FROM tokens WHERE token = ?');
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(401);
        echo json_encode(['message' => 'Sesi tidak valid atau sudah kedaluwarsa. Silakan login ulang.']);
        exit;
    }
    return (int) $row['user_id'];
}

function readJsonBody()
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function generateToken()
{
    return bin2hex(random_bytes(32));
}
