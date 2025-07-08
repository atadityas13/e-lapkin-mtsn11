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
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --card-shadow: 0 8px 25px rgba(0,0,0,0.1);
            --card-hover-shadow: 0 12px 35px rgba(0,0,0,0.15);
            --accent-blue: #667eea;
            --accent-purple: #764ba2;
            --success-gradient: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

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
            color: var(--accent-blue);
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.05));
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
            flex: 1;
            min-width: 0;
        }

        .nav-header .brand-content {
            flex: 1;
            min-width: 0;
        }

        .nav-header .brand-content .fw-bold {
            font-size: 1.1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .nav-header .brand-content small {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .nav-header .user-info {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 12px;
            padding: 8px 12px;
            min-width: 120px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .nav-header .user-info .user-name {
            font-size: 0.85rem;
            font-weight: 600;
            line-height: 1.2;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .nav-header .user-info .user-nip {
            font-size: 0.75rem;
            opacity: 0.9;
            line-height: 1.1;
            margin-bottom: 2px;
        }

        .nav-header .user-info .user-period {
            font-size: 0.7rem;
            opacity: 0.8;
            line-height: 1.1;
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

        .period-card {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.02));
        }

        .rkb-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9ff 100%);
            border-left: 4px solid var(--accent-blue);
        }

        .rkb-card:hover {
            border-left-color: var(--accent-purple);
        }

        .btn {
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary-large {
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 15px;
            background: var(--primary-gradient);
            border: none;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }

        .btn-primary-large:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-outline-primary-custom {
            border: 2px solid var(--accent-blue);
            color: var(--accent-blue);
            background: transparent;
        }

        .btn-outline-primary-custom:hover {
            background: var(--primary-gradient);
            border-color: transparent;
            color: white;
        }

        .status-badge {
            border-radius: 20px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-custom {
            border-radius: 20px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-kegiatan { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .badge-jp { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .badge-dokumen { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .badge-laporan { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }
        .badge-hari { background: linear-gradient(135deg, #d299c2 0%, #fef9d7 100%); }
        .badge-jam { background: linear-gradient(135deg, #89f7fe 0%, #66a6ff 100%); }
        .badge-menit { background: linear-gradient(135deg, #fdbb2d 0%, #22c1c3 100%); }
        .badge-unit { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }

        .rhk-badge {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.15), rgba(118, 75, 162, 0.1));
            border-radius: 12px;
            padding: 8px 12px;
            font-size: 11px;
            color: var(--accent-blue);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .quantity-badge {
            background: var(--success-gradient);
            color: white;
            border-radius: 15px;
            padding: 6px 12px;
            font-weight: 600;
            font-size: 13px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9ff 100%);
        }

        .empty-state i {
            font-size: 4rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
        }

        .floating-action {
            position: fixed;
            bottom: 100px;
            right: 20px;
            z-index: 999;
        }

        .floating-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-gradient);
            color: white;
            border: none;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            transition: all 0.3s ease;
        }

        .floating-btn:hover {
            transform: scale(1.1) translateY(-2px);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.5);
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9ff 100%);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(102, 126, 234, 0.1);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-shadow);
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.85rem;
            font-weight: 500;
            margin-top: 5px;
        }

        .period-selector {
            background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(248,249,255,0.8));
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 15px;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .rkb-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .rkb-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .dropdown-menu {
            border-radius: 12px;
            border: none;
            box-shadow: var(--card-shadow);
        }

        .dropdown-item {
            padding: 10px 15px;
            border-radius: 8px;
            margin: 2px;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.05));
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

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid rgba(102, 126, 234, 0.2);
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .alert {
            border-radius: 15px;
            border: none;
        }

        .verification-status {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.05));
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid var(--accent-blue);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .action-buttons .btn {
            flex: 1;
            min-width: 120px;
        }

        @media (max-width: 576px) {
            .floating-action {
                bottom: 90px;
                right: 15px;
            }
            
            .floating-btn {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
            
            .card-body {
                padding: 15px;
            }
            
            .rkb-meta {
                flex-direction: column;
                align-items: flex-start;
            }

            .action-buttons .btn {
                min-width: 100px;
                font-size: 0.85rem;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
                gap: 10px;
            }
        }

        @media (max-width: 576px) {
            .nav-header {
                padding: 15px 10px;
            }
            
            .nav-header .container-fluid {
                gap: 10px !important;
                flex-wrap: nowrap;
            }
            
            .nav-header .navbar-brand {
                font-size: 1.1rem;
                flex: 1;
                min-width: 0;
            }
            
            .nav-header .brand-content .fw-bold {
                font-size: 0.95rem;
            }
            
            .nav-header .brand-content small {
                font-size: 0.7rem;
            }
            
            .nav-header .user-info {
                min-width: 100px;
                padding: 6px 8px;
            }
            
            .nav-header .user-info .user-name {
                font-size: 0.75rem;
            }
            
            .nav-header .user-info .user-nip {
                font-size: 0.7rem;
            }
            
            .nav-header .user-info .user-period {
                font-size: 0.65rem;
            }
        }

        @media (max-width: 450px) {
            .nav-header .container-fluid {
                flex-direction: column;
                gap: 12px !important;
                align-items: stretch;
            }
            
            .nav-header .navbar-brand {
                justify-content: flex-start;
                width: 100%;
            }
            
            .nav-header .user-info {
                align-self: center;
                min-width: 200px;
                text-align: center;
            }
            
            .nav-header .user-info .user-name {
                font-size: 0.8rem;
            }
            
            .nav-header .user-info .user-nip {
                font-size: 0.75rem;
            }
            
            .nav-header .user-info .user-period {
                font-size: 0.7rem;
            }
        }

        @media (max-width: 390px) {
            .nav-header {
                padding: 12px 8px;
            }
            
            .nav-header .brand-content .fw-bold {
                font-size: 0.9rem;
            }
            
            .nav-header .brand-content small {
                font-size: 0.65rem;
            }
            
            .nav-header .user-info {
                min-width: 180px;
                padding: 6px;
            }
            
            .nav-header .user-info .user-name {
                font-size: 0.75rem;
            }
            
            .nav-header .user-info .user-nip {
                font-size: 0.7rem;
            }
            
            .nav-header .user-info .user-period {
                font-size: 0.65rem;
            }
        }

        /* Mobile Header Styles */
        .mobile-header {
            display: none;
            position: relative;
            z-index: 1000;
        }

        .mobile-header .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            background: var(--primary-gradient);
            color: white;
            border-radius: 0 0 25px 25px;
            box-shadow: var(--card-shadow);
        }

        .mobile-header .header-left {
            display: flex;
            align-items: center;
        }

        .mobile-header .back-btn {
            background: transparent;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            margin-right: 10px;
        }

        .mobile-header .page-info {
            flex: 1;
            min-width: 0;
        }

        .mobile-header .page-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .mobile-header .page-subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .mobile-header .user-info {
            display: none;
        }

        @media (max-width: 768px) {
            .mobile-header {
                display: block;
            }
            
            .nav-header {
                display: none;
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

    <nav class="navbar nav-header">
        <div class="container-fluid d-flex align-items-center gap-3">
            <a class="navbar-brand d-flex align-items-center text-white text-decoration-none" href="dashboard.php">
                <i class="fas fa-arrow-left me-3"></i>
                <div class="brand-content">
                    <div class="fw-bold">Rencana Kerja Bulanan</div>
                    <small class="opacity-75">Manajemen RKB</small>
                </div>
            </a>
            <div class="user-info text-white">
                <div class="user-name"><?= htmlspecialchars($userData['nama']) ?></div>
                <div class="user-nip"><?= htmlspecialchars($userData['nip']) ?></div>
                <div class="user-period"><?= htmlspecialchars($activePeriod) ?></div>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-3">
        <!-- Stats Overview -->
        <!-- <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= count($rkbs) ?></div>
                <div class="stat-label">Total RKB</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $filter_month ?></div>
                <div class="stat-label">Bulan Aktif</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($rhk_list) ?></div>
                <div class="stat-label">RHK Tersedia</div>
            </div>
        </div> -->

        <!-- Modal for Period Not Set -->
        <?php if ($periode_bulan_belum_diatur): ?>
        <div class="modal fade" id="modalPeriodeBulanBelumDiatur" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-calendar-alt me-2"></i>Periode Bulan Belum Diatur
                        </h5>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Informasi:</strong> Periode bulan untuk RKB belum diatur. Silakan pilih periode bulan yang akan digunakan.
                        </div>
                        <p>Silakan pilih periode bulan yang akan digunakan untuk Rencana Kerja Bulanan (RKB) Anda:</p>
                        <form id="setBulanForm" method="POST">
                            <div class="mb-3">
                                <label for="bulan_aktif_modal" class="form-label fw-semibold">Pilih Bulan Periode:</label>
                                <select class="form-select" id="bulan_aktif_modal" name="bulan_aktif" required>
                                    <option value="">-- Pilih Bulan --</option>
                                    <?php foreach ($months as $num => $name): ?>
                                        <option value="<?= $num ?>" <?= ($num == (int)date('m')) ? 'selected' : '' ?>><?= $name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <input type="hidden" name="set_bulan_aktif" value="1">
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-info" onclick="submitPeriodForm()">
                            <i class="fas fa-check me-1"></i>Atur Periode Bulan
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Period Info with Change Option -->
        <div class="card period-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-calendar-alt text-primary me-2"></i>
                        Periode: <?= $months[$filter_month] . ' ' . $filter_year ?>
                    </h6>
                    <button class="btn btn-outline-primary-custom btn-sm" onclick="showPeriodChangeModal()">
                        <i class="fas fa-edit me-1"></i>Ubah
                    </button>
                </div>
                
                <!-- Status Alert -->
                <?php if ($status_verval_rkb == 'diajukan'): ?>
                    <div class="verification-status">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-clock text-info me-2 fs-5"></i>
                            <div>
                                <div class="fw-semibold text-info">Status: Menunggu Verifikasi</div>
                                <small class="text-muted">RKB periode ini sudah diajukan dan menunggu verifikasi Pejabat Penilai.</small>
                            </div>
                        </div>
                    </div>
                <?php elseif ($status_verval_rkb == 'disetujui'): ?>
                    <div class="verification-status" style="border-left-color: #28a745;">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle text-success me-2 fs-5"></i>
                            <div>
                                <div class="fw-semibold text-success">Status: Disetujui</div>
                                <small class="text-muted">RKB periode ini sudah diverifikasi/validasi oleh Pejabat Penilai.</small>
                            </div>
                        </div>
                    </div>
                <?php elseif ($status_verval_rkb == 'ditolak'): ?>
                    <div class="verification-status" style="border-left-color: #dc3545;">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-times-circle text-danger me-2 fs-5"></i>
                            <div>
                                <div class="fw-semibold text-danger">Status: Ditolak</div>
                                <small class="text-muted">RKB periode ini ditolak oleh Pejabat Penilai. Silakan perbaiki dan ajukan ulang.</small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="action-buttons">
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
                
                <?php if ($periode_rhk_belum_diatur): ?>
                    <div class="alert alert-warning mt-3 mb-0">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle me-2 fs-5"></i>
                            <div>
                                <div class="fw-semibold">Perhatian: Belum ada RHK</div>
                                <small>Silakan <a href="rhk.php" class="alert-link fw-semibold">buat RHK terlebih dahulu</a> sebelum membuat RKB.</small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Period Change Modal -->
        <div class="modal fade" id="modalUbahPeriode" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-calendar-alt me-2"></i>Ubah Periode Bulan
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="ubahPeriodeForm" method="POST">
                            <div class="mb-3">
                                <label for="bulan_aktif_ubah" class="form-label">Pilih Bulan Periode:</label>
                                <select class="form-select" id="bulan_aktif_ubah" name="bulan_aktif" required>
                                    <?php foreach ($months as $num => $name): ?>
                                        <option value="<?= $num ?>" <?= ($num == $filter_month) ? 'selected' : '' ?>><?= $name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-1"></i>
                                Data RKB yang tampil akan mengikuti bulan yang dipilih.
                            </div>
                            <input type="hidden" name="set_bulan_aktif" value="1">
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-primary" onclick="confirmPeriodChange()">
                            <i class="fas fa-check me-1"></i>Ubah Periode
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal for Period Change -->
        <div class="modal fade" id="modalKonfirmasiUbahPeriode" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Ubah Periode
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Apakah Anda yakin ingin mengubah periode bulan aktif?</p>
                        <p class="text-muted small">Data RKB yang tampil akan mengikuti bulan yang dipilih.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-warning" onclick="submitPeriodChange()">
                            <i class="fas fa-check me-1"></i>Ya, Ubah Periode
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- RKB List -->
        <?php if (empty($rkbs)): ?>
            <div class="card">
                <div class="card-body empty-state">
                    <i class="fas fa-calendar-plus"></i>
                    <h5 class="text-muted mb-3">Belum ada RKB bulan ini</h5>
                    <p class="text-muted mb-4">Mulai buat rencana kerja bulanan untuk mencapai target kinerja Anda</p>
                    <?php if (!$periode_rhk_belum_diatur): ?>
                        <button class="btn btn-primary-large" onclick="showAddModal()">
                            <i class="fas fa-plus me-2"></i>Buat RKB Pertama
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($rkbs as $index => $rkb): ?>
                <div class="card rkb-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-primary me-2">#<?= $index + 1 ?></span>
                                    <span class="rhk-badge"><?= htmlspecialchars($rkb['nama_rhk']) ?></span>
                                </div>
                                <h6 class="rkb-title"><?= htmlspecialchars($rkb['uraian_kegiatan']) ?></h6>
                                
                                <div class="rkb-meta">
                                    <span class="quantity-badge">
                                        <i class="fas fa-bullseye me-1"></i>
                                        <?= htmlspecialchars($rkb['kuantitas']) ?> <?= htmlspecialchars($rkb['satuan']) ?>
                                    </span>
                                    <?php if ($rkb['lampiran']): ?>
                                        <a href="../uploads/rkb/<?= htmlspecialchars($rkb['lampiran']) ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-paperclip me-1"></i>Lampiran
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light rounded-circle" data-bs-toggle="dropdown" style="width: 35px; height: 35px;">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="editRkb(<?= $rkb['id_rkb'] ?>, <?= $rkb['id_rhk'] ?>, '<?= htmlspecialchars($rkb['uraian_kegiatan']) ?>', '<?= htmlspecialchars($rkb['kuantitas']) ?>', '<?= htmlspecialchars($rkb['satuan']) ?>')" 
                                           <?= ($status_verval_rkb == 'diajukan' || $status_verval_rkb == 'disetujui') ? 'style="display:none;"' : '' ?>>
                                            <i class="fas fa-edit me-2 text-warning"></i>Edit RKB
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item text-danger" href="#" onclick="deleteRkb(<?= $rkb['id_rkb'] ?>)"
                                           <?= ($status_verval_rkb == 'diajukan' || $status_verval_rkb == 'disetujui') ? 'style="display:none;"' : '' ?>>
                                            <i class="fas fa-trash me-2"></i>Hapus RKB
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

    <!-- Floating Action Button -->
    <div class="floating-action">
        <button class="floating-btn" onclick="showAddModal()" title="Tambah RKB"
            <?= ($status_verval_rkb == 'diajukan' || $status_verval_rkb == 'disetujui' || $periode_rhk_belum_diatur) ? 'style="display:none;"' : '' ?>>
            <i class="fas fa-plus"></i>
        </button>
    </div>

    <!-- Bottom Navigation -->
    <?php include __DIR__ . '/components/bottom-nav.php'; ?>

    <!-- Add/Edit RKB Modal -->
    <div class="modal fade" id="rkbModal" tabindex="-1">
        <div class="modal-dialog">
            <form id="rkbForm" method="POST" enctype="multipart/form-data">
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
                            <label for="id_rhk_modal" class="form-label">Pilih RHK Terkait <span class="text-danger">*</span></label>
                            <select class="form-select" id="id_rhk_modal" name="id_rhk" required>
                                <option value="">-- Pilih RHK --</option>
                                <?php foreach ($rhk_list as $rhk): ?>
                                    <option value="<?= htmlspecialchars($rhk['id_rhk']) ?>">
                                        <?= htmlspecialchars($rhk['nama_rhk']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($rhk_list)): ?>
                                <div class="form-text text-danger">Anda belum memiliki RHK. Silakan <a href="rhk.php">tambah RHK terlebih dahulu</a>.</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label for="uraian_kegiatan_modal" class="form-label mb-0">Uraian Kinerja Bulanan (RKB) <span class="text-danger">*</span></label>
                                <?php if (!empty($previous_rkb_list)): ?>
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="showPreviousRkb()">
                                        <i class="fas fa-history me-1"></i>RKB Terdahulu
                                    </button>
                                <?php endif; ?>
                            </div>
                            <textarea class="form-control" id="uraian_kegiatan_modal" name="uraian_kegiatan" rows="3" required placeholder="Deskripsi kegiatan yang akan dilakukan..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Kuantitas Target <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="kuantitas" id="kuantitas_modal" required placeholder="Contoh: 12">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Satuan Target <span class="text-danger">*</span></label>
                                    <select class="form-select" name="satuan" id="satuan_modal" required>
                                        <option value="">-- Pilih Satuan --</option>
                                        <option value="Kegiatan">Kegiatan</option>
                                        <option value="JP">JP</option>
                                        <option value="Dokumen">Dokumen</option>
                                        <option value="Laporan">Laporan</option>
                                        <option value="Hari">Hari</option>
                                        <option value="Jam">Jam</option>
                                        <option value="Menit">Menit</option>
                                        <option value="Unit">Unit</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <small><strong>Catatan:</strong> Semua field yang bertanda (<span class="text-danger">*</span>) wajib diisi. Lampiran bersifat opsional dan dapat ditambahkan nanti jika diperlukan.</small>
                        </div> -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">Simpan</button>
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
                        <i class="fas fa-eye me-2"></i>Preview Rencana Kerja Bulanan (RKB)
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
                            <i class="fas fa-calendar-plus fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0">Belum ada data RKB untuk periode ini.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-sm">
                                <thead class="table-primary">
                                    <tr class="text-center">
                                        <th width="5%">No</th>
                                        <th width="20%">RHK Terkait</th>
                                        <th width="50%">Uraian Kinerja Bulanan</th>
                                        <th width="15%">Kuantitas</th>
                                        <th width="10%">Satuan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rkbs as $index => $rkb): ?>
                                        <tr>
                                            <td class="text-center"><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars($rkb['nama_rhk']) ?></td>
                                            <td><?= htmlspecialchars($rkb['uraian_kegiatan']) ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-primary">
                                                    <?= htmlspecialchars($rkb['kuantitas']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-success">
                                                    <?= htmlspecialchars($rkb['satuan']) ?>
                                                </span>
                                            </td>
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
                            <i class="fas fa-calendar-plus fa-3x text-muted mb-3"></i>
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

        // Auto show modal if period not set
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($periode_bulan_belum_diatur): ?>
            var modalBulan = new bootstrap.Modal(document.getElementById('modalPeriodeBulanBelumDiatur'));
            modalBulan.show();
            <?php endif; ?>
        });

        function submitPeriodForm() {
            const bulanDipilih = document.getElementById('bulan_aktif_modal').value;
            if (!bulanDipilih) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Peringatan',
                    text: 'Silakan pilih bulan terlebih dahulu.',
                    timer: 2000,
                    showConfirmButton: false
                });
                return;
            }
            document.getElementById('setBulanForm').submit();
        }

        function showPeriodChangeModal() {
            new bootstrap.Modal(document.getElementById('modalUbahPeriode')).show();
        }

        function confirmPeriodChange() {
            // Hide period change modal and show confirmation
            bootstrap.Modal.getInstance(document.getElementById('modalUbahPeriode')).hide();
            setTimeout(() => {
                new bootstrap.Modal(document.getElementById('modalKonfirmasiUbahPeriode')).show();
            }, 300);
        }

        function submitPeriodChange() {
            document.getElementById('ubahPeriodeForm').submit();
        }

        function showAddModal() {
            document.getElementById('rkbModalTitle').textContent = 'Tambah RKB';
            document.getElementById('rkbAction').value = 'add';
            document.getElementById('rkbId').value = '';
            document.getElementById('rkbForm').reset();
            document.getElementById('submitBtn').textContent = 'Simpan';
            new bootstrap.Modal(document.getElementById('rkbModal')).show();
        }

        function editRkb(id, id_rhk, uraian, kuantitas, satuan) {
            document.getElementById('rkbModalTitle').textContent = 'Edit RKB';
            document.getElementById('rkbAction').value = 'edit';
            document.getElementById('rkbId').value = id;
            document.getElementById('id_rhk_modal').value = id_rhk;
            document.getElementById('uraian_kegiatan_modal').value = uraian;
            document.getElementById('kuantitas_modal').value = kuantitas;
            document.getElementById('satuan_modal').value = satuan;
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
            document.getElementById('uraian_kegiatan_modal').value = uraian;
            document.getElementById('kuantitas_modal').value = kuantitas;
            document.getElementById('satuan_modal').value = satuan;
            
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
                text: 'RKB akan dikunci dan menunggu verifikasi Pejabat Penilai.',
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
                text: 'Anda yakin ingin menghapus RKB ini?',
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

        // Add smooth scroll animation for cards
        document.addEventListener('DOMContentLoaded', function() {
            // Animate cards on load
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>