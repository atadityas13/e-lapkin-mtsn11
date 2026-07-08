<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Tangkap fatal error agar tidak 500 kosong (memudahkan debug di SimpatiSans)
register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err) return;
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($err['type'] ?? 0, $fatalTypes, true)) return;

    // Jika response sudah ada, jangan overwrite
    if (headers_sent()) return;

    http_response_code(500);
    $msg = ($err['message'] ?? 'Fatal error');
    $file = ($err['file'] ?? '');
    $line = ($err['line'] ?? '');
    error_log("mobile sso fatal: {$msg} @ {$file}:{$line}");
    echo json_encode([
        'success' => false,
        'message' => 'Server error (e-Lapkin).',
        'detail' => "{$msg} @ {$file}:{$line}",
    ]);
});

// Lokasi file config bisa berbeda antar layout (root config/ vs mobile-app/config/).
// Cari di beberapa kandidat agar tahan terhadap perbedaan struktur folder.
$requireFirstExisting = function (array $candidates, string $label) {
    foreach ($candidates as $path) {
        if (is_file($path)) {
            require_once $path;
            return $path;
        }
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => "Konfigurasi e-Lapkin tidak ditemukan ({$label}).",
        'detail' => 'Tried: ' . implode(' | ', $candidates),
    ]);
    exit;
};

$loadedDatabasePath = $requireFirstExisting([
    // Dari mobile-app/api/auth/sso.php ke e-lapkin-mtsn11/config/database.php: naik 3 level.
    __DIR__ . '/../../../config/database.php',
    // Fallback layout lain (jika suatu saat dipindah).
    __DIR__ . '/../../config/database.php',
    // Paling belakang: jika struktur server berbeda.
    __DIR__ . '/../../../../config/database.php',
], 'database.php');

$connFallbackAttempted = false;
if (!isset($conn) || !($conn instanceof mysqli)) {
    $connFallbackAttempted = true;
    // Jika database.php tidak mendefinisikan $conn tapi mendefinisikan parameter koneksi,
    // kita inisialisasi ulang di sini.
    if (isset($host, $user, $pass, $db_name)) {
        $conn = new mysqli($host, $user, $pass, $db_name);
    }
}

$connDebugOk = isset($conn) && ($conn instanceof mysqli);
if (! $connDebugOk) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection not initialized (mysqli expected).',
        'database_loaded_path' => $loadedDatabasePath,
        'conn_fallback_attempted' => $connFallbackAttempted,
        'conn_fallback_had_params' => [
            'host' => isset($host),
            'user' => isset($user),
            'pass' => isset($pass),
            'db_name' => isset($db_name),
        ],
        'conn_is_set' => isset($conn),
        'conn_type' => isset($conn) ? gettype($conn) : null,
        'conn_class' => (isset($conn) && is_object($conn)) ? get_class($conn) : null,
    ]);
    exit;
}

$loadedMobileAppsPath = $requireFirstExisting([
    // mobile-app/config/mobile_apps.php dari mobile-app/api/auth/: naik 2 level
    __DIR__ . '/../../config/mobile_apps.php',
    // root/config/mobile_apps.php (kalau ada)
    __DIR__ . '/../../../../config/mobile_apps.php',
    __DIR__ . '/../../../config/mobile_apps.php',
], 'mobile_apps.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$app = validateMobileUserAgentForApp();
validateOptionalMobileHeaders($app);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$nip = trim($input['nip'] ?? '');
$timestamp = (int) ($input['timestamp'] ?? 0);
$signature = $input['signature'] ?? '';
$profileHash = trim($input['profile_hash'] ?? '');
$profile = $input['profile'] ?? [];

if ($nip === '' || $timestamp <= 0 || $signature === '' || !is_array($profile) || empty($profile)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Data SSO tidak lengkap.']);
    exit;
}

$result = performMobileSsoLogin($conn, $nip, $timestamp, $signature, $profile, $profileHash);

if (!$result['success']) {
    http_response_code(401);
}

echo json_encode($result);
