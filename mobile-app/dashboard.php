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

// Get notifications implementation from topbar.php adapted for mobile
$notifications = [];

// Get current active period
$stmt_period = $conn->prepare("SELECT tahun_aktif, bulan_aktif FROM pegawai WHERE id_pegawai = ?");
$stmt_period->bind_param("i", $id_pegawai);
$stmt_period->execute();
$stmt_period->bind_result($tahun_aktif, $bulan_aktif);
$stmt_period->fetch();
$stmt_period->close();

$current_year = $tahun_aktif ?: (int)date('Y');
$current_month = $bulan_aktif ?: (int)date('m');

// Check if active period is different from current date
$real_current_year = (int)date('Y');
$real_current_month = (int)date('m');

if ($current_year != $real_current_year || $current_month != $real_current_month) {
    $period_message = '';
    $period_link = '';
    if ($current_year != $real_current_year) {
        $period_message = 'Periode Tahun RHK sebelumnya telah berakhir, silakan membuat periode baru';
        $period_link = 'rhk.php';
    } elseif ($current_month != $real_current_month) {
        $period_message = 'Periode Bulan RKB sebelumnya telah berakhir, silakan membuat periode baru';
        $period_link = 'rkb.php';
    }
    
    $notifications[] = [
        'type' => 'danger',
        'icon' => 'fas fa-exclamation-triangle',
        'message' => $period_message,
        'link' => $period_link
    ];
}

// Check for missing LKH entries in current period
$stmt_last_lkh = $conn->prepare("
    SELECT MAX(DATE(tanggal_lkh)) as last_lkh_date 
    FROM lkh 
    WHERE id_pegawai = ? 
    AND MONTH(tanggal_lkh) = ? 
    AND YEAR(tanggal_lkh) = ?
");
$stmt_last_lkh->bind_param("iii", $id_pegawai, $current_month, $current_year);
$stmt_last_lkh->execute();
$stmt_last_lkh->bind_result($last_lkh_date);
$stmt_last_lkh->fetch();
$stmt_last_lkh->close();

$today = date('Y-m-d');
$current_period_start = date('Y-m-01', strtotime("$current_year-$current_month-01"));

if ($last_lkh_date) {
    $last_entry = new DateTime($last_lkh_date);
    $today_date = new DateTime($today);
    $days_without_lkh = $last_entry->diff($today_date)->days;
    
    if ($days_without_lkh > 0) {
        if ($days_without_lkh == 1) {
            $lkh_message = "Hari ini anda belum mengisi laporan kinerja harian, silahkan mengisi laporan";
        } else {
            $lkh_message = "Anda sudah $days_without_lkh hari belum mengisi laporan kinerja harian, silahkan mengisi laporan";
        }
        
        $notifications[] = [
            'type' => 'warning',
            'icon' => 'fas fa-calendar-times',
            'message' => $lkh_message,
            'link' => 'lkh_add.php'
        ];
    }
} else {
    // No LKH entries found for this period at all
    $period_start = new DateTime($current_period_start);
    $today_date = new DateTime($today);
    $days_in_period = $period_start->diff($today_date)->days + 1;
    
    if ($days_in_period == 1) {
        $lkh_message = "Hari ini anda belum mengisi laporan kinerja harian, silahkan mengisi laporan";
    } else {
        $lkh_message = "Anda sudah $days_in_period hari belum mengisi laporan kinerja harian, silahkan mengisi laporan";
    }
    
    $notifications[] = [
        'type' => 'warning',
        'icon' => 'fas fa-calendar-times',
        'message' => $lkh_message,
        'link' => 'lkh_add.php'
    ];
}

// Check if reports have been generated for this period by checking filesystem
$reports_already_generated = false;

// Get NIP for filename generation
$stmt_nip = $conn->prepare("SELECT nip FROM pegawai WHERE id_pegawai = ?");
$stmt_nip->bind_param("i", $id_pegawai);
$stmt_nip->execute();
$stmt_nip->bind_result($nip_pegawai);
$stmt_nip->fetch();
$stmt_nip->close();

if ($nip_pegawai) {
    $nama_file_nip = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nip_pegawai);
    $months_name = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    // Check if LKB PDF exists
    $lkb_filename = __DIR__ . "/../generated/lkb_{$months_name[$current_month]}_{$current_year}_{$nama_file_nip}.pdf";
    $lkb_exists = file_exists($lkb_filename);
    
    // Check if LKH PDF exists  
    $lkh_filename = __DIR__ . "/../generated/LKH_{$months_name[$current_month]}_{$current_year}_{$nama_file_nip}.pdf";
    $lkh_exists = file_exists($lkh_filename);
    
    $reports_already_generated = ($lkb_exists || $lkh_exists);
}

// Check RKB status (only show if reports not generated)
if (!$reports_already_generated) {
    $stmt_rkb = $conn->prepare("SELECT status_verval FROM rkb WHERE id_pegawai = ? AND bulan = ? AND tahun = ? LIMIT 1");
    $stmt_rkb->bind_param("iii", $id_pegawai, $current_month, $current_year);
    $stmt_rkb->execute();
    $stmt_rkb->bind_result($rkb_status);
    $stmt_rkb->fetch();
    $stmt_rkb->close();
    
    // Check LKH status (only show if reports not generated)
    $stmt_lkh = $conn->prepare("SELECT status_verval FROM lkh WHERE id_pegawai = ? AND MONTH(tanggal_lkh) = ? AND YEAR(tanggal_lkh) = ? LIMIT 1");
    $stmt_lkh->bind_param("iii", $id_pegawai, $current_month, $current_year);
    $stmt_lkh->execute();
    $stmt_lkh->bind_result($lkh_status);
    $stmt_lkh->fetch();
    $stmt_lkh->close();
    
    // Build notifications for RKB/LKH status
    if ($rkb_status === 'diajukan') {
        $notifications[] = [
            'type' => 'info',
            'icon' => 'fas fa-clock',
            'message' => 'RKB Anda sudah diajukan, menunggu approval'
        ];
    }
    
    if ($lkh_status === 'diajukan') {
        $notifications[] = [
            'type' => 'info', 
            'icon' => 'fas fa-clock',
            'message' => 'LKH Anda sudah diajukan, menunggu approval'
        ];
    }
    
    if ($rkb_status === 'disetujui' && $lkh_status === 'disetujui') {
        $notifications[] = [
            'type' => 'success',
            'icon' => 'fas fa-check-circle',
            'message' => 'RKB/LKH periode ini sudah disetujui, Anda sudah bisa generate laporan',
            'link' => 'generate_rkb-lkh.php'
        ];
    } elseif ($rkb_status === 'disetujui') {
        $notifications[] = [
            'type' => 'info',
            'icon' => 'fas fa-info-circle', 
            'message' => 'RKB periode ini sudah disetujui'
        ];
    } elseif ($lkh_status === 'disetujui') {
        $notifications[] = [
            'type' => 'info',
            'icon' => 'fas fa-info-circle',
            'message' => 'LKH periode ini sudah disetujui'
        ];
    }
} else {
    // Reports have been generated - show success notification
    $months_name_notif = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $notifications[] = [
        'type' => 'success',
        'icon' => 'fas fa-trophy',
        'message' => "Selamat LKB/LKH anda di periode bulan {$months_name_notif[$current_month]} berhasil digenerate, rencanakan periode berikutnya!",
        'link' => 'generate_lkb-lkh.php'
    ];
}

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
            <!-- Notifications -->
            <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="alert alert-<?= $notification['type'] ?> d-flex align-items-start mb-3" role="alert">
                        <i class="<?= $notification['icon'] ?> me-3 mt-1"></i>
                        <div class="flex-grow-1">
                            <div class="mb-2"><?= htmlspecialchars($notification['message']) ?></div>
                            <?php if (isset($notification['link'])): ?>
                                <a href="<?= htmlspecialchars($notification['link']) ?>" class="btn btn-sm btn-outline-<?= $notification['type'] ?>">
                                    <i class="fas fa-arrow-right me-1"></i>
                                    <?php if (strpos($notification['link'], 'generate') !== false): ?>
                                        <?php if (strpos($notification['message'], 'Selamat') !== false): ?>
                                            Lihat Laporan
                                        <?php else: ?>
                                            Generate Laporan
                                        <?php endif; ?>
                                    <?php elseif (strpos($notification['link'], 'rhk') !== false): ?>
                                        Atur Periode RHK
                                    <?php elseif (strpos($notification['link'], 'rkb') !== false): ?>
                                        Atur Periode RKB
                                    <?php elseif (strpos($notification['link'], 'lkh') !== false): ?>
                                        Isi LKH Sekarang
                                    <?php endif; ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card notification-card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h6 class="text-muted">Semua dalam kondisi baik</h6>
                        <p class="text-muted mb-0">Tidak ada notifikasi yang perlu ditindaklanjuti</p>
                    </div>
                </div>
            <?php endif; ?>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
