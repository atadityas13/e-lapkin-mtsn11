<?php
/**
 * E-LAPKIN Mobile Dashboard
 */

// Start output buffering to catch any unwanted output
ob_start();

session_start();

// Include mobile session config
require_once __DIR__ . '/config/mobile_session.php';
require_once __DIR__ . '/../config/database.php';

// Check mobile login (only validate session, not headers for dashboard)
checkMobileLogin();

// Get user session data
$userData = getMobileSessionData();

// Handle logout first
if (isset($_POST['logout'])) {
    mobileLogout();
}

// Get user details from database
$id_pegawai = $userData['id_pegawai'];
$stmt = $conn->prepare("SELECT nip, nama, jabatan FROM pegawai WHERE id_pegawai = ?");
$stmt->bind_param("i", $id_pegawai);
$stmt->execute();
$result = $stmt->get_result();
$userInfo = $result->fetch_assoc();
$stmt->close();

// Check if user has submitted LKH today
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM lkh WHERE id_pegawai = ? AND DATE(tanggal_lkh) = ?");
$stmt->bind_param("is", $id_pegawai, $today);
$stmt->execute();
$result = $stmt->get_result();
$todayLkh = $result->fetch_assoc();
$hasLkhToday = $todayLkh['count'] > 0;
$stmt->close();

// Clear any unwanted output before HTML
ob_clean();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-LAPKIN Mobile - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .notification-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #dee2e6;
            z-index: 1000;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
        }
        .bottom-nav .nav-item {
            flex: 1;
            text-align: center;
        }
        .bottom-nav .nav-link {
            padding: 10px 8px;
            color: #6c757d;
            text-decoration: none;
            display: block;
            font-size: 12px;
        }
        .bottom-nav .nav-link.active {
            color: #0d6efd;
            background-color: rgba(13, 110, 253, 0.1);
        }
        .bottom-nav .nav-link i {
            font-size: 16px;
            margin-bottom: 4px;
        }
        .main-content {
            padding-bottom: 100px;
        }
        body {
            padding-bottom: 70px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="main-content">
        <!-- Header -->
        <div class="gradient-bg text-white p-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">E-LAPKIN</h5>
                    <small>Selamat Datang</small>
                </div>
                <div class="text-end">
                    <div class="dropdown">
                        <button class="btn btn-link text-white p-0" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle fa-2x"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text"><strong><?= htmlspecialchars($userInfo['nama']) ?></strong></span></li>
                            <li><span class="dropdown-item-text">NIP: <?= htmlspecialchars($userInfo['nip']) ?></span></li>
                            <li><span class="dropdown-item-text"><?= htmlspecialchars($userInfo['jabatan']) ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" style="display: inline;">
                                    <button type="submit" name="logout" class="dropdown-item text-danger">
                                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <h6 class="mb-1"><?= htmlspecialchars($userInfo['nama']) ?></h6>
                <small>NIP: <?= htmlspecialchars($userInfo['nip']) ?></small><br>
                <small><?= htmlspecialchars($userInfo['jabatan']) ?></small>
            </div>
        </div>

        <!-- Main Content -->
        <div class="container-fluid px-3 mt-3">
            <!-- LKH Reminder -->
            <?php if (!$hasLkhToday): ?>
                <div class="card notification-card mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-exclamation-circle fa-2x text-warning"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">Laporan Kinerja Harian</h6>
                                <p class="mb-2 small">Hari ini tanggal <?= date('d F Y') ?>, Anda belum mengisi laporan Kinerja Harian.</p>
                                <a href="lkh_add.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i>Laporkan Sekarang
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card notification-card mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-check-circle fa-2x text-success"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">Laporan Kinerja Harian</h6>
                                <p class="mb-0 small">Anda sudah mengisi laporan kinerja harian untuk hari ini. Terima kasih!</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="card notification-card">
                <div class="card-body">
                    <h6 class="card-title mb-3"><i class="fas fa-bolt text-warning me-2"></i>Aksi Cepat</h6>
                    <div class="row g-2">
                        <div class="col-12">
                            <a href="lkh_add.php" class="btn btn-primary w-100 py-3">
                                <i class="fas fa-plus-circle fa-lg mb-2 d-block"></i>
                                <span>Tambah Laporan Kinerja Harian</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <div class="bottom-nav">
        <div class="d-flex">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link active">
                    <i class="fas fa-home d-block"></i>
                    <small>Beranda</small>
                </a>
            </div>
            <div class="nav-item">
                <a href="lkh.php" class="nav-link">
                    <i class="fas fa-list d-block"></i>
                    <small>LKH</small>
                </a>
            </div>
            <div class="nav-item">
                <a href="rkb.php" class="nav-link">
                    <i class="fas fa-calendar d-block"></i>
                    <small>RKB</small>
                </a>
            </div>
            <div class="nav-item">
                <a href="rhk.php" class="nav-link">
                    <i class="fas fa-tasks d-block"></i>
                    <small>RHK</small>
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
