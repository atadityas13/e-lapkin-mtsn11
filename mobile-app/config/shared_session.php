<?php
/**
 * Shared session bridge for web-to-mobile app integration
 */

function createMobileBridgeUrl() {
    // Check if user is logged in to web version
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        return false;
    }
    
    // Create a temporary token for mobile bridge
    $token = bin2hex(random_bytes(32));
    $expiry = time() + 300; // 5 minutes
    
    // Store bridge token in session
    $_SESSION['mobile_bridge_token'] = $token;
    $_SESSION['mobile_bridge_expiry'] = $expiry;
    
    // Return mobile app URL with bridge token
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    return "{$protocol}://{$host}/mobile-app/bridge.php?token={$token}";
}

function validateMobileBridge($token) {
    if (!isset($_SESSION['mobile_bridge_token']) || 
        !isset($_SESSION['mobile_bridge_expiry'])) {
        return false;
    }
    
    if (time() > $_SESSION['mobile_bridge_expiry']) {
        unset($_SESSION['mobile_bridge_token']);
        unset($_SESSION['mobile_bridge_expiry']);
        return false;
    }
    
    if ($token !== $_SESSION['mobile_bridge_token']) {
        return false;
    }
    
    // Token is valid, clean up
    unset($_SESSION['mobile_bridge_token']);
    unset($_SESSION['mobile_bridge_expiry']);
    
    return true;
}
?>
