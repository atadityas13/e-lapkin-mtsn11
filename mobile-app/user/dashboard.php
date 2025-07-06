<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA - MOBILE DASHBOARD
 * ========================================================
 * 
 * File: Mobile Dashboard
 * Deskripsi: Dashboard khusus untuk aplikasi mobile
 * 
 * @package    E-Lapkin-MTSN11-Mobile
 * @version    1.0.0
 * ========================================================
 */

// Define ABSPATH
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Include mobile security
require_once __DIR__ . '/../config/mobile_security.php';

// Include database config if exists
if (file_exists(__DIR__ . '/../config/mobile_database.php')) {
    require_once __DIR__ . '/../config/mobile_database.php';
}

// Include session if exists
if (file_exists(__DIR__ . '/../template/session_mobile.php')) {
    require_once __DIR__ . '/../template/session_mobile.php';
} else {
    // Basic session check
    session_start();
    if (!isset($_SESSION['mobile_loggedin']) || $_SESSION['mobile_loggedin'] !== true) {
        header("Location: /mobile-app/auth/mobile_login.php");
        exit();
    }
}

// Blokir akses non-mobile
block_non_mobile_access();

// Log akses dashboard
log_mobile_access('dashboard_access');

// Set page title
$page_title = "Dashboard - E-LAPKIN Mobile";

// Fallback functions if database functions don't exist
if (!function_exists('getMobileUserData')) {
    function getMobileUserData() {
        return [
            'nama' => $_SESSION['mobile_user_name'] ?? 'User Mobile',
            'nip' => $_SESSION['mobile_user_nip'] ?? '000000000000000000',
            'jabatan' => $_SESSION['mobile_user_jabatan'] ?? 'Staff',
            'id_pegawai' => $_SESSION['mobile_user_id'] ?? 1
        ];
    }
}

if (!function_exists('getMobileLKHSummary')) {
    function getMobileLKHSummary($id_pegawai, $month, $year) {
        return [
            'hari_approved' => 15,
            'hari_pending' => 3,
            'hari_rejected' => 1
        ];
    }
}

if (!function_exists('getMobileRKBData')) {
    function getMobileRKBData($id_pegawai, $year) {
        return [
            'total_kegiatan' => 25
        ];
    }
}

// Get user data
$user_data = getMobileUserData();

// Get statistics
$current_month = date('m');
$current_year = date('Y');

$lkh_summary = getMobileLKHSummary($user_data['id_pegawai'], $current_month, $current_year);
$rkb_summary = getMobileRKBData($user_data['id_pegawai'], $current_year);

// Calculate performance percentage
$total_days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);
$working_days = $total_days_in_month - (floor($total_days_in_month / 7) * 2); // Rough calculation
$performance_percentage = $working_days > 0 ? round(($lkh_summary['hari_approved'] / $working_days) * 100) : 0;

// Recent activities (mock data for now)
$recent_activities = [
    [
        'type' => 'lkh',
        'title' => 'LKH ' . date('d F Y'),
        'status' => 'pending',
        'time' => '2 jam yang lalu'
    ],
    [
        'type' => 'rkb',
        'title' => 'Update RKB ' . date('F Y'),
        'status' => 'approved',
        'time' => '1 hari yang lalu'
    ]
];

// Check if header template exists, if not create inline header
if (file_exists(__DIR__ . '/../template/header_mobile.php')) {
    include __DIR__ . '/../template/header_mobile.php';
} else {
    // Inline header fallback
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
        <title><?= $page_title ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="/mobile-app/assets/css/mobile.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body class="mobile-body">
    <?php
}

// Check if navigation template exists
if (file_exists(__DIR__ . '/../template/navigation_mobile.php')) {
    include __DIR__ . '/../template/navigation_mobile.php';
} else {
    // Simple navigation fallback
    ?>
    <nav class="mobile-nav">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center py-2">
                <h5 class="mb-0 text-white">E-LAPKIN Mobile</h5>
                <a href="/mobile-app/auth/mobile_logout.php" class="btn btn-sm btn-outline-light">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </nav>
    <?php
}
?>

<!-- Main Content -->
<div class="mobile-content">
    <div class="container-fluid p-4">
        
        <!-- Welcome Card -->
        <div class="mobile-card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-2">Selamat Datang! 👋</h5>
                        <h6 class="text-primary fw-bold mb-1"><?= htmlspecialchars($user_data['nama']) ?></h6>
                        <p class="text-muted mb-0">
                            <small>
                                <i class="fas fa-id-card me-1"></i><?= htmlspecialchars($user_data['nip']) ?><br>
                                <i class="fas fa-briefcase me-1"></i><?= htmlspecialchars($user_data['jabatan']) ?>
                            </small>
                        </p>
                    </div>
                    <div class="col-auto">
                        <div class="text-center">
                            <div class="icon-circle bg-gradient-primary text-white mb-2">
                                <i class="fas fa-user"></i>
                            </div>
                            <small class="text-muted">
                                <?= date('d M Y') ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <!-- LKH Approved -->
            <div class="col-6">
                <div class="stat-card">
                    <div class="icon primary">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="number"><?= $lkh_summary['hari_approved'] ?></div>
                    <div class="label">LKH Disetujui</div>
                </div>
            </div>
            
            <!-- LKH Pending -->
            <div class="col-6">
                <div class="stat-card">
                    <div class="icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="number"><?= $lkh_summary['hari_pending'] ?></div>
                    <div class="label">LKH Pending</div>
                </div>
            </div>
            
            <!-- RKB Total -->
            <div class="col-6">
                <div class="stat-card">
                    <div class="icon success">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="number"><?= $rkb_summary['total_kegiatan'] ?></div>
                    <div class="label">Total RKB</div>
                </div>
            </div>
            
            <!-- Performance -->
            <div class="col-6">
                <div class="stat-card">
                    <div class="icon <?= $performance_percentage >= 80 ? 'success' : ($performance_percentage >= 60 ? 'warning' : 'danger') ?>">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="number"><?= $performance_percentage ?>%</div>
                    <div class="label">Kinerja</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mobile-card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Aksi Cepat
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <a href="/mobile-app/user/lkh.php" class="btn btn-primary btn-mobile w-100">
                            <i class="fas fa-plus me-2"></i>
                            <span>Input LKH</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="/mobile-app/user/rkb.php" class="btn btn-success btn-mobile w-100">
                            <i class="fas fa-calendar me-2"></i>
                            <span>Lihat RKB</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="/mobile-app/user/laporan.php" class="btn btn-info btn-mobile w-100">
                            <i class="fas fa-chart-bar me-2"></i>
                            <span>Laporan</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="/mobile-app/user/generate_lkb.php" class="btn btn-warning btn-mobile w-100">
                            <i class="fas fa-file-pdf me-2"></i>
                            <span>Generate PDF</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Chart -->
        <div class="mobile-card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Grafik Kinerja Bulan Ini
                </h6>
            </div>
            <div class="card-body">
                <div class="progress mb-3" style="height: 12px;">
                    <div class="progress-bar bg-gradient-primary" 
                         role="progressbar" 
                         style="width: <?= $performance_percentage ?>%"
                         aria-valuenow="<?= $performance_percentage ?>" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                    </div>
                </div>
                <div class="row text-center">
                    <div class="col-4">
                        <div class="fw-bold text-success"><?= $lkh_summary['hari_approved'] ?></div>
                        <small class="text-muted">Disetujui</small>
                    </div>
                    <div class="col-4">
                        <div class="fw-bold text-warning"><?= $lkh_summary['hari_pending'] ?></div>
                        <small class="text-muted">Pending</small>
                    </div>
                    <div class="col-4">
                        <div class="fw-bold text-danger"><?= $lkh_summary['hari_rejected'] ?></div>
                        <small class="text-muted">Ditolak</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="mobile-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Aktivitas Terbaru
                </h6>
                <a href="/mobile-app/user/laporan.php" class="btn btn-sm btn-outline-primary">
                    Lihat Semua
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_activities)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox text-muted mb-3" style="font-size: 3rem;"></i>
                    <p class="text-muted">Belum ada aktivitas hari ini</p>
                </div>
                <?php else: ?>
                <?php foreach ($recent_activities as $activity): ?>
                <div class="d-flex align-items-center mb-3">
                    <div class="me-3">
                        <div class="icon-circle <?= $activity['status'] === 'approved' ? 'bg-success' : ($activity['status'] === 'pending' ? 'bg-warning' : 'bg-danger') ?> text-white" style="width: 40px; height: 40px; font-size: 0.9rem;">
                            <i class="fas <?= $activity['type'] === 'lkh' ? 'fa-calendar-check' : 'fa-tasks' ?>"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold"><?= htmlspecialchars($activity['title']) ?></div>
                        <small class="text-muted">
                            <span class="badge badge-<?= $activity['status'] === 'approved' ? 'success' : ($activity['status'] === 'pending' ? 'warning' : 'danger') ?> me-2">
                                <?= ucfirst($activity['status']) ?>
                            </span>
                            <?= htmlspecialchars($activity['time']) ?>
                        </small>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Calendar Widget -->
        <div class="mobile-card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-calendar me-2"></i>
                    <?= date('F Y') ?>
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col">
                        <div class="fw-bold text-primary" style="font-size: 2rem;"><?= date('d') ?></div>
                        <small class="text-muted"><?= date('l') ?></small>
                    </div>
                    <div class="col-auto">
                        <div class="vr" style="height: 60px;"></div>
                    </div>
                    <div class="col">
                        <div class="text-start">
                            <div class="fw-semibold">Hari Kerja</div>
                            <div class="text-muted"><?= $working_days ?> hari</div>
                            <div class="fw-semibold mt-2">LKH Selesai</div>
                            <div class="text-success"><?= $lkh_summary['hari_approved'] ?> hari</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tips Card -->
        <div class="mobile-card mb-4">
            <div class="card-body">
                <div class="d-flex align-items-start">
                    <div class="me-3">
                        <div class="icon-circle bg-gradient-info text-white">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="fw-bold text-info">Tips Produktivitas</h6>
                        <p class="mb-0">
                            Jangan lupa untuk mengisi LKH setiap hari agar laporan kinerja Anda selalu update dan akurat.
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Footer Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update time every minute
    function updateTime() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('id-ID', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
        
        const timeElements = document.querySelectorAll('.current-time');
        timeElements.forEach(el => {
            el.textContent = timeStr;
        });
    }
    
    updateTime();
    setInterval(updateTime, 60000);
    
    // Add click animation to stat cards
    document.querySelectorAll('.stat-card').forEach(card => {
        card.addEventListener('click', function() {
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });
    
    // Show welcome message for first time users
    const isFirstVisit = !localStorage.getItem('mobile_dashboard_visited');
    if (isFirstVisit) {
        setTimeout(() => {
            Swal.fire({
                title: 'Selamat Datang!',
                text: 'Selamat datang di E-LAPKIN Mobile. Aplikasi ini memudahkan Anda mengelola laporan kinerja harian.',
                icon: 'success',
                confirmButtonText: 'Mulai Menggunakan',
                confirmButtonColor: '#4e73df'
            });
            localStorage.setItem('mobile_dashboard_visited', 'true');
        }, 1000);
    }
});

// Add to home screen prompt
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
    deferredPrompt = e;
    
    // Show install button after 10 seconds
    setTimeout(() => {
        if (deferredPrompt) {
            Swal.fire({
                title: 'Install Aplikasi',
                text: 'Ingin menginstall E-LAPKIN Mobile di perangkat Anda untuk akses yang lebih mudah?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Install',
                cancelButtonText: 'Nanti Saja',
                confirmButtonColor: '#4e73df'
            }).then((result) => {
                if (result.isConfirmed) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then((choiceResult) => {
                        if (choiceResult.outcome === 'accepted') {
                            console.log('User accepted the install prompt');
                        }
                        deferredPrompt = null;
                    });
                }
            });
        }
    }, 10000);
});
</script>

<!-- Additional mobile styles -->
<style>
.icon-circle {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
}

.bg-gradient-info {
    background: linear-gradient(135deg, #36b9cc 0%, #258391 100%);
}

.progress-bar {
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
}

.badge-success {
    background-color: #1cc88a;
}

.badge-warning {
    background-color: #f6c23e;
}

.badge-danger {
    background-color: #e74a3b;
}

@media (max-width: 576px) {
    .mobile-content {
        padding: 0.5rem;
    }
    
    .container-fluid {
        padding: 0.5rem !important;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-card .number {
        font-size: 1.5rem;
    }
}
</style>

</body>
</html>
