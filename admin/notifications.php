<?php
class FirebaseNotificationService
{
    private $conn;
    private $fcmUrl = 'https://fcm.googleapis.com/fcm/send'; // ✅ Gunakan endpoint Legacy API
    private $serverKey = 'YOUR_SERVER_KEY_HERE'; // ✅ Ganti dengan Server Key dari Firebase

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    // Kirim ke semua pengguna (device token aktif)
    public function sendToAll($title, $message)
    {
        $tokens = $this->getAllActiveTokens();
        return $this->sendBulkNotification($tokens, $title, $message);
    }

    // Kirim ke pengguna tertentu berdasarkan id_pegawai
    public function sendToUsers(array $userIds, $title, $message)
    {
        $tokens = $this->getTokensByUserIds($userIds);
        return $this->sendBulkNotification($tokens, $title, $message);
    }

    // Kirim ke topik FCM
    public function sendToTopic($topic, $title, $message)
    {
        $payload = [
            'to' => "/topics/{$topic}",
            'notification' => [
                'title' => $title,
                'body' => $message,
                'sound' => 'default'
            ],
            'priority' => 'high'
        ];

        return $this->sendRequest($payload);
    }

    // ==========================
    // PRIVATE HELPER FUNCTIONS
    // ==========================

    private function getAllActiveTokens()
    {
        $tokens = [];
        $result = $this->conn->query("SELECT token FROM user_fcm_tokens WHERE is_active = 1");
        while ($row = $result->fetch_assoc()) {
            $tokens[] = $row['token'];
        }
        return $tokens;
    }

    private function getTokensByUserIds(array $userIds)
    {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $types = str_repeat('i', count($userIds));
        $stmt = $this->conn->prepare("SELECT token FROM user_fcm_tokens WHERE id_pegawai IN ($placeholders) AND is_active = 1");

        $stmt->bind_param($types, ...$userIds);
        $stmt->execute();
        $result = $stmt->get_result();

        $tokens = [];
        while ($row = $result->fetch_assoc()) {
            $tokens[] = $row['token'];
        }
        return $tokens;
    }

    private function sendBulkNotification(array $tokens, $title, $message)
    {
        $success = 0;
        $failure = 0;
        $responses = [];

        foreach ($tokens as $token) {
            $payload = [
                'to' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $message,
                    'sound' => 'default'
                ],
                'priority' => 'high'
            ];

            $response = $this->sendRequest($payload);
            $responses[] = $response;

            if ($response['http_code'] == 200) {
                $data = json_decode($response['raw_response'], true);
                if (isset($data['success']) && $data['success'] == 1) {
                    $success++;
                } else {
                    $failure++;
                }
            } else {
                $failure++;
            }
        }

        return [
            'success' => $success > 0,
            'total_success' => $success,
            'total_failure' => $failure,
            'responses' => $responses
        ];
    }

    private function sendRequest(array $payload)
    {
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
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'http_code' => $httpCode,
            'raw_response' => $result
        ];
    }
}
