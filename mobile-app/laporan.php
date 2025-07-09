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
        }

        .nav-tabs .nav-item {
            margin-bottom: 0;
            flex: 1;
        }

        .nav-tabs .nav-link {
            border: none;
            border-radius: 15px;
            margin-right: 5px;
            background: white;
            color: #6c757d;
            font-weight: 600;
            padding: 15px 10px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            font-size: 0.9rem;
            width: 100%;
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
            margin-right: 8px;
            font-size: 1.1rem;
        }

        @media (max-width: 576px) {
            .nav-tabs .nav-link {
                padding: 12px 8px;
                font-size: 0.8rem;
                margin-right: 3px;
            }
            
            .nav-tabs .nav-link i {
                margin-right: 5px;
                font-size: 1rem;
            }
        }

        @media (max-width: 400px) {
            .nav-tabs .nav-link {
                padding: 10px 5px;
                font-size: 0.75rem;
            }
            
            .nav-tabs .nav-link i {
                display: block;
                margin-right: 0;
                margin-bottom: 3px;
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

        /* Yearly Report Styles */
        .yearly-report-actions {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.05));
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }

        .yearly-report-actions .btn {
            margin: 5px;
            min-width: 120px;
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
        <div class="bg-white px-3 pt-3">
            <ul class="nav nav-tabs d-flex" id="reportTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="lkb-tab" data-bs-toggle="tab" data-bs-target="#lkb-pane" 
                            type="button" role="tab" aria-controls="lkb-pane" aria-selected="true">
                        <i class="fas fa-file-alt"></i>LKB
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="lkh-tab" data-bs-toggle="tab" data-bs-target="#lkh-pane" 
                            type="button" role="tab" aria-controls="lkh-pane" aria-selected="false">
                        <i class="fas fa-list"></i>LKH
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tahunan-tab" data-bs-toggle="tab" data-bs-target="#tahunan-pane" 
                            type="button" role="tab" aria-controls="tahunan-pane" aria-selected="false">
                        <i class="fas fa-chart-line"></i>Tahunan
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
                    <!-- Year Selection and Actions -->
                    <div class="yearly-report-actions">
                        <form method="GET" class="d-flex align-items-center justify-content-center gap-2 mb-3">
                            <input type="hidden" name="tab" value="tahunan">
                            <label for="year" class="form-label mb-0 text-nowrap fw-semibold">Pilih Tahun:</label>
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
                                <i class="fas fa-search me-1"></i>Tampilkan
                            </button>
                        </form>
                        
                        <!-- Download Actions -->
                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                            <button type="button" class="btn btn-success btn-sm" onclick="downloadYearlyReport(<?= $selected_year ?>)">
                                <i class="fas fa-download me-1"></i>Download PDF
                            </button>
                            <button type="button" class="btn btn-info btn-sm" onclick="printYearlyReport()">
                                <i class="fas fa-print me-1"></i>Cetak
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="exportYearlyExcel(<?= $selected_year ?>)">
                                <i class="fas fa-file-excel me-1"></i>Export Excel
                            </button>
                        </div>
                    </div>
                    
                    <!-- Yearly Report Content -->
                    <div id="yearly-report-content">
                        <?php 
                        // Set year for yearly report
                        $_GET['year'] = $selected_year;
                        include 'generate_yearly_report.php'; 
                        ?>
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

        // Yearly Report Functions
        function downloadYearlyReport(year) {
            console.log('Downloading yearly report for year:', year);
            
            Swal.fire({
                title: 'Generate Laporan Tahunan',
                text: 'Sedang memproses laporan tahunan untuk tahun ' + year,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Send request to generate yearly PDF with better error handling
            fetch('generate_yearly_pdf.php?year=' + year, {
                method: 'GET',
                headers: {
                    'Accept': 'application/pdf',
                    'Cache-Control': 'no-cache'
                }
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                if (!response.ok) {
                    // Try to get error message from response
                    return response.text().then(text => {
                        throw new Error(`Server error (${response.status}): ${text.substring(0, 100)}`);
                    });
                }
                
                // Check if response is actually PDF
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('pdf')) {
                    console.warn('Response is not PDF, content-type:', contentType);
                }
                
                return response.blob();
            })
            .then(blob => {
                console.log('Blob received, size:', blob.size);
                
                if (blob.size === 0) {
                    throw new Error('File kosong atau tidak dapat digenerate');
                }
                
                const url = window.URL.createObjectURL(blob);
                const filename = 'Laporan_Tahunan_' + year + '_<?= $nama_file_nip ?>.pdf';
                
                Swal.close();
                
                // Try multiple download methods
                downloadFileMultiMethod(url, filename);
                
                // Clean up
                setTimeout(() => {
                    window.URL.revokeObjectURL(url);
                }, 10000);
            })
            .catch(error => {
                console.error('Error details:', error);
                Swal.close();
                
                // Show detailed error with fallback option
                Swal.fire({
                    icon: 'error',
                    title: 'Download Gagal',
                    html: `
                        <p>Terjadi kesalahan saat generate laporan:</p>
                        <small style="color: #666;">${error.message}</small>
                        <br><br>
                        <button onclick="openYearlyReportDirectly(${year})" class="btn btn-secondary btn-sm">
                            Buka di Tab Baru
                        </button>
                    `,
                    confirmButtonText: 'OK'
                });
            });
        }

        // Enhanced download function with multiple methods
        function downloadFileMultiMethod(url, filename) {
            console.log('Attempting download with multiple methods:', filename);
            
            // Method 1: Android native interface
            if (typeof Android !== 'undefined' && Android.downloadFile) {
                try {
                    Android.downloadFile(url, filename);
                    Swal.fire({
                        icon: 'success',
                        title: 'Download Dimulai',
                        text: 'File sedang diunduh melalui aplikasi Android...',
                        timer: 3000,
                        showConfirmButton: false
                    });
                    return;
                } catch (e) {
                    console.error('Android download failed:', e);
                }
            }
            
            // Method 2: Standard download link
            try {
                const link = document.createElement('a');
                link.href = url;
                link.download = filename;
                link.style.display = 'none';
                
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                Swal.fire({
                    icon: 'success',
                    title: 'Download Dimulai',
                    text: 'File sedang diunduh. Periksa folder Download Anda.',
                    timer: 3000,
                    showConfirmButton: false
                });
                return;
            } catch (e) {
                console.error('Standard download failed:', e);
            }
            
            // Method 3: Open in new window/tab
            try {
                window.open(url, '_blank');
                Swal.fire({
                    icon: 'info',
                    title: 'File Dibuka',
                    text: 'File dibuka di tab baru. Gunakan menu browser untuk menyimpan.',
                    timer: 3000,
                    showConfirmButton: false
                });
            } catch (e) {
                console.error('All download methods failed:', e);
                Swal.fire({
                    icon: 'error',
                    title: 'Download Tidak Berhasil',
                    text: 'Silakan coba lagi atau hubungi administrator.',
                    confirmButtonText: 'OK'
                });
            }
        }

        // Direct link fallback
        function openYearlyReportDirectly(year) {
            const url = 'generate_yearly_pdf.php?year=' + year;
            window.open(url, '_blank');
        }

        function exportYearlyExcel(year) {
            // For now, show coming soon message
            Swal.fire({
                icon: 'info',
                title: 'Fitur Segera Hadir',
                text: 'Export Excel untuk laporan tahunan sedang dalam pengembangan.',
                confirmButtonText: 'OK'
            });
        }

        // Handle tab switching with URL update
        document.querySelectorAll('#reportTabs button[data-bs-toggle="tab"]').forEach(function(tab) {
            tab.addEventListener('shown.bs.tab', function(e) {
                const tabId = e.target.getAttribute('aria-controls');
                const url = new URL(window.location);
                url.searchParams.set('tab', tabId);
                window.history.replaceState({}, '', url);
                
                // Re-animate items when tab is shown
                setTimeout(() => {
                    const activePane = document.querySelector('.tab-pane.active');
                    const items = activePane.querySelectorAll('.report-item, .mobile-yearly-report');
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
            });
        });

        // Check if we should show a specific tab based on URL params
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('tab');
            
            if (activeTab && ['lkb-pane', 'lkh-pane', 'tahunan-pane'].includes(activeTab)) {
                const tabButton = document.querySelector(`#${activeTab.replace('-pane', '-tab')}`);
                if (tabButton) {
                    const tab = new bootstrap.Tab(tabButton);
                    tab.show();
                }
            }
        });
    </script>
</body>
</html>
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
