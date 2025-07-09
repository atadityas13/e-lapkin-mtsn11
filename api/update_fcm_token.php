<?php
// Ambil user_id dan fcm_token dari request POST
$userId = $_POST['user_id'] ?? null;
$fcmToken = $_POST['fcm_token'] ?? null;

// Cek apakah userId dan fcmToken ada
if ($userId && $fcmToken) {
    // Simpan/update token untuk user_id ini
    // ...existing code...
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Missing user_id or fcm_token']);
}