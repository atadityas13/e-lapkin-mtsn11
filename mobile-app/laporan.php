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
        }

        .nav-tabs .nav-link {
            border: none;
            border-radius: 15px;
            margin-right: 10px;
            background: white;
            color: #6c757d;
            font-weight: 600;
            padding: 12px 20px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
    </style>
    <?= getMobileHeaderCSS() ?>
</head>
<body>
    <!-- Header -->
    <?php renderMobileHeader('Laporan Kinerja', 'Generate & Unduh', 'dashboard.php', $userData, $activePeriod); ?>

    <div class="container-fluid px-3">
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
        <ul class="nav nav-tabs" id="reportTabs" role="tablist">
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
                    <i class="fas fa-calendar-alt"></i>Tahunan
                </button>
            </li>
        </ul>

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
                                                        onclick="previewPDF('../generated/<?= $lkb_filename_for_download ?>', '<?= $lkb_filename_for_download ?>', 'LKB <?= $months[$bulan] ?> <?= $tahun ?>')">
                                                    <i class="fas fa-eye me-1"></i>Preview
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
                                                        onclick="previewPDF('../generated/<?= $lkh_filename_for_download ?>', '<?= $lkh_filename_for_download ?>', 'LKH <?= $months[$bulan] ?> <?= $tahun ?>')">
                                                    <i class="fas fa-eye me-1"></i>Preview
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

            <!-- Tahunan Tab Pane -->
            <div class="tab-pane fade" id="tahunan-pane" role="tabpanel" aria-labelledby="tahunan-tab">
                <div class="tab-header">
                    <h5><i class="fas fa-calendar-alt text-warning me-2"></i>Laporan Kinerja Tahunan</h5>
                    <p>Laporan kinerja komprehensif selama satu tahun periode aktif</p>
                </div>
                
                <!-- Yearly Report Controls -->
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h6 class="card-title mb-1">
                                    <i class="fas fa-file-pdf text-danger me-2"></i>
                                    Laporan Tahun <?= $activePeriod['tahun'] ?>
                                </h6>
                                <small class="text-muted">
                                    Rekapitulasi RKB dan LKH selama tahun <?= $activePeriod['tahun'] ?>
                                </small>
                            </div>
                            <div class="col-4 text-end">
                                <button type="button" class="btn btn-primary btn-sm" id="generateYearlyReport">
                                    <i class="fas fa-eye me-1"></i>
                                    <span class="d-none d-sm-inline">Preview</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Yearly Summary Cards -->
                <div class="row mb-3">
                    <?php
                    // Get yearly statistics
                    $yearly_stats = [];
                    
                    // Count RKB entries
                    $stmt_rkb_count = $conn->prepare("SELECT COUNT(*) as total_rkb FROM rkb WHERE id_pegawai = ? AND tahun = ?");
                    $stmt_rkb_count->bind_param("ii", $id_pegawai_login, $activePeriod['tahun']);
                    $stmt_rkb_count->execute();
                    $result_rkb = $stmt_rkb_count->get_result()->fetch_assoc();
                    $yearly_stats['total_rkb'] = $result_rkb['total_rkb'];
                    $stmt_rkb_count->close();
                    
                    // Count LKH entries
                    $stmt_lkh_count = $conn->prepare("SELECT COUNT(*) as total_lkh FROM lkh WHERE id_pegawai = ? AND YEAR(tanggal_lkh) = ?");
                    $stmt_lkh_count->bind_param("ii", $id_pegawai_login, $activePeriod['tahun']);
                    $stmt_lkh_count->execute();
                    $result_lkh = $stmt_lkh_count->get_result()->fetch_assoc();
                    $yearly_stats['total_lkh'] = $result_lkh['total_lkh'];
                    $stmt_lkh_count->close();
                    
                    // Count approved months
                    $stmt_approved = $conn->prepare("
                        SELECT COUNT(DISTINCT rkb.bulan) as approved_months 
                        FROM rkb 
                        WHERE rkb.id_pegawai = ? AND rkb.tahun = ? AND rkb.status_verval = 'disetujui'
                    ");
                    $stmt_approved->bind_param("ii", $id_pegawai_login, $activePeriod['tahun']);
                    $stmt_approved->execute();
                    $result_approved = $stmt_approved->get_result()->fetch_assoc();
                    $yearly_stats['approved_months'] = $result_approved['approved_months'];
                    $stmt_approved->close();
                    ?>
                    
                    <div class="col-4">
                        <div class="card text-center border-primary">
                            <div class="card-body p-2">
                                <div class="text-primary">
                                    <i class="fas fa-file-alt fa-2x mb-1"></i>
                                </div>
                                <h6 class="card-title mb-0 text-primary"><?= $yearly_stats['total_rkb'] ?></h6>
                                <small class="text-muted">Total RKB</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-4">
                        <div class="card text-center border-info">
                            <div class="card-body p-2">
                                <div class="text-info">
                                    <i class="fas fa-list fa-2x mb-1"></i>
                                </div>
                                <h6 class="card-title mb-0 text-info"><?= $yearly_stats['total_lkh'] ?></h6>
                                <small class="text-muted">Total LKH</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-4">
                        <div class="card text-center border-success">
                            <div class="card-body p-2">
                                <div class="text-success">
                                    <i class="fas fa-check-circle fa-2x mb-1"></i>
                                </div>
                                <h6 class="card-title mb-0 text-success"><?= $yearly_stats['approved_months'] ?></h6>
                                <small class="text-muted">Bulan Disetujui</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Summary -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-calendar me-2"></i>
                            Ringkasan Bulanan Tahun <?= $activePeriod['tahun'] ?>
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <?php
                        for ($bulan_mobile = 1; $bulan_mobile <= 12; $bulan_mobile++):
                            // Check month status
                            $stmt_month_status = $conn->prepare("
                                SELECT 
                                    COUNT(DISTINCT rkb.id_rkb) as rkb_count,
                                    COUNT(DISTINCT lkh.id_lkh) as lkh_count,
                                    MAX(rkb.status_verval) as status_verval
                                FROM rkb 
                                LEFT JOIN lkh ON rkb.id_rkb = lkh.id_rkb
                                WHERE rkb.id_pegawai = ? AND rkb.bulan = ? AND rkb.tahun = ?
                            ");
                            $stmt_month_status->bind_param("iii", $id_pegawai_login, $bulan_mobile, $activePeriod['tahun']);
                            $stmt_month_status->execute();
                            $month_data = $stmt_month_status->get_result()->fetch_assoc();
                            $stmt_month_status->close();
                            
                            if ($month_data['rkb_count'] == 0) continue; // Skip months without data
                            
                            $status_badge = '';
                            $status_icon = '';
                            if ($month_data['status_verval'] === 'disetujui') {
                                $status_badge = 'bg-success';
                                $status_icon = 'fas fa-check-circle';
                            } elseif ($month_data['status_verval'] === 'diajukan') {
                                $status_badge = 'bg-warning';
                                $status_icon = 'fas fa-clock';
                            } else {
                                $status_badge = 'bg-secondary';
                                $status_icon = 'fas fa-times-circle';
                            }
                        ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h6 class="mb-1"><?= $months[$bulan_mobile] ?> <?= $activePeriod['tahun'] ?></h6>
                                <div class="d-flex gap-3">
                                    <small class="text-muted">
                                        <i class="fas fa-file-alt me-1"></i><?= $month_data['rkb_count'] ?> RKB
                                    </small>
                                    <small class="text-muted">
                                        <i class="fas fa-list me-1"></i><?= $month_data['lkh_count'] ?> LKH
                                    </small>
                                </div>
                            </div>
                            <span class="badge <?= $status_badge ?> rounded-pill">
                                <i class="<?= $status_icon ?> me-1"></i>
                                <?= ucfirst($month_data['status_verval'] ?: 'Belum') ?>
                            </span>
                        </div>
                        <?php endfor; ?>
                        
                        <?php if ($yearly_stats['total_rkb'] == 0): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">Belum Ada Data</h6>
                            <p class="text-muted small mb-0">
                                Belum ada RKB yang dibuat untuk tahun <?= $activePeriod['tahun'] ?>
                            </p>
                        </div>
                        <?php endif; ?>
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

    <!-- Modal Generate Yearly Report -->
    <div class="modal fade" id="yearlyReportModal" tabindex="-1">
        <div class="modal-dialog modal-fullscreen-sm-down modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-calendar-alt me-2"></i>Preview Laporan Tahunan
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" style="max-height: 70vh; overflow-y: auto;">
                    <div id="yearlyReportContent">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Memuat laporan tahunan...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary" id="printYearlyReport">
                        <i class="fas fa-print me-1"></i>Cetak Laporan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal PDF Preview -->
    <div class="modal fade" id="pdfPreviewModal" tabindex="-1">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="pdfPreviewTitle">
                        <i class="fas fa-file-pdf me-2"></i>Preview Dokumen
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" style="height: 80vh;">
                    <div id="pdfPreviewContent" class="h-100 d-flex flex-column">
                        <!-- PDF will be loaded here -->
                        <div class="d-flex justify-content-center align-items-center h-100">
                            <div class="text-center">
                                <div class="spinner-border text-primary mb-3" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p>Memuat dokumen PDF...</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Tutup
                    </button>
                    <button type="button" class="btn btn-success" id="downloadFromPreview">
                        <i class="fas fa-download me-1"></i>Download PDF
                    </button>
                    <button type="button" class="btn btn-info" id="printFromPreview">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                </div>
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

        // Yearly Report Functionality
        document.getElementById('generateYearlyReport').addEventListener('click', function() {
            const modal = new bootstrap.Modal(document.getElementById('yearlyReportModal'));
            const contentDiv = document.getElementById('yearlyReportContent');
            
            // Reset content
            contentDiv.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Memuat laporan tahunan...</p>
                </div>
            `;
            
            modal.show();
            
            // Fetch yearly report data
            fetch('generate_yearly_report.php?year=<?= $activePeriod["tahun"] ?>')
                .then(response => response.text())
                .then(html => {
                    contentDiv.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    contentDiv.innerHTML = `
                        <div class="alert alert-danger m-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Gagal memuat laporan tahunan. Silakan coba lagi.
                        </div>
                    `;
                });
        });

        // Print Yearly Report
        document.getElementById('printYearlyReport').addEventListener('click', function() {
            const reportContent = document.getElementById('yearlyReportContent').innerHTML;
            
            if (reportContent.includes('spinner-border') || reportContent.includes('alert-danger')) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Laporan Belum Siap',
                    text: 'Harap tunggu hingga laporan selesai dimuat.'
                });
                return;
            }
            
            // Create print window
            const printWindow = window.open('', '_blank', 'width=800,height=600');
            
            if (!printWindow) {
                Swal.fire({
                    icon: 'error',
                    title: 'Pop-up Diblokir',
                    text: 'Silakan izinkan pop-up untuk mencetak laporan.'
                });
                return;
            }
            
            const printHtml = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Laporan Kinerja Tahunan - <?= $activePeriod["tahun"] ?></title>
                    <meta charset="utf-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1">
                    <style>
                        body { 
                            font-family: Arial, sans-serif; 
                            margin: 0; 
                            padding: 20px; 
                            font-size: 12px; 
                            line-height: 1.4; 
                        }
                        .print-header { 
                            text-align: center; 
                            margin-bottom: 30px; 
                            border-bottom: 2px solid #000; 
                            padding-bottom: 20px; 
                        }
                        .print-header h1 { 
                            font-size: 18px; 
                            margin: 0; 
                            text-transform: uppercase; 
                        }
                        .print-header h2 { 
                            font-size: 16px; 
                            margin: 8px 0; 
                        }
                        table { 
                            width: 100%; 
                            border-collapse: collapse; 
                            font-size: 10px; 
                        }
                        th, td { 
                            border: 1px solid #000; 
                            padding: 6px; 
                            text-align: center; 
                            vertical-align: middle; 
                        }
                        th { 
                            background-color: #e0e0e0; 
                            font-weight: bold; 
                        }
                        .employee-info td { 
                            text-align: left; 
                        }
                        .employee-info td:first-child { 
                            background-color: #f0f0f0; 
                            font-weight: bold; 
                            width: 150px; 
                        }
                        @media print {
                            body { margin: 0; }
                            .no-print { display: none !important; }
                        }
                    </style>
                </head>
                <body>
                    <div class="print-header">
                        <h1>MTsN 11 MAJALENGKA</h1>
                        <h2>LAPORAN KINERJA PEGAWAI TAHUNAN</h2>
                        <h3>TAHUN <?= $activePeriod["tahun"] ?></h3>
                    </div>
                    ${reportContent}
                    <script>
                        window.onload = function() {
                            window.print();
                            setTimeout(function() {
                                window.close();
                            }, 1000);
                        };
                    </script>
                </body>
                </html>
            `;
            
            printWindow.document.write(printHtml);
            printWindow.document.close();
        });

        // PDF Preview Functionality
        let currentPdfUrl = '';
        let currentPdfFilename = '';

        function previewPDF(url, filename, title) {
            console.log('Preview PDF called:', url, filename, title);
            
            // Store current PDF info for download
            currentPdfUrl = url;
            currentPdfFilename = filename;
            
            // Set modal title
            document.getElementById('pdfPreviewTitle').innerHTML = `<i class="fas fa-file-pdf me-2"></i>${title}`;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('pdfPreviewModal'));
            const contentDiv = document.getElementById('pdfPreviewContent');
            
            // Reset content
            contentDiv.innerHTML = `
                <div class="d-flex justify-content-center align-items-center h-100">
                    <div class="text-center">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Memuat dokumen PDF...</p>
                    </div>
                </div>
            `;
            
            modal.show();
            
            // Add timestamp to prevent caching
            const pdfUrl = url + '?t=' + new Date().getTime();
            
            // Try different methods to display PDF
            setTimeout(() => {
                displayPdfContent(pdfUrl, contentDiv);
            }, 500);
        }

        function displayPdfContent(url, container) {
            // Method 1: Try PDF embed
            const embedHtml = `
                <div class="h-100 position-relative">
                    <embed src="${url}" type="application/pdf" width="100%" height="100%" 
                           onload="console.log('PDF loaded successfully')" 
                           onerror="handlePdfError()">
                    <div id="pdfFallback" style="display: none;" class="h-100 d-flex flex-column justify-content-center align-items-center">
                        <div class="text-center">
                            <i class="fas fa-file-pdf fa-4x text-danger mb-3"></i>
                            <h5>PDF tidak dapat ditampilkan</h5>
                            <p class="text-muted mb-3">Browser Anda tidak mendukung preview PDF atau file tidak dapat dimuat.</p>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                                <button class="btn btn-primary" onclick="downloadFile('${currentPdfUrl}', '${currentPdfFilename}')">
                                    <i class="fas fa-download me-2"></i>Download PDF
                                </button>
                                <button class="btn btn-info" onclick="openInNewTab('${url}')">
                                    <i class="fas fa-external-link-alt me-2"></i>Buka di Tab Baru
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            container.innerHTML = embedHtml;
            
            // Fallback for mobile/WebView that might not support embed
            if (navigator.userAgent.includes('wv') || /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
                setTimeout(() => {
                    // Check if PDF loaded, if not show fallback
                    const embed = container.querySelector('embed');
                    if (embed && (embed.offsetHeight === 0 || embed.contentDocument === null)) {
                        handlePdfError();
                    }
                }, 2000);
            }
        }

        function handlePdfError() {
            console.log('PDF embed failed, showing fallback');
            const fallback = document.getElementById('pdfFallback');
            const embed = document.querySelector('#pdfPreviewContent embed');
            
            if (fallback && embed) {
                embed.style.display = 'none';
                fallback.style.display = 'flex';
            }
        }

        function openInNewTab(url) {
            window.open(url, '_blank');
        }

        // Download from preview modal
        document.getElementById('downloadFromPreview').addEventListener('click', function() {
            if (currentPdfUrl && currentPdfFilename) {
                downloadFile(currentPdfUrl, currentPdfFilename);
                
                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'Download Dimulai',
                    text: 'File PDF sedang diunduh...',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });

        // Print from preview modal
        document.getElementById('printFromPreview').addEventListener('click', function() {
            if (currentPdfUrl) {
                // Try to print the embedded PDF
                const embed = document.querySelector('#pdfPreviewContent embed');
                if (embed && embed.contentWindow) {
                    try {
                        embed.contentWindow.print();
                    } catch (e) {
                        console.log('Direct print failed, opening in new window');
                        printPdfInNewWindow();
                    }
                } else {
                    printPdfInNewWindow();
                }
            }
        });

        function printPdfInNewWindow() {
            const printWindow = window.open(currentPdfUrl, '_blank', 'width=800,height=600');
            if (printWindow) {
                printWindow.onload = function() {
                    setTimeout(() => {
                        printWindow.print();
                    }, 1000);
                };
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Pop-up Diblokir',
                    text: 'Silakan izinkan pop-up untuk mencetak dokumen.'
                });
            }
        }

        // Handle PDF preview modal close
        document.getElementById('pdfPreviewModal').addEventListener('hidden.bs.modal', function() {
            // Clear PDF content to free memory
            const contentDiv = document.getElementById('pdfPreviewContent');
            contentDiv.innerHTML = `
                <div class="d-flex justify-content-center align-items-center h-100">
                    <div class="text-center text-muted">
                        <i class="fas fa-file-pdf fa-3x mb-3"></i>
                        <p>Modal ditutup</p>
                    </div>
                </div>
            `;
            
            // Clear current PDF info
            currentPdfUrl = '';
            currentPdfFilename = '';
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

        // Main download function with multiple fallbacks
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

        // Enhanced tab switching with proper Bootstrap integration
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded - Initializing tabs and functions');
            
            // Initialize Bootstrap tabs properly
            const triggerTabList = [].slice.call(document.querySelectorAll('#reportTabs button[data-bs-toggle="tab"]'))
            
            triggerTabList.forEach(function (triggerEl) {
                const tabTrigger = new bootstrap.Tab(triggerEl)
                
                triggerEl.addEventListener('shown.bs.tab', function (event) {
                    console.log('Tab shown:', event.target.id); // Debug log
                    
                    // Get the active tab pane
                    const targetPane = document.querySelector(event.target.getAttribute('data-bs-target'));
                    
                    if (targetPane) {
                        // Animate items in the active pane
                        setTimeout(() => {
                            const items = targetPane.querySelectorAll('.report-item, .card');
                            items.forEach((item, index) => {
                                item.style.opacity = '0';
                                item.style.transform = 'translateY(10px)';
                                setTimeout(() => {
                                    item.style.transition = 'all 0.3s ease';
                                    item.style.opacity = '1';
                                    item.style.transform = 'translateY(0)';
                                }, index * 50);
                            });
                        }, 50);
                    }
                });

                // Add click event for manual triggering
                triggerEl.addEventListener('click', function (event) {
                    event.preventDefault();
                    console.log('Tab clicked:', this.id); // Debug log
                    tabTrigger.show();
                });
            });

            // Initial animation for the default active tab (LKB)
            setTimeout(() => {
                const activePane = document.querySelector('.tab-pane.show.active');
                if (activePane) {
                    const items = activePane.querySelectorAll('.report-item');
                    items.forEach((item, index) => {
                        item.style.opacity = '0';
                        item.style.transform = 'translateY(20px)';
                        setTimeout(() => {
                            item.style.transition = 'all 0.5s ease';
                            item.style.opacity = '1';
                            item.style.transform = 'translateY(0)';
                        }, index * 100);
                    });
                }
            }, 100);

            // Special handling for yearly tab
            const yearlyTab = document.getElementById('tahunan-tab');
            if (yearlyTab) {
                yearlyTab.addEventListener('shown.bs.tab', function() {
                    console.log('Yearly tab shown'); // Debug log
                    setTimeout(() => {
                        const yearlyPane = document.getElementById('tahunan-pane');
                        if (yearlyPane) {
                            const cards = yearlyPane.querySelectorAll('.card');
                            cards.forEach((card, index) => {
                                card.style.opacity = '0';
                                card.style.transform = 'translateY(20px)';
                                setTimeout(() => {
                                    card.style.transition = 'all 0.4s ease';
                                    card.style.opacity = '1';
                                    card.style.transform = 'translateY(0)';
                                }, index * 100);
                            });
                        }
                    }, 100);
                });
            }

            // Debug: Log all tabs found
            console.log('Tabs found:', triggerTabList.length);
            triggerTabList.forEach(tab => {
                console.log('Tab ID:', tab.id, 'Target:', tab.getAttribute('data-bs-target'));
            });

            // Check if bottom navigation exists
            const bottomNav = document.querySelector('.bottom-nav, .fixed-bottom, .navbar-bottom');
            if (bottomNav) {
                console.log('Bottom navigation found:', bottomNav);
            } else {
                console.warn('Bottom navigation not found - checking include');
            }

            // Ensure all button functions are working
            console.log('All functions initialized successfully');
        });
    </script>
</body>
</html>