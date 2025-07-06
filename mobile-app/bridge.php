<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA - MOBILE BRIDGE
 * ========================================================
 * 
 * File: Mobile Bridge
 * Deskripsi: Bridge untuk transisi dari web ke mobile app
 * 
 * @package    E-Lapkin-MTSN11-Mobile
 * @version    1.0.0
 * ========================================================
 */

session_start();

// Define ABSPATH
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Include dependencies
require_once __DIR__ . '/config/mobile_security.php';
require_once __DIR__ . '/config/shared_session.php';

// Check if this is a valid mobile access request
$token = $_GET['token'] ?? '';
$redirect = $_GET['redirect'] ?? '/mobile-app/user/dashboard.php';

if (empty($token)) {
    header("Location: /mobile-app/index.php?error=invalid_token");
    exit();
}

// Verify token
$user_id = verifyMobileAccessToken($token);
if (!$user_id) {
    header("Location: /mobile-app/index.php?error=token_expired");
    exit();
}

// Check if user can access mobile
if (!canAccessMobileFromWeb()) {
    header("Location: /mobile-app/index.php?error=access_denied");
    exit();
}

// Share web session to mobile
if (shareWebSessionToMobile()) {
    // Log successful bridge access
    log_mobile_access('bridge_access_success', [
        'user_id' => $user_id,
        'from_web' => true
    ]);
    
    // Clear the token (one-time use)
    unset($_SESSION['mobile_access_token']);
    unset($_SESSION['mobile_token_expires']);
    unset($_SESSION['mobile_token_user_id']);
    
    // Redirect to mobile app
    header("Location: " . $redirect);
    exit();
} else {
    // Bridge failed
    log_mobile_access('bridge_access_failed', [
        'user_id' => $user_id,
        'reason' => 'session_share_failed'
    ]);
    
    header("Location: /mobile-app/index.php?error=bridge_failed");
    exit();
}
?>
