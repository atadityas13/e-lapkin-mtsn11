<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA - MOBILE APP VERSION
 * ========================================================
 * 
 * Mobile App Report Page with Tabs
 * 
 * @package    E-Lapkin-MTSN11
 * @version    1.0.0
 * ========================================================
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../template/session_user.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

$id_pegawai_login = $_SESSION['id_pegawai'];
$nama_pegawai_login = $_SESSION['nama'];
$role_pegawai_login = $_SESSION['role'];

// Get active period
function get_periode_aktif($conn, $id_pegawai) {
    $stmt = $conn->prepare("SELECT tahun_aktif FROM pegawai WHERE id_pegawai = ?");
    $stmt->bind_param("i", $id_pegawai);
    $stmt->execute();
    $stmt->bind_result($tahun_aktif);
    $stmt->fetch();
    $stmt->close();
    return ['tahun' => $tahun_aktif ?: (int)date('Y')];
}

$periode_aktif = get_periode_aktif($conn, $id_pegawai_login);
$periode_tahun = $periode_aktif['tahun'];

// Get current month and year for monthly report
$current_month = (int)date('m');
$current_year = (int)date('Y');
$filter_month = isset($_GET['month']) ? (int)$_GET['month'] : $current_month;
$filter_year = isset($_GET['year']) ? (int)$_GET['year'] : $current_year;

// Check data availability for both reports
$stmt_monthly = $conn->prepare("SELECT COUNT(*) as total FROM rhk r 
    JOIN rkb ON r.id_rhk = rkb.id_rhk 
    WHERE r.id_pegawai = ? AND rkb.bulan = ? AND rkb.tahun = ?");
$stmt_monthly->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
$stmt_monthly->execute();
$monthly_data_count = $stmt_monthly->get_result()->fetch_assoc()['total'];
$stmt_monthly->close();

$stmt_annual = $conn->prepare("SELECT COUNT(*) as total FROM rhk r 
    JOIN rkb ON r.id_rhk = rkb.id_rhk 
    WHERE r.id_pegawai = ? AND rkb.tahun = ?");
$stmt_annual->bind_param("ii", $id_pegawai_login, $periode_tahun);
$stmt_annual->execute();
$annual_data_count = $stmt_annual->get_result()->fetch_assoc()['total'];
$stmt_annual->close();

$page_title = "Laporan - Mobile App";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - E-Lapkin MTsN 11</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .mobile-report-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 0;
        }

        .mobile-header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 0 0 20px 20px;
            padding: 20px;
            margin-bottom: 20px;
            color: white;
        }

        .tab-navigation {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 8px;
            margin: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .nav-pills .nav-link {
            border-radius: 10px;
            padding: 12px 20px;
            margin: 0 4px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: #6c757d;
            background: transparent;
            border: none;
        }

        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .nav-pills .nav-link:not(.active):hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .tab-content {
            margin: 15px;
        }

        .report-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .quick-stats {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border-radius: 15px;
            padding: 20px;
            margin: 15px 0;
            color: white;
        }

        .feature-highlight {
            background: linear-gradient(135deg, #ffeaa7, #fdcb6e);
            border-radius: 15px;
            padding: 20px;
            margin: 15px 0;
            text-align: center;
        }

        .action-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 15px 30px;
            color: white;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.6);
            color: white;
            text-decoration: none;
        }

        .action-btn:disabled {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            transform: none;
            box-shadow: 0 4px 10px rgba(108, 117, 125, 0.3);
            cursor: not-allowed;
        }

        .month-selector {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin: 15px 0;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .loading-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            max-width: 300px;
        }

        @media (max-width: 768px) {
            .tab-navigation, .tab-content {
                margin: 10px;
            }
            
            .report-card {
                padding: 20px;
            }
            
            .nav-pills .nav-link {
                padding: 10px 16px;
                font-size: 14px;
            }
        }
    </style>
</head>

<body>
    <div class="mobile-report-container">
        <!-- Mobile Header -->
        <div class="mobile-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">📊 Laporan Kinerja</h4>
                    <small class="opacity-75">Mobile App - E-Lapkin MTsN 11</small>
                </div>
                <button class="btn btn-light btn-sm" onclick="window.history.back()">
                    <i class="fas fa-arrow-left"></i> Kembali
                </button>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <ul class="nav nav-pills justify-content-center" id="reportTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="monthly-tab" data-bs-toggle="pill" data-bs-target="#monthly" type="button" role="tab">
                        📅 Bulanan
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="annual-tab" data-bs-toggle="pill" data-bs-target="#annual" type="button" role="tab">
                        📊 Tahunan
                    </button>
                </li>
            </ul>
        </div>

        <!-- Tab Content -->
        <div class="tab-content" id="reportTabContent">
            <!-- Monthly Report Tab -->
            <div class="tab-pane fade show active" id="monthly" role="tabpanel">
                <div class="report-card">
                    <h5 class="mb-3">
                        <i class="fas fa-calendar-month text-primary me-2"></i>
                        Laporan Bulanan
                    </h5>
                    
                    <!-- Month Selector -->
                    <div class="month-selector">
                        <label class="form-label fw-bold">Pilih Bulan & Tahun:</label>
                        <div class="row">
                            <div class="col-7">
                                <select class="form-select" id="monthSelect">
                                    <?php
                                    $months = [
                                        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                                    ];
                                    foreach ($months as $num => $name) {
                                        $selected = ($num == $filter_month) ? 'selected' : '';
                                        echo "<option value='$num' $selected>$name</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-5">
                                <select class="form-select" id="yearSelect">
                                    <?php
                                    for ($y = $current_year; $y >= ($current_year - 3); $y--) {
                                        $selected = ($y == $filter_year) ? 'selected' : '';
                                        echo "<option value='$y' $selected>$y</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Monthly Stats -->
                    <div class="quick-stats">
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="h3 mb-1"><?php echo $monthly_data_count; ?></div>
                                <small>RKB Bulan Ini</small>
                            </div>
                            <div class="col-6">
                                <?php
                                $stmt_monthly_lkh = $conn->prepare("SELECT COUNT(*) as total FROM lkh l 
                                    JOIN rkb ON l.id_rkb = rkb.id_rkb 
                                    JOIN rhk ON rkb.id_rhk = rhk.id_rhk 
                                    WHERE rhk.id_pegawai = ? AND rkb.bulan = ? AND rkb.tahun = ?");
                                $stmt_monthly_lkh->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
                                $stmt_monthly_lkh->execute();
                                $monthly_lkh_count = $stmt_monthly_lkh->get_result()->fetch_assoc()['total'];
                                $stmt_monthly_lkh->close();
                                ?>
                                <div class="h3 mb-1"><?php echo $monthly_lkh_count; ?></div>
                                <small>LKH Bulan Ini</small>
                            </div>
                        </div>
                    </div>

                    <!-- Monthly Action -->
                    <?php if ($monthly_data_count > 0): ?>
                        <button class="action-btn" onclick="downloadMonthlyPDF()">
                            <i class="fas fa-download me-2"></i>
                            Download Laporan Bulanan PDF
                        </button>
                    <?php else: ?>
                        <div class="alert alert-warning text-center">
                            <i class="fas fa-info-circle me-2"></i>
                            Belum ada data untuk bulan <?php echo $months[$filter_month] . ' ' . $filter_year; ?>
                        </div>
                        <button class="action-btn" disabled>
                            <i class="fas fa-ban me-2"></i>
                            Tidak Ada Data Bulanan
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Annual Report Tab -->
            <div class="tab-pane fade" id="annual" role="tabpanel">
                <div class="report-card">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-line text-success me-2"></i>
                        Laporan Tahunan
                    </h5>
                    
                    <!-- Annual Stats -->
                    <div class="quick-stats">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">📈 Statistik Tahun <?php echo $periode_tahun; ?></h6>
                            <i class="fas fa-chart-bar fa-2x opacity-75"></i>
                        </div>
                        
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="h3 mb-1"><?php echo $annual_data_count; ?></div>
                                <small>Total RKB</small>
                            </div>
                            <div class="col-6">
                                <?php
                                $stmt_annual_lkh = $conn->prepare("SELECT COUNT(*) as total FROM lkh l 
                                    JOIN rkb ON l.id_rkb = rkb.id_rkb 
                                    JOIN rhk ON rkb.id_rhk = rhk.id_rhk 
                                    WHERE rhk.id_pegawai = ? AND rkb.tahun = ?");
                                $stmt_annual_lkh->bind_param("ii", $id_pegawai_login, $periode_tahun);
                                $stmt_annual_lkh->execute();
                                $annual_lkh_count = $stmt_annual_lkh->get_result()->fetch_assoc()['total'];
                                $stmt_annual_lkh->close();
                                ?>
                                <div class="h3 mb-1"><?php echo $annual_lkh_count; ?></div>
                                <small>Total LKH</small>
                            </div>
                        </div>
                        
                        <div class="text-center mt-3">
                            <small class="opacity-75">
                                <i class="fas fa-calendar-alt me-1"></i>
                                Periode: Januari - Desember <?php echo $periode_tahun; ?>
                            </small>
                        </div>
                    </div>

                    <!-- Feature Highlights -->
                    <div class="feature-highlight">
                        <div class="row">
                            <div class="col-6">
                                <i class="fas fa-file-pdf text-danger fa-2x mb-2"></i>
                                <h6>Format PDF</h6>
                                <small>Siap cetak & bagikan</small>
                            </div>
                            <div class="col-6">
                                <i class="fas fa-table text-primary fa-2x mb-2"></i>
                                <h6>Komprehensif</h6>
                                <small>Semua data tahun ini</small>
                            </div>
                        </div>
                    </div>

                    <!-- Annual Action -->
                    <?php if ($annual_data_count > 0): ?>
                        <a href="laporan_tahunan.php" class="action-btn">
                            <i class="fas fa-external-link-alt me-2"></i>
                            Buka Laporan Tahunan
                        </a>
                        <small class="text-muted d-block text-center mt-2">
                            <i class="fas fa-info-circle me-1"></i>
                            Akan membuka halaman laporan tahunan lengkap
                        </small>
                    <?php else: ?>
                        <div class="alert alert-warning text-center">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Belum ada data untuk tahun <?php echo $periode_tahun; ?>
                        </div>
                        <button class="action-btn" disabled>
                            <i class="fas fa-ban me-2"></i>
                            Tidak Ada Data Tahunan
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Security Notice -->
        <div class="report-card">
            <div class="text-center">
                <i class="fas fa-shield-alt text-success fa-2x mb-3"></i>
                <h6>Keamanan Data</h6>
                <p class="text-muted mb-0">
                    File PDF yang diunduh akan dihapus otomatis setelah 3 menit untuk melindungi privasi data Anda.
                </p>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h6>Menyiapkan Download</h6>
            <p class="text-muted mb-0">Mohon tunggu sebentar...</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Monthly report functions
        function downloadMonthlyPDF() {
            const month = document.getElementById('monthSelect').value;
            const year = document.getElementById('yearSelect').value;
            
            // Show loading
            showLoading();
            
            // Create form for monthly PDF download
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'generate_pdf_bulanan.php';
            form.style.display = 'none';
            
            const monthInput = document.createElement('input');
            monthInput.type = 'hidden';
            monthInput.name = 'month';
            monthInput.value = month;
            form.appendChild(monthInput);
            
            const yearInput = document.createElement('input');
            yearInput.type = 'hidden';
            yearInput.name = 'year';
            yearInput.value = year;
            form.appendChild(yearInput);
            
            const mobileInput = document.createElement('input');
            mobileInput.type = 'hidden';
            mobileInput.name = 'mobile_app';
            mobileInput.value = '1';
            form.appendChild(mobileInput);
            
            document.body.appendChild(form);
            form.submit();
            
            // Hide loading after delay
            setTimeout(() => {
                hideLoading();
                document.body.removeChild(form);
            }, 3000);
        }

        // Update monthly data when month/year changes
        document.getElementById('monthSelect').addEventListener('change', updateMonthlyData);
        document.getElementById('yearSelect').addEventListener('change', updateMonthlyData);

        function updateMonthlyData() {
            const month = document.getElementById('monthSelect').value;
            const year = document.getElementById('yearSelect').value;
            showLoading();
            window.location.href = `laporan.php?month=${month}&year=${year}`;
        }

        // Loading functions
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }

        // Tab switching enhancements
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('[data-bs-toggle="pill"]');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Add haptic feedback for mobile
                    if (navigator.vibrate) {
                        navigator.vibrate(50);
                    }
                });
            });
            
            // Add touch feedback
            const actionButtons = document.querySelectorAll('.action-btn');
            actionButtons.forEach(button => {
                button.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.98)';
                });
                
                button.addEventListener('touchend', function() {
                    this.style.transform = 'scale(1)';
                });
            });

            // Handle URL hash for direct tab access
            const hash = window.location.hash;
            if (hash === '#annual') {
                const annualTab = document.getElementById('annual-tab');
                const monthlyTab = document.getElementById('monthly-tab');
                const annualPane = document.getElementById('annual');
                const monthlyPane = document.getElementById('monthly');
                
                if (annualTab && annualPane) {
                    monthlyTab.classList.remove('active');
                    monthlyPane.classList.remove('show', 'active');
                    annualTab.classList.add('active');
                    annualPane.classList.add('show', 'active');
                }
            }
        });

        // Handle page visibility to hide loading if user switches tabs/apps
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                hideLoading();
            }
        });
    </script>
</body>
</html>
            <!-- LKB Tab Pane -->
            <div class="tab-pane fade show active" id="lkb-pane" role="tabpanel" aria-labelledby="lkb-tab">
                <div class="tab-header">
                    <h5><i class="fas fa-file-alt text-primary me-2"></i>Laporan Kinerja Bulanan (LKB)</h5>
                    <p>Laporan kinerja yang disusun berdasarkan rencana kerja bulanan</p>
                </div>
                
                <div class="report-section">
                    <?php foreach ($years as $tahun): ?>
                        <?php for ($bulan = 1; $bulan <= 12; $bulan++): ?>
                            <?php
                            // Check RKB existence and status
                            $stmt = $conn->prepare("SELECT id_rkb, status_verval FROM rkb WHERE id_pegawai=? AND bulan=? AND tahun=?");
                            $stmt->bind_param("iii", $id_pegawai_login, $bulan, $tahun);
                            $stmt->execute();
                            $stmt->store_result();
                            $count_rkb = $stmt->num_rows;
                            $id_rkb = null;
                            $status_verval_rkb = null;
                            if ($count_rkb > 0) {
                                $stmt->bind_result($id_rkb, $status_verval_rkb);
                                $stmt->fetch();
                            }
                            $stmt->close();

                            if ($count_rkb == 0) continue; // Skip months without RKB
                            
                            if ($status_verval_rkb === 'disetujui'):
                                $pdf_exists_lkb = lkb_pdf_exists($id_pegawai_login, $bulan, $tahun, $nama_file_nip, $months);
                                $lkb_filename_for_download = "LKB_{$months[$bulan]}_{$tahun}_{$nama_file_nip}.pdf";
                            ?>
                                <div class="report-item lkb-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="report-period"><?= $months[$bulan] ?> <?= $tahun ?></div>
                                            <span class="badge bg-success badge-status">
                                                <i class="fas fa-check-circle me-1"></i>Disetujui
                                            </span>
                                        </div>
                                        <div class="report-actions">
                                            <?php if ($pdf_exists_lkb): ?>
                                                <button type="button" class="btn btn-download btn-sm" 
                                                        onclick="downloadFile('../generated/<?= $lkb_filename_for_download ?>', '<?= $lkb_filename_for_download ?>')">
                                                    <i class="fas fa-download me-1"></i>Download
                                                </button>
                                                <button type="button" class="btn btn-regenerate btn-sm" 
                                                        data-bs-toggle="modal" data-bs-target="#generateLkbModal" 
                                                        data-bulan="<?= $bulan ?>" data-tahun="<?= $tahun ?>">
                                                    <i class="fas fa-sync me-1"></i>Generate Ulang
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-generate btn-sm" 
                                                        data-bs-toggle="modal" data-bs-target="#generateLkbModal" 
                                                        data-bulan="<?= $bulan ?>" data-tahun="<?= $tahun ?>">
                                                    <i class="fas fa-cogs me-1"></i>Generate LKB
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($status_verval_rkb === 'diajukan'): ?>
                                <div class="report-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="report-period"><?= $months[$bulan] ?> <?= $tahun ?></div>
                                            <span class="badge bg-warning badge-status">
                                                <i class="fas fa-clock me-1"></i>Menunggu Approval
                                            </span>
                                        </div>
                                        <div class="report-actions">
                                            <span class="text-muted small">Belum dapat digenerate</span>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="report-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="report-period"><?= $months[$bulan] ?> <?= $tahun ?></div>
                                            <span class="badge bg-secondary badge-status">
                                                <i class="fas fa-times-circle me-1"></i>Belum Terkirim
                                            </span>
                                        </div>
                                        <div class="report-actions">
                                            <span class="text-muted small">Belum dapat digenerate</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- LKH Tab Pane -->
            <div class="tab-pane fade" id="lkh-pane" role="tabpanel" aria-labelledby="lkh-tab">
                <div class="tab-header">
                    <h5><i class="fas fa-list text-info me-2"></i>Laporan Kinerja Harian (LKH)</h5>
                    <p>Laporan kinerja harian berdasarkan aktivitas setiap hari kerja</p>
                </div>
                
                <div class="report-section">
                    <?php foreach ($years as $tahun): ?>
                        <?php for ($bulan = 1; $bulan <= 12; $bulan++): ?>
                            <?php
                            // Check LKH existence and status
                            $stmt = $conn->prepare("SELECT id_lkh, status_verval FROM lkh WHERE id_pegawai=? AND MONTH(tanggal_lkh)=? AND YEAR(tanggal_lkh)=?");
                            $stmt->bind_param("iii", $id_pegawai_login, $bulan, $tahun);
                            $stmt->execute();
                            $stmt->store_result();
                            $count_lkh = $stmt->num_rows;
                            $id_lkh = null;
                            $status_verval_lkh = null;
                            if ($count_lkh > 0) {
                                $stmt->bind_result($id_lkh, $status_verval_lkh);
                                $stmt->fetch();
                            }
                            $stmt->close();

                            if ($count_lkh == 0) continue; // Skip months without LKH
                            
                            if ($status_verval_lkh === 'disetujui'):
                                $pdf_exists_lkh = lkh_pdf_exists($id_pegawai_login, $bulan, $tahun, $nama_file_nip, $months);
                                $lkh_filename_for_download = "LKH_{$months[$bulan]}_{$tahun}_{$nama_file_nip}.pdf";
                            ?>
                                <div class="report-item lkh-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="report-period"><?= $months[$bulan] ?> <?= $tahun ?></div>
                                            <span class="badge bg-success badge-status">
                                                <i class="fas fa-check-circle me-1"></i>Disetujui
                                            </span>
                                        </div>
                                        <div class="report-actions">
                                            <?php if ($pdf_exists_lkh): ?>
                                                <button type="button" class="btn btn-download btn-sm" 
                                                        onclick="downloadFile('../generated/<?= $lkh_filename_for_download ?>', '<?= $lkh_filename_for_download ?>')">
                                                    <i class="fas fa-download me-1"></i>Download
                                                </button>
                                                <button type="button" class="btn btn-regenerate btn-sm" 
                                                        data-bs-toggle="modal" data-bs-target="#generateLkhModal" 
                                                        data-bulan="<?= $bulan ?>" data-tahun="<?= $tahun ?>">
                                                    <i class="fas fa-sync me-1"></i>Generate Ulang
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-generate btn-sm" 
                                                        data-bs-toggle="modal" data-bs-target="#generateLkhModal" 
                                                        data-bulan="<?= $bulan ?>" data-tahun="<?= $tahun ?>">
                                                    <i class="fas fa-cogs me-1"></i>Generate LKH
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($status_verval_lkh === 'diajukan'): ?>
                                <div class="report-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="report-period"><?= $months[$bulan] ?> <?= $tahun ?></div>
                                            <span class="badge bg-warning badge-status">
                                                <i class="fas fa-clock me-1"></i>Menunggu Approval
                                            </span>
                                        </div>
                                        <div class="report-actions">
                                            <span class="text-muted small">Belum dapat digenerate</span>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="report-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="report-period"><?= $months[$bulan] ?> <?= $tahun ?></div>
                                            <span class="badge bg-secondary badge-status">
                                                <i class="fas fa-times-circle me-1"></i>Belum Terkirim
                                            </span>
                                        </div>
                                        <div class="report-actions">
                                            <span class="text-muted small">Belum dapat digenerate</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Yearly Report Tab -->
            <div class="tab-pane fade" id="tahunan-pane" role="tabpanel" aria-labelledby="tahunan-tab">
                <div class="tab-header">
                    <h5><i class="fas fa-chart-line text-info me-2"></i>Laporan Kinerja Tahunan</h5>
                    <p>Laporan kinerja yang disusun berdasarkan rencana kerja tahunan</p>
                </div>
                
                <div class="p-3">
                    <!-- Year Selection -->
                    <div class="mb-3">
                        <form method="GET" class="d-flex align-items-center gap-2">
                            <input type="hidden" name="tab" value="tahunan">
                            <label for="year" class="form-label mb-0 text-nowrap">Pilih Tahun:</label>
                            <select name="year" id="year" class="form-select form-select-sm" style="max-width: 120px;">
                                <?php
                                $current_year = (int)date('Y');
                                $selected_year = isset($_GET['year']) ? (int)$_GET['year'] : $current_year;
                                for ($y = $current_year; $y >= $current_year - 5; $y--) {
                                    $selected = ($y === $selected_year) ? 'selected' : '';
                                    echo "<option value='$y' $selected>$y</option>";
                                }
                                ?>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    
                    <!-- Yearly Report Content -->
                    <?php 
                    // Set year for yearly report
                    $_GET['year'] = $selected_year;
                    include 'generate_yearly_report.php'; 
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Generate LKB -->
    <div class="modal fade" id="generateLkbModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-file-alt me-2"></i>Generate LKB
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="generateLkbForm" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="tempat_cetak_lkb" class="form-label">Tempat Cetak</label>
                            <input type="text" class="form-control" id="tempat_cetak_lkb" name="tempat_cetak" value="Cingambul" required>
                        </div>
                        <div class="mb-3">
                            <label for="tanggal_cetak_lkb" class="form-label">Tanggal Cetak</label>
                            <input type="date" class="form-control" id="tanggal_cetak_lkb" name="tanggal_cetak" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-cogs me-1"></i>Generate LKB
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Generate LKH -->
    <div class="modal fade" id="generateLkhModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-list me-2"></i>Generate LKH
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="generateLkhForm" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="tempat_cetak_lkh" class="form-label">Tempat Cetak</label>
                            <input type="text" class="form-control" id="tempat_cetak_lkh" name="tempat_cetak" value="Cingambul" required>
                        </div>
                        <div class="mb-3">
                            <label for="tanggal_cetak_lkh" class="form-label">Tanggal Cetak</label>
                            <input type="date" class="form-control" id="tanggal_cetak_lkh" name="tanggal_cetak" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-cogs me-1"></i>Generate LKH
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <?php include __DIR__ . '/components/bottom-nav.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Show notifications
        <?php if (isset($_SESSION['mobile_notification'])): ?>
            Swal.fire({
                icon: '<?= $_SESSION['mobile_notification']['type'] ?>',
                title: '<?= $_SESSION['mobile_notification']['title'] ?>',
                text: '<?= $_SESSION['mobile_notification']['text'] ?>',
                timer: 3000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['mobile_notification']); ?>
        <?php endif; ?>

        // Set form action when LKB modal is opened
        document.getElementById('generateLkbModal').addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var bulan = button.getAttribute('data-bulan');
            var tahun = button.getAttribute('data-tahun');
            var form = document.getElementById('generateLkbForm');
            form.action = 'generate_lkb.php?bulan=' + bulan + '&tahun=' + tahun + '&aksi=generate';
        });

        // Set form action when LKH modal is opened
        document.getElementById('generateLkhModal').addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var bulan = button.getAttribute('data-bulan');
            var tahun = button.getAttribute('data-tahun');
            var form = document.getElementById('generateLkhForm');
            form.action = 'generate_lkh.php?bulan=' + bulan + '&tahun=' + tahun + '&aksi=generate';
        });

        // Fixed download function for Android WebView compatibility
        function downloadFile(url, filename) {
            console.log('Download initiated:', url, filename);
            
            try {
                // Method 1: Try Android interface first
                if (typeof Android !== 'undefined' && Android.downloadFile) {
                    console.log('Using Android interface');
                    Android.downloadFile(url, filename);
                    Swal.fire({
                        icon: 'success',
                        title: 'Download Dimulai',
                        text: 'File sedang diunduh melalui aplikasi Android...',
                        timer: 3000,
                        showConfirmButton: false
                    });
                    return;
                }
                
                // Method 2: Try window.location for WebView
                if (navigator.userAgent.includes('wv')) {
                    console.log('Using WebView window.location method');
                    window.location.href = url;
                    Swal.fire({
                        icon: 'info',
                        title: 'Membuka File',
                        text: 'File akan dibuka/diunduh...',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    return;
                }
                
                // Method 3: Traditional download link
                const link = document.createElement('a');
                link.href = url;
                link.download = filename;
                link.target = '_blank';
                
                // Add to DOM temporarily
                document.body.appendChild(link);
                
                // Trigger click
                link.click();
                
                // Clean up
                setTimeout(() => {
                    document.body.removeChild(link);
                }, 100);
                
                console.log('Download link clicked');
                
                // Show appropriate message
                Swal.fire({
                    icon: 'info',
                    title: 'Download Diproses',
                    text: 'Silakan periksa folder Download Anda.',
                    timer: 3000,
                    showConfirmButton: false
                });
                
            } catch (error) {
                console.error('Download error:', error);
                
                // Fallback: Try direct navigation
                Swal.fire({
                    icon: 'question',
                    title: 'Metode Download Alternatif',
                    text: 'Klik "Buka File" untuk mengunduh atau melihat file.',
                    showCancelButton: true,
                    confirmButtonText: 'Buka File',
                    cancelButtonText: 'Tutup'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.open(url, '_blank');
                    }
                });
            }
        }

        // Alternative fetch-based download with proper headers
        async function downloadFileWithFetch(url, filename) {
            try {
                console.log('Fetch download started:', url);
                
                Swal.fire({
                    title: 'Mengunduh...',
                    text: 'Sedang memproses unduhan file',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Add timestamp to prevent caching issues
                const downloadUrl = url + '?t=' + new Date().getTime();
                
                const response = await fetch(downloadUrl, {
                    method: 'GET',
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const blob = await response.blob();
                console.log('Blob created, size:', blob.size);
                
                // Check if we're in WebView
                if (navigator.userAgent.includes('wv')) {
                    // For WebView, try to trigger native download
                    const reader = new FileReader();
                    reader.onload = function() {
                        const base64data = reader.result.split(',')[1];
                        
                        // Try Android interface for base64 download
                        if (typeof Android !== 'undefined' && Android.downloadBase64) {
                            Android.downloadBase64(base64data, filename, blob.type);
                        } else {
                            // Fallback to blob URL
                            const downloadUrl = window.URL.createObjectURL(blob);
                            window.location.href = downloadUrl;
                        }
                    };
                    reader.readAsDataURL(blob);
                } else {
                    // Normal browser download
                    const downloadUrl = window.URL.createObjectURL(blob);
                    
                    const link = document.createElement('a');
                    link.href = downloadUrl;
                    link.download = filename;
                    link.style.display = 'none';
                    
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    // Clean up
                    setTimeout(() => {
                        window.URL.revokeObjectURL(downloadUrl);
                    }, 1000);
                }
                
                Swal.fire({
                    icon: 'success',
                    title: 'Download Selesai',
                    text: 'File berhasil diunduh! Periksa folder Download.',
                    timer: 3000,
                    showConfirmButton: false
                });
                
            } catch (error) {
                console.error('Fetch download error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Download Gagal',
                    text: 'Terjadi kesalahan: ' + error.message,
                    confirmButtonText: 'OK'
                });
            }
        }

        // Main download function with multiple fallbacks - FIXED VERSION
        function enhancedDownload(url, filename) {
            console.log('Enhanced download called:', url, filename);
            
            // Detect environment
            const isAndroid = /Android/i.test(navigator.userAgent);
            const isWebView = navigator.userAgent.includes('wv');
            
            console.log('Environment:', { isAndroid, isWebView });
            
            // Strategy 1: Android WebView with native interface
            if (isAndroid && isWebView && typeof Android !== 'undefined') {
                if (Android.downloadFile) {
                    console.log('Using Android.downloadFile');
                    try {
                        Android.downloadFile(url, filename);
                        Swal.fire({
                            icon: 'success',
                            title: 'Download Dimulai',
                            text: 'File sedang diunduh ke perangkat Anda...',
                            timer: 3000,
                            showConfirmButton: false
                        });
                        return;
                    } catch (e) {
                        console.error('Android download failed:', e);
                    }
                }
            }
            
            // Strategy 2: Fetch API for WebView
            if (isWebView && window.fetch) {
                console.log('Using fetch download for WebView');
                downloadFileWithFetch(url, filename);
                return;
            }
            
            // Strategy 3: Direct URL navigation for WebView
            if (isWebView) {
                console.log('Using direct navigation for WebView');
                window.location.href = url;
                Swal.fire({
                    icon: 'info',
                    title: 'Membuka File',
                    text: 'File akan dibuka atau diunduh...',
                    timer: 2000,
                    showConfirmButton: false
                });
                return;
            }
            
            // Strategy 4: Traditional download for regular browsers
            console.log('Using traditional download');
            // Call the original downloadFile function, NOT recursively calling enhancedDownload
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            link.target = '_blank';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            Swal.fire({
                icon: 'info',
                title: 'Download Diproses',
                text: 'Silakan periksa folder Download Anda.',
                timer: 3000,
                showConfirmButton: false
            });
        }

        // Set the main download function
        window.downloadFile = enhancedDownload;

        // Add smooth scroll animation for report items
        document.addEventListener('DOMContentLoaded', function() {
            const items = document.querySelectorAll('.report-item');
            items.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    item.style.transition = 'all 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Add smooth animation when switching tabs
        document.addEventListener('DOMContentLoaded', function() {
            const triggerTabList = document.querySelectorAll('#reportTabs button')
            triggerTabList.forEach(triggerEl => {
                const tabTrigger = new bootstrap.Tab(triggerEl)

                triggerEl.addEventListener('click', event => {
                    event.preventDefault()
                    tabTrigger.show()
                    
                    // Re-animate items when tab is shown
                    setTimeout(() => {
                        const activePane = document.querySelector('.tab-pane.active');
                        const items = activePane.querySelectorAll('.report-item');
                        items.forEach((item, index) => {
                            item.style.opacity = '0';
                            item.style.transform = 'translateY(10px)';
                            setTimeout(() => {
                                item.style.transition = 'all 0.3s ease';
                                item.style.opacity = '1';
                                item.style.transform = 'translateY(0)';
                            }, index * 50);
                        });
                    }, 100);
                })
            })

            // Initial animation for LKB tab (active by default)
            const items = document.querySelectorAll('#lkb-pane .report-item');
            items.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    item.style.transition = 'all 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>
