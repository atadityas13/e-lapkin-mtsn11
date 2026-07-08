<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

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
    __DIR__ . '/../../config/database.php',
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

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection not initialized.',
        'conn_fallback_attempted' => $connFallbackAttempted,
    ]);
    exit;
}

$loadedMobileAppsPath = $resolveFirstExisting([
    __DIR__ . '/../config/mobile_apps.php',
    __DIR__ . '/../../../config/mobile_apps.php',
], 'mobile_apps.php');

require_once $loadedMobileAppsPath;

$loadedHolidayHelperPath = $resolveFirstExisting([
    __DIR__ . '/../../config/hari_libur_helper.php',
    __DIR__ . '/../../../config/hari_libur_helper.php',
], 'hari_libur_helper.php');

require_once $loadedHolidayHelperPath;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$app = validateMobileUserAgentForApp();
validateOptionalMobileHeaders($app);

if (!isset($_SESSION['mobile_loggedin']) || $_SESSION['mobile_loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'logged_in' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action !== 'get_by_year') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

if (!isset($_GET['tahun'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parameter tahun diperlukan']);
    exit;
}

$tahun = (int) $_GET['tahun'];

try {
    $holidays = get_all_hari_libur($conn, $tahun);
    echo json_encode([
        'success' => true,
        'tahun' => $tahun,
        'data' => $holidays,
        'count' => count($holidays),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . htmlspecialchars($e->getMessage()),
    ]);
}
