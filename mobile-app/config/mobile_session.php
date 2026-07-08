<?php
/**
 * Mobile Session Management for E-LAPKIN
 */

require_once __DIR__ . '/mobile_apps.php';
require_once __DIR__ . '/talim_embed.php';

// Legacy constants for backward compatibility
define('MOBILE_PACKAGE_NAME', $MOBILE_APPS['elapkin']['package']);
define('MOBILE_USER_AGENT', $MOBILE_APPS['elapkin']['user_agent']);

// Alternative function to try different date formats if needed
function generateMobileTokenWithTimezone($timezone = 'UTC') {
    $originalTimezone = date_default_timezone_get();
    date_default_timezone_set($timezone);
    $token = generateMobileTokenForDate(date('Y-m-d'));
    date_default_timezone_set($originalTimezone);
    return $token;
}

// Validate mobile token with multiple timezone attempts
function validateMobileToken() {
    // Handle both uppercase and lowercase header names
    $headers = getallheaders();
    $receivedToken = '';
    
    // Check different possible header name variations
    if (isset($headers['X-Mobile-Token'])) {
        $receivedToken = $headers['X-Mobile-Token'];
    } elseif (isset($headers['x-mobile-token'])) {
        $receivedToken = $headers['x-mobile-token'];
    } elseif (isset($_SERVER['HTTP_X_MOBILE_TOKEN'])) {
        $receivedToken = $_SERVER['HTTP_X_MOBILE_TOKEN'];
    }
    
    // Primary validation with Asia/Jakarta timezone
    $expectedToken = generateMobileToken();
    if ($receivedToken === $expectedToken) {
        return; // Token valid
    }
    
    // Fallback: Try UTC timezone as backup
    $expectedTokenUTC = generateMobileTokenWithTimezone('UTC');
    if ($receivedToken === $expectedTokenUTC) {
        return; // Token valid
    }
    
    // Token validation failed
    http_response_code(403);
    die(json_encode([
        'error' => 'Invalid mobile token.',
        'code' => 'INVALID_TOKEN'
    ]));
}

// Validate package name
function validatePackageName() {
    $app = resolveMobileApp();
    if (!$app) {
        http_response_code(403);
        die(json_encode([
            'error' => 'Access denied. Mobile app access only.',
            'code' => 'INVALID_USER_AGENT'
        ]));
    }

    $receivedPackage = getRequestHeader('X-App-Package');
    if ($receivedPackage !== $app['package']) {
        http_response_code(403);
        die(json_encode([
            'error' => 'Invalid app package.',
            'code' => 'INVALID_PACKAGE'
        ]));
    }
}

// User Agent validation
function validateMobileUserAgent() {
    validateMobileUserAgentForApp();
}

// Comprehensive mobile security check
function validateMobileAccess() {
    validateMobileUserAgent();
    validateMobileToken();
    validatePackageName();
}

// Check mobile login
function checkMobileLogin() {
    if (!isset($_SESSION['mobile_loggedin']) || $_SESSION['mobile_loggedin'] !== true) {
        header("Location: index.php");
        exit();
    }
}

// Get mobile session data
function getMobileSessionData() {
    return [
        'id_pegawai' => $_SESSION['mobile_id_pegawai'] ?? 0,
        'nip' => $_SESSION['mobile_nip'] ?? '',
        'nama' => $_SESSION['mobile_nama'] ?? '',
        'jabatan' => $_SESSION['mobile_jabatan'] ?? '',
        'unit_kerja' => $_SESSION['mobile_unit_kerja'] ?? '',
        'role' => $_SESSION['mobile_role'] ?? 'user'
    ];
}

// Get formatted active period
function getMobileActivePeriod($conn, $id_pegawai) {
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $stmt = $conn->prepare("SELECT tahun_aktif, bulan_aktif FROM pegawai WHERE id_pegawai = ?");
    $stmt->bind_param("i", $id_pegawai);
    $stmt->execute();
    $stmt->bind_result($tahun_aktif, $bulan_aktif);
    $stmt->fetch();
    $stmt->close();
    
    $tahun = $tahun_aktif ?: (int)date('Y');
    $bulan = $bulan_aktif ?: (int)date('m');
    
    return $months[$bulan] . ' - ' . $tahun;
}

// Mobile logout
function mobileLogout() {
    unset($_SESSION['mobile_loggedin']);
    unset($_SESSION['mobile_id_pegawai']);
    unset($_SESSION['mobile_nip']);
    unset($_SESSION['mobile_nama']);
    unset($_SESSION['mobile_jabatan']);
    unset($_SESSION['mobile_unit_kerja']);
    unset($_SESSION['mobile_role']);
    header("Location: index.php");
    exit();
}

// DON'T automatically validate - let each page decide when to validate
// validateMobileAccess(); // REMOVED THIS LINE
?>
