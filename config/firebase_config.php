<?php
/**
 * Firebase Configuration
 * Add your FCM Server Key to .env or define it here
 */

// FCM Server Key - Get this from Firebase Console > Project Settings > Cloud Messaging > Server Key
define('FCM_SERVER_KEY', 'AAAA...:APA91bH...'); // Replace with your actual FCM Server Key

// FCM Send URL
define('FCM_SEND_URL', 'https://fcm.googleapis.com/fcm/send');

// Default notification icon (optional)
define('FCM_DEFAULT_ICON', 'ic_notification');

// Default notification sound (optional)
define('FCM_DEFAULT_SOUND', 'default');

/**
 * Get FCM Server Key from environment or config
 */
function getFCMServerKey() {
    // Try to get from environment first
    $envKey = getenv('FCM_SERVER_KEY');
    if ($envKey) {
        return $envKey;
    }
    
    // Fallback to constant
    return FCM_SERVER_KEY;
}
