<?php
/**
 * E-LAPKIN Mobile LKH Management
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

$periode_aktif = get_periode_aktif($conn, $id_pegawai_login);
$filter_month = $periode_aktif['bulan'];
$filter_year = $periode_aktif['tahun'];

// Get LKH verification status
$status_verval_lkh = '';
$stmt_status = $conn->prepare("SELECT status_verval FROM lkh WHERE id_pegawai = ? AND MONTH(tanggal_lkh) = ? AND YEAR(tanggal_lkh) = ? LIMIT 1");
$stmt_status->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
$stmt_status->execute();
$stmt_status->bind_result($status_verval_lkh);
$stmt_status->fetch();
$stmt_status->close();

// Helper function for SweetAlert-like notifications
function set_mobile_notification($type, $title, $text) {
    $_SESSION['mobile_notification'] = [
        'type' => $type,
        'title' => $title,
        'text' => $text
    ];
}

// Handle POST requests
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Prevent actions if LKH is already approved
    if ($status_verval_lkh == 'disetujui') {
        set_mobile_notification('error', 'Tidak Diizinkan', 'LKH periode ini sudah diverifikasi dan tidak dapat diubah.');
        header('Location: lkh.php');
        exit();
    }

    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == 'add' || $action == 'edit') {
            $tanggal_lkh = trim($_POST['tanggal_lkh']);
            $id_rkb = (int)$_POST['id_rkb'];
            $uraian_kegiatan_lkh = trim($_POST['uraian_kegiatan_lkh']);
            $jumlah_realisasi = trim($_POST['jumlah_realisasi']);
            
            // Map satuan_realisasi from number to string
            $satuan_map = [
                "1" => "Kegiatan", "2" => "JP", "3" => "Dokumen", "4" => "Laporan",
                "5" => "Hari", "6" => "Jam", "7" => "Menit", "8" => "Unit"
            ];
            $satuan_realisasi = isset($_POST['satuan_realisasi']) && isset($satuan_map[$_POST['satuan_realisasi']])
                ? $satuan_map[$_POST['satuan_realisasi']] : '';
            $nama_kegiatan_harian = isset($_POST['nama_kegiatan_harian']) ? trim($_POST['nama_kegiatan_harian']) : '';

            // Handle file upload for add action
            $lampiran = NULL;
            if ($action == 'add' && isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] == UPLOAD_ERR_OK) {
                $file_tmp_name = $_FILES['lampiran']['tmp_name'];
                $file_extension = strtolower(pathinfo($_FILES['lampiran']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    set_mobile_notification('error', 'Gagal', 'Format file tidak diizinkan. Hanya PDF, JPG, JPEG, dan PNG yang diperbolehkan.');
                    header('Location: lkh.php');
                    exit();
                }
                
                if ($_FILES['lampiran']['size'] > 2 * 1024 * 1024) {
                    set_mobile_notification('error', 'Gagal', 'Ukuran file terlalu besar. Maksimal 2MB.');
                    header('Location: lkh.php');
                    exit();
                }
                
                $file_name = 'lkh_' . $id_pegawai_login . '_' . date('YmdHis') . '_' . uniqid() . '.' . $file_extension;
                $upload_dir = __DIR__ . '/../uploads/lkh/';
                $file_path = $upload_dir . $file_name;

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                if (move_uploaded_file($file_tmp_name, $file_path)) {
                    $lampiran = $file_name;
                } else {
                    set_mobile_notification('error', 'Gagal', 'Gagal mengunggah lampiran.');
                    header('Location: lkh.php');
                    exit();
                }
            } elseif ($action == 'add' && isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] != UPLOAD_ERR_NO_FILE) {
                // Handle other upload errors
                $upload_errors = [
                    UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi upload_max_filesize)',
                    UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi MAX_FILE_SIZE)',
                    UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
                    UPLOAD_ERR_NO_TMP_DIR => 'Direktori temporary tidak ditemukan',
                    UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
                    UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP'
                ];
                
                $error_msg = isset($upload_errors[$_FILES['lampiran']['error']]) 
                    ? $upload_errors[$_FILES['lampiran']['error']] 
                    : 'Error upload tidak dikenal: ' . $_FILES['lampiran']['error'];
                    
                set_mobile_notification('error', 'Gagal Upload', $error_msg);
                header('Location: lkh.php');
                exit();
            }

            // Validation
            if (empty($tanggal_lkh) || empty($id_rkb) || empty($nama_kegiatan_harian) || empty($uraian_kegiatan_lkh) || empty($jumlah_realisasi) || empty($satuan_realisasi)) {
                set_mobile_notification('error', 'Gagal', 'Semua field harus diisi.');
            } else {
                try {
                    if ($action == 'add') {
                        $stmt = $conn->prepare("INSERT INTO lkh (id_pegawai, id_rkb, tanggal_lkh, uraian_kegiatan_lkh, jumlah_realisasi, satuan_realisasi, nama_kegiatan_harian, lampiran) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("iissssss", $id_pegawai_login, $id_rkb, $tanggal_lkh, $uraian_kegiatan_lkh, $jumlah_realisasi, $satuan_realisasi, $nama_kegiatan_harian, $lampiran);
                    } else {
                        $id_lkh = (int)$_POST['id_lkh'];
                        $stmt = $conn->prepare("UPDATE lkh SET id_rkb = ?, tanggal_lkh = ?, uraian_kegiatan_lkh = ?, jumlah_realisasi = ?, satuan_realisasi = ?, nama_kegiatan_harian = ? WHERE id_lkh = ? AND id_pegawai = ?");
                        $stmt->bind_param("isssssii", $id_rkb, $tanggal_lkh, $uraian_kegiatan_lkh, $jumlah_realisasi, $satuan_realisasi, $nama_kegiatan_harian, $id_lkh, $id_pegawai_login);
                    }

                    if ($stmt->execute()) {
                        set_mobile_notification('success', ($action == 'add') ? 'Berhasil' : 'Update Berhasil', ($action == 'add') ? "LKH berhasil ditambahkan!" : "LKH berhasil diperbarui!");
                    } else {
                        set_mobile_notification('error', 'Gagal', ($action == 'add') ? "Gagal menambahkan LKH: " . $stmt->error : "Gagal memperbarui LKH: " . $stmt->error);
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    set_mobile_notification('error', 'Gagal', 'Terjadi kesalahan database. Periksa data yang dimasukkan.');
                }
            }
            header('Location: lkh.php');
            exit();
            
        } elseif ($action == 'delete') {
            $id_lkh_to_delete = (int)$_POST['id_lkh'];
            
            // Get file name to delete
            $stmt_get_file = $conn->prepare("SELECT lampiran FROM lkh WHERE id_lkh = ? AND id_pegawai = ?");
            $stmt_get_file->bind_param("ii", $id_lkh_to_delete, $id_pegawai_login);
            $stmt_get_file->execute();
            $stmt_get_file->bind_result($file_to_delete);
            $stmt_get_file->fetch();
            $stmt_get_file->close();
            
            $stmt = $conn->prepare("DELETE FROM lkh WHERE id_lkh = ? AND id_pegawai = ?");
            $stmt->bind_param("ii", $id_lkh_to_delete, $id_pegawai_login);

            if ($stmt->execute()) {
                if ($file_to_delete && file_exists(__DIR__ . '/../uploads/lkh/' . $file_to_delete)) {
                    unlink(__DIR__ . '/../uploads/lkh/' . $file_to_delete);
                }
                set_mobile_notification('success', 'Berhasil', 'LKH berhasil dihapus!');
            } else {
                set_mobile_notification('error', 'Gagal', "Gagal menghapus LKH: " . $stmt->error);
            }
            $stmt->close();
            header('Location: lkh.php');
            exit();
        }
    }
    
    // Submit/cancel LKH verification
    if (isset($_POST['ajukan_verval_lkh'])) {
        if ($status_verval_lkh == 'disetujui') {
            set_mobile_notification('error', 'Tidak Diizinkan', 'LKH periode ini sudah diverifikasi dan tidak dapat diubah statusnya.');
            header('Location: lkh.php');
            exit();
        }
        
        // Check if there's LKH data for this period
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM lkh WHERE id_pegawai = ? AND MONTH(tanggal_lkh) = ? AND YEAR(tanggal_lkh) = ?");
        $stmt_check->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
        $stmt_check->execute();
        $stmt_check->bind_result($count_lkh);
        $stmt_check->fetch();
        $stmt_check->close();
        
        if ($count_lkh == 0) {
            set_mobile_notification('error', 'Gagal', 'Tidak dapat mengajukan verval karena belum ada data LKH untuk periode ini.');
            header('Location: lkh.php');
            exit();
        }
        
        $stmt = $conn->prepare("UPDATE lkh SET status_verval = 'diajukan' WHERE id_pegawai = ? AND MONTH(tanggal_lkh) = ? AND YEAR(tanggal_lkh) = ?");
        $stmt->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
        if ($stmt->execute()) {
            set_mobile_notification('success', 'Berhasil', 'Pengajuan verval LKH berhasil dikirim. Menunggu verifikasi Pejabat Penilai.');
        } else {
            set_mobile_notification('error', 'Gagal', 'Gagal mengajukan verval LKH: ' . htmlspecialchars($stmt->error));
        }
        $stmt->close();
        header('Location: lkh.php');
        exit();
        
    } elseif (isset($_POST['batal_verval_lkh'])) {
        if ($status_verval_lkh == 'disetujui') {
            set_mobile_notification('error', 'Tidak Diizinkan', 'LKH periode ini sudah diverifikasi dan tidak dapat diubah statusnya.');
            header('Location: lkh.php');
            exit();
        }
        
        $stmt = $conn->prepare("UPDATE lkh SET status_verval = NULL WHERE id_pegawai = ? AND MONTH(tanggal_lkh) = ? AND YEAR(tanggal_lkh) = ?");
        $stmt->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
        if ($stmt->execute()) {
            set_mobile_notification('success', 'Dibatalkan', 'Pengajuan verval LKH dibatalkan. Anda dapat mengedit/mengirim ulang.');
        } else {
            set_mobile_notification('error', 'Gagal', 'Gagal membatalkan verval LKH: ' . htmlspecialchars($stmt->error));
        }
        $stmt->close();
        header('Location: lkh.php');
        exit();
    }
}

// Ensure status_verval column exists
function ensure_status_verval_column_lkh($conn) {
    $result = $conn->query("SHOW COLUMNS FROM lkh LIKE 'status_verval'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE lkh ADD COLUMN status_verval ENUM('diajukan','disetujui','ditolak') DEFAULT NULL");
    }
}
ensure_status_verval_column_lkh($conn);

// Ensure lampiran column exists
function ensure_lampiran_column_lkh($conn) {
    $result = $conn->query("SHOW COLUMNS FROM lkh LIKE 'lampiran'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE lkh ADD COLUMN lampiran VARCHAR(255) DEFAULT NULL");
    }
}
ensure_lampiran_column_lkh($conn);

// Get LKH data for current month
$lkhs = [];
$stmt_lkh = $conn->prepare("
    SELECT lkh.id_lkh, lkh.id_rkb, lkh.tanggal_lkh, lkh.uraian_kegiatan_lkh, lkh.jumlah_realisasi, 
           lkh.satuan_realisasi, lkh.lampiran, lkh.nama_kegiatan_harian, rkb.uraian_kegiatan AS rkb_uraian 
    FROM lkh 
    JOIN rkb ON lkh.id_rkb = rkb.id_rkb 
    WHERE lkh.id_pegawai = ? AND MONTH(lkh.tanggal_lkh) = ? AND YEAR(lkh.tanggal_lkh) = ? 
    ORDER BY lkh.tanggal_lkh DESC
");
$stmt_lkh->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
$stmt_lkh->execute();
$result_lkh = $stmt_lkh->get_result();
while ($row = $result_lkh->fetch_assoc()) {
    $lkhs[] = $row;
}
$stmt_lkh->close();

// Get RKB list for current period
$rkb_list = [];
$stmt_rkb_list = $conn->prepare("SELECT id_rkb, uraian_kegiatan FROM rkb WHERE id_pegawai = ? AND bulan = ? AND tahun = ? ORDER BY uraian_kegiatan ASC");
$stmt_rkb_list->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
$stmt_rkb_list->execute();
$result_rkb_list = $stmt_rkb_list->get_result();
while ($row = $result_rkb_list->fetch_assoc()) {
    $rkb_list[] = $row;
}
$stmt_rkb_list->close();

// Check if RKB period is not set
$periode_rkb_belum_diatur = empty($rkb_list);

// Get previous LKH list for reference
$previous_lkh_list = [];
$stmt_previous_lkh = $conn->prepare("
    SELECT 
        l1.nama_kegiatan_harian,
        l1.uraian_kegiatan_lkh,
        l1.jumlah_realisasi,
        l1.satuan_realisasi
    FROM lkh l1
    INNER JOIN (
        SELECT 
            nama_kegiatan_harian,
            uraian_kegiatan_lkh,
            MAX(id_lkh) as max_id_lkh
        FROM lkh 
        WHERE id_pegawai = ? AND NOT (MONTH(tanggal_lkh) = ? AND YEAR(tanggal_lkh) = ?)
        GROUP BY nama_kegiatan_harian, uraian_kegiatan_lkh
    ) l2 ON l1.nama_kegiatan_harian = l2.nama_kegiatan_harian 
         AND l1.uraian_kegiatan_lkh = l2.uraian_kegiatan_lkh
         AND l1.id_lkh = l2.max_id_lkh
    WHERE l1.id_pegawai = ? AND NOT (MONTH(l1.tanggal_lkh) = ? AND YEAR(l1.tanggal_lkh) = ?)
    ORDER BY l1.id_lkh DESC, l1.nama_kegiatan_harian ASC
    LIMIT 20
");

$stmt_previous_lkh->bind_param("iiiiii", $id_pegawai_login, $filter_month, $filter_year, $id_pegawai_login, $filter_month, $filter_year);
$stmt_previous_lkh->execute();
$result_previous_lkh = $stmt_previous_lkh->get_result();

while ($row = $result_previous_lkh->fetch_assoc()) {
    $previous_lkh_list[] = [
        'nama_kegiatan_harian' => $row['nama_kegiatan_harian'],
        'uraian_kegiatan_lkh' => $row['uraian_kegiatan_lkh'],
        'jumlah_realisasi' => $row['jumlah_realisasi'],
        'satuan_realisasi' => $row['satuan_realisasi']
    ];
}

$stmt_previous_lkh->close();

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
    <title>LKH - E-Lapkin Mobile</title>
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
            z-index: 1000;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
        }
        .bottom-nav .nav-item {
            flex: 1;
            text-align: center;
        }
        .bottom-nav .nav-link {
            padding: 12px 8px;
            color: #6c757d;
            text-decoration: none;
            display: block;
            font-size: 11px;
            font-weight: 500;
            transition: all 0.3s ease;
            border-radius: 12px;
            margin: 4px;
        }
        .bottom-nav .nav-link.active {
            color: #0d6efd;
            background: linear-gradient(135deg, rgba(13, 110, 253, 0.1), rgba(13, 110, 253, 0.05));
            transform: translateY(-2px);
        }
        .bottom-nav .nav-link i {
            font-size: 18px;
            margin-bottom: 4px;
            transition: transform 0.2s ease;
        }
        .bottom-nav .nav-link.active i {
            transform: scale(1.1);
        }
        body {
            padding-bottom: 80px;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f2ff 100%);
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 20px;
        }
        .btn {
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-1px);
        }
        .status-badge {
            border-radius: 20px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .nav-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 0;
        }
        .btn-primary-large {
            padding: 10px 20px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
            transition: all 0.3s ease;
        }
        .btn-primary-large:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(13, 110, 253, 0.4);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="navbar nav-header">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center text-white" href="dashboard.php">
                <i class="fas fa-arrow-left me-2"></i>
                <span>Laporan Kinerja Harian</span>
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
                <?php if ($status_verval_lkh == 'diajukan'): ?>
                    <div class="alert alert-info alert-dismissible">
                        <i class="fas fa-clock me-2"></i>
                        LKH periode ini sudah diajukan dan menunggu verifikasi Pejabat Penilai.
                    </div>
                <?php elseif ($status_verval_lkh == 'disetujui'): ?>
                    <div class="alert alert-success alert-dismissible">
                        <i class="fas fa-check-circle me-2"></i>
                        LKH periode ini sudah diverifikasi/validasi oleh Pejabat Penilai.
                    </div>
                <?php elseif ($status_verval_lkh == 'ditolak'): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <i class="fas fa-times-circle me-2"></i>
                        LKH periode ini ditolak oleh Pejabat Penilai. Silakan perbaiki dan ajukan ulang.
                    </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="mb-3">
                    <div class="d-flex gap-2 flex-wrap mb-2">
                        <button class="btn btn-info btn-sm" onclick="showPreviewModal()" 
                            <?= empty($lkhs) ? 'disabled' : '' ?>>
                            <i class="fas fa-eye me-1"></i>Preview LKH
                        </button>
                        
                        <?php if ($status_verval_lkh == 'diajukan'): ?>
                            <button class="btn btn-warning btn-sm" onclick="confirmCancelVerval()">
                                <i class="fas fa-times me-1"></i>Batal Ajukan
                            </button>
                        <?php elseif ($status_verval_lkh == '' || $status_verval_lkh == null || $status_verval_lkh == 'ditolak'): ?>
                            <button class="btn btn-success btn-sm" onclick="confirmSubmitVerval()" 
                                <?= empty($lkhs) ? 'disabled' : '' ?>>
                                <i class="fas fa-paper-plane me-1"></i>Ajukan Verval
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <button class="btn btn-primary btn-primary-large" onclick="showAddModal()"
                            <?= ($status_verval_lkh == 'diajukan' || $status_verval_lkh == 'disetujui' || $periode_rkb_belum_diatur) ? 'disabled' : '' ?>>
                            <i class="fas fa-plus me-2"></i>Tambah LKH
                        </button>
                    </div>
                </div>
                
                <?php if ($periode_rkb_belum_diatur): ?>
                    <div class="alert alert-warning mt-3 mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Perhatian:</strong> Belum ada RKB untuk periode ini. 
                        <a href="rkb.php" class="alert-link">Buat RKB terlebih dahulu</a>.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- LKH List -->
        <?php if (empty($lkhs)): ?>
            <div class="card text-center">
                <div class="card-body py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h6 class="text-muted">Belum ada LKH bulan ini</h6>
                    <p class="text-muted">Mulai tambahkan kegiatan harian Anda</p>
                    <?php if (!$periode_rkb_belum_diatur): ?>
                        <button class="btn btn-primary btn-primary-large" onclick="showAddModal()">
                            <i class="fas fa-plus me-2"></i>Tambah LKH
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($lkhs as $lkh): ?>
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-primary me-2">
                                        <?= date('d M', strtotime($lkh['tanggal_lkh'])) ?>
                                    </span>
                                    <small class="text-muted">
                                        <?= date('l', strtotime($lkh['tanggal_lkh'])) ?>
                                    </small>
                                </div>
                                <h6 class="card-title mb-2"><?= htmlspecialchars($lkh['nama_kegiatan_harian']) ?></h6>
                                <p class="card-text small text-muted mb-2">
                                    <?= htmlspecialchars($lkh['uraian_kegiatan_lkh']) ?>
                                </p>
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-success me-2">
                                        <?= htmlspecialchars($lkh['jumlah_realisasi'] . ' ' . $lkh['satuan_realisasi']) ?>
                                    </span>
                                    <?php if ($lkh['lampiran']): ?>
                                        <a href="../uploads/lkh/<?= htmlspecialchars($lkh['lampiran']) ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-paperclip me-1"></i>Lampiran
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="editLkh(<?= $lkh['id_lkh'] ?>, '<?= htmlspecialchars($lkh['tanggal_lkh']) ?>', '<?= $lkh['id_rkb'] ?>', '<?= htmlspecialchars($lkh['nama_kegiatan_harian']) ?>', '<?= htmlspecialchars($lkh['uraian_kegiatan_lkh']) ?>', '<?= htmlspecialchars($lkh['jumlah_realisasi']) ?>', '<?= htmlspecialchars($lkh['satuan_realisasi']) ?>')" 
                                           <?= ($status_verval_lkh == 'diajukan' || $status_verval_lkh == 'disetujui') ? 'style="display:none;"' : '' ?>>
                                            <i class="fas fa-edit me-2"></i>Edit
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item text-danger" href="#" onclick="deleteLkh(<?= $lkh['id_lkh'] ?>)"
                                           <?= ($status_verval_lkh == 'diajukan' || $status_verval_lkh == 'disetujui') ? 'style="display:none;"' : '' ?>>
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
                <a href="rkb.php" class="nav-link">
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
                <a href="lkh.php" class="nav-link active">
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

    <!-- Add/Edit LKH Modal -->
    <div class="modal fade" id="lkhModal" tabindex="-1">
        <div class="modal-dialog">
            <form id="lkhForm" method="POST" enctype="multipart/form-data">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="lkhModalTitle">Tambah LKH</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="lkhAction" value="add">
                        <input type="hidden" name="id_lkh" id="lkhId">
                        
                        <div class="mb-3">
                            <label class="form-label">Tanggal Kegiatan</label>
                            <input type="date" class="form-control" name="tanggal_lkh" id="tanggalLkh" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">RKB Terkait</label>
                            <select class="form-select" name="id_rkb" id="rkbSelect" required>
                                <option value="">-- Pilih RKB --</option>
                                <?php foreach ($rkb_list as $rkb): ?>
                                    <option value="<?= $rkb['id_rkb'] ?>"><?= htmlspecialchars($rkb['uraian_kegiatan']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Nama Kegiatan Harian</label>
                                <?php if (!empty($previous_lkh_list)): ?>
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="showPreviousLkh()">
                                        <i class="fas fa-history me-1"></i>LKH Terdahulu
                                    </button>
                                <?php endif; ?>
                            </div>
                            <input type="text" class="form-control" name="nama_kegiatan_harian" id="namaKegiatan" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Uraian Kegiatan LKH</label>
                            <textarea class="form-control" name="uraian_kegiatan_lkh" id="uraianKegiatan" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Jumlah Realisasi</label>
                            <input type="text" class="form-control" name="jumlah_realisasi" id="jumlahRealisasi" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Satuan Realisasi</label>
                            <select class="form-select" name="satuan_realisasi" id="satuanRealisasi" required>
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
                        
                        <div class="mb-3" id="lampiranDiv">
                            <label class="form-label">Lampiran (opsional)</label>
                            <input type="file" class="form-control" name="lampiran" id="lampiranInput" 
                                   accept=".pdf,.jpg,.jpeg,.png,image/*,application/pdf" 
                                   capture="environment">
                            <div class="form-text">Format: PDF, JPG, JPEG, PNG. Maksimal 2MB.</div>
                            <div id="filePreview" class="mt-2" style="display: none;">
                                <small class="text-success">
                                    <i class="fas fa-check-circle me-1"></i>
                                    File dipilih: <span id="fileName"></span>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Preview LKH Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-eye me-2"></i>Preview Laporan Kinerja Harian (LKH)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <h6 class="fw-bold">Periode: <?= $months[$filter_month] . ' ' . $filter_year ?></h6>
                        <h6 class="fw-bold">Nama Pegawai: <?= htmlspecialchars($userData['nama']) ?></h6>
                    </div>
                    
                    <?php if (empty($lkhs)): ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0">Belum ada data LKH untuk periode ini.</p>
                        </div>
                    <?php else: ?>
                        <?php
                        // Group LKH by date
                        $lkh_grouped = [];
                        foreach ($lkhs as $lkh) {
                            $date_key = $lkh['tanggal_lkh'];
                            if (!isset($lkh_grouped[$date_key])) {
                                $lkh_grouped[$date_key] = [];
                            }
                            $lkh_grouped[$date_key][] = $lkh;
                        }
                        ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-sm">
                                <thead class="table-primary">
                                    <tr class="text-center">
                                        <th width="5%">No</th>
                                        <th width="15%">Hari / Tanggal</th>
                                        <th width="30%">Kegiatan</th>
                                        <th width="40%">Uraian Tugas Kegiatan</th>
                                        <th width="10%">Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1; 
                                    $hariList = [
                                        'Sun' => 'Minggu', 'Mon' => 'Senin', 'Tue' => 'Selasa', 'Wed' => 'Rabu',
                                        'Thu' => 'Kamis', 'Fri' => 'Jumat', 'Sat' => 'Sabtu'
                                    ];
                                    
                                    foreach ($lkh_grouped as $date => $lkh_items): 
                                        $hari = $hariList[date('D', strtotime($date))];
                                        $tanggal_formatted = $hari . ', ' . date('d-m-Y', strtotime($date));
                                        $first_item = true;
                                    ?>
                                        <?php foreach ($lkh_items as $lkh): ?>
                                            <tr>
                                                <?php if ($first_item): ?>
                                                    <td class="text-center" rowspan="<?= count($lkh_items) ?>">
                                                        <?= $no++ ?>
                                                    </td>
                                                    <td class="text-center" rowspan="<?= count($lkh_items) ?>">
                                                        <small><?= $tanggal_formatted ?></small>
                                                    </td>
                                                <?php endif; ?>
                                                <td>
                                                    <small>- <?= htmlspecialchars($lkh['nama_kegiatan_harian'] ?? '') ?></small>
                                                </td>
                                                <td>
                                                    <small>- <?= htmlspecialchars($lkh['uraian_kegiatan_lkh']) ?></small>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-primary">
                                                        <small><?= htmlspecialchars($lkh['jumlah_realisasi'] . ' ' . $lkh['satuan_realisasi']) ?></small>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php $first_item = false; ?>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted">
                                        <strong>Total LKH:</strong> <?= count($lkhs) ?> kegiatan
                                    </small>
                                </div>
                                <div class="col-6 text-end">
                                    <small class="text-muted">
                                        <strong>Status:</strong> 
                                        <?php 
                                        if ($status_verval_lkh == 'diajukan') {
                                            echo '<span class="badge bg-warning">Menunggu Verifikasi</span>';
                                        } elseif ($status_verval_lkh == 'disetujui') {
                                            echo '<span class="badge bg-success">Disetujui</span>';
                                        } elseif ($status_verval_lkh == 'ditolak') {
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

    <!-- Previous LKH Modal -->
    <div class="modal fade" id="previousLkhModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-history me-2"></i>LKH Terdahulu
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-1"></i>
                        Pilih salah satu LKH terdahulu untuk mengisi form otomatis. Data akan disalin ke form tambah LKH.
                    </div>
                    
                    <?php if (empty($previous_lkh_list)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Belum ada data LKH terdahulu yang dapat dijadikan referensi.</p>
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <input type="text" class="form-control" id="searchPreviousLkh" placeholder="🔍 Cari LKH terdahulu...">
                        </div>
                        
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($previous_lkh_list as $index => $prev_lkh): ?>
                                <div class="card mb-2 previous-lkh-item" 
                                     data-nama="<?= htmlspecialchars($prev_lkh['nama_kegiatan_harian']) ?>"
                                     data-uraian="<?= htmlspecialchars($prev_lkh['uraian_kegiatan_lkh']) ?>"
                                     data-jumlah="<?= htmlspecialchars($prev_lkh['jumlah_realisasi']) ?>"
                                     data-satuan="<?= htmlspecialchars($prev_lkh['satuan_realisasi']) ?>">
                                    <div class="card-body p-3">
                                        <h6 class="card-title mb-2"><?= htmlspecialchars($prev_lkh['nama_kegiatan_harian']) ?></h6>
                                        <p class="card-text small text-muted mb-2">
                                            <?= htmlspecialchars($prev_lkh['uraian_kegiatan_lkh']) ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge bg-secondary me-1"><?= htmlspecialchars($prev_lkh['jumlah_realisasi']) ?></span>
                                                <span class="badge bg-primary"><?= htmlspecialchars($prev_lkh['satuan_realisasi']) ?></span>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-success" 
                                                    onclick="selectPreviousLkh('<?= htmlspecialchars($prev_lkh['nama_kegiatan_harian']) ?>', '<?= htmlspecialchars($prev_lkh['uraian_kegiatan_lkh']) ?>', '<?= htmlspecialchars($prev_lkh['jumlah_realisasi']) ?>', '<?= htmlspecialchars($prev_lkh['satuan_realisasi']) ?>')">
                                                <i class="fas fa-check me-1"></i>Gunakan
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div id="noDataPrevious" class="text-center py-3 d-none">
                            <i class="fas fa-search fa-2x text-muted mb-2"></i>
                            <p class="text-muted">Tidak ada LKH yang sesuai dengan pencarian.</p>
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
        <input type="hidden" name="id_lkh" id="deleteId">
    </form>

    <form id="vervalForm" method="POST" style="display: none;">
        <input type="hidden" name="ajukan_verval_lkh" id="vervalAction">
    </form>

    <form id="cancelVervalForm" method="POST" style="display: none;">
        <input type="hidden" name="batal_verval_lkh" value="1">
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

        function showAddModal() {
            document.getElementById('lkhModalTitle').textContent = 'Tambah LKH';
            document.getElementById('lkhAction').value = 'add';
            document.getElementById('lkhId').value = '';
            document.getElementById('lkhForm').reset();
            document.getElementById('tanggalLkh').value = '<?= $current_date ?>';
            document.getElementById('lampiranDiv').style.display = 'block';
            document.getElementById('submitBtn').textContent = 'Simpan';
            new bootstrap.Modal(document.getElementById('lkhModal')).show();
        }

        function editLkh(id, tanggal, idRkb, nama, uraian, jumlah, satuan) {
            document.getElementById('lkhModalTitle').textContent = 'Edit LKH';
            document.getElementById('lkhAction').value = 'edit';
            document.getElementById('lkhId').value = id;
            document.getElementById('tanggalLkh').value = tanggal;
            document.getElementById('rkbSelect').value = idRkb;
            document.getElementById('namaKegiatan').value = nama;
            document.getElementById('uraianKegiatan').value = uraian;
            document.getElementById('jumlahRealisasi').value = jumlah;
            
            // Set satuan dropdown
            const satuanMap = {
                'Kegiatan': '1', 'JP': '2', 'Dokumen': '3', 'Laporan': '4',
                'Hari': '5', 'Jam': '6', 'Menit': '7', 'Unit': '8'
            };
            document.getElementById('satuanRealisasi').value = satuanMap[satuan] || '';
            
            // Hide file upload for edit
            document.getElementById('lampiranDiv').style.display = 'none';
            document.getElementById('submitBtn').textContent = 'Update';
            
            new bootstrap.Modal(document.getElementById('lkhModal')).show();
        }

        function showPreviewModal() {
            new bootstrap.Modal(document.getElementById('previewModal')).show();
        }

        function showPreviousLkh() {
            new bootstrap.Modal(document.getElementById('previousLkhModal')).show();
        }

        function selectPreviousLkh(nama, uraian, jumlah, satuan) {
            document.getElementById('namaKegiatan').value = nama;
            document.getElementById('uraianKegiatan').value = uraian;
            document.getElementById('jumlahRealisasi').value = jumlah;
            
            // Set satuan dropdown
            const satuanMap = {
                'Kegiatan': '1', 'JP': '2', 'Dokumen': '3', 'Laporan': '4',
                'Hari': '5', 'Jam': '6', 'Menit': '7', 'Unit': '8'
            };
            document.getElementById('satuanRealisasi').value = satuanMap[satuan] || '';
            
            // Close previous LKH modal
            bootstrap.Modal.getInstance(document.getElementById('previousLkhModal')).hide();
            
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'LKH Terpilih!',
                text: 'Data LKH terdahulu berhasil disalin ke form.',
                timer: 1500,
                showConfirmButton: false
            });
            
            // Ensure add LKH modal stays open
            setTimeout(function() {
                const modalLkh = bootstrap.Modal.getInstance(document.getElementById('lkhModal'));
                if (!modalLkh || !modalLkh._isShown) {
                    const newModalLkh = new bootstrap.Modal(document.getElementById('lkhModal'));
                    newModalLkh.show();
                }
            }, 100);
        }

        function confirmSubmitVerval() {
            Swal.fire({
                title: 'Ajukan Verval LKH?',
                text: 'LKH akan bisa digenerate setelah di verval oleh Pejabat Penilai.',
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
                text: 'Anda dapat mengedit/menghapus/mengirim ulang LKH setelah membatalkan.',
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

        function deleteLkh(id) {
            Swal.fire({
                title: 'Konfirmasi Hapus',
                text: 'Anda yakin ingin menghapus LKH ini?',
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

        // File input handling for mobile
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('lampiranInput');
            const filePreview = document.getElementById('filePreview');
            const fileName = document.getElementById('fileName');
            
            if (fileInput) {
                fileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        fileName.textContent = file.name;
                        filePreview.style.display = 'block';
                        
                        // Validate file size
                        if (file.size > 2 * 1024 * 1024) {
                            Swal.fire({
                                icon: 'error',
                                title: 'File Terlalu Besar',
                                text: 'Ukuran file maksimal 2MB',
                                timer: 3000,
                                showConfirmButton: false
                            });
                            fileInput.value = '';
                            filePreview.style.display = 'none';
                            return;
                        }
                        
                        // Validate file type
                        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                        if (!allowedTypes.includes(file.type)) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Format File Tidak Didukung',
                                text: 'Hanya file PDF, JPG, JPEG, dan PNG yang diperbolehkan',
                                timer: 3000,
                                showConfirmButton: false
                            });
                            fileInput.value = '';
                            filePreview.style.display = 'none';
                            return;
                        }
                    } else {
                        filePreview.style.display = 'none';
                    }
                });
                
                // Force click for better mobile compatibility
                fileInput.addEventListener('touchstart', function(e) {
                    e.preventDefault();
                    this.click();
                });
            }
            
            // ...existing code...
        });
    </script>
</body>
</html>