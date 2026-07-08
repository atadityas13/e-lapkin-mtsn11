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
            return;
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

$requireFirstExisting([
    __DIR__ . '/../../../config/database.php',
    __DIR__ . '/../../config/database.php',
    __DIR__ . '/../config/database.php',
], 'database.php');

$requireFirstExisting([
    __DIR__ . '/../../config/mobile_apps.php',
    __DIR__ . '/../../../config/mobile_apps.php',
    __DIR__ . '/../config/mobile_apps.php',
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
