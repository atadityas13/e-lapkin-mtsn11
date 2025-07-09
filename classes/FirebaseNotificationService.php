<?php
require_once __DIR__ . '/../config/firebase_config.php';

class FirebaseNotificationService
{
    private $conn;
    private $serverKey;
    private $fcmUrl = 'https://fcm.googleapis.com/fcm/send';

    public function __construct($database_connection)
    {
        $this->conn = $database_connection;
        // Get FCM server key from config or environment
        $this->serverKey = $this->getFcmServerKey();
        if (empty($this->serverKey)) {
            error_log("FCM server key is not set. Please check config/firebase.php");
        }
        // Optional: check if config file exists
        $configFile = __DIR__ . '/../config/firebase.php';
        if (!file_exists($configFile)) {
            error_log("FCM config file not found at: " . $configFile);
        }
    }

    private function getFcmServerKey()
    {
        // You can store this in config file or database
        // For now, get it from config file
        $configFile = __DIR__ . '/../config/firebase.php';
        if (file_exists($configFile)) {
            $config = include $configFile;
            return $config['fcm_server_key'] ?? '';
        }
        
        // Fallback: return the server key directly (not recommended for production)
        return 'AAAA9vX-3pI:APA91bEhKv6XQjQlXyJ_aP3u4N_qY0XzH9w_Vk2C3dE4fG5hI6jK7lM8nO9pQ0rS1tU2vW3xY4zA5bC6dE7fG8hI9jK0lM1nO2pQ3rS4tU5vW6xY7zA8bC9dE0fG1hI2jK3';
    }

    /**
     * Send notification to all active users
     */
    public function sendToAll($title, $message, $data = [])
    {
        $tokens = $this->getAllActiveTokens();
        if (empty($tokens)) {
            return ['success' => false, 'error' => 'No active tokens found', 'total_success' => 0, 'total_failure' => 0];
        }
        
        return $this->sendToMultiple($tokens, $title, $message, $data);
    }

    /**
     * Send notification to specific users
     */
    public function sendToUsers($userIds, $title, $message, $data = [])
    {
        if (empty($userIds)) {
            return ['success' => false, 'error' => 'No user IDs provided', 'total_success' => 0, 'total_failure' => 0];
        }
        
        $tokens = $this->getTokensByUserIds($userIds);
        if (empty($tokens)) {
            return ['success' => false, 'error' => 'No tokens found for specified users', 'total_success' => 0, 'total_failure' => 0];
        }
        
        return $this->sendToMultiple($tokens, $title, $message, $data);
    }

    /**
     * Send notification to topic subscribers
     */
    public function sendToTopic($topic, $title, $message, $data = [])
    {
        $payload = [
            'to' => '/topics/' . $topic,
            'notification' => [
                'title' => $title,
                'body' => $message,
                'sound' => 'default',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
            ],
            'data' => array_merge($data, [
                'title' => $title,
                'message' => $message,
                'type' => 'topic',
                'topic' => $topic
            ])
        ];

        $result = $this->sendFcmRequest($payload);
        
        return [
            'success' => $result['success'],
            'total_success' => $result['success'] ? 1 : 0,
            'total_failure' => $result['success'] ? 0 : 1,
            'response' => $result
        ];
    }

    /**
     * Send notification to multiple tokens
     */
    public function sendToMultiple($tokens, $title, $message, $data = [])
    {
        if (empty($tokens)) {
            return ['success' => false, 'error' => 'No tokens provided', 'total_success' => 0, 'total_failure' => 0];
        }

        $totalSuccess = 0;
        $totalFailure = 0;
        $responses = [];

        // FCM allows maximum 1000 tokens per request
        $chunks = array_chunk($tokens, 1000);

        foreach ($chunks as $chunk) {
            $payload = [
                'registration_ids' => $chunk,
                'notification' => [
                    'title' => $title,
                    'body' => $message,
                    'sound' => 'default',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                ],
                'data' => array_merge($data, [
                    'title' => $title,
                    'message' => $message,
                    'type' => 'multiple'
                ])
            ];

            $result = $this->sendFcmRequest($payload);
            $responses[] = $result;

            if ($result['success'] && isset($result['response']['success'])) {
                $totalSuccess += $result['response']['success'];
            }
            if ($result['success'] && isset($result['response']['failure'])) {
                $totalFailure += $result['response']['failure'];
            } else if (!$result['success']) {
                $totalFailure += count($chunk);
            }
        }

        return [
            'success' => $totalSuccess > 0,
            'total_success' => $totalSuccess,
            'total_failure' => $totalFailure,
            'responses' => $responses
        ];
    }

    /**
     * Send FCM request using cURL
     */
    private function sendFcmRequest($payload)
    {
        if (empty($this->serverKey)) {
            return [
                'success' => false,
                'error' => 'FCM server key not configured',
                'response' => null
            ];
        }

        $headers = [
            'Authorization: key=' . $this->serverKey,
            'Content-Type: application/json'
        ];

        // Pastikan cURL menggunakan IPv4 (hindari masalah DNS/IPv6 di shared hosting)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->fcmUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // <--- Tambahkan baris ini

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("FCM cURL Error: " . $error);
            return [
                'success' => false,
                'error' => 'cURL Error: ' . $error,
                'response' => null
            ];
        }

        $responseData = json_decode($response, true);
        
        // Log the response for debugging
        error_log("FCM Response: " . $response);

        return [
            'success' => $httpCode === 200 && $responseData !== null,
            'response' => $responseData,
            'http_code' => $httpCode,
            'raw_response' => $response
        ];
    }

    /**
     * Get all active FCM tokens
     */
    private function getAllActiveTokens()
    {
        $stmt = $this->conn->prepare("SELECT fcm_token FROM user_fcm_tokens WHERE is_active = 1");
        if (!$stmt) {
            error_log("Database error in getAllActiveTokens: " . $this->conn->error);
            return [];
        }
        
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
     * Get FCM tokens by user IDs
     */
    private function getTokensByUserIds($userIds)
    {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $this->conn->prepare("SELECT fcm_token FROM user_fcm_tokens WHERE user_id IN ($placeholders) AND is_active = 1");
        
        if (!$stmt) {
            error_log("Database error in getTokensByUserIds: " . $this->conn->error);
            return [];
        }
        
        $types = str_repeat('i', count($userIds));
        $stmt->bind_param($types, ...$userIds);
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
     * Save or update FCM token for user
     */
    public function saveUserToken($userId, $fcmToken, $deviceType = 'android', $appVersion = null)
    {
        // First, deactivate old tokens for this user
        $stmt = $this->conn->prepare("UPDATE user_fcm_tokens SET is_active = 0 WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();
        }

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
        
        if ($stmt) {
            $stmt->bind_param("isss", $userId, $fcmToken, $deviceType, $appVersion);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        return false;
    }
}