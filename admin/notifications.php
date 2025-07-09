<?php
session_start();
require_once __DIR__ . '/../template/session_admin.php';
require_once '../config/database.php';
require_once '../classes/FirebaseNotificationService.php';

$page_title = "Manajemen Notifikasi";
$message = '';
$message_type = '';

// Initialize Firebase service
$firebaseService = new FirebaseNotificationService($conn);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_notification'])) {
        $title = trim($_POST['title']);
        $messageText = trim($_POST['message']);
        $type = $_POST['type'];
        $targetUsers = $_POST['target_users'] ?? [];
        $topic = $_POST['topic'] ?? '';
        
        // Save notification to database
        $stmt = $conn->prepare("
            INSERT INTO notifications (title, message, type, target_users, topic, created_by) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $targetUsersJson = json_encode($targetUsers);
        $stmt->bind_param("sssssi", $title, $messageText, $type, $targetUsersJson, $topic, $_SESSION['id_pegawai']);
        
        if ($stmt->execute()) {
            $notificationId = $conn->insert_id;
            $stmt->close();
            
            // Send notification
            try {
                $result = null;
                switch ($type) {
                    case 'all':
                        $result = $firebaseService->sendToAll($title, $messageText);
                        break;
                    case 'specific':
                        $result = $firebaseService->sendToUsers($targetUsers, $title, $messageText);
                        break;
                    case 'topic':
                        $result = $firebaseService->sendToTopic($topic, $title, $messageText);
                        break;
                }
                
                // Update notification status
                $status = $result['success'] ? 'sent' : 'failed';
                $successCount = $result['total_success'] ?? 0;
                $failureCount = $result['total_failure'] ?? 0;
                $fcmResponse = json_encode($result);
                
                $updateStmt = $conn->prepare("
                    UPDATE notifications 
                    SET status = ?, sent_at = NOW(), success_count = ?, failure_count = ?, fcm_response = ?
                    WHERE id = ?
                ");
                $updateStmt->bind_param("siisi", $status, $successCount, $failureCount, $fcmResponse, $notificationId);
                $updateStmt->execute();
                $updateStmt->close();
                
                if ($result['success']) {
                    $message = "Notifikasi berhasil dikirim! Terkirim: {$successCount}, Gagal: {$failureCount}";
                    $message_type = "success";
                } else {
                    $message = "Gagal mengirim notifikasi: " . ($result['error'] ?? 'Unknown error');
                    $message_type = "danger";
                }
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $message_type = "danger";
            }
        } else {
            $message = "Error saving notification: " . $stmt->error;
            $message_type = "danger";
            $stmt->close();
        }
    }
}

// Get notifications list
$notifications = $conn->query("
    SELECT n.*, p.nama as sender_name 
    FROM notifications n 
    LEFT JOIN pegawai p ON n.created_by = p.id_pegawai 
    ORDER BY n.created_at DESC 
    LIMIT 20
");

// Get users for targeting
$users = $conn->query("SELECT id_pegawai, nama, jabatan, unit_kerja FROM pegawai WHERE role = 'user' ORDER BY nama");

// Get notification statistics
$stats = [
    'total' => $conn->query("SELECT COUNT(*) as count FROM notifications")->fetch_assoc()['count'],
    'sent' => $conn->query("SELECT COUNT(*) as count FROM notifications WHERE status = 'sent'")->fetch_assoc()['count'],
    'failed' => $conn->query("SELECT COUNT(*) as count FROM notifications WHERE status = 'failed'")->fetch_assoc()['count'],
    'tokens' => $conn->query("SELECT COUNT(*) as count FROM user_fcm_tokens WHERE is_active = 1")->fetch_assoc()['count']
];

include __DIR__ . '/../template/header.php';
include __DIR__ . '/../template/menu_admin.php';
include __DIR__ . '/../template/topbar.php';
?>

<div id="layoutSidenav_content">
    <main>
        <div class="container-fluid px-4">
            <h1 class="mt-4 mb-3"><i class="fas fa-bell"></i> Manajemen Notifikasi</h1>
            <p class="lead">Kirim notifikasi push ke aplikasi mobile pegawai.</p>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-primary text-white mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="small text-white-50">Total Notifikasi</div>
                                    <div class="h2"><?php echo $stats['total']; ?></div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-bell fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-success text-white mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="small text-white-50">Berhasil Terkirim</div>
                                    <div class="h2"><?php echo $stats['sent']; ?></div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-danger text-white mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="small text-white-50">Gagal Terkirim</div>
                                    <div class="h2"><?php echo $stats['failed']; ?></div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-times-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-info text-white mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="small text-white-50">Device Terdaftar</div>
                                    <div class="h2"><?php echo $stats['tokens']; ?></div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-mobile-alt fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Send Notification Form -->
                <div class="col-lg-6">
                    <div class="card shadow">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-paper-plane me-2"></i>Kirim Notifikasi Baru</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Judul Notifikasi *</label>
                                    <input type="text" class="form-control" id="title" name="title" required maxlength="100"
                                           placeholder="Masukkan judul notifikasi...">
                                </div>

                                <div class="mb-3">
                                    <label for="message" class="form-label">Pesan Notifikasi *</label>
                                    <textarea class="form-control" id="message" name="message" rows="4" required maxlength="500"
                                              placeholder="Masukkan pesan notifikasi..."></textarea>
                                    <small class="text-muted">Maksimal 500 karakter</small>
                                </div>

                                <div class="mb-3">
                                    <label for="type" class="form-label">Tipe Pengiriman *</label>
                                    <select class="form-select" id="type" name="type" required onchange="toggleTargetOptions()">
                                        <option value="">Pilih Tipe</option>
                                        <option value="all">Semua Pengguna</option>
                                        <option value="specific">Pengguna Tertentu</option>
                                        <option value="topic">Berdasarkan Topik</option>
                                    </select>
                                </div>

                                <!-- Specific Users Selection -->
                                <div class="mb-3 d-none" id="users-selection">
                                    <label for="target_users" class="form-label">Pilih Pengguna</label>
                                    <select class="form-select" id="target_users" name="target_users[]" multiple size="6">
                                        <?php while ($user = $users->fetch_assoc()): ?>
                                            <option value="<?php echo $user['id_pegawai']; ?>">
                                                <?php echo htmlspecialchars($user['nama']); ?> - 
                                                <?php echo htmlspecialchars($user['jabatan']); ?> 
                                                (<?php echo htmlspecialchars($user['unit_kerja']); ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <small class="text-muted">Tahan Ctrl untuk memilih multiple pengguna</small>
                                </div>

                                <!-- Topic Selection -->
                                <div class="mb-3 d-none" id="topic-selection">
                                    <label for="topic" class="form-label">Pilih Topik</label>
                                    <select class="form-select" id="topic" name="topic">
                                        <option value="">Pilih Topik</option>
                                        <option value="guru">Guru</option>
                                        <option value="admin">Admin</option>
                                        <option value="umum">Umum</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" name="send_notification" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i>Kirim Notifikasi
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Notifications History -->
                <div class="col-lg-6">
                    <div class="card shadow">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Riwayat Notifikasi</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Judul</th>
                                            <th>Status</th>
                                            <th>Terkirim</th>
                                            <th>Tanggal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($notifications && $notifications->num_rows > 0): ?>
                                            <?php while ($notif = $notifications->fetch_assoc()): ?>
                                                <tr>
                                                    <td class="text-truncate" style="max-width: 150px;" 
                                                        title="<?php echo htmlspecialchars($notif['title']); ?>">
                                                        <?php echo htmlspecialchars($notif['title']); ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($notif['status'] === 'sent'): ?>
                                                            <span class="badge bg-success">Terkirim</span>
                                                        <?php elseif ($notif['status'] === 'failed'): ?>
                                                            <span class="badge bg-danger">Gagal</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">Draft</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo $notif['success_count']; ?></td>
                                                    <td><?php echo date('d/m H:i', strtotime($notif['created_at'])); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">Belum ada notifikasi</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <?php include __DIR__ . '/../template/footer.php'; ?>
</div>

<script>
function toggleTargetOptions() {
    const type = document.getElementById('type').value;
    const usersSelection = document.getElementById('users-selection');
    const topicSelection = document.getElementById('topic-selection');
    
    // Hide all selections first
    usersSelection.classList.add('d-none');
    topicSelection.classList.add('d-none');
    
    // Show relevant selection
    if (type === 'specific') {
        usersSelection.classList.remove('d-none');
        document.getElementById('target_users').required = true;
        document.getElementById('topic').required = false;
    } else if (type === 'topic') {
        topicSelection.classList.remove('d-none');
        document.getElementById('topic').required = true;
        document.getElementById('target_users').required = false;
    } else {
        document.getElementById('target_users').required = false;
        document.getElementById('topic').required = false;
    }
}

// Character counter for message
document.getElementById('message').addEventListener('input', function() {
    const maxLength = 500;
    const currentLength = this.value.length;
    const remaining = maxLength - currentLength;
    
    let counterElement = document.getElementById('char-counter');
    if (!counterElement) {
        counterElement = document.createElement('small');
        counterElement.id = 'char-counter';
        counterElement.classList.add('text-muted');
        this.parentNode.appendChild(counterElement);
    }
    
    counterElement.textContent = `${currentLength}/${maxLength} karakter`;
    
    if (remaining < 50) {
        counterElement.classList.remove('text-muted');
        counterElement.classList.add('text-warning');
    } else {
        counterElement.classList.remove('text-warning');
        counterElement.classList.add('text-muted');
    }
});
</script>
