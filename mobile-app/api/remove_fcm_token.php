<?php
require_once '../config/database.php';
// ...verifikasi header seperti save_fcm_token.php...

$userId = $_POST['user_id'] ?? null;
$deviceId = $_POST['device_id'] ?? null;

if (!$userId || !$deviceId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing user_id or device_id']);
    exit;
}

$stmt = $conn->prepare("UPDATE user_fcm_tokens SET is_active = 0 WHERE user_id = ? AND device_id = ?");
$stmt->bind_param("is", $userId, $deviceId);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'message' => 'Token removed']);
