<?php
session_start();
require_once __DIR__ . '/../template/session_admin.php';
require_once '../config/database.php';
require_once '../classes/FirebaseNotificationService.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$page_title = "Manajemen Notifikasi";
$message = '';
$message_type = '';

$firebaseService = new FirebaseNotificationService($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_notification'])) {
        $title = trim($_POST['title']);
        $messageText = trim($_POST['message']);
        $type = $_POST['type'];
        $targetUsers = $_POST['target_users'] ?? [];
        $topic = $_POST['topic'] ?? '';

        if (empty($title) || empty($messageText) || empty($type)) {
            $message = "Semua field wajib diisi";
            $message_type = "danger";
        } else {
            $stmt = $conn->prepare("INSERT INTO notifications (title, message, type, target_users, topic, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $targetUsersJson = json_encode($targetUsers);
                $stmt->bind_param("sssssi", $title, $messageText, $type, $targetUsersJson, $topic, $_SESSION['id_pegawai']);

                if ($stmt->execute()) {
                    $notificationId = $conn->insert_id;
                    $stmt->close();

                    try {
                        $result = null;
                        switch ($type) {
                            case 'all':
                                $result = $firebaseService->sendToAll($title, $messageText);
                                break;
                            case 'specific':
                                if (empty($targetUsers)) {
                                    throw new Exception("Pilih minimal satu pengguna");
                                }
                                $result = $firebaseService->sendToUsers($targetUsers, $title, $messageText);
                                break;
                            case 'topic':
                                if (empty($topic)) {
                                    throw new Exception("Pilih topik terlebih dahulu");
                                }
                                $result = $firebaseService->sendToTopic($topic, $title, $messageText);
                                break;
                            default:
                                throw new Exception("Tipe notifikasi tidak valid");
                        }

                        $status = $result['success'] ? 'sent' : 'failed';
                        $successCount = $result['total_success'] ?? 0;
                        $failureCount = $result['total_failure'] ?? 0;
                        $fcmResponse = json_encode($result);

                        $updateStmt = $conn->prepare("UPDATE notifications SET status = ?, sent_at = NOW(), success_count = ?, failure_count = ?, fcm_response = ? WHERE id = ?");
                        $updateStmt->bind_param("siisi", $status, $successCount, $failureCount, $fcmResponse, $notificationId);
                        $updateStmt->execute();
                        $updateStmt->close();

                        if ($result['success']) {
                            $message = "Notifikasi berhasil dikirim! Terkirim: {$successCount}, Gagal: {$failureCount}";
                            $message_type = "success";
                        } else {
                            $errorMsg = $result['error'] ?? 'Unknown error';
                            if (isset($result['response'])) {
                                $errorMsg .= ' | Response: ' . json_encode($result['response']);
                            }
                            $message = "Gagal mengirim notifikasi: " . $errorMsg;
                            $message_type = "danger";
                            error_log("FCM Error Details: " . json_encode($result));
                        }
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $message_type = "danger";

                        $updateStmt = $conn->prepare("UPDATE notifications SET status = 'failed', fcm_response = ? WHERE id = ?");
                        $errorResponse = json_encode(['error' => $e->getMessage()]);
                        $updateStmt->bind_param("si", $errorResponse, $notificationId);
                        $updateStmt->execute();
                        $updateStmt->close();
                    }
                } else {
                    $message = "Error saving notification: " . $stmt->error;
                    $message_type = "danger";
                    $stmt->close();
                }
            } else {
                $message = "Database error: " . $conn->error;
                $message_type = "danger";
            }
        }
    }
}

$notifications = $conn->query("SELECT n.*, p.nama as sender_name FROM notifications n LEFT JOIN pegawai p ON n.created_by = p.id_pegawai ORDER BY n.created_at DESC LIMIT 20");
$users = $conn->query("SELECT id_pegawai, nama, jabatan, unit_kerja FROM pegawai WHERE role = 'user' ORDER BY nama");
$stats = [
    'total' => $conn->query("SELECT COUNT(*) as count FROM notifications")->fetch_assoc()['count'],
    'sent' => $conn->query("SELECT COUNT(*) as count FROM notifications WHERE status = 'sent'")->fetch_assoc()['count'],
    'failed' => $conn->query("SELECT COUNT(*) as count FROM notifications WHERE status = 'failed'")->fetch_assoc()['count'],
    'tokens' => $conn->query("SELECT COUNT(*) as count FROM user_fcm_tokens WHERE is_active = 1")->fetch_assoc()['count']
];

include __DIR__ . '/../template/header.php';
include __DIR__ . '/../template/menu_admin.php';
include __DIR__ . '/../template/topbar.php';
