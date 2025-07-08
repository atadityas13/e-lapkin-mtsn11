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
            --primary-color: #4f46e5;
            --primary-dark: #3730a3;
            --secondary-color: #6b7280;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --light-gray: #f8fafc;
            --border-color: #e2e8f0;
            --text-dark: #1f2937;
            --text-light: #6b7280;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--light-gray);
            color: var(--text-dark);
            line-height: 1.5;
            padding-bottom: 80px;
        }

        /* Header Styles */
        .mobile-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .mobile-header .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 100%;
        }

        .mobile-header .header-left {
            display: flex;
            align-items: center;
            flex: 1;
            min-width: 0;
        }

        .mobile-header .back-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            margin-right: 0.75rem;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: background-color 0.2s;
        }

        .mobile-header .back-btn:hover {
            background-color: rgba(255,255,255,0.1);
        }

        .mobile-header .page-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
            flex: 1;
            min-width: 0;
        }

        .mobile-header .page-subtitle {
            font-size: 0.8rem;
            opacity: 0.9;
            margin-top: 0.1rem;
        }

        .mobile-header .user-info {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border-radius: 0.75rem;
            padding: 0.5rem 0.75rem;
            font-size: 0.8rem;
            text-align: right;
            min-width: 120px;
        }

        .mobile-header .user-info .user-name {
            font-weight: 600;
            margin-bottom: 0.1rem;
        }

        .mobile-header .user-info .user-details {
            opacity: 0.9;
            font-size: 0.75rem;
        }

        /* Main Content */
        .main-content {
            padding: 1rem;
        }

        /* Info Alert */
        .info-alert {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05));
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .info-alert .alert-icon {
            color: var(--info-color);
            font-size: 1.25rem;
            margin-top: 0.1rem;
        }

        .info-alert .alert-content {
            flex: 1;
        }

        .info-alert .alert-title {
            font-weight: 600;
            color: var(--info-color);
            margin-bottom: 0.25rem;
        }

        .info-alert .alert-text {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        /* Section Styles */
        .section {
            margin-bottom: 2rem;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }

        .section-title i {
            color: var(--primary-color);
        }

        /* Card Styles */
        .report-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }

        .report-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .report-card.lkb-card {
            border-left: 4px solid var(--primary-color);
        }

        .report-card.lkh-card {
            border-left: 4px solid var(--info-color);
        }

        .report-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
        }

        .report-card-info {
            flex: 1;
        }

        .report-period {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .report-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            flex-shrink: 0;
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.5rem;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .status-badge.approved {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .status-badge.pending {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05));
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .status-badge.draft {
            background: linear-gradient(135deg, rgba(107, 114, 128, 0.1), rgba(107, 114, 128, 0.05));
            color: var(--secondary-color);
            border: 1px solid rgba(107, 114, 128, 0.2);
        }

        /* Button Styles */
        .btn {
            border-radius: 0.5rem;
            padding: 0.375rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            color: white;
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            color: white;
        }

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
            color: white;
        }

        .btn-secondary {
            background: var(--secondary-color);
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
            color: white;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 1rem;
            border: none;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            background: var(--primary-color);
            color: white;
            border-radius: 1rem 1rem 0 0;
            padding: 1rem 1.5rem;
            border-bottom: none;
        }

        .modal-header.bg-info {
            background: var(--info-color);
        }

        .modal-title {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        /* Form Styles */
        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .form-control {
            border-radius: 0.5rem;
            border: 1px solid var(--border-color);
            padding: 0.75rem;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(79, 70, 229, 0.25);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .mobile-header {
                padding: 0.75rem;
            }

            .mobile-header .page-title {
                font-size: 1rem;
            }

            .mobile-header .user-info {
                font-size: 0.75rem;
                padding: 0.5rem;
                min-width: 100px;
            }

            .main-content {
                padding: 0.75rem;
            }

            .report-card {
                padding: 0.75rem;
            }

            .report-card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .report-actions {
                width: 100%;
            }

            .btn {
                flex: 1;
                justify-content: center;
            }

            .modal-body {
                padding: 1rem;
            }
        }

        @media (max-width: 480px) {
            .mobile-header .header-content {
                gap: 0.5rem;
            }

            .mobile-header .user-info {
                min-width: 80px;
                font-size: 0.7rem;
            }

            .report-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <header class="mobile-header">
        <div class="header-content">
            <div class="header-left">
                <button class="back-btn" onclick="window.location.href='dashboard.php'">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div>
                    <h1 class="page-title">Laporan Kinerja</h1>
                    <p class="page-subtitle">Generate & Download LKB/LKH</p>
                </div>
            </div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($userData['nama']) ?></div>
                <div class="user-details"><?= htmlspecialchars($userData['nip']) ?></div>
                <div class="user-details"><?= htmlspecialchars($activePeriod) ?></div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Info Alert -->
        <div class="info-alert">
            <div class="alert-icon">
                <i class="fas fa-info-circle"></i>
            </div>
            <div class="alert-content">
                <div class="alert-title">Informasi Laporan</div>
                <div class="alert-text">
                    LKB & LKH dapat digenerate jika data sudah disetujui oleh Pejabat Penilai. 
                    File akan tersimpan dalam format PDF dan dapat diunduh kapan saja.
                </div>
            </div>
        </div>

        <!-- LKB Section -->
        <section class="section">
            <h2 class="section-title">
                <i class="fas fa-file-alt"></i>
                Laporan Kinerja Bulanan (LKB)
            </h2>
            
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
                        <div class="report-card lkb-card">
                            <div class="report-card-header">
                                <div class="report-card-info">
                                    <div class="report-period"><?= $months[$bulan] ?> <?= $tahun ?></div>
                                    <span class="status-badge approved">
                                        <i class="fas fa-check-circle"></i>
                                        Disetujui
                                    </span>
                                </div>
                                <div class="report-actions">
                                    <?php if ($pdf_exists_lkb): ?>
                                        <button type="button" class="btn btn-success btn-sm" 
                                                onclick="downloadFile('../generated/<?= $lkb_filename_for_download ?>', '<?= $lkb_filename_for_download ?>')">
                                            <i class="fas fa-download"></i>
                                            Download
                                        </button>
                                        <button type="button" class="btn btn-warning btn-sm" 
                                                data-bs-toggle="modal" data-bs-target="#generateLkbModal" 
                                                data-bulan="<?= $bulan ?>" data-tahun="<?= $tahun ?>">
                                            <i class="fas fa-sync"></i>
                                            Generate Ulang
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-primary btn-sm" 
                                                data-bs-toggle="modal" data-bs-target="#generateLkbModal" 
                                                data-bulan="<?= $bulan ?>" data-tahun="<?= $tahun ?>">
                                            <i class="fas fa-cogs"></i>
                                            Generate LKB
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($status_verval_rkb === 'diajukan'): ?>
                        <div class="report-card">
                            <div class="report-card-header">
                                <div class="report-card-info">
                                    <div class="report-period"><?= $months[$bulan] ?> <?= $tahun ?></div>
                                    <span class="status-badge pending">
                                        <i class="fas fa-clock"></i>
                                        Menunggu Approval
                                    </span>
                                </div>
                                <div class="report-actions">
                                    <span class="text-muted small">Belum dapat digenerate</span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="report-card">
                            <div class="report-card-header">
                                <div class="report-card-info">
                                    <div class="report-period"><?= $months[$bulan] ?> <?= $tahun ?></div>
                                    <span class="status-badge draft">
                                        <i class="fas fa-times-circle"></i>
                                        Belum Terkirim
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
        </section>

        <!-- LKH Section -->
        <section class="section">
            <h2 class="section-title">
                <i class="fas fa-list"></i>
                Laporan Kinerja Harian (LKH)
            </h2>
            
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
                        <div class="report-card lkh-card">
                            <div class="report-card-header">
                                <div class="report-card-info">
                                    <div class="report-period"><?= $months[$bulan] ?> <?= $tahun ?></div>
                                    <span class="status-badge approved">
                                        <i class="fas fa-check-circle"></i>
                                        Disetujui
                                    </span>
                                </div>
                                <div class="report-actions">
                                    <?php if ($pdf_exists_lkh): ?>
                                        <button type="button" class="btn btn-success btn-sm" 
                                                onclick="downloadFile('../generated/<?= $lkh_filename_for_download ?>', '<?= $lkh_filename_for_download ?>')">
                                            <i class="fas fa-download"></i>
                                            Download
                                        </button>
                                        <button type="button" class="btn btn-warning btn-sm" 
                                                data-bs-toggle="modal" data-bs-target="#generateLkhModal" 
                                                data-bulan="<?= $bulan ?>" data-tahun="<?= $tahun ?>">
                                            <i class="fas fa-sync"></i>
                                            Generate Ulang
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-primary btn-sm" 
                                                data-bs-toggle="modal" data-bs-target="#generateLkhModal" 
                                                data-bulan="<?= $bulan ?>" data-tahun="<?= $tahun ?>">
                                            <i class="fas fa-cogs"></i>
                                            Generate LKH
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($status_verval_lkh === 'diajukan'): ?>
                        <div class="report-card">
                            <div class="report-card-header">
                                <div class="report-card-info">
                                    <div class="report-period"><?= $months[$bulan] ?> <?= $tahun ?></div>
                                    <span class="status-badge pending">
                                        <i class="fas fa-clock"></i>
                                        Menunggu Approval
                                    </span>
                                </div>
                                <div class="report-actions">
                                    <span class="text-muted small">Belum dapat digenerate</span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="report-card">
                            <div class="report-card-header">
                                <div class="report-card-info">
                                    <div class="report-period"><?= $months[$bulan] ?> <?= $tahun ?></div>
                                    <span class="status-badge draft">
                                        <i class="fas fa-times-circle"></i>
                                        Belum Terkirim
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
        </section>
    </main>

    <!-- Modal Generate LKB -->
    <div class="modal fade" id="generateLkbModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
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
                <div class="modal-header bg-info">
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
                        <button type="submit" class="btn btn-primary">
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
    </script>
</body>
</html>
