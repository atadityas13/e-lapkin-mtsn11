<?php
/**
 * E-LAPKIN Mobile Report Management
 */

// Start output buffering to catch any unwanted output
ob_start();

session_start();

// Include mobile session config
require_once __DIR__ . '/config/mobile_session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/components/mobile-header.php';

// Check mobile login
checkMobileLogin();

// Get user session data
$userData = getMobileSessionData();
$id_pegawai_login = $userData['id_pegawai'];
$nama_pegawai_login = $userData['nama'];

$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Get active period from user settings
$periode_mulai = $periode_akhir = null;
$stmt_periode = $conn->prepare("SELECT tahun_aktif FROM pegawai WHERE id_pegawai = ?");
$stmt_periode->bind_param("i", $id_pegawai_login);
$stmt_periode->execute();
$stmt_periode->bind_result($tahun_aktif_pegawai);
$stmt_periode->fetch();
$stmt_periode->close();

if ($tahun_aktif_pegawai) {
    $periode_mulai = $periode_akhir = (int)$tahun_aktif_pegawai;
} else {
    $periode_mulai = $periode_akhir = (int)date('Y');
}

// Initialize years array
$years = [];
for ($y = $periode_akhir; $y >= $periode_mulai; $y--) {
    $years[] = $y;
}

// Get NIP for filename generation
$nip_pegawai = '';
$stmt_nip = $conn->prepare("SELECT nip FROM pegawai WHERE id_pegawai = ?");
$stmt_nip->bind_param("i", $id_pegawai_login);
$stmt_nip->execute();
$stmt_nip->bind_result($nip_pegawai_db);
$stmt_nip->fetch();
$stmt_nip->close();

$nama_file_nip = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nip_pegawai_db);

// Check if PDF files exist
function lkb_pdf_exists($id_pegawai, $bulan, $tahun, $nama_file_nip_param, $months_param) {
    $filename = __DIR__ . "/../generated/LKB_{$months_param[$bulan]}_{$tahun}_{$nama_file_nip_param}.pdf";
    return file_exists($filename);
}

function lkh_pdf_exists($id_pegawai, $bulan, $tahun, $nama_file_nip_param, $months_param) {
    $filename = __DIR__ . "/../generated/LKH_{$months_param[$bulan]}_{$tahun}_{$nama_file_nip_param}.pdf";
    return file_exists($filename);
}

// Helper function for notifications
function set_mobile_notification($type, $title, $text) {
    $_SESSION['mobile_notification'] = [
        'type' => $type,
        'title' => $title,
        'text' => $text
    ];
}

// Clear any unwanted output before HTML
ob_clean();
$activePeriod = getMobileActivePeriod($conn, $id_pegawai_login);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - E-Lapkin Mobile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --card-shadow: 0 8px 25px rgba(0,0,0,0.1);
            --card-hover-shadow: 0 12px 35px rgba(0,0,0,0.15);
            --accent-blue: #667eea;
            --accent-purple: #764ba2;
            --success-gradient: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        }

        body {
            padding-bottom: 80px;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f2ff 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .nav-header {
            background: var(--primary-gradient);
            color: white;
            padding: 20px 15px;
            border-radius: 0 0 25px 25px;
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
        }

        .nav-header .navbar-brand {
            font-size: 1.3rem;
            font-weight: 600;
        }

        .nav-header .text-end {
            text-align: right !important;
            min-width: 150px;
        }

        .nav-header .text-end > div {
            white-space: nowrap;
            margin-bottom: 2px;
        }

        .card {
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            border: none;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-hover-shadow);
        }

        .report-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9ff 100%);
        }

        .lkb-card {
            border-left: 4px solid #007bff;
        }

        .lkh-card {
            border-left: 4px solid #17a2b8;
        }

        .btn {
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-sm {
            padding: 4px 8px;
            font-size: 0.75rem;
        }

        .btn-download {
            background: var(--success-gradient);
            border: none;
            color: white;
        }

        .btn-download:hover {
            color: white;
            box-shadow: 0 4px 15px rgba(86, 171, 47, 0.3);
        }

        .btn-generate {
            background: var(--primary-gradient);
            border: none;
            color: white;
        }

        .btn-generate:hover {
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-regenerate {
            background: var(--warning-gradient);
            border: none;
            color: white;
        }

        .btn-regenerate:hover {
            color: white;
            box-shadow: 0 4px 15px rgba(240, 147, 251, 0.3);
        }

        .badge {
            border-radius: 15px;
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-status {
            padding: 8px 12px;
        }

        .report-header {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.05));
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .report-section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 10px;
            width: 20px;
        }

        .report-item {
            background: white;
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .report-item:hover {
            transform: translateX(5px);
            border-left-color: var(--accent-blue);
        }

        .report-item-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 10px;
        }

        .report-period {
            font-weight: 600;
            color: #495057;
        }

        .report-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: var(--card-hover-shadow);
        }

        .modal-header {
            border-radius: 20px 20px 0 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }

        .form-control {
            border-radius: 10px;
            border: 2px solid rgba(102, 126, 234, 0.2);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .info-alert {
            background: linear-gradient(135deg, rgba(23, 162, 184, 0.1), rgba(19, 132, 150, 0.05));
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #17a2b8;
        }

        @media (max-width: 576px) {
            .card-body {
                padding: 15px;
            }
            
            .report-actions {
                flex-direction: column;
            }
            
            .report-actions .btn {
                width: 100%;
            }
        }

        /* Tab Styles */
        .nav-tabs {
            border: none;
            margin-bottom: 20px;
            padding: 0 10px;
        }

        .nav-tabs .nav-item {
            margin-bottom: 0;
            flex: 1;
        }

        .nav-tabs .nav-link {
            border: none;
            border-radius: 15px;
            margin-right: 8px;
            background: white;
            color: #6c757d;
            font-weight: 600;
            padding: 18px 20px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            font-size: 1rem;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 65px;
        }

        .nav-tabs .nav-link:hover {
            color: var(--accent-blue);
            background: rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }

        .nav-tabs .nav-link.active {
            background: var(--primary-gradient);
            color: white;
            border: none;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .nav-tabs .nav-link i {
            font-size: 1.3rem;
            margin-bottom: 5px;
            display: block;
        }

        .nav-tabs .nav-link .tab-text {
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        @media (max-width: 768px) {
            .nav-tabs {
                padding: 0 5px;
            }
            
            .nav-tabs .nav-link {
                padding: 15px 15px;
                font-size: 0.9rem;
                margin-right: 5px;
                min-height: 60px;
            }
            
            .nav-tabs .nav-link i {
                font-size: 1.2rem;
                margin-bottom: 4px;
            }
            
            .nav-tabs .nav-link .tab-text {
                font-size: 0.8rem;
            }
        }

        @media (max-width: 576px) {
            .nav-tabs {
                padding: 0 3px;
            }
            
            .nav-tabs .nav-link {
                padding: 12px 10px;
                font-size: 0.8rem;
                margin-right: 3px;
                min-height: 55px;
            }
            
            .nav-tabs .nav-link i {
                font-size: 1.1rem;
                margin-bottom: 3px;
            }
            
            .nav-tabs .nav-link .tab-text {
                font-size: 0.75rem;
            }
        }

        @media (max-width: 400px) {
            .nav-tabs .nav-link {
                padding: 10px 8px;
                font-size: 0.75rem;
                margin-right: 2px;
                min-height: 50px;
            }
            
            .nav-tabs .nav-link i {
                font-size: 1rem;
                margin-bottom: 2px;
            }
            
            .nav-tabs .nav-link .tab-text {
                font-size: 0.7rem;
            }
        }

        .tab-content {
            background: transparent;
        }

        .tab-pane {
            animation: fadeInUp 0.3s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .tab-header {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.05));
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }

        .tab-header h5 {
            margin: 0;
            color: #2d3748;
            font-weight: 600;
        }

        .tab-header p {
            margin: 5px 0 0 0;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .mobile-container {
            max-width: 100%;
            margin: 0 auto;
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .tab-content {
            background: white;
            border-radius: 0 0 15px 15px;
            min-height: 70vh;
        }
        
        .nav-tabs .nav-link {
            font-size: 12px;
            padding: 8px 12px;
        }
        
        .nav-tabs .nav-link.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        @media (max-width: 576px) {
            .nav-tabs .nav-link {
                font-size: 10px;
                padding: 6px 8px;
            }
        }
    </style>
    <?= getMobileHeaderCSS() ?>
</head>
<body>
    <div class="mobile-container">
        <!-- Loader "Tunggu sebentar..." jika proses generate sedang berlangsung -->
        <?php if (isset($_SESSION['mobile_loader']) && $_SESSION['mobile_loader']): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Tunggu sebentar...',
                        text: 'LKB sedang diproses. Mohon tunggu hingga selesai.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                });
            </script>
            <?php unset($_SESSION['mobile_loader']); ?>
        <?php endif; ?>
        <!-- Header -->
        <?php renderMobileHeader('Laporan Kinerja', 'Generate & Unduh', 'dashboard.php', $userData, $activePeriod); ?>

        <!-- Info Alert -->
        <div class="info-alert">
            <div class="d-flex align-items-start">
                <i class="fas fa-info-circle text-info me-3 mt-1"></i>
                <div>
                    <div class="fw-semibold text-info mb-1">Informasi Laporan</div>
                    <small class="text-muted">
                        LKB & LKH dapat digenerate jika data sudah disetujui oleh Pejabat Penilai. 
                        File akan tersimpan dalam format PDF dan dapat diunduh kapan saja.
                    </small>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="bg-white px-2 pt-3">
            <ul class="nav nav-tabs d-flex" id="reportTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="lkb-tab" data-bs-toggle="tab" data-bs-target="#lkb-pane" 
                            type="button" role="tab" aria-controls="lkb-pane" aria-selected="true">
                        <i class="fas fa-file-alt"></i>
                        <span class="tab-text">LKB</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="lkh-tab" data-bs-toggle="tab" data-bs-target="#lkh-pane" 
                            type="button" role="tab" aria-controls="lkh-pane" aria-selected="false">
                        <i class="fas fa-list"></i>
                        <span class="tab-text">LKH</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tahunan-tab" data-bs-toggle="tab" data-bs-target="#tahunan-pane" 
                            type="button" role="tab" aria-controls="tahunan-pane" aria-selected="false">
                        <i class="fas fa-chart-line"></i>
                        <span class="tab-text">Tahunan</span>
                    </button>
                </li>
            </ul>
        </div>

        <!-- Tab Content -->
        <div class="tab-content" id="reportTabContent">
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
                    <!-- Development Notice -->
                    <div class="report-item" style="background: linear-gradient(135deg, #ffeaa7, #fdcb6e); border: none; text-align: center; padding: 30px 20px;">
                        <div class="mb-3">
                            <i class="fas fa-tools fa-3x text-warning mb-3"></i>
                            <h5 class="text-dark mb-2">Fitur Sedang Dikembangkan</h5>
                            <p class="text-dark mb-0">
                                Laporan Tahunan saat ini masih dalam tahap pengembangan untuk versi mobile. 
                                Untuk mengakses laporan tahunan, silakan gunakan aplikasi web utama melalui browser.
                            </p>
                        </div>
                    </div>

                    <!-- Year Selection (for display only) -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center gap-2 justify-content-center">
                            <label class="form-label mb-0 text-nowrap">Tahun Aktif:</label>
                            <select class="form-select form-select-sm" style="max-width: 120px;" disabled>
                                <?php
                                $current_year = (int)date('Y');
                                $selected_year = isset($_GET['year']) ? (int)$_GET['year'] : $current_year;
                                for ($y = $current_year; $y >= $current_year - 5; $y--) {
                                    $selected = ($y === $selected_year) ? 'selected' : '';
                                    echo "<option value='$y' $selected>$y</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <!-- Annual Report Stats (Read-only) -->
                    <?php
                    // Get annual data count for selected year
                    $stmt_annual_check = $conn->prepare("SELECT COUNT(*) as total FROM rhk r 
                        JOIN rkb ON r.id_rhk = rkb.id_rhk 
                        WHERE r.id_pegawai = ? AND rkb.tahun = ?");
                    $stmt_annual_check->bind_param("ii", $id_pegawai_login, $selected_year);
                    $stmt_annual_check->execute();
                    $annual_data_selected = $stmt_annual_check->get_result()->fetch_assoc()['total'];
                    $stmt_annual_check->close();

                    $stmt_annual_lkh_check = $conn->prepare("SELECT COUNT(*) as total FROM lkh l 
                        JOIN rkb ON l.id_rkb = rkb.id_rkb 
                        JOIN rhk ON rkb.id_rhk = rhk.id_rhk 
                        WHERE rhk.id_pegawai = ? AND rkb.tahun = ?");
                    $stmt_annual_lkh_check->bind_param("ii", $id_pegawai_login, $selected_year);
                    $stmt_annual_lkh_check->execute();
                    $annual_lkh_selected = $stmt_annual_lkh_check->get_result()->fetch_assoc()['total'];
                    $stmt_annual_lkh_check->close();
                    ?>

                    <!-- Annual Statistics Card (Read-only) -->
                    <div class="report-item" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border: none;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">📊 Data Tahun <?php echo $selected_year; ?></h6>
                            <i class="fas fa-chart-bar fa-2x opacity-75"></i>
                        </div>
                        
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="h3 mb-1"><?php echo $annual_data_selected; ?></div>
                                <small>Total RKB</small>
                            </div>
                            <div class="col-6">
                                <div class="h3 mb-1"><?php echo $annual_lkh_selected; ?></div>
                                <small>Total LKH</small>
                            </div>
                        </div>
                        
                        <div class="text-center mt-3">
                            <small class="opacity-75">
                                <i class="fas fa-calendar-alt me-1"></i>
                                Data tersedia untuk tahun <?php echo $selected_year; ?>
                            </small>
                        </div>
                    </div>

                    <!-- Web Access Instructions -->
                    <div class="report-item">
                        <div class="text-center mb-3">
                            <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width: 60px; height: 60px;">
                                <i class="fas fa-globe fa-2x text-primary"></i>
                            </div>
                            <h6>Akses Melalui Aplikasi Web</h6>
                            <p class="text-muted small mb-3">
                                Untuk mengakses laporan tahunan, silakan buka aplikasi web E-Lapkin melalui browser
                            </p>
                        </div>

                        <!-- Web Access Button -->
                        <button class="btn btn-primary w-100 mb-3" onclick="openWebApplication()">
                            <i class="fas fa-external-link-alt me-2"></i>
                            Buka Aplikasi Web E-Lapkin
                        </button>

                        <!-- Alternative Manual Access -->
                        <div class="alert alert-info">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-info-circle text-info me-2 mt-1"></i>
                                <div>
                                    <small class="fw-bold">Cara Akses Manual:</small>
                                    <ol class="small mb-0 mt-1 ps-3">
                                        <li>Buka browser (Chrome, Firefox, dll)</li>
                                        <li>Ketik alamat: <code>https://e-lapkin.mtsn11majalengka.sch.id/</code></li>
                                        <li>Login dengan akun yang sama</li>
                                        <li>Pilih menu "Laporan" → "Laporan Kinerja Tahunan"</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Feature Coming Soon -->
                    <div class="report-item" style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.05)); border-left: 4px solid #667eea;">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-rocket text-primary fa-2x me-3"></i>
                            <div>
                                <h6 class="mb-1">Segera Hadir di Mobile App</h6>
                                <small class="text-muted">
                                    Fitur laporan tahunan akan segera tersedia di versi mobile. 
                                    Terima kasih atas kesabaran Anda.
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Development Timeline -->
                    <div class="report-item">
                        <h6 class="text-center mb-3">
                            <i class="fas fa-roadmap text-warning me-2"></i>
                            Roadmap Pengembangan
                        </h6>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="mb-2">
                                    <i class="fas fa-check-circle text-success fa-2x"></i>
                                </div>
                                <small class="fw-bold text-success">LKB & LKH</small>
                                <div class="small text-muted">Selesai</div>
                            </div>
                            <div class="col-4">
                                <div class="mb-2">
                                    <i class="fas fa-cog fa-spin text-warning fa-2x"></i>
                                </div>
                                <small class="fw-bold text-warning">Laporan Tahunan</small>
                                <div class="small text-muted">Dalam Pengembangan</div>
                            </div>
                            <div class="col-4">
                                <div class="mb-2">
                                    <i class="fas fa-clock text-secondary fa-2x"></i>
                                </div>
                                <small class="fw-bold text-secondary">Fitur Lainnya</small>
                                <div class="small text-muted">Akan Datang</div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Support -->
                    <div class="report-item">
                        <div class="text-center">
                            <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-2" style="width: 50px; height: 50px;">
                                <i class="fas fa-headset text-info"></i>
                            </div>
                            <div class="small text-muted">
                                Butuh bantuan? Hubungi admin sistem untuk informasi lebih lanjut.
                            </div>
                        </div>
                    </div>
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

        // Loader on form submit for Generate LKB
        document.getElementById('generateLkbForm').addEventListener('submit', function(e) {
            Swal.fire({
                title: 'Tunggu sebentar...',
                text: 'LKB sedang diproses. Mohon tunggu hingga selesai.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            // Form will submit normally after showing loader
        });

        // Loader on form submit for Generate LKH
        document.getElementById('generateLkhForm').addEventListener('submit', function(e) {
            Swal.fire({
                title: 'Tunggu sebentar...',
                text: 'LKH sedang diproses. Mohon tunggu hingga selesai.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        });

        // Loader if session mobile_loader is set (server-side)
        <?php if (isset($_SESSION['mobile_loader']) && $_SESSION['mobile_loader']): ?>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Tunggu sebentar...',
                    text: 'LKB sedang diproses. Mohon tunggu hingga selesai.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            });
            <?php unset($_SESSION['mobile_loader']); ?>
        <?php endif; ?>

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

        // Annual report functions - Updated for web redirect
        function openWebApplication() {
            Swal.fire({
                title: 'Membuka Aplikasi Web',
                text: 'Anda akan dialihkan ke aplikasi web E-Lapkin',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Lanjutkan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#667eea'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Try to open in same window/tab for better mobile experience
                    const webUrl = 'https://e-lapkin.mtsn11majalengka.sch.id/';
                    
                    // For mobile WebView, try to open in external browser
                    if (navigator.userAgent.includes('wv')) {
                        // Try Android interface first
                        if (typeof Android !== 'undefined' && Android.openUrl) {
                            Android.openUrl(webUrl);
                            return;
                        }
                        
                        // Fallback for WebView
                        window.open(webUrl, '_system');
                    } else {
                        // Regular browser
                        window.open(webUrl, '_blank');
                    }
                    
                    // Show additional instructions
                    setTimeout(() => {
                        Swal.fire({
                            title: 'Informasi',
                            html: `
                                <p>Jika halaman tidak terbuka otomatis, silakan:</p>
                                <ol style="text-align: left; padding-left: 20px;">
                                    <li>Buka browser secara manual</li>
                                    <li>Ketik: <strong>${webUrl}</strong></li>
                                    <li>Login dengan akun yang sama</li>
                                </ol>
                            `,
                            icon: 'info',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#667eea'
                        });
                    }, 2000);
                }
            });
        }
    </script>
</body>
</html>