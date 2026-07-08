<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

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
    ]);
    exit;
};

$requireFirstExisting([
    __DIR__ . '/../../config/database.php',
    __DIR__ . '/../../../config/database.php',
    __DIR__ . '/../config/database.php',
], 'database.php');

$requireFirstExisting([
    __DIR__ . '/../config/mobile_apps.php',
    __DIR__ . '/../../config/mobile_apps.php',
    __DIR__ . '/../../../config/mobile_apps.php',
], 'mobile_apps.php');

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
