<?php
/**
 * E-LAPKIN Mobile RKB Management
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
$id_pegawai_login = $userData['id_pegawai'];
$nama_pegawai_login = $userData['nama'];

$current_date = date('Y-m-d');
$current_month = (int)date('m');
$current_year = (int)date('Y');

// Helper function for SweetAlert-like notifications
function set_mobile_notification($type, $title, $text) {
    $_SESSION['mobile_notification'] = [
        'type' => $type,
        'title' => $title,
        'text' => $text
    ];
}

// Get active period from user settings
function get_periode_aktif($conn, $id_pegawai) {
    $stmt = $conn->prepare("SELECT tahun_aktif, bulan_aktif FROM pegawai WHERE id_pegawai = ?");
    $stmt->bind_param("i", $id_pegawai);
    $stmt->execute();
    $stmt->bind_result($tahun_aktif, $bulan_aktif);
    $stmt->fetch();
    $stmt->close();
    return [
        'tahun' => $tahun_aktif ?: (int)date('Y'),
        'bulan' => $bulan_aktif ?: (int)date('m')
    ];
}

function set_periode_aktif($conn, $id_pegawai, $bulan) {
    $stmt = $conn->prepare("UPDATE pegawai SET bulan_aktif = ? WHERE id_pegawai = ?");
    $stmt->bind_param("ii", $bulan, $id_pegawai);
    $stmt->execute();
    $stmt->close();
}

// Ensure columns exist
function ensure_periode_columns($conn) {
    $result = $conn->query("SHOW COLUMNS FROM pegawai LIKE 'tahun_aktif'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE pegawai ADD COLUMN tahun_aktif INT DEFAULT NULL");
    }
    $result = $conn->query("SHOW COLUMNS FROM pegawai LIKE 'bulan_aktif'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE pegawai ADD COLUMN bulan_aktif TINYINT DEFAULT NULL");
    }
}
ensure_periode_columns($conn);

$periode_aktif = get_periode_aktif($conn, $id_pegawai_login);
$filter_month = $periode_aktif['bulan'];
$filter_year = $periode_aktif['tahun'];

// Get RKB verification status
$status_verval_rkb = '';
$stmt_status = $conn->prepare("SELECT status_verval FROM rkb WHERE id_pegawai = ? AND bulan = ? AND tahun = ? LIMIT 1");
$stmt_status->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
$stmt_status->execute();
$stmt_status->bind_result($status_verval_rkb);
$stmt_status->fetch();
$stmt_status->close();

// Handle POST requests
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Prevent actions if RKB is already approved
    if ($status_verval_rkb == 'disetujui') {
        set_mobile_notification('error', 'Tidak Diizinkan', 'RKB periode ini sudah diverifikasi dan tidak dapat diubah.');
        header('Location: rkb.php');
        exit();
    }

    if (isset($_POST['set_periode_aktif'])) {
        $bulan_aktif_baru = (int)$_POST['bulan_aktif'];
        set_periode_aktif($conn, $id_pegawai_login, $bulan_aktif_baru);
        set_mobile_notification('success', 'Periode Diubah', 'Periode aktif berhasil diubah.');
        header('Location: rkb.php');
        exit();
    }

    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == 'add' || $action == 'edit') {
            $id_rhk = (int)$_POST['id_rhk'];
            $uraian_kegiatan = trim($_POST['uraian_kegiatan']);
            $kuantitas = trim($_POST['kuantitas']);
            
            // Map satuan from number to string
            $satuan_map = [
                "1" => "Kegiatan", "2" => "JP", "3" => "Dokumen", "4" => "Laporan",
                "5" => "Hari", "6" => "Jam", "7" => "Menit", "8" => "Unit"
            ];
            $satuan = isset($_POST['satuan']) && isset($satuan_map[$_POST['satuan']])
                ? $satuan_map[$_POST['satuan']] : '';

            // Validation
            if (empty($id_rhk) || empty($uraian_kegiatan) || empty($kuantitas) || empty($satuan)) {
                set_mobile_notification('error', 'Gagal', 'Semua field harus diisi.');
            } else {
                try {
                    if ($action == 'add') {
                        $stmt = $conn->prepare("INSERT INTO rkb (id_pegawai, id_rhk, bulan, tahun, uraian_kegiatan, kuantitas, satuan) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("iiissss", $id_pegawai_login, $id_rhk, $filter_month, $filter_year, $uraian_kegiatan, $kuantitas, $satuan);
                    } else {
                        $id_rkb = (int)$_POST['id_rkb'];
                        $stmt = $conn->prepare("UPDATE rkb SET id_rhk = ?, uraian_kegiatan = ?, kuantitas = ?, satuan = ? WHERE id_rkb = ? AND id_pegawai = ?");
                        $stmt->bind_param("isssii", $id_rhk, $uraian_kegiatan, $kuantitas, $satuan, $id_rkb, $id_pegawai_login);
                    }

                    if ($stmt->execute()) {
                        set_mobile_notification('success', ($action == 'add') ? 'Berhasil' : 'Update Berhasil', ($action == 'add') ? "RKB berhasil ditambahkan!" : "RKB berhasil diperbarui!");
                    } else {
                        set_mobile_notification('error', 'Gagal', ($action == 'add') ? "Gagal menambahkan RKB: " . $stmt->error : "Gagal memperbarui RKB: " . $stmt->error);
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    set_mobile_notification('error', 'Gagal', 'Terjadi kesalahan database. Periksa data yang dimasukkan.');
                }
            }
            header('Location: rkb.php');
            exit();
            
        } elseif ($action == 'delete') {
            $id_rkb_to_delete = (int)$_POST['id_rkb'];
            
            // Check if RKB is used in LKH
            $cek_lkh = $conn->prepare("SELECT COUNT(*) FROM lkh WHERE id_rkb = ?");
            $cek_lkh->bind_param("i", $id_rkb_to_delete);
            $cek_lkh->execute();
            $cek_lkh->bind_result($jumlah_lkh);
            $cek_lkh->fetch();
            $cek_lkh->close();

            if ($jumlah_lkh > 0) {
                set_mobile_notification('error', 'Gagal', 'RKB tidak dapat dihapus karena sudah digunakan pada LKH.');
            } else {
                $stmt = $conn->prepare("DELETE FROM rkb WHERE id_rkb = ? AND id_pegawai = ?");
                $stmt->bind_param("ii", $id_rkb_to_delete, $id_pegawai_login);

                if ($stmt->execute()) {
                    set_mobile_notification('success', 'Berhasil', 'RKB berhasil dihapus!');
                } else {
                    set_mobile_notification('error', 'Gagal', "Gagal menghapus RKB: " . $stmt->error);
                }
                $stmt->close();
            }
            header('Location: rkb.php');
            exit();
        }
    }
    
    // Submit/cancel RKB verification
    if (isset($_POST['ajukan_verval_rkb'])) {
        if ($status_verval_rkb == 'disetujui') {
            set_mobile_notification('error', 'Tidak Diizinkan', 'RKB periode ini sudah diverifikasi dan tidak dapat diubah statusnya.');
            header('Location: rkb.php');
            exit();
        }
        
        // Check if there's RKB data for this period
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM rkb WHERE id_pegawai = ? AND bulan = ? AND tahun = ?");
        $stmt_check->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
        $stmt_check->execute();
        $stmt_check->bind_result($count_rkb);
        $stmt_check->fetch();
        $stmt_check->close();
        
        if ($count_rkb == 0) {
            set_mobile_notification('error', 'Gagal', 'Tidak dapat mengajukan verval karena belum ada data RKB untuk periode ini.');
            header('Location: rkb.php');
            exit();
        }
        
        $stmt = $conn->prepare("UPDATE rkb SET status_verval = 'diajukan' WHERE id_pegawai = ? AND bulan = ? AND tahun = ?");
        $stmt->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
        if ($stmt->execute()) {
            set_mobile_notification('success', 'Berhasil', 'Pengajuan verval RKB berhasil dikirim. Menunggu verifikasi Pejabat Penilai.');
        } else {
            set_mobile_notification('error', 'Gagal', 'Gagal mengajukan verval RKB: ' . htmlspecialchars($stmt->error));
        }
        $stmt->close();
        header('Location: rkb.php');
        exit();
        
    } elseif (isset($_POST['batal_verval_rkb'])) {
        if ($status_verval_rkb == 'disetujui') {
            set_mobile_notification('error', 'Tidak Diizinkan', 'RKB periode ini sudah diverifikasi dan tidak dapat diubah statusnya.');
            header('Location: rkb.php');
            exit();
        }
        
        $stmt = $conn->prepare("UPDATE rkb SET status_verval = NULL WHERE id_pegawai = ? AND bulan = ? AND tahun = ?");
        $stmt->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
        if ($stmt->execute()) {
            set_mobile_notification('success', 'Dibatalkan', 'Pengajuan verval RKB dibatalkan. Anda dapat mengedit/mengirim ulang.');
        } else {
            set_mobile_notification('error', 'Gagal', 'Gagal membatalkan verval RKB: ' . htmlspecialchars($stmt->error));
        }
        $stmt->close();
        header('Location: rkb.php');
        exit();
    }
}

// Check if period is not set
$stmt_check_periode = $conn->prepare("SELECT tahun_aktif, bulan_aktif FROM pegawai WHERE id_pegawai = ?");
$stmt_check_periode->bind_param("i", $id_pegawai_login);
$stmt_check_periode->execute();
$stmt_check_periode->bind_result($tahun_aktif_db, $bulan_aktif_db);
$stmt_check_periode->fetch();
$stmt_check_periode->close();

$periode_belum_diatur = ($tahun_aktif_db === null || $bulan_aktif_db === null);

// Get available months
$months_available = [];
$res = $conn->query("SELECT DISTINCT bulan FROM rkb WHERE id_pegawai = $id_pegawai_login AND tahun = $filter_year ORDER BY bulan DESC");
while ($row = $res->fetch_assoc()) {
    $months_available[] = $row['bulan'];
}

// Get RKB data for current period
$rkbs = [];
$stmt_rkb = $conn->prepare("
    SELECT rkb.id_rkb, rkb.id_rhk, rkb.uraian_kegiatan, rkb.kuantitas, rkb.satuan, rhk.nama_rhk
    FROM rkb 
    JOIN rhk ON rkb.id_rhk = rhk.id_rhk 
    WHERE rkb.id_pegawai = ? AND rkb.bulan = ? AND rkb.tahun = ? 
    ORDER BY rkb.created_at ASC
");
$stmt_rkb->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
$stmt_rkb->execute();
$result_rkb = $stmt_rkb->get_result();
while ($row = $result_rkb->fetch_assoc()) {
    $rkbs[] = $row;
}
$stmt_rkb->close();

// Get RHK list for dropdown
$rhk_list = [];
$stmt_rhk_list = $conn->prepare("SELECT id_rhk, nama_rhk FROM rhk WHERE id_pegawai = ? ORDER BY nama_rhk ASC");
$stmt_rhk_list->bind_param("i", $id_pegawai_login);
$stmt_rhk_list->execute();
$result_rhk_list = $stmt_rhk_list->get_result();
while ($row = $result_rhk_list->fetch_assoc()) {
    $rhk_list[] = $row;
}
$stmt_rhk_list->close();

// Check if RHK period is not set
$periode_rhk_belum_diatur = empty($rhk_list);

// Get previous RKB list for reference
$previous_rkb_list = [];
$stmt_previous_rkb = $conn->prepare("
    SELECT 
        r1.uraian_kegiatan,
        r1.kuantitas,
        r1.satuan
    FROM rkb r1
    INNER JOIN (
        SELECT 
            uraian_kegiatan,
            MAX(created_at) as max_created_at
        FROM rkb 
        WHERE id_pegawai = ? AND NOT (bulan = ? AND tahun = ?)
        GROUP BY uraian_kegiatan
    ) r2 ON r1.uraian_kegiatan = r2.uraian_kegiatan 
         AND r1.created_at = r2.max_created_at
    WHERE r1.id_pegawai = ? AND NOT (r1.bulan = ? AND r1.tahun = ?)
    ORDER BY r1.created_at DESC, r1.uraian_kegiatan ASC
");

$stmt_previous_rkb->bind_param("iiiiii", $id_pegawai_login, $filter_month, $filter_year, $id_pegawai_login, $filter_month, $filter_year);
$stmt_previous_rkb->execute();
$result_previous_rkb = $stmt_previous_rkb->get_result();

while ($row = $result_previous_rkb->fetch_assoc()) {
    $previous_rkb_list[] = [
        'uraian_kegiatan' => $row['uraian_kegiatan'],
        'kuantitas' => $row['kuantitas'],
        'satuan' => $row['satuan']
    ];
}

$stmt_previous_rkb->close();

$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Clear any unwanted output before HTML
ob_clean();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RKB - E-Lapkin Mobile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-top: 1px solid rgba(0,0,0,0.1);
            z-index: 1050;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
            height: 70px;
        }
        .bottom-nav .nav-item {
            flex: 1;
            text-align: center;
        }
        .bottom-nav .nav-link {
            padding: 8px 4px;
            color: #6c757d;
            text-decoration: none;
            display: block;
            font-size: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            border-radius: 8px;
            margin: 2px;
            line-height: 1.2;
        }
        .bottom-nav .nav-link.active {
            color: #0d6efd;
            background: linear-gradient(135deg, rgba(13, 110, 253, 0.1), rgba(13, 110, 253, 0.05));
            transform: translateY(-1px);
        }
        .bottom-nav .nav-link i {
            font-size: 16px;
            margin-bottom: 2px;
            transition: transform 0.2s ease;
            display: block;
        }
        .bottom-nav .nav-link.active i {
            transform: scale(1.05);
        }
        body {
            padding-bottom: 85px;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f2ff 100%);
            min-height: 100vh;
        }
        .container-fluid {
            padding-bottom: 20px;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 15px;
        }
        .btn {
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-1px);
        }
        .nav-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 0;
        }
        .btn-primary-large {
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
            transition: all 0.3s ease;
            width: 100%;
        }
        .btn-primary-large:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(13, 110, 253, 0.4);
        }
        .action-buttons {
            margin-bottom: 20px;
        }
        .action-buttons .btn-sm {
            padding: 8px 12px;
            font-size: 13px;
            margin-bottom: 8px;
        }
        @media (max-width: 576px) {
            .container-fluid {
                padding-left: 10px;
                padding-right: 10px;
            }
            .card-body {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="navbar nav-header">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center text-white" href="dashboard.php">
                <i class="fas fa-arrow-left me-2"></i>
                <span>RKB</span>
            </a>
            <div class="d-flex align-items-center text-white">
                <small><?= htmlspecialchars($userData['nama']) ?></small>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-3 pt-3">
        <!-- Period Info -->
        <div class="card">
            <div class="card-body">
                <h6 class="card-title mb-3">
                    <i class="fas fa-calendar-alt text-primary me-2"></i>
                    Periode: <?= $months[$filter_month] . ' ' . $filter_year ?>
                </h6>
                
                <!-- Status Alert -->
                <?php if ($status_verval_rkb == 'diajukan'): ?>
                    <div class="alert alert-info alert-dismissible">
                        <i class="fas fa-clock me-2"></i>
                        RKB periode ini sudah diajukan dan menunggu verifikasi Pejabat Penilai.
                    </div>
                <?php elseif ($status_verval_rkb == 'disetujui'): ?>
                    <div class="alert alert-success alert-dismissible">
                        <i class="fas fa-check-circle me-2"></i>
                        RKB periode ini sudah diverifikasi/validasi oleh Pejabat Penilai.
                    </div>
                <?php elseif ($status_verval_rkb == 'ditolak'): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <i class="fas fa-times-circle me-2"></i>
                        RKB periode ini ditolak oleh Pejabat Penilai. Silakan perbaiki dan ajukan ulang.
                    </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <div class="d-flex gap-2 flex-wrap mb-3">
                        <button class="btn btn-info btn-sm" onclick="showPreviewModal()" 
                            <?= empty($rkbs) ? 'disabled' : '' ?>>
                            <i class="fas fa-eye me-1"></i>Preview RKB
                        </button>
                        
                        <?php if ($status_verval_rkb == 'diajukan'): ?>
                            <button class="btn btn-warning btn-sm" onclick="confirmCancelVerval()">
                                <i class="fas fa-times me-1"></i>Batal Ajukan
                            </button>
                        <?php elseif ($status_verval_rkb == '' || $status_verval_rkb == null || $status_verval_rkb == 'ditolak'): ?>
                            <button class="btn btn-success btn-sm" onclick="confirmSubmitVerval()" 
                                <?= empty($rkbs) ? 'disabled' : '' ?>>
                                <i class="fas fa-paper-plane me-1"></i>Ajukan Verval
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-grid">
                        <button class="btn btn-primary btn-primary-large" onclick="showAddModal()"
                            <?= ($status_verval_rkb == 'diajukan' || $status_verval_rkb == 'disetujui' || $periode_rhk_belum_diatur) ? 'disabled' : '' ?>>
                            <i class="fas fa-plus me-2"></i>Tambah RKB
                        </button>
                    </div>
                </div>
                
                <?php if ($periode_rhk_belum_diatur): ?>
                    <div class="alert alert-warning mt-3 mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Perhatian:</strong> Belum ada RHK untuk periode ini. 
                        <a href="rhk.php" class="alert-link">Buat RHK terlebih dahulu</a>.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Period Not Set Modal -->
        <?php if ($periode_belum_diatur): ?>
        <div class="modal fade" id="modalPeriodeBelumDiatur" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle me-2"></i>Periode Bulan Belum Diatur
                        </h5>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Informasi:</strong> Periode bulan untuk RKB belum diatur. Tahun mengikuti periode RHK (<?= $filter_year ?>). Silakan pilih bulan yang akan digunakan.
                        </div>
                        <form id="setPeriodeForm" method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Pilih Bulan Periode:</label>
                                <select class="form-select" name="bulan_aktif" required>
                                    <option value="">-- Pilih Bulan --</option>
                                    <?php foreach ($months as $num => $name): ?>
                                        <option value="<?= $num ?>" <?= ($num == $current_month) ? 'selected' : '' ?>><?= $name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Tahun Periode:</label>
                                <input type="text" class="form-control" value="<?= $filter_year ?>" readonly>
                                <div class="form-text">Tahun mengikuti periode aktif RHK.</div>
                            </div>
                            <input type="hidden" name="set_periode_aktif" value="1">
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" onclick="submitPeriodForm()">
                            <i class="fas fa-check me-1"></i>Atur Periode
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- RKB List -->
        <?php if (empty($rkbs)): ?>
            <div class="card text-center">
                <div class="card-body py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h6 class="text-muted">Belum ada RKB bulan ini</h6>
                    <p class="text-muted">Mulai tambahkan rencana kinerja bulanan Anda</p>
                    <?php if (!$periode_rhk_belum_diatur): ?>
                        <button class="btn btn-primary btn-primary-large" onclick="showAddModal()">
                            <i class="fas fa-plus me-2"></i>Tambah RKB
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($rkbs as $rkb): ?>
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-primary me-2">
                                        <?= htmlspecialchars($rkb['nama_rhk']) ?>
                                    </span>
                                </div>
                                <h6 class="card-title mb-2"><?= htmlspecialchars($rkb['uraian_kegiatan']) ?></h6>
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-success me-2">
                                        Target: <?= htmlspecialchars($rkb['kuantitas'] . ' ' . $rkb['satuan']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="editRkb(<?= $rkb['id_rkb'] ?>, '<?= $rkb['id_rhk'] ?>', '<?= htmlspecialchars($rkb['uraian_kegiatan']) ?>', '<?= htmlspecialchars($rkb['kuantitas']) ?>', '<?= htmlspecialchars($rkb['satuan']) ?>')" 
                                           <?= ($status_verval_rkb == 'diajukan' || $status_verval_rkb == 'disetujui') ? 'style="display:none;"' : '' ?>>
                                            <i class="fas fa-edit me-2"></i>Edit
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item text-danger" href="#" onclick="deleteRkb(<?= $rkb['id_rkb'] ?>)"
                                           <?= ($status_verval_rkb == 'diajukan' || $status_verval_rkb == 'disetujui') ? 'style="display:none;"' : '' ?>>
                                            <i class="fas fa-trash me-2"></i>Hapus
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Bottom Navigation -->
    <div class="bottom-nav">
        <div class="d-flex">
            <div class="nav-item">
                <a href="rhk.php" class="nav-link">
                    <i class="fas fa-tasks d-block"></i>
                    <small>RHK</small>
                </a>
            </div>
            <div class="nav-item">
                <a href="rkb.php" class="nav-link active">
                    <i class="fas fa-calendar d-block"></i>
                    <small>RKB</small>
                </a>
            </div>
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home d-block"></i>
                    <small>Beranda</small>
                </a>
            </div>
            <div class="nav-item">
                <a href="lkh.php" class="nav-link">
                    <i class="fas fa-list d-block"></i>
                    <small>LKH</small>
                </a>
            </div>
            <div class="nav-item">
                <a href="laporan.php" class="nav-link">
                    <i class="fas fa-file-alt d-block"></i>
                    <small>Laporan</small>
                </a>
            </div>
        </div>
    </div>

    <!-- Add/Edit RKB Modal -->
    <div class="modal fade" id="rkbModal" tabindex="-1">
        <div class="modal-dialog">
            <form id="rkbForm" method="POST">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="rkbModalTitle">Tambah RKB</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="rkbAction" value="add">
                        <input type="hidden" name="id_rkb" id="rkbId">
                        
                        <div class="mb-3">
                            <label class="form-label">Periode</label>
                            <input type="text" class="form-control" value="<?= $months[$filter_month] . ' ' . $filter_year ?>" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Pilih RHK Terkait</label>
                            <select class="form-select" name="id_rhk" id="rhkSelect" required>
                                <option value="">-- Pilih RHK --</option>
                                <?php foreach ($rhk_list as $rhk): ?>
                                    <option value="<?= $rhk['id_rhk'] ?>"><?= htmlspecialchars($rhk['nama_rhk']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($rhk_list)): ?>
                                <div class="form-text text-danger">Anda belum memiliki RHK. Silakan <a href="rhk.php">tambah RHK terlebih dahulu</a>.</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Uraian Kinerja Bulanan (RKB)</label>
                                <?php if (!empty($previous_rkb_list)): ?>
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="showPreviousRkb()">
                                        <i class="fas fa-history me-1"></i>RKB Terdahulu
                                    </button>
                                <?php endif; ?>
                            </div>
                            <textarea class="form-control" name="uraian_kegiatan" id="uraianKegiatan" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Kuantitas Target</label>
                            <input type="text" class="form-control" name="kuantitas" id="kuantitas" placeholder="Contoh: 12" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Satuan Target</label>
                            <select class="form-select" name="satuan" id="satuan" required>
                                <option value="">-- Pilih Satuan --</option>
                                <option value="1">Kegiatan</option>
                                <option value="2">JP</option>
                                <option value="3">Dokumen</option>
                                <option value="4">Laporan</option>
                                <option value="5">Hari</option>
                                <option value="6">Jam</option>
                                <option value="7">Menit</option>
                                <option value="8">Unit</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="submitBtn" <?= empty($rhk_list) ? 'disabled' : '' ?>>Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Preview RKB Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-eye me-2"></i>Preview Rencana Kinerja Bulanan (RKB)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <h6 class="fw-bold">Periode: <?= $months[$filter_month] . ' ' . $filter_year ?></h6>
                        <h6 class="fw-bold">Nama Pegawai: <?= htmlspecialchars($userData['nama']) ?></h6>
                    </div>
                    
                    <?php if (empty($rkbs)): ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0">Belum ada data RKB untuk periode ini.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-sm">
                                <thead class="table-primary">
                                    <tr class="text-center">
                                        <th width="5%">No</th>
                                        <th width="65%">Uraian Kegiatan</th>
                                        <th width="15%">Jumlah</th>
                                        <th width="15%">Satuan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1; 
                                    foreach ($rkbs as $rkb): ?>
                                        <tr>
                                            <td class="text-center"><?= $no++ ?></td>
                                            <td><small><?= htmlspecialchars($rkb['uraian_kegiatan']) ?></small></td>
                                            <td class="text-center"><small><?= htmlspecialchars($rkb['kuantitas']) ?></small></td>
                                            <td class="text-center"><small><?= htmlspecialchars($rkb['satuan']) ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted">
                                        <strong>Total RKB:</strong> <?= count($rkbs) ?> rencana kegiatan
                                    </small>
                                </div>
                                <div class="col-6 text-end">
                                    <small class="text-muted">
                                        <strong>Status:</strong> 
                                        <?php 
                                        if ($status_verval_rkb == 'diajukan') {
                                            echo '<span class="badge bg-warning">Menunggu Verifikasi</span>';
                                        } elseif ($status_verval_rkb == 'disetujui') {
                                            echo '<span class="badge bg-success">Disetujui</span>';
                                        } elseif ($status_verval_rkb == 'ditolak') {
                                            echo '<span class="badge bg-danger">Ditolak</span>';
                                        } else {
                                            echo '<span class="badge bg-secondary">Belum Diajukan</span>';
                                        }
                                        ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Previous RKB Modal -->
    <div class="modal fade" id="previousRkbModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-history me-2"></i>RKB Terdahulu
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-1"></i>
                        Pilih salah satu RKB terdahulu untuk mengisi form otomatis. Data akan disalin ke form tambah RKB.
                    </div>
                    
                    <?php if (empty($previous_rkb_list)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Belum ada data RKB terdahulu yang dapat dijadikan referensi.</p>
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <input type="text" class="form-control" id="searchPreviousRkb" placeholder="🔍 Cari RKB terdahulu...">
                        </div>
                        
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($previous_rkb_list as $index => $prev_rkb): ?>
                                <div class="card mb-2 previous-rkb-item" 
                                     data-uraian="<?= htmlspecialchars($prev_rkb['uraian_kegiatan']) ?>"
                                     data-kuantitas="<?= htmlspecialchars($prev_rkb['kuantitas']) ?>"
                                     data-satuan="<?= htmlspecialchars($prev_rkb['satuan']) ?>">
                                    <div class="card-body p-3">
                                        <h6 class="card-title mb-2"><?= htmlspecialchars($prev_rkb['uraian_kegiatan']) ?></h6>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge bg-secondary me-1"><?= htmlspecialchars($prev_rkb['kuantitas']) ?></span>
                                                <span class="badge bg-primary"><?= htmlspecialchars($prev_rkb['satuan']) ?></span>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-success" 
                                                    onclick="selectPreviousRkb('<?= htmlspecialchars($prev_rkb['uraian_kegiatan']) ?>', '<?= htmlspecialchars($prev_rkb['kuantitas']) ?>', '<?= htmlspecialchars($prev_rkb['satuan']) ?>')">
                                                <i class="fas fa-check me-1"></i>Gunakan
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div id="noDataPrevious" class="text-center py-3 d-none">
                            <i class="fas fa-search fa-2x text-muted mb-2"></i>
                            <p class="text-muted">Tidak ada RKB yang sesuai dengan pencarian.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Forms for Actions -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id_rkb" id="deleteId">
    </form>

    <form id="vervalForm" method="POST" style="display: none;">
        <input type="hidden" name="ajukan_verval_rkb" id="vervalAction">
    </form>

    <form id="cancelVervalForm" method="POST" style="display: none;">
        <input type="hidden" name="batal_verval_rkb" value="1">
    </form>

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

        // Auto show period modal if not set
        <?php if ($periode_belum_diatur): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var modalPeriode = new bootstrap.Modal(document.getElementById('modalPeriodeBelumDiatur'));
            modalPeriode.show();
        });
        <?php endif; ?>

        function submitPeriodForm() {
            const bulanDipilih = document.querySelector('#setPeriodeForm select[name="bulan_aktif"]').value;
            if (!bulanDipilih) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Peringatan',
                    text: 'Silakan pilih bulan terlebih dahulu.',
                    timer: 2000
                });
                return;
            }
            document.getElementById('setPeriodeForm').submit();
        }

        function showAddModal() {
            document.getElementById('rkbModalTitle').textContent = 'Tambah RKB';
            document.getElementById('rkbAction').value = 'add';
            document.getElementById('rkbId').value = '';
            document.getElementById('rkbForm').reset();
            document.getElementById('submitBtn').textContent = 'Simpan';
            new bootstrap.Modal(document.getElementById('rkbModal')).show();
        }

        function editRkb(id, idRhk, uraian, kuantitas, satuan) {
            document.getElementById('rkbModalTitle').textContent = 'Edit RKB';
            document.getElementById('rkbAction').value = 'edit';
            document.getElementById('rkbId').value = id;
            document.getElementById('rhkSelect').value = idRhk;
            document.getElementById('uraianKegiatan').value = uraian;
            document.getElementById('kuantitas').value = kuantitas;
            
            // Set satuan dropdown
            const satuanMap = {
                'Kegiatan': '1', 'JP': '2', 'Dokumen': '3', 'Laporan': '4',
                'Hari': '5', 'Jam': '6', 'Menit': '7', 'Unit': '8'
            };
            document.getElementById('satuan').value = satuanMap[satuan] || '';
            
            document.getElementById('submitBtn').textContent = 'Update';
            new bootstrap.Modal(document.getElementById('rkbModal')).show();
        }

        function showPreviewModal() {
            new bootstrap.Modal(document.getElementById('previewModal')).show();
        }

        function showPreviousRkb() {
            new bootstrap.Modal(document.getElementById('previousRkbModal')).show();
        }

        function selectPreviousRkb(uraian, kuantitas, satuan) {
            document.getElementById('uraianKegiatan').value = uraian;
            document.getElementById('kuantitas').value = kuantitas;
            
            // Set satuan dropdown
            const satuanMap = {
                'Kegiatan': '1', 'JP': '2', 'Dokumen': '3', 'Laporan': '4',
                'Hari': '5', 'Jam': '6', 'Menit': '7', 'Unit': '8'
            };
            document.getElementById('satuan').value = satuanMap[satuan] || '';
            
            // Close previous RKB modal
            bootstrap.Modal.getInstance(document.getElementById('previousRkbModal')).hide();
            
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'RKB Terpilih!',
                text: 'Data RKB terdahulu berhasil disalin ke form.',
                timer: 1500,
                showConfirmButton: false
            });
            
            // Ensure add RKB modal stays open
            setTimeout(function() {
                const modalRkb = bootstrap.Modal.getInstance(document.getElementById('rkbModal'));
                if (!modalRkb || !modalRkb._isShown) {
                    const newModalRkb = new bootstrap.Modal(document.getElementById('rkbModal'));
                    newModalRkb.show();
                }
            }, 100);
        }

        function confirmSubmitVerval() {
            Swal.fire({
                title: 'Ajukan Verval RKB?',
                text: 'RKB akan bisa digenerate setelah di verval oleh Pejabat Penilai.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Ajukan',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('vervalAction').value = '1';
                    document.getElementById('vervalForm').submit();
                }
            });
        }

        function confirmCancelVerval() {
            Swal.fire({
                title: 'Batalkan Pengajuan?',
                text: 'Anda dapat mengedit/menghapus/mengirim ulang RKB setelah membatalkan.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Batalkan',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('cancelVervalForm').submit();
                }
            });
        }

        function deleteRkb(id) {
            Swal.fire({
                title: 'Konfirmasi Hapus',
                text: 'Anda yakin ingin menghapus RKB ini? RKB yang sudah digunakan pada LKH tidak dapat dihapus.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteId').value = id;
                    document.getElementById('deleteForm').submit();
                }
            });
        }

        // Search functionality for previous RKB
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchPreviousRkb');
            const items = document.querySelectorAll('.previous-rkb-item');
            const noDataMessage = document.getElementById('noDataPrevious');
            
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    let visibleCount = 0;
                    
                    items.forEach(function(item) {
                        const uraian = item.getAttribute('data-uraian').toLowerCase();
                        const kuantitas = item.getAttribute('data-kuantitas').toLowerCase();
                        const satuan = item.getAttribute('data-satuan').toLowerCase();
                        
                        if (uraian.includes(searchTerm) || kuantitas.includes(searchTerm) || satuan.includes(searchTerm)) {
                            item.style.display = '';
                            visibleCount++;
                        } else {
                            item.style.display = 'none';
                        }
                    });
                    
                    // Show/hide no data message
                    if (noDataMessage) {
                      if (visibleCount === 0 && searchTerm.length > 0) {
                        noDataMessage.classList.remove('d-none');
                      } else {
                        noDataMessage.classList.add('d-none');
                      }
                    }
                });
            }
        });
    </script>
</body>
</html>