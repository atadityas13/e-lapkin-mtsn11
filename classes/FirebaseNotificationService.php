<?php
// Hapus baris ini karena kita akan menggunakan Composer autoload:
// require_once __DIR__ . '/../config/firebase_config.php';

// Pastikan path ke autoload Composer sudah benar
// Asumsi: file ini (FirebaseNotificationService.php) berada di 'path/to/your-app/services/'
// dan folder 'vendor' berada di 'path/to/your-app/'
require __DIR__ . '/../vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Exception\FirebaseException; // Tambahkan untuk penanganan error inisialisasi

class FirebaseNotificationService
{
    private $conn;
    private $messaging; // Instance Firebase Messaging

    public function __construct($database_connection)
    {
        $this->conn = $database_connection;
        
        // Path ke file JSON akun layanan Anda
        $serviceAccountPath = __DIR__ . '/../config/cbtapp-mtsn-11-majalengka-firebase-adminsdk-fbsvc-da2685b508.json'; 

        // Log path file service account yang digunakan
        error_log("Firebase Service Account path: " . $serviceAccountPath);

        // Cek environment variable GOOGLE_APPLICATION_CREDENTIALS
        $envCred = getenv('GOOGLE_APPLICATION_CREDENTIALS');
        if ($envCred) {
            error_log("GOOGLE_APPLICATION_CREDENTIALS env: " . $envCred);
        } else {
            error_log("GOOGLE_APPLICATION_CREDENTIALS env not set.");
        }

        // Saran cek waktu server
        error_log("PENTING: Pastikan waktu server sinkron (gunakan NTP). Error 'invalid_grant' sering terjadi jika waktu server tidak akurat.");

        if (!file_exists($serviceAccountPath)) {
            $errorMsg = "Firebase Service Account JSON file not found at: " . $serviceAccountPath;
            error_log($errorMsg);
            // Anda mungkin ingin melempar exception fatal di sini jika file ini mutlak diperlukan
            // throw new \Exception($errorMsg);
            return; // Hentikan eksekusi jika file tidak ditemukan
        }

        try {
            $factory = (new Factory)->withServiceAccount($serviceAccountPath);
            $this->messaging = $factory->createMessaging();
            error_log("Firebase Messaging initialized successfully.");
        } catch (FirebaseException $e) {
            $errorMsg = "Error initializing Firebase Messaging: " . $e->getMessage();
            error_log($errorMsg);
            // throw new \Exception($errorMsg, 0, $e); // Melempar exception untuk penanganan lebih lanjut
        } catch (\Exception $e) { // Tangkap exception umum lainnya
            $errorMsg = "An unexpected error occurred during Firebase Messaging initialization: " . $e->getMessage();
            error_log($errorMsg);
            // throw new \Exception($errorMsg, 0, $e);
        }
    }

    // Metode getFcmServerKey() tidak lagi diperlukan, bisa dihapus
    // private function getFcmServerKey() { ... }

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
        if (!$this->messaging) {
            error_log("Firebase Messaging not initialized. Cannot send to topic.");
            return ['success' => false, 'error' => 'Firebase Messaging not initialized', 'total_success' => 0, 'total_failure' => 0];
        }

        $notification = Notification::create($title, $message);
        
        // Data kustom yang akan dikirim ke aplikasi Android
        $customData = array_merge($data, [
            'title' => $title,
            'message' => $message,
            'type' => 'topic_message', // Tambahkan tipe untuk penanganan di Android
            'topic' => $topic
        ]);

        $messageObj = CloudMessage::withTarget('topic', $topic)
                                ->withNotification($notification)
                                ->withData($customData); // Gunakan data kustom

        try {
            $this->messaging->send($messageObj);
            error_log("FCM Topic sent successfully to: " . $topic);
            return [
                'success' => true,
                'total_success' => 1,
                'total_failure' => 0,
                'response' => 'Message sent to topic successfully'
            ];
        } catch (MessagingException $e) {
            error_log("FCM Topic Error for topic '" . $topic . "': " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'total_success' => 0,
                'total_failure' => 1,
                'response' => null
            ];
        }
    }

    /**
     * Send notification to multiple tokens
     */
    public function sendToMultiple($tokens, $title, $message, $data = [])
    {
        if (!$this->messaging) {
            error_log("Firebase Messaging not initialized. Cannot send to multiple tokens.");
            return ['success' => false, 'error' => 'Firebase Messaging not initialized', 'total_success' => 0, 'total_failure' => 0];
        }
        if (empty($tokens)) {
            return ['success' => false, 'error' => 'No tokens provided', 'total_success' => 0, 'total_failure' => 0];
        }

        $notification = Notification::create($title, $message);
        $customData = array_merge($data, [
            'title' => $title,
            'message' => $message,
            'type' => 'direct_message' // Tambahkan tipe untuk penanganan di Android
        ]);

        $messageObj = CloudMessage::new()
                                ->withNotification($notification)
                                ->withData($customData); // Gunakan data kustom

        $report = null;
        try {
            // sendMulticast akan secara otomatis membagi menjadi batch 500
            $report = $this->messaging->sendMulticast($messageObj, $tokens);
            
            $successCount = $report->successCount();
            $failureCount = $report->failureCount();

            // Handle invalid/expired tokens (NotRegistered, InvalidArgument)
            foreach ($report->failures()->getItems() as $failure) {
                // target()->value() mengembalikan token FCM yang gagal
                $failedToken = $failure->target()->value();
                $errorMessage = $failure->error()->getMessage();

                if ($errorMessage === 'NotRegistered' || $errorMessage === 'InvalidArgument') {
                    // Token tidak valid, hapus atau nonaktifkan dari database
                    $this->deactivateToken($failedToken); 
                    error_log("FCM: Invalid token '" . $failedToken . "' - Deactivated. Error: " . $errorMessage);
                } else {
                    // Log error lainnya jika diperlukan
                    error_log("FCM: Error for token '" . $failedToken . "': " . $errorMessage);
                }
                // Anda juga bisa memeriksa untuk token yang perlu di-refresh, tapi ini jarang terjadi dengan FCM v1
                // Jika ada kasus where token berubah, FCM akan mengirim 'Canonical ID' di response lama.
                // Firebase Admin SDK biasanya menanganinya secara internal jika ada.
            }

            return [
                'success' => $successCount > 0,
                'total_success' => $successCount,
                'total_failure' => $failureCount,
                'responses' => $report->getResults()
            ];

        } catch (MessagingException $e) {
            error_log("FCM Multicast Error: " . $e->getMessage());
            // Tambahkan log detail jika error invalid_grant
            if (strpos($e->getMessage(), 'invalid_grant') !== false) {
                error_log("=== PENTING: Error 'invalid_grant' biasanya disebabkan oleh waktu server yang tidak sinkron, kredensial service account salah/expired, atau environment variable GOOGLE_APPLICATION_CREDENTIALS tidak sesuai. ===");
                error_log("=== Saran: Cek waktu server (date -u), cek file service-account.json, dan pastikan environment variable sudah benar. ===");
            }
            // Log JSON error detail agar mudah dicek di log file
            $errorDetails = [
                'success' => false,
                'error' => $e->getMessage(),
                'total_success' => 0,
                'total_failure' => count($tokens),
                'response' => null
            ];
            error_log("FCM Error Details: " . json_encode($errorDetails));
            return $errorDetails;
        }
    }

    // Metode sendFcmRequest() tidak lagi diperlukan, karena digantikan oleh Firebase Admin SDK.
    // private function sendFcmRequest($payload) { ... }

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
        if (empty($userIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $this->conn->prepare("SELECT fcm_token FROM user_fcm_tokens WHERE user_id IN ($placeholders) AND is_active = 1");
        
        if (!$stmt) {
            error_log("Database error in getTokensByUserIds: " . $this->conn->error);
            return [];
        }
        
        // Build types string dynamically based on number of user IDs
        $types = str_repeat('i', count($userIds));
        $stmt->bind_param($types, ...$userIds); // Menggunakan splat operator untuk array
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
    public function saveUserToken($userId, $fcmToken, $deviceType = 'android', $appVersion = null, $deviceId = null)
    {
        // Untuk on DUPLICATE KEY UPDATE, fcm_token harus menjadi UNIQUE KEY
        // atau gabungan (user_id, device_id) jika device_id juga unik per user.
        // Asumsi fcm_token adalah UNIQUE KEY.

        // Pertama, nonaktifkan token lama untuk user_id tertentu jika ada
        // agar hanya ada satu token aktif per user_id, device_type (dan device_id jika relevan)
        // Jika Anda ingin mendukung banyak perangkat per pengguna, sesuaikan logika ini.
        // Misalnya: UPDATE user_fcm_tokens SET is_active = 0 WHERE user_id = ? AND device_id = ?;
        // Current logic deactivates all tokens for a user_id which is okay for single device per user.
        $stmt = $this->conn->prepare("UPDATE user_fcm_tokens SET is_active = 0 WHERE user_id = ? AND fcm_token != ?");
        if ($stmt) {
            $stmt->bind_param("is", $userId, $fcmToken);
            $stmt->execute();
            $stmt->close();
        }

        // Insert or update the new token
        // Menambahkan device_id ke kolom INSERT/UPDATE
        $stmt = $this->conn->prepare("
            INSERT INTO user_fcm_tokens (user_id, fcm_token, device_id, device_type, app_version, last_used_at, is_active) 
            VALUES (?, ?, ?, ?, ?, NOW(), 1)
            ON DUPLICATE KEY UPDATE 
            device_id = VALUES(device_id),
            device_type = VALUES(device_type),
            app_version = VALUES(app_version),
            last_used_at = NOW(),
            is_active = 1
        ");
        
        if ($stmt) {
            // Pastikan tipe parameter sesuai: i (int), s (string)
            $stmt->bind_param("issss", $userId, $fcmToken, $deviceId, $deviceType, $appVersion);
            $result = $stmt->execute();
            if (!$result) {
                error_log("Error saving FCM token: " . $stmt->error);
            }
            $stmt->close();
            return $result;
        }
        error_log("Failed to prepare saveUserToken statement: " . $this->conn->error);
        return false;
    }

    // Metode baru untuk deactivate/update token, seperti yang disarankan sebelumnya
    private function deactivateToken($fcmToken) {
        $stmt = $this->conn->prepare("UPDATE user_fcm_tokens SET is_active = 0 WHERE fcm_token = ?");
        if ($stmt) {
            $stmt->bind_param("s", $fcmToken);
            $stmt->execute();
            $stmt->close();
            error_log("Token " . $fcmToken . " deactivated in DB.");
        } else {
            error_log("Error preparing deactivateToken statement: " . $this->conn->error);
        }
    }

    // Metode updateToken jarang digunakan dengan FCM v1 karena token jarang berubah
    // kecuali jika Anda menerima informasi update token secara eksplisit dari FCM
    // Misalnya, jika FCM mengembalikan 'registration_id' baru.
    // Firebase Admin SDK seharusnya sudah menangani ini secara internal.
    // private function updateToken($oldToken, $newToken) {
    //     $stmt = $this->conn->prepare("UPDATE user_fcm_tokens SET fcm_token = ?, last_used_at = NOW(), is_active = 1 WHERE fcm_token = ?");
    //     if ($stmt) {
    //         $stmt->bind_param("ss", $newToken, $oldToken);
    //         $stmt->execute();
    //         $stmt->close();
    //         error_log("Token " . $oldToken . " updated to " . $newToken . " in DB.");
    //     } else {
    //         error_log("Error preparing updateToken statement: " . $this->conn->error);
    //     }
    // }
}