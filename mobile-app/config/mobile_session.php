<?php
/**
 * Mobile Session Management for E-LAPKIN
 */

// Mobile app configuration
define('MOBILE_SECRET_KEY', 'MTSN11-MOBILE-KEY-2025');
define('MOBILE_PACKAGE_NAME', 'id.sch.mtsn11majalengka.elapkin');
define('MOBILE_USER_AGENT', 'E-LAPKIN-MTSN11-Mobile-App/1.0');

// Generate mobile token (exactly same logic as Android app)
function generateMobileToken() {
    // Use UTC timezone to match Android's SimpleDateFormat default behavior
    $currentDate = gmdate('Y-m-d');
    $input = MOBILE_SECRET_KEY . $currentDate;
    $token = md5($input);
    
    return $token;
}

// Alternative function to try different date formats if needed
function generateMobileTokenWithTimezone($timezone = 'UTC') {
    $originalTimezone = date_default_timezone_get();
    date_default_timezone_set($timezone);
    
    $currentDate = date('Y-m-d');
    $input = MOBILE_SECRET_KEY . $currentDate;
    $token = md5($input);
    
    date_default_timezone_set($originalTimezone);
    return $token;
}

// Validate mobile token with multiple timezone attempts
function validateMobileToken() {
    $receivedToken = $_SERVER['HTTP_X_MOBILE_TOKEN'] ?? '';
    
    // Try different timezone possibilities
    $timezones = ['UTC', 'Asia/Jakarta', 'GMT'];
    $validToken = false;
    $expectedToken = '';
    
    foreach ($timezones as $tz) {
        $expectedToken = generateMobileTokenWithTimezone($tz);
        if ($receivedToken === $expectedToken) {
            $validToken = true;
            break;
        }
    }
    
    if (!$validToken) {
        // Also try the default function
        $expectedToken = generateMobileToken();
        if ($receivedToken === $expectedToken) {
            $validToken = true;
        }
    }
    
    if (!$validToken) {
        http_response_code(403);
        die(json_encode([
            'error' => 'Invalid mobile token.',
            'code' => 'INVALID_TOKEN'
        ]));
    }
}

// Validate package name
function validatePackageName() {
    $receivedPackage = $_SERVER['HTTP_X_APP_PACKAGE'] ?? '';
    
    if ($receivedPackage !== MOBILE_PACKAGE_NAME) {
        http_response_code(403);
        die(json_encode([
            'error' => 'Invalid app package.',
            'code' => 'INVALID_PACKAGE'
        ]));
    }
}

// User Agent validation
function validateMobileUserAgent() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if ($user_agent !== MOBILE_USER_AGENT) {
        http_response_code(403);
        die(json_encode([
            'error' => 'Access denied. Mobile app access only.',
            'code' => 'INVALID_USER_AGENT'
        ]));
    }
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

// Initialize mobile session check (validate all security headers)
validateMobileAccess();
?>
