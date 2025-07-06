<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA - SHARED SESSION HANDLER
 * ========================================================
 * 
 * File: Shared Session Management
 * Deskripsi: Sistem berbagi session antara web utama dan mobile app
 * 
 * @package    E-Lapkin-MTSN11
 * @version    1.0.0
 * ========================================================
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    die('Direct access denied');
}

/**
 * Share session from web to mobile
 * Digunakan ketika user sudah login di web dan ingin akses mobile
 */
function shareWebSessionToMobile() {
    // Cek apakah user sudah login di web
    if (!isset($_SESSION['user_login']) || $_SESSION['user_login'] !== true) {
        return false;
    }
    
    // Cek apakah role adalah user (admin tidak bisa akses mobile)
    if (($_SESSION['role'] ?? '') !== 'user') {
        return false;
    }
    
    // Set session mobile
    $_SESSION['mobile_loggedin'] = true;
    $_SESSION['mobile_login_time'] = time();
    $_SESSION['mobile_last_activity'] = time();
    $_SESSION['mobile_app_version'] = '1.0.0';
    $_SESSION['mobile_shared_from_web'] = true;
    
    return true;
}

/**
 * Check if user can access mobile from web session
 */
function canAccessMobileFromWeb() {
    return isset($_SESSION['user_login']) && 
           $_SESSION['user_login'] === true && 
           ($_SESSION['role'] ?? '') === 'user';
}

/**
 * Generate mobile access token for web user
 */
function generateMobileAccessToken($user_id) {
    $token = bin2hex(random_bytes(32));
    $expires = time() + 3600; // 1 hour
    
    // Store token in session for verification
    $_SESSION['mobile_access_token'] = $token;
    $_SESSION['mobile_token_expires'] = $expires;
    $_SESSION['mobile_token_user_id'] = $user_id;
    
    return $token;
}

/**
 * Verify mobile access token
 */
function verifyMobileAccessToken($token) {
    if (!isset($_SESSION['mobile_access_token']) || 
        !isset($_SESSION['mobile_token_expires']) ||
        !isset($_SESSION['mobile_token_user_id'])) {
        return false;
    }
    
    // Check if token matches and not expired
    if ($_SESSION['mobile_access_token'] === $token && 
        time() < $_SESSION['mobile_token_expires']) {
        return $_SESSION['mobile_token_user_id'];
    }
    
    return false;
}

/**
 * Sync session data between web and mobile
 */
function syncSessionData() {
    // Update common session data
    if (isset($_SESSION['mobile_loggedin']) && $_SESSION['mobile_loggedin'] === true) {
        $_SESSION['mobile_last_activity'] = time();
    }
}

/**
 * Create mobile bridge URL for seamless transition
 */
function createMobileBridgeUrl($redirect_to = '/mobile-app/user/dashboard.php') {
    if (!canAccessMobileFromWeb()) {
        return false;
    }
    
    $token = generateMobileAccessToken($_SESSION['id_pegawai']);
    $bridge_url = '/mobile-app/bridge.php?token=' . urlencode($token) . '&redirect=' . urlencode($redirect_to);
    
    return $bridge_url;
}
?>
