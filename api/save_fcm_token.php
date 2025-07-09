<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Mobile-Token, X-App-Package');

require_once '../config/database.php';

// Verify mobile token
function verifyMobileToken($headers) {
    $secretKey = "MTSN11-MOBILE-KEY-2025";
    $currentDate = date('Y-m-d');
    $expectedToken = md5($secretKey . $currentDate);
    
    $providedToken = $headers['X-Mobile-Token'] ?? '';
    $appPackage = $headers['X-App-Package'] ?? '';
    
    return $providedToken === $expectedToken && $appPackage === 'id.sch.mtsn11majalengka.elapkin';
}

// Get headers
$headers = getallheaders();

// Verify request
if (!verifyMobileToken($headers)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$fcmToken = $_POST['fcm_token'] ?? null;
$userId = $_POST['user_id'] ?? null;
$deviceId = $_POST['device_id'] ?? null;
$deviceType = $_POST['device_type'] ?? 'android';
$appVersion = $_POST['app_version'] ?? null;

if (!$fcmToken || !$userId || !$deviceId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields: fcm_token, user_id, device_id']);
    exit;
}

try {
    // Verify user exists
    $userStmt = $conn->prepare("SELECT id_pegawai, nama FROM pegawai WHERE id_pegawai = ?");
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    
    if ($userResult->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    $user = $userResult->fetch_assoc();
    $userStmt->close();

    // Create table if not exists
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS user_fcm_tokens (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            fcm_token VARCHAR(500) NOT NULL,
            device_id VARCHAR(100) NOT NULL,
            device_type VARCHAR(20) DEFAULT 'android',
            app_version VARCHAR(20) NULL,
            last_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES pegawai(id_pegawai),
            UNIQUE KEY unique_user_device (user_id, device_id)
        )
    ";
    $conn->query($createTableQuery);

    // Nonaktifkan token lama user di device ini
    $conn->query("UPDATE user_fcm_tokens SET is_active = 0 WHERE user_id = '$userId' AND device_id = '$deviceId'");

    // Insert or update the new token
    $stmt = $conn->prepare("
        INSERT INTO user_fcm_tokens (user_id, fcm_token, device_id, device_type, app_version, last_used_at, is_active) 
        VALUES (?, ?, ?, ?, ?, NOW(), 1)
        ON DUPLICATE KEY UPDATE 
            fcm_token = VALUES(fcm_token),
            device_type = VALUES(device_type),
            app_version = VALUES(appVersion),
            last_used_at = NOW(),
            is_active = 1
    ");
    
    $stmt->bind_param("issss", $userId, $fcmToken, $deviceId, $deviceType, $appVersion);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Log the registration
        error_log("FCM Token registered for user: {$user['nama']} (ID: $userId)");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Token saved successfully',
            'user' => $user['nama']
        ]);
    } else {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save token: ' . $conn->error]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
