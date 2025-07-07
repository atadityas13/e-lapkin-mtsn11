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

// Get current date and time in Indonesian (WIB timezone)
date_default_timezone_set('Asia/Jakarta');
$days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
          'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

$day_name = $days[date('w')];
$month_name = $months[date('n')];
$current_date = $day_name . ', ' . date('d') . ' ' . $month_name . ' ' . date('Y');

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
            'link' => 'lkh.php'
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
        'link' => 'lkh.php'
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
            'link' => 'laporan.php'
        ];
    } elseif ($rkb_status === 'disetujui') {
        $notifications[] = [
            'type' => 'info',
            'icon' => 'fas fa-info-circle', 
            'message' => 'RKB periode ini sudah disetujui',
            'link' => 'laporan.php'
        ];
    } elseif ($lkh_status === 'disetujui') {
        $notifications[] = [
            'type' => 'info',
            'icon' => 'fas fa-info-circle',
            'message' => 'LKH periode ini sudah disetujui',
            'link' => 'laporan.php'
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
        'link' => 'rkb.php'
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
    <title>E-Lapkin Mobile - MTsN 11 Majalengka</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }
        .gradient-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><defs><radialGradient id="a" cx="50%" cy="0%" r="50%"><stop offset="0%" stop-color="white" stop-opacity="0.1"/><stop offset="100%" stop-color="white" stop-opacity="0"/></radialGradient></defs><rect width="100" height="20" fill="url(%23a)"/></svg>');
            opacity: 0.3;
        }
        .header-content {
            position: relative;
            z-index: 1;
        }
        .profile-img {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.4);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
        }
        .profile-img:hover {
            transform: scale(1.05);
        }
        .profile-img-userinfo {
            width: 60px;
            height: 80px;
            border-radius: 12px;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.4);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
        }
        .profile-img-userinfo:hover {
            transform: scale(1.05);
        }
        .profile-placeholder-userinfo {
            width: 60px;
            height: 80px;
            border-radius: 12px;
            background: rgba(255,255,255,0.2);
            border: 3px solid rgba(255,255,255,0.4);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.3s ease;
        }
        .profile-placeholder-userinfo:hover {
            transform: scale(1.05);
        }
        .app-title {
            font-weight: 700;
            font-size: 1.4rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .datetime-text {
            font-size: 13px;
            opacity: 0.9;
            background: rgba(255,255,255,0.1);
            padding: 4px 8px;
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }
        .live-time {
            font-weight: 600;
            font-size: 14px;
            color: #ffd700;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }
        .notification-card {
            border-radius: 20px;
            border: none;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border-left: 5px solid;
            margin-bottom: 16px;
            backdrop-filter: blur(10px);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.12);
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
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            transition: transform 0.2s ease;
        }
        .notification-icon:hover {
            transform: scale(1.1);
        }
        .notification-icon.danger { background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%); }
        .notification-icon.warning { background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); color: #000; }
        .notification-icon.info { background: linear-gradient(135deg, #0dcaf0 0%, #0aace0 100%); }
        .notification-icon.success { background: linear-gradient(135deg, #198754 0%, #146c43 100%); }
        .notification-btn {
            border-radius: 25px;
            font-size: 12px;
            padding: 8px 20px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .notification-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(0,0,0,0.2);
        }
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-top: 1px solid rgba(0,0,0,0.1);
            z-index: 1000;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
        }
        .bottom-nav .nav-item {
            flex: 1;
            text-align: center;
        }
        .bottom-nav .nav-link {
            padding: 12px 8px;
            color: #6c757d;
            text-decoration: none;
            display: block;
            font-size: 11px;
            font-weight: 500;
            transition: all 0.3s ease;
            border-radius: 12px;
            margin: 4px;
        }
        .bottom-nav .nav-link.active {
            color: #0d6efd;
            background: linear-gradient(135deg, rgba(13, 110, 253, 0.1), rgba(13, 110, 253, 0.05));
            transform: translateY(-2px);
        }
        .bottom-nav .nav-link:hover {
            color: #0d6efd;
            background: rgba(13, 110, 253, 0.05);
        }
        .bottom-nav .nav-link i {
            font-size: 18px;
            margin-bottom: 4px;
            transition: transform 0.2s ease;
        }
        .bottom-nav .nav-link.active i {
            transform: scale(1.1);
        }
        .main-content {
            padding-bottom: 100px;
        }
        body {
            padding-bottom: 80px;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f2ff 100%);
        }
        .user-info {
            background: rgba(255,255,255,0.15);
            border-radius: 15px;
            padding: 12px;
            backdrop-filter: blur(10px);
        }
        .info-section {
            animation: fadeInUp 0.6s ease-out;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(13, 110, 253, 0); }
            100% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0); }
        }
        @media (max-width: 768px) {
            .app-title { font-size: 1.2rem; }
            .profile-img { width: 50px; height: 50px; }
            .notification-card { margin-bottom: 12px; }
            .container-fluid { padding-left: 12px; padding-right: 12px; }
        }
    </style>
</head>
<body class="bg-light">
    <div class="main-content">
        <!-- Header -->
        <div class="gradient-bg text-white p-4">
            <div class="header-content">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="app-title mb-1">E-Lapkin Mobile</h5>
                        <div class="datetime-text">
                            <div><?= $current_date ?></div>
                            <div class="live-time" id="liveTime"></div>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-link text-white p-0" type="button" data-bs-toggle="dropdown">
                                <?php if (!empty($foto_profil) && file_exists($photo_file_path)): ?>
                                    <img src="<?= htmlspecialchars($photo_web_path) ?>" alt="Foto Profil" class="profile-img pulse">
                                <?php else: ?>
                                    <i class="fas fa-user-circle fa-3x"></i>
                                <?php endif; ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="border-radius: 15px;">
                                <li><span class="dropdown-item-text"><strong><?= htmlspecialchars($userInfo['nama']) ?></strong></span></li>
                                <li><span class="dropdown-item-text"><?= htmlspecialchars($userInfo['nip']) ?></span></li>
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
                <div class="user-info">
                    <div class="d-flex align-items-center">
                        <?php if (!empty($foto_profil) && file_exists($photo_file_path)): ?>
                            <img src="<?= htmlspecialchars($photo_web_path) ?>" alt="Foto Profil" class="profile-img-userinfo me-3">
                        <?php else: ?>
                            <div class="profile-placeholder-userinfo me-3">
                                <i class="fas fa-user text-white fa-lg"></i>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h6 class="mb-1 fw-bold"><?= htmlspecialchars($userInfo['nama']) ?></h6>
                            <small class="opacity-75">NIP : <?= htmlspecialchars($userInfo['nip']) ?></small><br>
                            <small class="opacity-75"><?= htmlspecialchars($userInfo['jabatan']) ?></small><br>
                            <small class="opacity-75"><?= htmlspecialchars($userInfo['unit_kerja']) ?></small><br>
                            <small class="opacity-75">
                                <?php 
                                $months_indo = [
                                    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                                ];
                                echo "Periode Aktif: " . $months_indo[$current_month] . " - " . $current_year;
                                ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="container-fluid px-3 mt-4">
            <!-- App Info Section -->
            <div class="row mb-4 info-section">
                <div class="col-lg-6 mb-3">
                    <div class="notification-card warning p-4">
                        <h6 class="text-warning mb-3 fw-bold">
                            <i class="fas fa-question-circle me-2"></i>Sudahkah Anda mengisi kegiatan hari ini?
                        </h6>
                        <p class="mb-3" style="font-size: 14px; line-height: 1.6;">
                            Pastikan Anda melaporkan aktivitas harian Anda secara rutin untuk menjaga akurasi kinerja.
                        </p>
                        <a href="lkh.php" class="btn btn-warning notification-btn">
                            <i class="fas fa-list d-block"></i>Lihat Laporan Harian
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 mb-3">
                    <div class="notification-card info p-4">
                        <h6 class="text-primary mb-3 fw-bold">
                            <i class="fas fa-mobile-alt me-2"></i>E-Lapkin Mobile
                        </h6>
                        <p class="mb-3" style="font-size: 14px; line-height: 1.6;">
                            Aplikasi E-LAPKIN Mobile digunakan untuk pengelolaan Laporan Kinerja Pegawai di lingkungan <strong>MTsN 11 Majalengka.</strong> Hadir dengan versi mobile yang ringkas dan mudah digunakan.
                        </p>
                        <div class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <small>Atur Rencana Hasil Kerja Tahunan </small>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <small>Tentukan Rencana Kinerja Bulanan</small>
                        </div><div class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <small>Input Laporan Kinerja Harian</small>
                        </div>
                        <div class="mb-0">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <small>Penilaian kinerja dilakukan secara digital lebih efektif dan efisien.</small>
                        </div>
                        <br>
                        <p class="mb-3" style="font-size: 14px; line-height: 1.6;">
                            Generate dan Cetak LKB - LKH sementara hanya dilakukan pada <strong>E-Lapkin versi Web</strong>.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Notifications -->
            <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-card <?= $notification['type'] ?> p-4">
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
                    <h6 class="text-success mb-2">Sip, Mantap!</h6>
                    <p class="text-muted mb-0" style="font-size: 14px;">Laporan hari ini sudah terisi, tidak ada yang perlu dilakukan lagi.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <?php 
    // Ensure the components directory exists
    if (!is_dir(__DIR__ . '/components')) {
        mkdir(__DIR__ . '/components', 0755, true);
    }
    include __DIR__ . '/components/bottom-nav.php'; 
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Live time update
        function updateTime() {
            const now = new Date();
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const seconds = now.getSeconds().toString().padStart(2, '0');
            document.getElementById('liveTime').textContent = `${hours}:${minutes}:${seconds} WIB`;
        }
        
        // Update time immediately and then every second
        updateTime();
        setInterval(updateTime, 1000);
        
        // Add smooth scroll behavior
        document.documentElement.style.scrollBehavior = 'smooth';
    </script>
</body>
</html>