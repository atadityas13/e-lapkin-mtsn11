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

// Get active period from user settings (same logic as web version)
function ensure_tahun_aktif_column($conn) {
    $result = $conn->query("SHOW COLUMNS FROM pegawai LIKE 'tahun_aktif'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE pegawai ADD COLUMN tahun_aktif INT DEFAULT NULL");
    }
}
ensure_tahun_aktif_column($conn);

function ensure_bulan_aktif_column($conn) {
    $result = $conn->query("SHOW COLUMNS FROM pegawai LIKE 'bulan_aktif'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE pegawai ADD COLUMN bulan_aktif TINYINT DEFAULT NULL");
    }
}
ensure_bulan_aktif_column($conn);

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

function get_bulan_aktif($conn, $id_pegawai) {
    $stmt = $conn->prepare("SELECT bulan_aktif FROM pegawai WHERE id_pegawai = ?");
    $stmt->bind_param("i", $id_pegawai);
    $stmt->execute();
    $stmt->bind_result($bulan_aktif);
    $stmt->fetch();
    $stmt->close();
    return $bulan_aktif ?: (int)date('m');
}

function set_bulan_aktif($conn, $id_pegawai, $bulan) {
    $stmt = $conn->prepare("UPDATE pegawai SET bulan_aktif = ? WHERE id_pegawai = ?");
    $stmt->bind_param("ii", $bulan, $id_pegawai);
    $stmt->execute();
    $stmt->close();
}

$periode_aktif = get_periode_aktif($conn, $id_pegawai_login);
$filter_month = $periode_aktif['bulan'];
$filter_year = $periode_aktif['tahun'];

// Check if period has been set before
$stmt_check_bulan = $conn->prepare("SELECT bulan_aktif FROM pegawai WHERE id_pegawai = ?");
$stmt_check_bulan->bind_param("i", $id_pegawai_login);
$stmt_check_bulan->execute();
$stmt_check_bulan->bind_result($bulan_aktif_db);
$stmt_check_bulan->fetch();
$stmt_check_bulan->close();

// Period not set if bulan_aktif is still NULL in database
$periode_bulan_belum_diatur = ($bulan_aktif_db === null);

// Get RKB verification status
$status_verval_rkb = '';
$stmt_status = $conn->prepare("SELECT status_verval FROM rkb WHERE id_pegawai = ? AND bulan = ? AND tahun = ? LIMIT 1");
$stmt_status->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
$stmt_status->execute();
$stmt_status->bind_result($status_verval_rkb);
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
    // Process period change
    if (isset($_POST['set_bulan_aktif'])) {
        $bulan_aktif_baru = (int)$_POST['bulan_aktif'];
        set_bulan_aktif($conn, $id_pegawai_login, $bulan_aktif_baru);
        set_mobile_notification('success', 'Periode Diubah', 'Periode bulan aktif berhasil diubah.');
        header('Location: rkb.php');
        exit();
    }

    // Prevent actions if RKB is already approved
    if ($status_verval_rkb == 'disetujui') {
        set_mobile_notification('error', 'Tidak Diizinkan', 'RKB periode ini sudah diverifikasi dan tidak dapat diubah.');
        header('Location: rkb.php');
        exit();
    }

    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == 'add' || $action == 'edit') {
            $id_rhk = (int)$_POST['id_rhk'];
            $uraian_kegiatan = trim($_POST['uraian_kegiatan']);
            $kuantitas = trim($_POST['kuantitas']);
            $satuan = trim($_POST['satuan']);
            
            // Handle file upload for add action (optional)
            $lampiran = NULL;
            if ($action == 'add' && isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] == UPLOAD_ERR_OK) {
                $file_tmp_name = $_FILES['lampiran']['tmp_name'];
                $file_extension = strtolower(pathinfo($_FILES['lampiran']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    set_mobile_notification('error', 'Gagal', 'Format file tidak diizinkan. Hanya PDF, JPG, JPEG, dan PNG yang diperbolehkan.');
                    header('Location: rkb.php');
                    exit();
                }
                
                if ($_FILES['lampiran']['size'] > 2 * 1024 * 1024) {
                    set_mobile_notification('error', 'Gagal', 'Ukuran file terlalu besar. Maksimal 2MB.');
                    header('Location: rkb.php');
                    exit();
                }
                
                $file_name = 'rkb_' . $id_pegawai_login . '_' . date('YmdHis') . '_' . uniqid() . '.' . $file_extension;
                $upload_dir = __DIR__ . '/../uploads/rkb/';
                $file_path = $upload_dir . $file_name;

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                if (move_uploaded_file($file_tmp_name, $file_path)) {
                    $lampiran = $file_name;
                } else {
                    set_mobile_notification('error', 'Gagal', 'Gagal mengunggah lampiran.');
                    header('Location: rkb.php');
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
                header('Location: rkb.php');
                exit();
            }

            // Validation - lampiran is now truly optional
            if (empty($id_rhk) || empty($uraian_kegiatan) || empty($kuantitas) || empty($satuan)) {
                set_mobile_notification('error', 'Gagal', 'Semua field wajib harus diisi.');
            } else {
                // Validate satuan against ENUM values (same as web version)
                $valid_satuan = ['Kegiatan','JP','Dokumen','Laporan','Hari','Jam','Menit','Unit'];
                if (!in_array($satuan, $valid_satuan)) {
                    set_mobile_notification('error', 'Gagal', 'Satuan tidak valid. Pilih salah satu: ' . implode(', ', $valid_satuan));
                    header('Location: rkb.php');
                    exit();
                }
                
                try {
                    if ($action == 'add') {
                        // Match web version database structure
                        $stmt = $conn->prepare("INSERT INTO rkb (id_pegawai, id_rhk, bulan, tahun, uraian_kegiatan, kuantitas, satuan, lampiran) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("iiisssss", $id_pegawai_login, $id_rhk, $filter_month, $filter_year, $uraian_kegiatan, $kuantitas, $satuan, $lampiran);
                    } else {
                        $id_rkb = (int)$_POST['id_rkb'];
                        $stmt = $conn->prepare("UPDATE rkb SET id_rhk = ?, uraian_kegiatan = ?, kuantitas = ?, satuan = ? WHERE id_rkb = ? AND id_pegawai = ?");
                        $stmt->bind_param("isssii", $id_rhk, $uraian_kegiatan, $kuantitas, $satuan, $id_rkb, $id_pegawai_login);
                    }

                    if ($stmt->execute()) {
                        set_mobile_notification('success', ($action == 'add') ? 'Berhasil' : 'Update Berhasil', ($action == 'add') ? "RKB berhasil ditambahkan!" : "RKB berhasil diperbarui!");
                    } else {
                        if (strpos($stmt->error, 'Data truncated') !== false) {
                            set_mobile_notification('error', 'Gagal', 'Data terlalu panjang untuk field satuan. Pilih salah satu opsi yang tersedia.');
                        } else {
                            set_mobile_notification('error', 'Gagal', ($action == 'add') ? "Gagal menambahkan RKB: " . $stmt->error : "Gagal memperbarui RKB: " . $stmt->error);
                        }
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'Data truncated') !== false) {
                        set_mobile_notification('error', 'Gagal', 'Terjadi kesalahan database: Data satuan tidak sesuai format yang diizinkan.');
                    } else {
                        set_mobile_notification('error', 'Gagal', 'Terjadi kesalahan database. Periksa data yang dimasukkan.');
                    }
                }
            }
            header('Location: rkb.php');
            exit();
            
        } elseif ($action == 'delete') {
            $id_rkb_to_delete = (int)$_POST['id_rkb'];
            
            // Check if RKB is used in LKH (same as web version)
            $cek_lkh = $conn->prepare("SELECT COUNT(*) FROM lkh WHERE id_rkb = ?");
            $cek_lkh->bind_param("i", $id_rkb_to_delete);
            $cek_lkh->execute();
            $cek_lkh->bind_result($jumlah_lkh);
            $cek_lkh->fetch();
            $cek_lkh->close();

            if ($jumlah_lkh > 0) {
                set_mobile_notification('error', 'Gagal', 'RKB tidak dapat dihapus karena sudah digunakan pada LKH.');
                header('Location: rkb.php');
                exit();
            }
            
            // Get file name to delete
            $stmt_get_file = $conn->prepare("SELECT lampiran FROM rkb WHERE id_rkb = ? AND id_pegawai = ?");
            $stmt_get_file->bind_param("ii", $id_rkb_to_delete, $id_pegawai_login);
            $stmt_get_file->execute();
            $stmt_get_file->bind_result($file_to_delete);
            $stmt_get_file->fetch();
            $stmt_get_file->close();
            
            $stmt = $conn->prepare("DELETE FROM rkb WHERE id_rkb = ? AND id_pegawai = ?");
            $stmt->bind_param("ii", $id_rkb_to_delete, $id_pegawai_login);

            if ($stmt->execute()) {
                if ($file_to_delete && file_exists(__DIR__ . '/../uploads/rkb/' . $file_to_delete)) {
                    unlink(__DIR__ . '/../uploads/rkb/' . $file_to_delete);
                }
                set_mobile_notification('success', 'Berhasil', 'RKB berhasil dihapus!');
            } else {
                set_mobile_notification('error', 'Gagal', "Gagal menghapus RKB: " . $stmt->error);
            }
            $stmt->close();
            header('Location: rkb.php');
            exit();
        }
    }
    
    // Submit/cancel RKB verification (same logic as web version)
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

// Ensure status_verval column exists
function ensure_status_verval_column_rkb($conn) {
    $result = $conn->query("SHOW COLUMNS FROM rkb LIKE 'status_verval'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE rkb ADD COLUMN status_verval ENUM('diajukan','disetujui','ditolak') DEFAULT NULL");
    }
}
ensure_status_verval_column_rkb($conn);

// Ensure lampiran column exists
function ensure_lampiran_column_rkb($conn) {
    $result = $conn->query("SHOW COLUMNS FROM rkb LIKE 'lampiran'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE rkb ADD COLUMN lampiran VARCHAR(255) DEFAULT NULL");
    }
}
ensure_lampiran_column_rkb($conn);

// Get RKB data for current period (match web version structure)
$rkbs = [];
$stmt_rkb = $conn->prepare("
    SELECT rkb.id_rkb, rkb.id_rhk, rkb.bulan, rkb.tahun, rkb.uraian_kegiatan, rkb.kuantitas, rkb.satuan, rkb.lampiran, rhk.nama_rhk
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

// Get RHK list for dropdown (same as web version)
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

// Get previous RKB list for reference (same logic as web version)
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

// Get active period for display
$activePeriod = getMobileActivePeriod($conn, $id_pegawai_login);
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
            gap: 1rem;
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
            cursor: pointer;
        }

        .mobile-header .back-btn:hover {
            background-color: rgba(255,255,255,0.1);
        }

        .mobile-header .page-info {
            flex: 1;
            min-width: 0;
        }

        .mobile-header .page-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mobile-header .page-subtitle {
            font-size: 0.8rem;
            opacity: 0.9;
            margin-top: 0.1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mobile-header .user-info {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 0.75rem;
            padding: 0.5rem 0.75rem;
            font-size: 0.8rem;
            text-align: right;
            min-width: 120px;
            flex-shrink: 0;
        }

        .mobile-header .user-info .user-name {
            font-weight: 600;
            margin-bottom: 0.1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mobile-header .user-info .user-details {
            opacity: 0.9;
            font-size: 0.75rem;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Main Content */
        .main-content {
            padding: 1rem;
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
        }

        @media (max-width: 480px) {
            .mobile-header .header-content {
                gap: 0.5rem;
            }

            .mobile-header .user-info {
                min-width: 80px;
                font-size: 0.7rem;
                padding: 0.4rem 0.6rem;
            }

            .mobile-header .user-info .user-details {
                font-size: 0.7rem;
            }
        }

        /* ...existing RKB-specific styles... */
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
                <div class="page-info">
                    <h1 class="page-title">Rencana Kinerja Bulanan</h1>
                    <p class="page-subtitle">Kelola RKB Bulanan Anda</p>
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
        <!-- ...existing content... -->
    </main>

    <!-- ...existing modals and content... -->
</body>
</html>