<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// PENTING: closure hanya MENGEMBALIKAN path, `require` dilakukan di scope file ini,
// supaya variabel top-level file config ($conn, $host, dst.) tidak terjebak di scope closure.
$resolveFirstExisting = function (array $candidates, string $label) {
    foreach ($candidates as $path) {
        if (is_file($path)) {
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

$loadedDatabasePath = $resolveFirstExisting([
    // Dari mobile-app/api/me.php ke e-lapkin-mtsn11/config/database.php: naik 2 level.
    __DIR__ . '/../../config/database.php',
    // Fallback jika struktur server berbeda.
    __DIR__ . '/../../../config/database.php',
], 'database.php');

require_once $loadedDatabasePath;

$connFallbackAttempted = false;
if (!isset($conn) || !($conn instanceof mysqli)) {
    $connFallbackAttempted = true;
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

$loadedMobileAppsPath = $resolveFirstExisting([
    // mobile-app/config/mobile_apps.php dari mobile-app/api/: naik 1 level
    __DIR__ . '/../config/mobile_apps.php',
    // root/config/mobile_apps.php (kalau ada)
    __DIR__ . '/../../../config/mobile_apps.php',
], 'mobile_apps.php');

require_once $loadedMobileAppsPath;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$app = validateMobileUserAgentForApp();
validateOptionalMobileHeaders($app);

if (!isset($_SESSION['mobile_loggedin']) || $_SESSION['mobile_loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'logged_in' => false]);
    exit;
}

$idPegawai = (int) ($_SESSION['mobile_id_pegawai'] ?? 0);
$stats = $idPegawai > 0 ? getMobileDashboardStats($conn, $idPegawai) : ['lkh_bulan_ini' => 0, 'lkh_hari_ini' => 0];

echo json_encode([
    'success' => true,
    'logged_in' => true,
    'source' => $_SESSION['mobile_simpatisans'] ?? false ? 'simpatisans' : 'elapkin',
    'user' => [
        'nip' => $_SESSION['mobile_nip'] ?? '',
        'nama' => $_SESSION['mobile_nama'] ?? '',
        'jabatan' => $_SESSION['mobile_jabatan'] ?? '',
        'unit_kerja' => $_SESSION['mobile_unit_kerja'] ?? '',
        'kode_guru' => $_SESSION['mobile_kode_guru'] ?? '',
        'nip_penilai' => $_SESSION['mobile_nip_penilai'] ?? '',
        'nama_penilai' => $_SESSION['mobile_nama_penilai'] ?? '',
    ],
    'stats' => $stats,
]);
