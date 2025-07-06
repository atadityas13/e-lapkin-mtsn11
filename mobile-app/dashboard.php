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
$stmt = $conn->prepare("SELECT nip, nama, jabatan, unit_kerja, foto_profil FROM pegawai WHERE id_pegawai = ?");
$stmt->bind_param("i", $id_pegawai);
$stmt->execute();
$result = $stmt->get_result();
$userInfo = $result->fetch_assoc();
$stmt->close();

// Handle profile photo path
$foto_profil = $userInfo['foto_profil'] ?? '';
$photo_web_path = '';
$photo_file_path = '';

if (!empty($foto_profil)) {
    if (strpos($foto_profil, 'uploads/foto_profil/') === 0) {
        $photo_web_path = '../' . $foto_profil;
        $photo_file_path = __DIR__ . '/../' . $foto_profil;
    } else {
        $photo_web_path = '../uploads/foto_profil/' . $foto_profil;
        $photo_file_path = __DIR__ . '/../uploads/foto_profil/' . $foto_profil;
    }
}

// Get current date and time in Indonesian
$days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
          'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

$day_name = $days[date('w')];
$month_name = $months[date('n')];
$current_datetime = $day_name . ', ' . date('d') . ' ' . $month_name . ' ' . date('Y') . ' - ' . date('H:i');

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
        .profile-img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.3);
        }
        .notification-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid;
            margin-bottom: 12px;
        }
        .notification-card.danger {
            border-left-color: #dc3545;
            background: linear-gradient(135deg, #fff5f5 0%, #ffeaea 100%);
        }
        .notification-card.warning {
            border-left-color: #ffc107;
            background: linear-gradient(135deg, #fffbf0 0%, #fff3cd 100%);
        }
        .notification-card.info {
            border-left-color: #0dcaf0;
            background: linear-gradient(135deg, #f0fbff 0%, #d1ecf1 100%);
        }
        .notification-card.success {
            border-left-color: #198754;
            background: linear-gradient(135deg, #f0fff4 0%, #d4edda 100%);
        }
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }
        .notification-icon.danger { background: #dc3545; }
        .notification-icon.warning { background: #ffc107; color: #000; }
        .notification-icon.info { background: #0dcaf0; }
        .notification-icon.success { background: #198754; }
        .notification-btn {
            border-radius: 20px;
            font-size: 12px;
            padding: 6px 16px;
            font-weight: 500;
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
        .datetime-text {
            font-size: 13px;
            opacity: 0.9;
        }
    </style>
</head>
<body class="bg-light">
    <div class="main-content">
        <!-- Header -->
        <div class="gradient-bg text-white p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-1">E-LAPKIN</h5>
                    <small class="datetime-text"><?= $current_datetime ?></small>
                </div>
                <div class="text-end">
                    <div class="dropdown">
                        <button class="btn btn-link text-white p-0" type="button" data-bs-toggle="dropdown">
                            <?php if (!empty($foto_profil) && file_exists($photo_file_path)): ?>
                                <img src="<?= htmlspecialchars($photo_web_path) ?>" alt="Foto Profil" class="profile-img">
                            <?php else: ?>
                                <i class="fas fa-user-circle fa-3x"></i>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text"><strong><?= htmlspecialchars($userInfo['nama']) ?></strong></span></li>
                            <li><span class="dropdown-item-text">NIP: <?= htmlspecialchars($userInfo['nip']) ?></span></li>
                            <li><span class="dropdown-item-text"><?= htmlspecialchars($userInfo['jabatan']) ?></span></li>
                            <li><span class="dropdown-item-text"><?= htmlspecialchars($userInfo['unit_kerja']) ?></span></li>
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
            <div class="d-flex align-items-center">
                <?php if (!empty($foto_profil) && file_exists($photo_file_path)): ?>
                    <img src="<?= htmlspecialchars($photo_web_path) ?>" alt="Foto Profil" class="profile-img me-3">
                <?php else: ?>
                    <div class="profile-img me-3 d-flex align-items-center justify-content-center" style="background: rgba(255,255,255,0.2);">
                        <i class="fas fa-user text-white"></i>
                    </div>
                <?php endif; ?>
                <div>
                    <h6 class="mb-1"><?= htmlspecialchars($userInfo['nama']) ?></h6>
                    <small>NIP: <?= htmlspecialchars($userInfo['nip']) ?></small><br>
                    <small><?= htmlspecialchars($userInfo['jabatan']) ?></small><br>
                    <small><?= htmlspecialchars($userInfo['unit_kerja']) ?></small>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="container-fluid px-3 mt-3">
            <!-- App Info Section -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div class="notification-card info p-3">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-mobile-alt me-2"></i>Aplikasi E-LAPKIN
                        </h6>
                        <p class="mb-3" style="font-size: 14px;">
                            Aplikasi E-LAPKIN digunakan untuk pengelolaan Laporan Kinerja Pegawai di lingkungan MTsN 11 Majalengka.
                        </p>
                        <div class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <small>Input RHK, RKB, dan LKH secara digital</small>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <small>Rekap dan cetak laporan kinerja bulanan</small>
                        </div>
                        <div class="mb-0">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <small>Penilaian kinerja lebih efektif dan efisien</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="notification-card warning p-3">
                        <h6 class="text-warning mb-3">
                            <i class="fas fa-question-circle me-2"></i>Sudahkah Anda mengisi kegiatan hari ini?
                        </h6>
                        <p class="mb-3" style="font-size: 14px;">
                            Pastikan Anda melaporkan aktivitas harian Anda secara rutin untuk menjaga akurasi kinerja.
                        </p>
                        <a href="lkh_add.php" class="btn btn-warning btn-sm">
                            <i class="fas fa-plus me-1"></i>Isi Laporan Harian
                        </a>
                    </div>
                </div>
            </div>

            <!-- Notifications -->
            <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-card <?= $notification['type'] ?> p-3">
                        <div class="d-flex align-items-start">
                            <div class="notification-icon <?= $notification['type'] ?> me-3">
                                <i class="<?= $notification['icon'] ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold mb-2" style="font-size: 14px;"><?= htmlspecialchars($notification['message']) ?></div>
                                <?php if (isset($notification['link'])): ?>
                                    <a href="<?= htmlspecialchars($notification['link']) ?>" class="btn btn-sm notification-btn btn-<?= $notification['type'] ?>">
                                        <?php if (strpos($notification['link'], 'generate') !== false): ?>
                                            <?php if (strpos($notification['message'], 'Selamat') !== false): ?>
                                                <i class="fas fa-eye me-1"></i>Lihat Laporan
                                            <?php else: ?>
                                                <i class="fas fa-download me-1"></i>Generate Laporan
                                            <?php endif; ?>
                                        <?php elseif (strpos($notification['link'], 'rhk') !== false): ?>
                                            <i class="fas fa-cog me-1"></i>Atur Periode RHK
                                        <?php elseif (strpos($notification['link'], 'rkb') !== false): ?>
                                            <i class="fas fa-calendar-plus me-1"></i>Atur Periode RKB
                                        <?php elseif (strpos($notification['link'], 'lkh') !== false): ?>
                                            <i class="fas fa-plus me-1"></i>Isi LKH Sekarang
                                        <?php endif; ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="notification-card success p-4 text-center">
                    <div class="notification-icon success mx-auto mb-3">
                        <i class="fas fa-check"></i>
                    </div>
                    <h6 class="text-success mb-2">Semua dalam kondisi baik</h6>
                    <p class="text-muted mb-0" style="font-size: 14px;">Tidak ada notifikasi yang perlu ditindaklanjuti</p>
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
