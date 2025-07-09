<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Mobile-Token, X-App-Package');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../classes/FirebaseNotificationService.php';

// Verify mobile token (same as your existing mobile verification)
function verifyMobileToken() {
    $headers = getallheaders();
    $mobileToken = $headers['X-Mobile-Token'] ?? '';
    $appPackage = $headers['X-App-Package'] ?? '';
    
    if (empty($mobileToken) || $appPackage !== 'id.sch.mtsn11majalengka.elapkin') {
        return false;
    }
    
    // Generate expected token
    $secretKey = "MTSN11-MOBILE-KEY-2025";
    $currentDate = date('Y-m-d');
    $expectedToken = md5($secretKey . $currentDate);
    
    return $mobileToken === $expectedToken;
}

// Verify request
if (!verifyMobileToken()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['user_id']) || !isset($input['fcm_token'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: user_id, fcm_token']);
    exit();
}

$userId = (int)$input['user_id'];
$fcmToken = trim($input['fcm_token']);
$deviceType = $input['device_type'] ?? 'android';
$appVersion = $input['app_version'] ?? null;

// Validate user exists
$stmt = $conn->prepare("SELECT id_pegawai FROM pegawai WHERE id_pegawai = ? AND role = 'user'");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit();
}
$stmt->close();

// Save FCM token
$firebaseService = new FirebaseNotificationService($conn);
$success = $firebaseService->saveUserToken($userId, $fcmToken, $deviceType, $appVersion);

if ($success) {
    echo json_encode([
        'success' => true,
        'message' => 'FCM token saved successfully'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to save FCM token'
    ]);
}
