<?php
/**
 * Mobile Session Management for E-LAPKIN
 */

// Mobile app configuration
define('MOBILE_SECRET_KEY', 'MTSN11-MOBILE-KEY-2025');
define('MOBILE_PACKAGE_NAME', 'id.sch.mtsn11majalengka.elapkin');
define('MOBILE_USER_AGENT', 'E-LAPKIN-MTSN11-Mobile-App/1.0');

// Set timezone to match Android (assuming WIB - Indonesia Western Time)
date_default_timezone_set('Asia/Jakarta');

// Generate mobile token (same logic as Android app)
function generateMobileToken() {
    $currentDate = date('Y-m-d');
    $input = MOBILE_SECRET_KEY . $currentDate;
    $token = md5($input);
    
    // Debug logging
    error_log("PHP Token Debug - Date: " . $currentDate);
    error_log("PHP Token Debug - Input: " . $input);
    error_log("PHP Token Debug - Generated: " . $token);
    
    return $token;
}

// Validate mobile token
function validateMobileToken() {
    $receivedToken = $_SERVER['HTTP_X_MOBILE_TOKEN'] ?? '';
    $expectedToken = generateMobileToken();
    
    // Debug logging
    error_log("Token Validation - Received: " . $receivedToken);
    error_log("Token Validation - Expected: " . $expectedToken);
    error_log("Token Validation - Match: " . ($receivedToken === $expectedToken ? 'YES' : 'NO'));
    
    if ($receivedToken !== $expectedToken) {
        http_response_code(403);
        die(json_encode([
            'error' => 'Invalid mobile token.',
            'code' => 'INVALID_TOKEN',
            'debug' => [
                'received' => $receivedToken,
                'expected' => $expectedToken,
                'date_used' => date('Y-m-d'),
                'timezone' => date_default_timezone_get()
            ]
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
