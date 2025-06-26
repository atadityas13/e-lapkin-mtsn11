<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * Sistem Elektronik Laporan Kinerja Harian
 * MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat
 * 
 * File: Main Entry Point
 * Deskripsi: Halaman utama aplikasi - redirect ke login atau dashboard
 * 
 * @package    E-Lapkin-MTSN11
 * @author     MTsN 11 Majalengka Development Team
 * @copyright  2025 MTsN 11 Majalengka. All rights reserved.
 * @license    Proprietary License
 * @version    1.0.0
 * @since      2025-01-01
 * @created    2025-06-25
 * @modified   2025-06-25
 * 
 * DISCLAIMER:
 * Software ini dikembangkan khusus untuk MTsN 11 Majalengka.
 * Dilarang keras menyalin, memodifikasi, atau mendistribusikan
 * tanpa izin tertulis dari MTsN 11 Majalengka.
 * 
 * CONTACT:
 * Website: https://mtsn11majalengka.sch.id
 * Email: mtsn11majalengka@gmail.com
 * Phone: (0233) 8319182
 * Address: Kp. Sindanghurip Desa Maniis Kec. Cingambul, Majalengka, Jawa Barat
 * 
 * ========================================================
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

// Ambil path foto profil dari session jika ada
$foto_profil_topbar = $_SESSION['foto_profil'] ?? '';
$nama_topbar = $_SESSION['nama'] ?? 'User';
$role_topbar = $_SESSION['role'] ?? 'user';

// Handle case where database stores full path or just filename
if (!empty($foto_profil_topbar)) {
    // If it already contains the full path, use it directly
    if (strpos($foto_profil_topbar, 'uploads/foto_profil/') === 0) {
        $photo_web_path = '../' . $foto_profil_topbar;
        $photo_file_path = __DIR__ . '/../' . $foto_profil_topbar;
    } else {
        // If it's just the filename, prepend the path
        $photo_web_path = '../uploads/foto_profil/' . $foto_profil_topbar;
        $photo_file_path = __DIR__ . '/../uploads/foto_profil/' . $foto_profil_topbar;
    }
}

// Get notifications for users only
$notifications = [];
$admin_notifications = [];
if ($role_topbar === 'user' && isset($_SESSION['id_pegawai'])) {
    require_once __DIR__ . '/../config/database.php';
    
    $id_pegawai = $_SESSION['id_pegawai'];
    
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
            $period_link = '/user/rhk.php';
        } elseif ($current_month != $real_current_month) {
            $period_message = 'Periode Bulan RKB sebelumnya telah berakhir, silakan membuat periode baru';
            $period_link = '/user/rkb.php';
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
                'link' => '/user/lkh.php'
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
            'link' => '/user/lkh.php'
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
                'type' => 'warning',
                'icon' => 'fas fa-clock',
                'message' => 'RKB Anda sudah diajukan, menunggu approval'
            ];
        }
        
        if ($lkh_status === 'diajukan') {
            $notifications[] = [
                'type' => 'warning', 
                'icon' => 'fas fa-clock',
                'message' => 'LKH Anda sudah diajukan, menunggu approval'
            ];
        }
        
        if ($rkb_status === 'disetujui' && $lkh_status === 'disetujui') {
            $notifications[] = [
                'type' => 'success',
                'icon' => 'fas fa-check-circle',
                'message' => 'RKB/LKH periode ini sudah disetujui, Anda sudah bisa generate laporan',
                'link' => '/user/generate_rkb-lkh.php'
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
            'link' => '/user/generate_lkb-lkh.php'
        ];
    }
} elseif ($role_topbar === 'admin') {
    require_once __DIR__ . '/../config/database.php';
    
    $months_name = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    // Get pending RKB submissions grouped by employee and period (latest 5)
    $stmt_rkb_pending = $conn->prepare("
        SELECT p.nama, rkb.bulan, rkb.tahun, MAX(rkb.created_at) as latest_time
        FROM rkb 
        JOIN pegawai p ON rkb.id_pegawai = p.id_pegawai 
        WHERE rkb.status_verval = 'diajukan' 
        GROUP BY rkb.id_pegawai, rkb.bulan, rkb.tahun
        ORDER BY latest_time DESC 
        LIMIT 5
    ");
    $stmt_rkb_pending->execute();
    $result_rkb = $stmt_rkb_pending->get_result();
    
    while ($row = $result_rkb->fetch_assoc()) {
        $admin_notifications[] = [
            'type' => 'warning',
            'icon' => 'fas fa-file-alt',
            'message' => $row['nama'] . ' mengajukan RKB ' . $months_name[$row['bulan']] . ' ' . $row['tahun'] . ' untuk diverval',
            'time' => $row['latest_time'],
            'link' => '/admin/approval.php'
        ];
    }
    $stmt_rkb_pending->close();
    
    // Get pending LKH submissions grouped by employee and period (latest 5)
    $stmt_lkh_pending = $conn->prepare("
        SELECT p.nama, MONTH(lkh.tanggal_lkh) as bulan, YEAR(lkh.tanggal_lkh) as tahun, MAX(lkh.created_at) as latest_time
        FROM lkh 
        JOIN pegawai p ON lkh.id_pegawai = p.id_pegawai 
        WHERE lkh.status_verval = 'diajukan' 
        GROUP BY lkh.id_pegawai, MONTH(lkh.tanggal_lkh), YEAR(lkh.tanggal_lkh)
        ORDER BY latest_time DESC 
        LIMIT 5
    ");
    $stmt_lkh_pending->execute();
    $result_lkh = $stmt_lkh_pending->get_result();
    
    while ($row = $result_lkh->fetch_assoc()) {
        $admin_notifications[] = [
            'type' => 'info',
            'icon' => 'fas fa-clipboard-list',
            'message' => $row['nama'] . ' mengajukan LKH ' . $months_name[$row['bulan']] . ' ' . $row['tahun'] . ' untuk diverval',
            'time' => $row['latest_time'],
            'link' => '/admin/approval.php'
        ];
    }
    $stmt_lkh_pending->close();
    
    // Get pending user registration requests (latest 5)
    $stmt_reg_pending = $conn->prepare("
        SELECT nama, created_at
        FROM pegawai 
        WHERE status = 'pending' 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt_reg_pending->execute();
    $result_reg = $stmt_reg_pending->get_result();
    
    while ($row = $result_reg->fetch_assoc()) {
        $admin_notifications[] = [
            'type' => 'primary',
            'icon' => 'fas fa-user-plus',
            'message' => $row['nama'] . ' mengajukan persetujuan pendaftaran akun',
            'time' => $row['created_at'],
            'link' => '/admin/manajemen_user.php'
        ];
    }
    $stmt_reg_pending->close();
    
    // Get pending password reset requests (latest 5)
    $stmt_reset_pending = $conn->prepare("
        SELECT nama, created_at
        FROM pegawai 
        WHERE reset_request = 1
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt_reset_pending->execute();
    $result_reset = $stmt_reset_pending->get_result();
    
    while ($row = $result_reset->fetch_assoc()) {
        $admin_notifications[] = [
            'type' => 'secondary',
            'icon' => 'fas fa-key',
            'message' => $row['nama'] . ' mengajukan permintaan reset password',
            'time' => $row['created_at'],
            'link' => '/admin/manajemen_user.php'
        ];
    }
    $stmt_reset_pending->close();
    
    // Sort notifications by time (newest first)
    usort($admin_notifications, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });
    
    // Keep only latest 5
    $admin_notifications = array_slice($admin_notifications, 0, 5);
    
    // Count total pending for badge (including new request types)
    $stmt_total_pending = $conn->prepare("
        SELECT 
            (SELECT COUNT(DISTINCT CONCAT(id_pegawai, '-', bulan, '-', tahun)) FROM rkb WHERE status_verval = 'diajukan') +
            (SELECT COUNT(DISTINCT CONCAT(id_pegawai, '-', MONTH(tanggal_lkh), '-', YEAR(tanggal_lkh))) FROM lkh WHERE status_verval = 'diajukan') +
            (SELECT COUNT(*) FROM pegawai WHERE status = 'pending') +
            (SELECT COUNT(*) FROM pegawai WHERE reset_request = 1) as total
    ");
    $stmt_total_pending->execute();
    $stmt_total_pending->bind_result($total_pending);
    $stmt_total_pending->fetch();
    $stmt_total_pending->close();
}

$notification_count = count($notifications);
// Jangan ada output apapun sebelum tag <?php di file ini, termasuk spasi/baris kosong!
?>
<nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
    <!-- Navbar Brand-->
    <a class="navbar-brand ps-3" href="<?= ($role_topbar === 'admin') ? '/admin/dashboard.php' : '/user/dashboard.php' ?>">
        E-LAPKIN <?= ($role_topbar === 'admin') ? 'Admin' : 'Pegawai' ?>
    </a>
    
    <!-- Sidebar Toggle-->
    <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" href="#!" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Spacer to push navbar items to the right -->
    <div class="ms-auto"></div>
    
    <!-- Navbar-->
    <ul class="navbar-nav">
        <?php if ($role_topbar === 'user'): ?>
        <!-- Notification Bell for Users -->
        <li class="nav-item dropdown me-2">
            <a class="nav-link dropdown-toggle position-relative" id="notificationDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-bell fa-fw"></i>
                <?php if ($notification_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.7rem;">
                        <?= $notification_count ?>
                    </span>
                <?php endif; ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown" style="min-width: 300px;">
                <li><h6 class="dropdown-header">Notifikasi</h6></li>
                <?php if (empty($notifications)): ?>
                    <li><span class="dropdown-item-text text-muted">Tidak ada notifikasi</span></li>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <li>
                            <?php if (isset($notification['link'])): ?>
                                <a class="dropdown-item py-2" href="<?= htmlspecialchars($notification['link']) ?>">
                            <?php else: ?>
                                <span class="dropdown-item py-2">
                            <?php endif; ?>
                                <div class="d-flex align-items-start">
                                    <i class="<?= $notification['icon'] ?> me-2 mt-1 text-<?= $notification['type'] ?>"></i>
                                    <div>
                                        <div class="fw-bold text-<?= $notification['type'] ?>"><?= htmlspecialchars($notification['message']) ?></div>
                                        <?php if (isset($notification['link'])): ?>
                                            <small class="text-muted">
                                                <?php if (strpos($notification['link'], 'generate') !== false): ?>
                                                    <?php if (strpos($notification['message'], 'Selamat') !== false): ?>
                                                        Klik untuk melihat laporan lainnya
                                                    <?php else: ?>
                                                        Klik untuk generate laporan
                                                    <?php endif; ?>
                                                <?php elseif (strpos($notification['link'], 'rhk') !== false): ?>
                                                    Klik untuk atur periode RHK
                                                <?php elseif (strpos($notification['link'], 'rkb') !== false): ?>
                                                    Klik untuk atur periode RKB
                                                <?php elseif (strpos($notification['link'], 'lkh') !== false): ?>
                                                    Klik untuk mengisi LKH
                                                <?php endif; ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php if (isset($notification['link'])): ?>
                                </a>
                            <?php else: ?>
                                </span>
                            <?php endif; ?>
                        </li>
                        <?php if ($notification !== end($notifications)): ?>
                            <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </li>
        <?php elseif ($role_topbar === 'admin'): ?>
        <!-- Notification Bell for Admin -->
        <li class="nav-item dropdown me-2">
            <a class="nav-link dropdown-toggle position-relative" id="adminNotificationDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-bell fa-fw"></i>
                <?php if (isset($total_pending) && $total_pending > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.7rem;">
                        <?= $total_pending ?>
                    </span>
                <?php endif; ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="adminNotificationDropdown" style="min-width: 400px;">
                <li><h6 class="dropdown-header">Notifikasi Admin</h6></li>
                <?php if (empty($admin_notifications)): ?>
                    <li><span class="dropdown-item-text text-muted">Tidak ada laporan yang perlu diverval</span></li>
                <?php else: ?>
                    <?php foreach ($admin_notifications as $notification): ?>
                        <li>
                            <a class="dropdown-item py-2" href="<?= htmlspecialchars($notification['link']) ?>">
                                <div class="d-flex align-items-start">
                                    <i class="<?= $notification['icon'] ?> me-2 mt-1 text-<?= $notification['type'] ?>"></i>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold text-<?= $notification['type'] ?>" style="font-size: 0.85rem;">
                                            <?= htmlspecialchars($notification['message']) ?>
                                        </div>
                                        <small class="text-muted">
                                            <?= date('d M Y H:i', strtotime($notification['time'])) ?>
                                        </small>
                                    </div>
                                </div>
                            </a>
                        </li>
                        <?php if ($notification !== end($admin_notifications)): ?>
                            <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <?php if (isset($total_pending) && $total_pending > 5): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-center py-2 text-primary fw-bold" href="/admin/approval.php">
                                <i class="fas fa-eye me-1"></i>Lihat Semua (<?= $total_pending ?> permintaan)
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>
        
        <!-- User Profile Dropdown -->
        <li class="nav-item dropdown me-3">
            <a class="nav-link dropdown-toggle d-flex align-items-center" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <?php if (!empty($foto_profil_topbar) && file_exists($photo_file_path)): ?>
                    <img src="<?= htmlspecialchars($photo_web_path) ?>" alt="Foto Profil" class="rounded-circle me-2" style="width: 25px; height: 25px; object-fit: cover;">
                <?php else: ?>
                    <i class="fas fa-user fa-fw me-2"></i>
                <?php endif; ?>
                <?= isset($_SESSION['nama']) ? htmlspecialchars($_SESSION['nama']) : 'User' ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                <?php if ($role_topbar === 'admin'): ?>
                    <li><a class="dropdown-item" href="/admin/pengaturan.php"><i class="fas fa-cogs me-2"></i>Pengaturan</a></li>
                <?php else: ?>
                    <li><a class="dropdown-item" href="/user/profil.php"><i class="fas fa-user-circle me-2"></i>Profil</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider" /></li>
                <li><a class="dropdown-item logout-btn" href="/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
            </ul>
        </li>
    </ul>
</nav>

<!-- Add SweetAlert CDN for all pages -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function toggleSidebar() {
    const body = document.body;
    const sidebar = document.getElementById('layoutSidenav_nav');
    
    if (body.classList.contains('sb-sidenav-toggled')) {
        body.classList.remove('sb-sidenav-toggled');
        localStorage.setItem('sb|sidebar-toggle', 'false');
    } else {
        body.classList.add('sb-sidenav-toggled');
        localStorage.setItem('sb|sidebar-toggle', 'true');
    }
}

// Restore sidebar state from localStorage
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = localStorage.getItem('sb|sidebar-toggle');
    if (sidebarToggle === 'true') {
        document.body.classList.add('sb-sidenav-toggled');
    }
    
    // Fix notification dropdown clicks for all pages
    setTimeout(function() {
        // Handle notification links
        const notificationLinks = document.querySelectorAll('.notification-dropdown .dropdown-item[href]');
        notificationLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.stopPropagation();
                const href = this.getAttribute('href');
                if (href && href !== '#') {
                    window.location.href = href;
                }
            });
        });
        
        // Ensure notification dropdowns work properly
        const notificationDropdowns = document.querySelectorAll('#notificationDropdown, #adminNotificationDropdown');
        notificationDropdowns.forEach(dropdown => {
            dropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
    }, 100);
    
    // Add logout confirmation for both user and admin - wait for SweetAlert to load
    setTimeout(function() {
      document.querySelectorAll('.logout-btn, a[href*="logout.php"]').forEach(function(logoutLink) {
        logoutLink.addEventListener('click', function(e) {
          e.preventDefault();
          const href = this.getAttribute('href');
          
          // Ensure SweetAlert is loaded
          if (typeof Swal !== 'undefined') {
            Swal.fire({
              title: 'Konfirmasi Logout',
              text: 'Apakah Anda yakin ingin keluar dari sistem?',
              icon: 'question',
              showCancelButton: true,
              confirmButtonColor: '#d33',
              cancelButtonColor: '#6c757d',
              confirmButtonText: 'Ya, Logout',
              cancelButtonText: 'Batal'
            }).then((result) => {
              if (result.isConfirmed) {
                window.location.href = href;
              }
            });
          } else {
            // Fallback if SweetAlert not loaded
            if (confirm('Apakah Anda yakin ingin keluar dari sistem?')) {
              window.location.href = href;
            }
          }
        });
      });
    }, 300); // Increased timeout to ensure SweetAlert loads
});
</script>

<style>
.notification-dropdown .dropdown-item {
    white-space: normal;
    word-wrap: break-word;
    cursor: pointer;
}

.notification-dropdown .dropdown-item:hover {
    background-color: #f8f9fa;
}

.notification-dropdown .dropdown-item[href] {
    color: inherit;
    text-decoration: none;
}

.notification-dropdown .dropdown-item[href]:hover {
    color: inherit;
    text-decoration: none;
}
</style>