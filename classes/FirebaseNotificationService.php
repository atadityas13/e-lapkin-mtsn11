<?php
require_once __DIR__ . '/../config/firebase_config.php';

class FirebaseNotificationService {
    private $serverKey;
    private $fcmUrl;
    private $conn;
    
    public function __construct($database_connection) {
        $this->serverKey = getFCMServerKey();
        $this->fcmUrl = FCM_SEND_URL;
        $this->conn = $database_connection;
    }
    
    /**
     * Send notification to all active users
     */
    public function sendToAll($title, $message, $data = []) {
        $tokens = $this->getActiveTokens();
        return $this->sendToMultiple($tokens, $title, $message, $data);
    }
    
    /**
     * Send notification to specific users
     */
    public function sendToUsers($userIds, $title, $message, $data = []) {
        if (empty($userIds)) {
            return ['success' => false, 'message' => 'No users specified'];
        }
        
        $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
        $stmt = $this->conn->prepare("
            SELECT fcm_token 
            FROM user_fcm_tokens 
            WHERE user_id IN ($placeholders) AND is_active = 1
        ");
        $stmt->bind_param(str_repeat('i', count($userIds)), ...$userIds);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tokens = [];
        while ($row = $result->fetch_assoc()) {
            $tokens[] = $row['fcm_token'];
        }
        $stmt->close();
        
        return $this->sendToMultiple($tokens, $title, $message, $data);
    }
    
    /**
     * Send notification to topic subscribers
     */
    public function sendToTopic($topic, $title, $message, $data = []) {
        $payload = [
            'to' => '/topics/' . $topic,
            'notification' => [
                'title' => $title,
                'body' => $message,
                'sound' => FCM_DEFAULT_SOUND,
                'icon' => FCM_DEFAULT_ICON
            ],
            'data' => $data
        ];
        
        return $this->sendFcmRequest($payload);
    }
    
    /**
     * Send notification to multiple tokens
     */
    public function sendToMultiple($tokens, $title, $message, $data = []) {
        if (empty($tokens)) {
            return [
                'success' => false, 
                'message' => 'No FCM tokens found',
                'total_success' => 0,
                'total_failure' => 0
            ];
        }
        
        // FCM allows maximum 1000 tokens per request
        $chunks = array_chunk($tokens, 1000);
        $results = [];
        $totalSuccess = 0;
        $totalFailure = 0;
        
        foreach ($chunks as $chunk) {
            $payload = [
                'registration_ids' => $chunk,
                'notification' => [
                    'title' => $title,
                    'body' => $message,
                    'sound' => FCM_DEFAULT_SOUND,
                    'icon' => FCM_DEFAULT_ICON
                ],
                'data' => array_merge($data, [
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'type' => 'notification'
                ])
            ];
            
            $result = $this->sendFcmRequest($payload);
            $results[] = $result;
            
            if ($result['success'] && isset($result['response']['success'])) {
                $totalSuccess += $result['response']['success'];
            }
            if ($result['success'] && isset($result['response']['failure'])) {
                $totalFailure += $result['response']['failure'];
            }
        }
        
        return [
            'success' => true,
            'total_success' => $totalSuccess,
            'total_failure' => $totalFailure,
            'responses' => $results
        ];
    }
    
    /**
     * Save or update FCM token for user
     */
    public function saveUserToken($userId, $fcmToken, $deviceType = 'android', $appVersion = null) {
        try {
            // First, deactivate old tokens for this user
            $stmt = $this->conn->prepare("UPDATE user_fcm_tokens SET is_active = 0 WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();
            
            // Insert or update the new token
            $stmt = $this->conn->prepare("
                INSERT INTO user_fcm_tokens (user_id, fcm_token, device_type, app_version, last_used_at, is_active) 
                VALUES (?, ?, ?, ?, NOW(), 1)
                ON DUPLICATE KEY UPDATE 
                    device_type = VALUES(device_type),
                    app_version = VALUES(app_version),
                    last_used_at = NOW(),
                    is_active = 1
            ");
            $stmt->bind_param("isss", $userId, $fcmToken, $deviceType, $appVersion);
            $result = $stmt->execute();
            $stmt->close();
            
            return $result;
        } catch (Exception $e) {
            error_log("Error saving FCM token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all active FCM tokens
     */
    private function getActiveTokens() {
        $stmt = $this->conn->prepare("SELECT fcm_token FROM user_fcm_tokens WHERE is_active = 1");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tokens = [];
        while ($row = $result->fetch_assoc()) {
            $tokens[] = $row['fcm_token'];
        }
        $stmt->close();
        
        return $tokens;
    }
    
    /**
     * Send FCM request using cURL
     */
    private function sendFcmRequest($payload) {
        $headers = [
            'Authorization: key=' . $this->serverKey,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->fcmUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("FCM cURL Error: " . $error);
            return [
                'success' => false,
                'error' => $error,
                'http_code' => $httpCode
            ];
        }
        
        $responseData = json_decode($response, true);
        
        // Log the response for debugging
        error_log("FCM Response: " . $response);
        
        return [
            'success' => $httpCode == 200,
            'response' => $responseData,
            'http_code' => $httpCode,
            'raw_response' => $response
        ];
    }
}
