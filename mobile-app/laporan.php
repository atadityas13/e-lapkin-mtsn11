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
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="navbar nav-header">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <a class="navbar-brand d-flex align-items-center text-white" href="dashboard.php">
                <i class="fas fa-arrow-left me-3"></i>
                <div>
                    <div class="fw-bold">Laporan Kinerja</div>
                    <small class="opacity-75">Generate & Download LKB/LKH</small>
                </div>
            </a>
            <div class="text-white text-end">
                <div class="fw-semibold" style="font-size: 0.85rem; line-height: 1.2;"><?= htmlspecialchars($userData['nama']) ?></div>
                <div class="small opacity-75" style="font-size: 0.75rem; line-height: 1.2;">NIP: <?= htmlspecialchars($userData['nip']) ?></div>
                <div class="small opacity-75" style="font-size: 0.75rem; line-height: 1.2;">Periode: <?= htmlspecialchars($activePeriod) ?></div>
            </div>
        </div>
    </nav>

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

        <!-- LKB Section -->
        <div class="report-section">
            <div class="section-title">
                <i class="fas fa-file-alt text-primary"></i>
                Laporan Kinerja Bulanan (LKB)
            </div>
            
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

        <!-- LKH Section -->
        <div class="report-section">
            <div class="section-title">
                <i class="fas fa-list text-info"></i>
                Laporan Kinerja Harian (LKH)
            </div>
            
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
    </script>
</body>
</html>
</body>
</html>
