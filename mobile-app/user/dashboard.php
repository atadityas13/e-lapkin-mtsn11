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

// Start session first
session_start();

// Include mobile security
require_once __DIR__ . '/../config/mobile_security.php';

// Include database config if exists
if (file_exists(__DIR__ . '/../config/mobile_database.php')) {
    require_once __DIR__ . '/../config/mobile_database.php';
}

// Single session check - remove duplicate checks
if (!isset($_SESSION['mobile_loggedin']) || $_SESSION['mobile_loggedin'] !== true) {
    error_log("Mobile dashboard access denied - redirecting to login");
    header("Location: /mobile-app/auth/mobile_login.php?error=session_required");
    exit();
}

// Blokir akses non-mobile
if (function_exists('block_non_mobile_access')) {
    block_non_mobile_access();
}

// Log akses dashboard
if (function_exists('log_mobile_access')) {
    log_mobile_access('dashboard_access');
}

// Set page title
$page_title = "Dashboard - E-LAPKIN Mobile";

// Define getMobileUserData function if not exists
if (!function_exists('getMobileUserData')) {
    function getMobileUserData() {
        return [
            'nama' => $_SESSION['nama'] ?? 'User Mobile',
            'nip' => $_SESSION['nip'] ?? '000000000000000000',
            'jabatan' => $_SESSION['jabatan'] ?? 'Staff',
            'unit_kerja' => $_SESSION['unit_kerja'] ?? '',
            'id_pegawai' => $_SESSION['id_pegawai'] ?? 1
        ];
    }
}

// Get user data
$user_data = getMobileUserData();

// Debug information (only in development)
if (isset($_GET['debug']) || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) {
    error_log("Mobile Dashboard Debug - User Data: " . print_r($user_data, true));
    error_log("Mobile Dashboard Debug - Session Keys: " . implode(', ', array_keys($_SESSION)));
}

// Get statistics with proper error handling
$current_month = (int)date('m');
$current_year = (int)date('Y');

// Try to get real data from database, fallback to mock if fails
try {
    $db_connection = null;
    if (function_exists('getMobileDBConnection')) {
        $db_connection = getMobileDBConnection();
    }
    
    if ($db_connection && function_exists('getMobileLKHSummary')) {
        $lkh_summary = getMobileLKHSummary($user_data['id_pegawai'], $current_month, $current_year);
        error_log("LKH Summary from DB: " . print_r($lkh_summary, true));
    } else {
        // Manual database query if function doesn't work
        if ($db_connection) {
            try {
                $stmt = $db_connection->prepare("
                    SELECT 
                        COUNT(*) as total_hari,
                        SUM(CASE WHEN status_verval = 'disetujui' THEN 1 ELSE 0 END) as hari_approved,
                        SUM(CASE WHEN status_verval = 'menunggu' OR status_verval IS NULL THEN 1 ELSE 0 END) as hari_pending,
                        SUM(CASE WHEN status_verval = 'ditolak' THEN 1 ELSE 0 END) as hari_rejected
                    FROM lkh 
                    WHERE id_pegawai = ? AND MONTH(tanggal_lkh) = ? AND YEAR(tanggal_lkh) = ?
                ");
                $stmt->execute([$user_data['id_pegawai'], $current_month, $current_year]);
                $result = $stmt->fetch();
                
                if ($result && $result['total_hari'] > 0) {
                    $lkh_summary = $result;
                    error_log("LKH Summary from manual query: " . print_r($lkh_summary, true));
                } else {
                    throw new Exception("No LKH data found");
                }
            } catch (Exception $e) {
                error_log("Manual LKH query failed: " . $e->getMessage());
                throw $e;
            }
        } else {
            throw new Exception("No database connection");
        }
    }
    
    if ($db_connection && function_exists('getMobileRKBData')) {
        $rkb_summary = getMobileRKBData($user_data['id_pegawai'], $current_year);
        error_log("RKB Summary from DB: " . print_r($rkb_summary, true));
    } else {
        // Manual RKB query
        if ($db_connection) {
            try {
                $stmt = $db_connection->prepare("
                    SELECT COUNT(*) as total_kegiatan
                    FROM rkb 
                    WHERE id_pegawai = ? AND tahun = ?
                ");
                $stmt->execute([$user_data['id_pegawai'], $current_year]);
                $result = $stmt->fetch();
                
                if ($result) {
                    $rkb_summary = $result;
                    error_log("RKB Summary from manual query: " . print_r($rkb_summary, true));
                } else {
                    throw new Exception("No RKB data found");
                }
            } catch (Exception $e) {
                error_log("Manual RKB query failed: " . $e->getMessage());
                throw $e;
            }
        } else {
            throw new Exception("No database connection");
        }
    }
    
} catch (Exception $e) {
    error_log("Mobile Dashboard Error: " . $e->getMessage());
    // Fallback data dengan informasi yang lebih realistic
    $lkh_summary = [
        'total_hari' => 0,
        'hari_approved' => 0,
        'hari_pending' => 0,
        'hari_rejected' => 0
    ];
    $rkb_summary = ['total_kegiatan' => 0];
}

// Calculate performance percentage
$total_days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);
$working_days = $total_days_in_month - (floor($total_days_in_month / 7) * 2); // Rough calculation

// Ensure we have valid data for performance calculation
$total_lkh_entries = isset($lkh_summary['total_hari']) ? (int)$lkh_summary['total_hari'] : 0;
$approved_entries = isset($lkh_summary['hari_approved']) ? (int)$lkh_summary['hari_approved'] : 0;

if ($total_lkh_entries > 0) {
    $performance_percentage = round(($approved_entries / $total_lkh_entries) * 100);
} else if ($working_days > 0) {
    $performance_percentage = round(($approved_entries / $working_days) * 100);
} else {
    $performance_percentage = 0;
}

// Ensure valid range
$performance_percentage = max(0, min(100, $performance_percentage));

error_log("Performance calculation: approved=$approved_entries, total=$total_lkh_entries, working_days=$working_days, percentage=$performance_percentage");

// Get recent activities with better error handling
$recent_activities = [];
try {
    if (function_exists('getMobileRecentActivities') && $db_connection) {
        $recent_activities = getMobileRecentActivities($user_data['id_pegawai'], 3);
    }
    
    // If no activities or function failed, create sample data
    if (empty($recent_activities)) {
        $recent_activities = [
            [
                'type' => 'lkh',
                'activity' => 'LKH ' . date('d F Y'),
                'status' => $approved_entries > 0 ? 'disetujui' : 'menunggu',
                'date' => date('Y-m-d'),
                'time' => '2 jam yang lalu'
            ]
        ];
    }
} catch (Exception $e) {
    error_log("Recent activities error: " . $e->getMessage());
    $recent_activities = [];
}

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
        <link href="../assets/css/mobile.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>
            /* Inline critical CSS untuk memastikan tampilan */
            .mobile-card {
                background: white;
                border-radius: 15px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                border: none;
                margin-bottom: 1rem;
            }
            
            .stat-card {
                background: white;
                border-radius: 15px;
                padding: 1.5rem;
                text-align: center;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                transition: transform 0.2s ease;
                border: none;
            }
            
            .stat-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            }
            
            .stat-card .icon {
                width: 50px;
                height: 50px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 10px;
                font-size: 1.5rem;
            }
            
            .stat-card .icon.primary { background: linear-gradient(135deg, #4e73df, #224abe); color: white; }
            .stat-card .icon.success { background: linear-gradient(135deg, #1cc88a, #17a673); color: white; }
            .stat-card .icon.warning { background: linear-gradient(135deg, #f6c23e, #dda20a); color: white; }
            .stat-card .icon.danger { background: linear-gradient(135deg, #e74a3b, #c0392b); color: white; }
            
            .stat-card .number {
                font-size: 2rem;
                font-weight: 700;
                color: #333;
                margin-bottom: 5px;
            }
            
            .stat-card .label {
                font-size: 0.85rem;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .mobile-content {
                background: transparent;
                padding: 1rem;
            }
            
            .mobile-nav {
                background: linear-gradient(135deg, #4e73df, #224abe);
                padding: 1rem 0;
            }
            
            .btn-mobile {
                border-radius: 12px;
                padding: 0.75rem 1rem;
                font-weight: 600;
                transition: all 0.2s ease;
                border: none;
                text-decoration: none;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-direction: column;
                min-height: 60px;
            }
            
            .btn-mobile:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            }
            
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
            
            @media (max-width: 576px) {
                .mobile-content { padding: 0.5rem; }
                .stat-card { padding: 1rem; }
                .stat-card .number { font-size: 1.5rem; }
            }
        </style>
    </head>
    <body class="mobile-body"
        style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;">
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
                                <?php if (!empty($user_data['unit_kerja'])): ?>
                                <br><i class="fas fa-building me-1"></i><?= htmlspecialchars($user_data['unit_kerja']) ?>
                                <?php endif; ?>
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
                
                <!-- Debug Info (hapus di production) -->
                <?php if (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false): ?>
                <div class="mt-3 p-2 bg-light rounded">
                    <small class="text-muted">
                        <strong>Debug Info:</strong><br>
                        ID Pegawai: <?= $user_data['id_pegawai'] ?><br>
                        Session Mobile: <?= $_SESSION['mobile_loggedin'] ? 'Yes' : 'No' ?><br>
                        LKH Data: <?= json_encode($lkh_summary) ?><br>
                        RKB Data: <?= json_encode($rkb_summary) ?>
                    </small>
                </div>
                <?php endif; ?>
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
                    <a href="/mobile-app/user/lkh.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i>Input LKH Sekarang
                    </a>
                </div>
                <?php else: ?>
                <?php foreach ($recent_activities as $activity): ?>
                <div class="d-flex align-items-center mb-3">
                    <div class="me-3">
                        <?php 
                        $status = $activity['status'] ?? 'menunggu';
                        $status_class = $status === 'disetujui' ? 'bg-success' : ($status === 'menunggu' ? 'bg-warning' : 'bg-danger');
                        ?>
                        <div class="icon-circle <?= $status_class ?> text-white" style="width: 40px; height: 40px; font-size: 0.9rem;">
                            <i class="fas <?= $activity['type'] === 'lkh' ? 'fa-calendar-check' : 'fa-tasks' ?>"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold"><?= htmlspecialchars($activity['activity'] ?? $activity['title'] ?? 'Aktivitas') ?></div>
                        <small class="text-muted">
                            <span class="badge bg-<?= $status === 'disetujui' ? 'success' : ($status === 'menunggu' ? 'warning' : 'danger') ?> me-2">
                                <?= ucfirst($status) ?>
                            </span>
                            <?= htmlspecialchars($activity['time'] ?? date('H:i')) ?>
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
</style>

</body>
</html>
