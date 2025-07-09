/**
 * FCM Integration for E-Lapkin Mobile App
 * Add this script to your mobile app HTML pages
 */

// Function to send FCM token to server
function sendFCMTokenToServer(token, userId) {
    const data = {
        user_id: userId,
        fcm_token: token,
        device_type: 'android',
        app_version: '1.0'
    };
    
    fetch('/api/fcm_token.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Mobile-Token': window.generateMobileToken(), // Your existing token function
            'X-App-Package': 'id.sch.mtsn11majalengka.elapkin'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('FCM token sent successfully');
        } else {
            console.error('Failed to send FCM token:', data.error);
        }
    })
    .catch(error => {
        console.error('Error sending FCM token:', error);
    });
}

// Function to subscribe to topic
function subscribeToTopic(topic) {
    if (typeof Android !== 'undefined' && Android.subscribeTopic) {
        Android.subscribeTopic(topic);
    }
}

// Function to unsubscribe from topic
function unsubscribeFromTopic(topic) {
    if (typeof Android !== 'undefined' && Android.unsubscribeTopic) {
        Android.unsubscribeTopic(topic);
    }
}

// Auto-subscribe to relevant topics when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Subscribe to general topics
    subscribeToTopic('umum');
    
    // Subscribe to role-based topics if user info is available
    if (typeof userRole !== 'undefined') {
        if (userRole === 'guru') {
            subscribeToTopic('guru');
        } else if (userRole === 'admin') {
            subscribeToTopic('admin');
        }
    }
});

// Set FCM token when received from Android
window.setFCMToken = function(token) {
    console.log('FCM Token received:', token);
    
    // Get user ID from session or local storage
    const userId = window.getCurrentUserId(); // Implement this function
    
    if (userId) {
        sendFCMTokenToServer(token, userId);
    }
};
