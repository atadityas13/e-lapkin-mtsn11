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
require_once __DIR__ . '/components/mobile-header.php';

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

// Get active period for display
$activePeriod = getMobileActivePeriod($conn, $id_pegawai_login);

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

            // Handle file upload for add action only
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
                    } else { // action == 'edit' - NO lampiran field in update, same as web version
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
        } elseif ($action == 'add_attachment') {
            $id_lkh = (int)$_POST['id_lkh'];
            
            // Handle file upload
            if (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] == UPLOAD_ERR_OK) {
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
                    $stmt = $conn->prepare("UPDATE lkh SET lampiran = ? WHERE id_lkh = ? AND id_pegawai = ?");
                    $stmt->bind_param("sii", $file_name, $id_lkh, $id_pegawai_login);
                    
                    if ($stmt->execute()) {
                        set_mobile_notification('success', 'Berhasil', 'Lampiran berhasil ditambahkan!');
                    } else {
                        set_mobile_notification('error', 'Gagal', 'Gagal menyimpan lampiran ke database.');
                        // Delete uploaded file if database save fails
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                    }
                    $stmt->close();
                } else {
                    set_mobile_notification('error', 'Gagal', 'Gagal mengunggah lampiran.');
                }
            } else {
                set_mobile_notification('error', 'Gagal', 'Tidak ada file yang dipilih atau terjadi kesalahan upload.');
            }
            
            header('Location: lkh.php');
            exit();
        } elseif ($action == 'remove_attachment') {
            $id_lkh = (int)$_POST['id_lkh'];
            
            // Get file name to delete
            $stmt_get_file = $conn->prepare("SELECT lampiran FROM lkh WHERE id_lkh = ? AND id_pegawai = ?");
            $stmt_get_file->bind_param("ii", $id_lkh, $id_pegawai_login);
            $stmt_get_file->execute();
            $stmt_get_file->bind_result($file_to_delete);
            $stmt_get_file->fetch();
            $stmt_get_file->close();
            
            // Remove lampiran from database
            $stmt = $conn->prepare("UPDATE lkh SET lampiran = NULL WHERE id_lkh = ? AND id_pegawai = ?");
            $stmt->bind_param("ii", $id_lkh, $id_pegawai_login);
            
            if ($stmt->execute()) {
                // Delete file if exists
                if ($file_to_delete && file_exists(__DIR__ . '/../uploads/lkh/' . $file_to_delete)) {
                    unlink(__DIR__ . '/../uploads/lkh/' . $file_to_delete);
                }
                set_mobile_notification('success', 'Berhasil', 'Lampiran berhasil dihapus!');
            } else {
                set_mobile_notification('error', 'Gagal', 'Gagal menghapus lampiran.');
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
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --card-shadow: 0 8px 25px rgba(0,0,0,0.1);
            --card-hover-shadow: 0 12px 35px rgba(0,0,0,0.15);
            --accent-blue: #667eea;
            --accent-purple: #764ba2;
            --success-gradient: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --lkh-gradient: linear-gradient(135deg,rgb(255, 245, 154) 0%,rgb(47, 147, 0) 100%);
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

        .period-card {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.02));
        }

        .lkh-card {
            background: linear-gradient(135deg, #ffffff 0%, #fff5f5 100%);
            border-left: 4px solidrgb(171, 171, 32);
        }

        .lkh-card:hover {
            border-left-color:rgb(169, 167, 5);
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

        .date-badge {
            background: var(--lkh-gradient);
            color: white;
            border-radius: 15px;
            padding: 8px 12px;
            font-weight: 600;
            font-size: 13px;
        }

        .quantity-badge {
            background: var(--success-gradient);
            color: white;
            border-radius: 15px;
            padding: 6px 12px;
            font-weight: 600;
            font-size: 13px;
        }

        .day-badge {
            background: linear-gradient(135deg, rgba(255, 107, 157, 0.15), rgba(196, 69, 105, 0.1));
            border-radius: 12px;
            padding: 4px 8px;
            font-size: 11px;
            color: #c44569;
            font-weight: 600;
            margin-left: 8px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: linear-gradient(135deg, #ffffff 0%, #fff5f5 100%);
        }

        .empty-state i {
            font-size: 4rem;
            background: var(--lkh-gradient);
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
            background: var(--lkh-gradient);
            color: white;
            border: none;
            box-shadow: 0 8px 25px rgba(255, 107, 157, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            transition: all 0.3s ease;
        }

        .floating-btn:hover {
            transform: scale(1.1) translateY(-2px);
            box-shadow: 0 12px 35px rgba(255, 107, 157, 0.5);
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #fff5f5 100%);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(255, 107, 157, 0.1);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-shadow);
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            background: var(--lkh-gradient);
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

        .lkh-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .lkh-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .verification-status {
            background: linear-gradient(135deg, rgba(255, 107, 157, 0.1), rgba(196, 69, 105, 0.05));
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solidrgb(197, 170, 20);
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
            background: linear-gradient(135deg, rgba(255, 107, 157, 0.1), rgba(196, 69, 105, 0.05));
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
            border: 2px solid rgba(255, 107, 157, 0.2);
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color:rgb(107, 191, 255);
            box-shadow: 0 0 0 0.2rem rgba(255, 107, 157, 0.25);
        }

        .alert {
            border-radius: 15px;
            border: none;
        }

        .lkh-description {
            color: #6c757d;
            font-size: 0.9rem;
            line-height: 1.4;
            margin-bottom: 10px;
        }

        @media (max-width: 576px) {
            .nav-header {
                padding: 15px 10px;
            }
            
            .nav-header .navbar-brand {
                font-size: 1.1rem;
            }
            
            .nav-header .text-end {
                min-width: 120px;
            }
            
            .nav-header .text-end > div {
                font-size: 0.75rem !important;
                line-height: 1.1 !important;
            }
            
            .container-fluid {
                padding-left: 10px !important;
                padding-right: 10px !important;
            }
            
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
            
            .lkh-meta {
                flex-direction: column;
                align-items: flex-start;
            }

            .action-buttons .btn {
                min-width: 100px;
                font-size: 0.85rem;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
                gap: 10px;
            }
            
            .period-card .card-body {
                padding: 12px;
            }
            
            .period-card .card-title {
                font-size: 1rem;
            }
            
            .verification-status {
                padding: 12px;
                margin-bottom: 12px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 8px;
            }
            
            .action-buttons .btn {
                width: 100%;
                margin-bottom: 0;
            }
            
            .lkh-card .card-body {
                padding: 12px;
            }
            
            .lkh-title {
                font-size: 1rem;
            }
            
            .date-badge {
                font-size: 11px;
                padding: 6px 10px;
            }
            
            .day-badge {
                font-size: 10px;
                padding: 3px 6px;
                margin-left: 6px;
            }
            
            .quantity-badge {
                font-size: 11px;
                padding: 5px 10px;
            }
            
            .dropdown {
                position: relative;
            }
            
            .dropdown-menu {
                min-width: 150px;
                font-size: 0.9rem;
            }
            
            .modal-dialog {
                margin: 10px;
            }
            
            .modal-content {
                border-radius: 15px;
            }
            
            .modal-body {
                padding: 15px;
            }
            
            .form-control, .form-select {
                font-size: 0.9rem;
            }
            
            .btn {
                font-size: 0.85rem;
                padding: 8px 12px;
            }
            
            .empty-state {
                padding: 40px 15px;
            }
            
            .empty-state i {
                font-size: 3rem;
            }
            
            .empty-state h5 {
                font-size: 1.1rem;
            }
            
            .empty-state p {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 390px) {
            .nav-header {
                padding: 12px 8px;
            }
            
            .nav-header .navbar-brand {
                font-size: 1rem;
            }
            
            .nav-header .text-end {
                min-width: 100px;
            }
            
            .nav-header .text-end > div {
                font-size: 0.7rem !important;
            }
            
            .card {
                margin-bottom: 15px;
            }
            
            .period-card .card-title {
                font-size: 0.95rem;
            }
            
            .lkh-title {
                font-size: 0.95rem;
            }
            
            .lkh-description {
                font-size: 0.8rem;
            }
        }

        /* Enhanced animations */
        .card {
            animation: slideInUp 0.3s ease-out;
        }

        .card:nth-child(even) {
            animation-delay: 0.1s;
        }

        .card:nth-child(odd) {
            animation-delay: 0.05s;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .floating-btn {
            animation: bounceIn 0.6s ease-out;
        }

        @keyframes bounceIn {
            0% {
                opacity: 0;
                transform: scale(0.3);
            }
            50% {
                opacity: 1;
                transform: scale(1.05);
            }
            70% {
                transform: scale(0.9);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        .nav-header {
            animation: slideInDown 0.4s ease-out;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .badge, .date-badge, .quantity-badge, .day-badge {
            animation: fadeInScale 0.4s ease-out;
        }

        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .btn {
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn:active::before {
            width: 200px;
            height: 200px;
        }

        .dropdown-menu {
            animation: slideInDown 0.2s ease-out;
        }

        .modal {
            animation: fadeIn 0.3s ease-out;
        }

        .modal-content {
            animation: slideInUp 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Improved touch interactions */
        .card, .btn, .floating-btn {
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
        }

        .card:active {
            transform: translateY(-1px) scale(0.98);
        }

        .btn:active {
            transform: translateY(1px) scale(0.98);
        }

        .floating-btn:active {
            transform: scale(0.9) translateY(-1px);
        }

        /* Additional responsive improvements */
        @media (max-width: 480px) {
            .modal-dialog {
                margin: 5px;
                width: calc(100% - 10px);
            }
            
            .dropdown-menu {
                position: absolute !important;
                top: 100% !important;
                left: auto !important;
                right: 0 !important;
                transform: none !important;
                margin: 0 !important;
                width: auto !important;
                min-width: 160px !important;
                max-width: 200px !important;
                z-index: 1000 !important;
                border-radius: 8px !important;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
                border: 1px solid rgba(0,0,0,0.1) !important;
            }
            
            .dropdown-item {
                padding: 10px 12px !important;
                font-size: 0.85rem !important;
                white-space: nowrap !important;
                display: flex !important;
                align-items: center !important;
                transition: all 0.2s ease !important;
            }
            
            .dropdown-item:hover {
                background-color: #f8f9fa !important;
                color: #495057 !important;
            }
            
            .dropdown-item i {
                width: 16px !important;
                margin-right: 8px !important;
                font-size: 0.8rem !important;
            }
            
            .dropdown-toggle::after {
                display: none !important;
            }
            
            .dropdown {
                position: relative !important;
            }
            
            .btn-light {
                background-color: #f8f9fa !important;
                border: 1px solid #dee2e6 !important;
                color: #6c757d !important;
            }
            
            .btn-light:hover {
                background-color: #e9ecef !important;
                border-color: #dee2e6 !important;
                color: #495057 !important;
            }
            
            .verification-status {
                flex-direction: column;
                gap: 10px;
            }
            
            .verification-status .d-flex {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 8px;
            }
            
            .verification-status i {
                font-size: 1.2rem !important;
            }
        }

        /* Loading state animations */
        .loading-state {
            opacity: 0.6;
            pointer-events: none;
            position: relative;
        }

        .loading-state::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--accent-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        /* Enhanced search and filter animations */
        .search-container {
            position: relative;
            margin-bottom: 15px;
        }

        .search-container .form-control {
            padding-left: 40px;
        }

        .search-container .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 5;
        }

        .previous-lkh-item {
            transition: all 0.3s ease;
        }

        .previous-lkh-item:hover {
            transform: translateX(5px);
            background-color: rgba(102, 126, 234, 0.05);
        }

        /* Enhanced scrollbar for mobile */
        .modal-body {
            scrollbar-width: thin;
            scrollbar-color: rgba(102, 126, 234, 0.3) transparent;
        }

        .modal-body::-webkit-scrollbar {
            width: 4px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: transparent;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: rgba(102, 126, 234, 0.3);
            border-radius: 2px;
        }

        .modal-body::-webkit-scrollbar-thumb:hover {
            background: rgba(102, 126, 234, 0.5);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php renderMobileHeader('LKH', 'Laporan Kinerja Harian', 'dashboard.php', $userData, $activePeriod); ?>

    <div class="container-fluid px-3">
        <!-- Stats Overview -->
        <!-- <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= count($lkhs) ?></div>
                <div class="stat-label">Total LKH</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $filter_month ?></div>
                <div class="stat-label">Bulan Aktif</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($rkb_list) ?></div>
                <div class="stat-label">RKB Tersedia</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count(array_unique(array_column($lkhs, 'tanggal_lkh'))) ?></div>
                <div class="stat-label">Hari Kerja</div>
            </div>
        </div> -->

        <!-- Period Info -->
        <div class="card period-card">
            <div class="card-body">
                <h6 class="card-title mb-3">
                    <i class="fas fa-calendar-alt text-primary me-2"></i>
                    Periode: <?= $months[$filter_month] . ' ' . $filter_year ?>
                </h6>
                
                <!-- Status Alert -->
                <?php if ($status_verval_lkh == 'diajukan'): ?>
                    <div class="verification-status">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-clock text-info me-2 fs-5"></i>
                            <div>
                                <div class="fw-semibold text-info">Status: Menunggu Verifikasi</div>
                                <small class="text-muted">LKH periode ini sudah diajukan dan menunggu verifikasi Pejabat Penilai.</small>
                            </div>
                        </div>
                    </div>
                <?php elseif ($status_verval_lkh == 'disetujui'): ?>
                    <div class="verification-status" style="border-left-color: #28a745;">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle text-success me-2 fs-5"></i>
                            <div>
                                <div class="fw-semibold text-success">Status: Disetujui</div>
                                <small class="text-muted">LKH periode ini sudah diverifikasi/validasi oleh Pejabat Penilai.</small>
                            </div>
                        </div>
                    </div>
                <?php elseif ($status_verval_lkh == 'ditolak'): ?>
                    <div class="verification-status" style="border-left-color: #dc3545;">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-times-circle text-danger me-2 fs-5"></i>
                            <div>
                                <div class="fw-semibold text-danger">Status: Ditolak</div>
                                <small class="text-muted">LKH periode ini ditolak oleh Pejabat Penilai. Silakan perbaiki dan ajukan ulang.</small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="action-buttons">
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
                
                <?php if ($periode_rkb_belum_diatur): ?>
                    <div class="alert alert-warning mt-3 mb-0">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle me-2 fs-5"></i>
                            <div>
                                <div class="fw-semibold">Perhatian: Belum ada RKB</div>
                                <small>Silakan <a href="rkb.php" class="alert-link fw-semibold">buat RKB terlebih dahulu</a> sebelum membuat LKH.</small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- LKH List -->
        <?php if (empty($lkhs)): ?>
            <div class="card">
                <div class="card-body empty-state">
                    <i class="fas fa-list"></i>
                    <h5 class="text-muted mb-3">Belum ada LKH bulan ini</h5>
                    <p class="text-muted mb-4">Mulai tambahkan kegiatan harian untuk melaporkan kinerja Anda</p>
                    <?php if (!$periode_rkb_belum_diatur): ?>
                        <button class="btn btn-primary-large" onclick="showAddModal()">
                            <i class="fas fa-plus me-2"></i>Buat LKH Pertama
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($lkhs as $index => $lkh): ?>
                <div class="card lkh-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="date-badge">
                                        <i class="fas fa-calendar-day me-1"></i>
                                        <?= date('d M', strtotime($lkh['tanggal_lkh'])) ?>
                                    </span>
                                    <span class="day-badge">
                                        <?php
                                        $hariIndonesia = [
                                            'Sunday' => 'Minggu',
                                            'Monday' => 'Senin', 
                                            'Tuesday' => 'Selasa',
                                            'Wednesday' => 'Rabu',
                                            'Thursday' => 'Kamis',
                                            'Friday' => 'Jumat',
                                            'Saturday' => 'Sabtu'
                                        ];
                                        echo $hariIndonesia[date('l', strtotime($lkh['tanggal_lkh']))];
                                        ?>
                                    </span>
                                </div>
                                <h6 class="lkh-title"><?= htmlspecialchars($lkh['nama_kegiatan_harian']) ?></h6>
                                <p class="lkh-description">
                                    <?= htmlspecialchars($lkh['uraian_kegiatan_lkh']) ?>
                                </p>
                                <div class="lkh-meta">
                                    <span class="quantity-badge">
                                        <i class="fas fa-chart-bar me-1"></i>
                                        <?= htmlspecialchars($lkh['jumlah_realisasi']) ?> <?= htmlspecialchars($lkh['satuan_realisasi']) ?>
                                    </span>
                                    <?php if ($lkh['lampiran']): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewAttachment('<?= htmlspecialchars($lkh['lampiran']) ?>', '<?= htmlspecialchars($lkh['nama_kegiatan_harian']) ?>')">
                                            <i class="fas fa-eye me-1"></i>Lihat
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeAttachment(<?= $lkh['id_lkh'] ?>)"
                                            <?= ($status_verval_lkh == 'diajukan' || $status_verval_lkh == 'disetujui') ? 'disabled' : '' ?>>
                                            <i class="fas fa-trash me-1"></i>Hapus
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-success" onclick="addAttachment(<?= $lkh['id_lkh'] ?>)"
                                            <?= ($status_verval_lkh == 'diajukan' || $status_verval_lkh == 'disetujui') ? 'disabled' : '' ?>>
                                            <i class="fas fa-plus me-1"></i>Tambah
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light rounded-circle d-flex align-items-center justify-content-center" 
                                        data-bs-toggle="dropdown" 
                                        aria-expanded="false"
                                        style="width: 32px; height: 32px; border: 1px solid #dee2e6;">
                                    <i class="fas fa-ellipsis-v text-muted"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li <?= ($status_verval_lkh == 'diajukan' || $status_verval_lkh == 'disetujui') ? 'style="display:none;"' : '' ?>>
                                        <a class="dropdown-item" href="#" onclick="editLkh(<?= $lkh['id_lkh'] ?>, '<?= htmlspecialchars($lkh['tanggal_lkh']) ?>', '<?= $lkh['id_rkb'] ?>', '<?= htmlspecialchars($lkh['nama_kegiatan_harian']) ?>', '<?= htmlspecialchars($lkh['uraian_kegiatan_lkh']) ?>', '<?= htmlspecialchars($lkh['jumlah_realisasi']) ?>', '<?= htmlspecialchars($lkh['satuan_realisasi']) ?>')">
                                            <i class="fas fa-edit text-warning"></i>
                                            <span>Edit</span>
                                        </a>
                                    </li>
                                    <?php if ($lkh['lampiran']): ?>
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="viewAttachment('<?= htmlspecialchars($lkh['lampiran']) ?>', '<?= htmlspecialchars($lkh['nama_kegiatan_harian']) ?>')">
                                            <i class="fas fa-eye text-info"></i>
                                            <span>Lihat Lampiran</span>
                                        </a>
                                    </li>
                                    <li <?= ($status_verval_lkh == 'diajukan' || $status_verval_lkh == 'disetujui') ? 'style="display:none;"' : '' ?>>
                                        <a class="dropdown-item" href="#" onclick="removeAttachment(<?= $lkh['id_lkh'] ?>)">
                                            <i class="fas fa-trash text-danger"></i>
                                            <span>Hapus Lampiran</span>
                                        </a>
                                    </li>
                                    <?php else: ?>
                                    <li <?= ($status_verval_lkh == 'diajukan' || $status_verval_lkh == 'disetujui') ? 'style="display:none;"' : '' ?>>
                                        <a class="dropdown-item" href="#" onclick="addAttachment(<?= $lkh['id_lkh'] ?>)">
                                            <i class="fas fa-plus text-success"></i>
                                            <span>Tambah Lampiran</span>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <li <?= ($status_verval_lkh == 'diajukan' || $status_verval_lkh == 'disetujui') ? 'style="display:none;"' : '' ?>>
                                        <a class="dropdown-item text-danger" href="#" onclick="deleteLkh(<?= $lkh['id_lkh'] ?>)">
                                            <i class="fas fa-trash text-danger"></i>
                                            <span>Hapus</span>
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
        <button class="floating-btn" onclick="showAddModal()" title="Tambah LKH"
            <?= ($status_verval_lkh == 'diajukan' || $status_verval_lkh == 'disetujui' || $periode_rkb_belum_diatur) ? 'style="display:none;"' : '' ?>>
            <i class="fas fa-plus"></i>
        </button>
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
                            <div class="form-text">Format: PDF, JPG, JPEG, PNG. Maksimal 2MB. <span id="currentFileInfo" class="text-info"></span></div>
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
                                        'Sunday' => 'Minggu', 
                                        'Monday' => 'Senin', 
                                        'Tuesday' => 'Selasa', 
                                        'Wednesday' => 'Rabu',
                                        'Thursday' => 'Kamis', 
                                        'Friday' => 'Jumat', 
                                        'Saturday' => 'Sabtu'
                                    ];
                                    
                                    foreach ($lkh_grouped as $date => $lkh_items): 
                                        $hari = $hariList[date('l', strtotime($date))];
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
                            <div class="search-container">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" class="form-control" id="searchPreviousLkh" placeholder="Cari LKH terdahulu...">
                            </div>
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
                                            </div>                            <button type="button" class="btn btn-sm btn-success" 
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

    <!-- Bottom Navigation -->
    <?php include __DIR__ . '/components/bottom-nav.php'; ?>

    <!-- Attachment Viewer Modal -->
    <div class="modal fade" id="attachmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-paperclip me-2"></i>
                        <span id="attachmentTitle">Lampiran LKH</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="attachmentContent">
                        <!-- Content will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <a href="#" id="downloadAttachment" class="btn btn-primary" target="_blank">
                        <i class="fas fa-download me-1"></i>Download
                   
                    </a>
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

    <!-- Add Attachment Modal -->
    <div class="modal fade" id="addAttachmentModal" tabindex="-1">
        <div class="modal-dialog">
            <form id="addAttachmentForm" method="POST" enctype="multipart/form-data">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-plus me-2"></i>Tambah Lampiran
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_attachment">
                        <input type="hidden" name="id_lkh" id="attachmentLkhId">
                        
                        <div class="mb-3">
                            <label class="form-label">Pilih File Lampiran</label>
                            <input type="file" class="form-control" name="lampiran" id="attachmentFile" 
                                   accept=".pdf,.jpg,.jpeg,.png,image/*,application/pdf" 
                                   capture="environment" required>
                            <div class="form-text">Format: PDF, JPG, JPEG, PNG. Maksimal 2MB.</div>
                            <div id="attachmentFilePreview" class="mt-2" style="display: none;">
                                <small class="text-success">
                                    <i class="fas fa-check-circle me-1"></i>
                                    File dipilih: <span id="attachmentFileName"></span>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="addAttachmentBtn">
                            <i class="fas fa-upload me-1"></i>Upload Lampiran
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Remove Attachment Form -->
    <form id="removeAttachmentForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="remove_attachment">
        <input type="hidden" name="id_lkh" id="removeAttachmentId">
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
            resetForm();
            document.getElementById('lkhModalTitle').textContent = 'Tambah LKH';
            document.getElementById('lkhAction').value = 'add';
            document.getElementById('lkhId').value = '';
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
                'Kegiatan': '1', 'JP': '2', 'Dokumen': '3', 'Laporan': '4'
            };
            document.getElementById('satuanRealisasi').value = satuanMap[satuan] || '';
            
            // Hide lampiran div for editing (same as web version)
            const lampiranDiv = document.getElementById('lampiranDiv');
            if (lampiranDiv) {
                lampiranDiv.style.display = 'none';
            }
            
            document.getElementById('submitBtn').textContent = 'Perbarui';
            new bootstrap.Modal(document.getElementById('lkhModal')).show();
        }

        function showPreviewModal() {
            new bootstrap.Modal(document.getElementById('previewModal')).show();
        }

        function confirmSubmitVerval() {
            Swal.fire({
                title: 'Ajukan Verval LKH',
                text: "Anda yakin ingin mengajukan verval LKH untuk periode ini?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Ajukan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('vervalForm').submit();
                }
            });
        }

        function confirmCancelVerval() {
            Swal.fire({
                title: 'Batal Ajukan Verval',
                text: "Anda yakin ingin membatalkan pengajuan verval LKH untuk periode ini?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Batalkan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('cancelVervalForm').submit();
                }
            });
        }

        function deleteLkh(id) {
            document.getElementById('deleteId').value = id;
            Swal.fire({
                title: 'Hapus LKH',
                text: "Anda yakin ingin menghapus LKH ini?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteForm').submit();
                }
            });
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
            
            // Hide previous LKH modal
            var previousLkhModal = bootstrap.Modal.getInstance(document.getElementById('previousLkhModal'));
            if (previousLkhModal) {
                previousLkhModal.hide();
            }
            
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'LKH Terpilih!',
                text: 'Data LKH terdahulu berhasil disalin ke form.',
                timer: 1500,
                showConfirmButton: false
            });
        }

        // Search functionality for previous LKH modal
        document.getElementById('searchPreviousLkh').addEventListener('input', function() {
            var query = this.value.toLowerCase();
            var items = document.querySelectorAll('.previous-lkh-item');
            var noDataDiv = document.getElementById('noDataPrevious');
            var hasVisibleItem = false;
            
            items.forEach(function(item) {
                var nama = item.getAttribute('data-nama').toLowerCase();
                var uraian = item.getAttribute('data-uraian').toLowerCase();
                
                if (nama.includes(query) || uraian.includes(query)) {
                    item.style.display = 'block';
                    hasVisibleItem = true;
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Show/hide no data message
            if (hasVisibleItem) {
                noDataDiv.classList.add('d-none');
            } else {
                noDataDiv.classList.remove('d-none');
            }
        });

        // Add attachment function
        function addAttachment(lkhId) {
            document.getElementById('attachmentLkhId').value = lkhId;
            document.getElementById('addAttachmentForm').reset();
            document.getElementById('attachmentFilePreview').style.display = 'none';
            new bootstrap.Modal(document.getElementById('addAttachmentModal')).show();
        }

        // Remove attachment function
        function removeAttachment(lkhId) {
            Swal.fire({
                title: 'Hapus Lampiran',
                text: "Anda yakin ingin menghapus lampiran ini?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('removeAttachmentId').value = lkhId;
                    document.getElementById('removeAttachmentForm').submit();
                }
            });
        }

        // View attachment function - Fixed
        function viewAttachment(fileName, title) {
            const attachmentPath = '../uploads/lkh/' + fileName;
            const fileExtension = fileName.split('.').pop().toLowerCase();
            
            // Set modal title
            document.getElementById('attachmentTitle').textContent = 'Lampiran: ' + title;
            
            // Set download link with proper onclick event
            const downloadBtn = document.getElementById('downloadAttachment');
            downloadBtn.href = attachmentPath;
            downloadBtn.onclick = function(e) {
                e.preventDefault();
                downloadFile(attachmentPath, fileName);
            };
            
            // Get attachment content container
            const contentDiv = document.getElementById('attachmentContent');
            
            // Show loading state
            contentDiv.innerHTML = `
                <div class="d-flex justify-content-center align-items-center" style="height: 200px;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <span class="ms-2">Memuat lampiran...</span>
                </div>
            `;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('attachmentModal'));
            modal.show();
            
            // Load content based on file type
            setTimeout(() => {
                if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                    // Image file
                    contentDiv.innerHTML = `
                        <div class="text-center">
                            <img src="${attachmentPath}" class="img-fluid rounded shadow" 
                                 style="max-height: 500px; max-width: 100%;" 
                                 alt="Lampiran ${title}"
                                 onload="console.log('Image loaded successfully')"
                                 onerror="this.parentElement.innerHTML='<div class=\\"alert alert-danger\\"><i class=\\"fas fa-exclamation-triangle me-2\\"></i>Gagal memuat gambar. File mungkin tidak ditemukan atau rusak.<br><small class=\\"text-muted\\">Path: ${fileName}</small></div>'">
                        </div>
                    `;
                } else if (fileExtension === 'pdf') {
                    // PDF file
                    contentDiv.innerHTML = `
                        <div class="text-center">
                            <div class="mb-3">
                                <i class="fas fa-file-pdf text-danger" style="font-size: 4rem;"></i>
                            </div>
                            <h5>Dokumen PDF</h5>
                            <p class="text-muted">File: ${fileName}</p>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                                <button onclick="window.open('${attachmentPath}', '_blank')" class="btn btn-primary">
                                    <i class="fas fa-external-link-alt me-1"></i>Buka di Tab Baru
                                </button>
                                <button onclick="downloadFile('${attachmentPath}', '${fileName}')" class="btn btn-outline-primary">
                                    <i class="fas fa-download me-1"></i>Download
                                </button>
                            </div>
                        </div>
                    `;
                } else {
                    // Other file types
                    contentDiv.innerHTML = `
                        <div class="text-center">
                            <div class="mb-3">
                                <i class="fas fa-file text-secondary" style="font-size: 4rem;"></i>
                            </div>
                            <h5>File Lampiran</h5>
                            <p class="text-muted">File: ${fileName}</p>
                            <p class="text-muted">Tipe: ${fileExtension.toUpperCase()}</p>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                                <button onclick="window.open('${attachmentPath}', '_blank')" class="btn btn-primary">
                                    <i class="fas fa-external-link-alt me-1"></i>Buka File
                                </button>
                                <button onclick="downloadFile('${attachmentPath}', '${fileName}')" class="btn btn-outline-primary">
                                    <i class="fas fa-download me-1"></i>Download
                                </button>
                            </div>
                        </div>
                    `;
                }
            }, 500);
        }

        // Download file function
        function downloadFile(filePath, fileName) {
            const tempLink = document.createElement('a');
            tempLink.href = filePath;
            tempLink.download = fileName;
            tempLink.style.display = 'none';
            document.body.appendChild(tempLink);
            tempLink.click();
            document.body.removeChild(tempLink);
            
            Swal.fire({
                icon: 'success',
                title: 'Download Dimulai',
                text: 'File sedang didownload...',
                timer: 2000,
                showConfirmButton: false
            });
        }

        // Enhanced form interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading state to buttons on form submission
            const forms = document.querySelectorAll('form');
            forms.forEach(function(form) {
                form.addEventListener('submit', function() {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.classList.add('loading-state');
                        submitBtn.disabled = true;
                    }
                });
            });

            // Add attachment form validation
            const attachmentFile = document.getElementById('attachmentFile');
            if (attachmentFile) {
                attachmentFile.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    const preview = document.getElementById('attachmentFilePreview');
                    const fileName = document.getElementById('attachmentFileName');
                    
                    if (file) {
                        fileName.textContent = file.name;
                        preview.style.display = 'block';
                        
                        // File size check
                        if (file.size > 2 * 1024 * 1024) { // 2MB
                            Swal.fire({
                                icon: 'error',
                                title: 'File Terlalu Besar',
                                text: 'Ukuran file maksimal 2MB. Silakan pilih file yang lebih kecil.',
                                confirmButtonText: 'OK'
                            });
                            this.value = '';
                            preview.style.display = 'none';
                            return;
                        }
                        
                        // File type check
                        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
                        if (!allowedTypes.includes(file.type)) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Format File Tidak Didukung',
                                text: 'Format file yang diperbolehkan: PDF, JPG, JPEG, PNG.',
                                confirmButtonText: 'OK'
                            });
                            this.value = '';
                            preview.style.display = 'none';
                            return;
                        }
                        
                        preview.style.animation = 'fadeInScale 0.3s ease-out';
                    } else {
                        preview.style.display = 'none';
                    }
                });
            }

            // Add attachment form submission
            const addAttachmentForm = document.getElementById('addAttachmentForm');
            if (addAttachmentForm) {
                addAttachmentForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const fileInput = document.getElementById('attachmentFile');
                    if (!fileInput || !fileInput.files.length) {
                        Swal.fire({
                            icon: 'error',
                            title: 'File Tidak Dipilih',
                            text: 'Silakan pilih file lampiran terlebih dahulu.',
                            confirmButtonText: 'OK'
                        });
                        return;
                    }
                    
                    const submitBtn = document.getElementById('addAttachmentBtn');
                    const originalText = submitBtn.innerHTML;
                    
                    // Show loading state
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Uploading...';
                    submitBtn.disabled = true;
                    
                    // Submit form after short delay
                    setTimeout(() => {
                        this.submit();
                    }, 500);
                });
            }

            // Add ripple effect to cards
            const cards = document.querySelectorAll('.card');
            cards.forEach(function(card) {
                card.addEventListener('click', function(e) {
                    if (!e.target.closest('.btn') && !e.target.closest('.dropdown')) {
                        const ripple = document.createElement('span');
                        const rect = card.getBoundingClientRect();
                        const size = Math.max(rect.width, rect.height);
                        const x = e.clientX - rect.left - size / 2;
                        const y = e.clientY - rect.top - size / 2;
                        
                        ripple.style.width = ripple.style.height = size + 'px';
                        ripple.style.left = x + 'px';
                        ripple.style.top = y + 'px';
                        ripple.classList.add('ripple-effect');
                        
                        card.appendChild(ripple);
                        
                        setTimeout(() => {
                            ripple.remove();
                        }, 600);
                    }
                });
            });

            // Smooth scroll for better UX
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 300);
                }, 5000);
            });

            // Enhanced dropdown behavior for mobile - Fixed positioning
            const dropdowns = document.querySelectorAll('.dropdown');
            dropdowns.forEach(function(dropdown) {
                const toggle = dropdown.querySelector('.dropdown-toggle');
                const menu = dropdown.querySelector('.dropdown-menu');
                
                if (toggle && menu) {
                    toggle.addEventListener('click', function(e) {
                        e.stopPropagation();
                        
                        // Close other dropdowns
                        document.querySelectorAll('.dropdown-menu.show').forEach(function(otherMenu) {
                            if (otherMenu !== menu) {
                                otherMenu.classList.remove('show');
                            }
                        });
                        
                        // Position menu correctly
                        if (window.innerWidth <= 480) {
                            const rect = toggle.getBoundingClientRect();
                            const menuHeight = menu.offsetHeight;
                            const windowHeight = window.innerHeight;
                            
                            // Check if menu would overflow bottom
                            if (rect.bottom + menuHeight > windowHeight) {
                                menu.style.top = 'auto';
                                menu.style.bottom = '100%';
                            } else {
                                menu.style.top = '100%';
                                menu.style.bottom = 'auto';
                            }
                            
                            menu.style.right = '0';
                            menu.style.left = 'auto';
                        }
                    });
                }
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
                        menu.classList.remove('show');
                    });
                }
            });

            // File preview functionality for main LKH form
            const lampiranInput = document.getElementById('lampiranInput');
            if (lampiranInput) {
                lampiranInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    const preview = document.getElementById('filePreview');
                    const fileName = document.getElementById('fileName');
                    
                    if (file) {
                        fileName.textContent = file.name;
                        preview.style.display = 'block';
                        
                        // File size check
                        if (file.size > 2 * 1024 * 1024) { // 2MB
                            Swal.fire({
                                icon: 'error',
                                title: 'File Terlalu Besar',
                                text: 'Ukuran file maksimal 2MB. Silakan pilih file yang lebih kecil.',
                                confirmButtonText: 'OK'
                            });
                            this.value = '';
                            preview.style.display = 'none';
                            return;
                        }
                        
                        // File type check
                        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
                        if (!allowedTypes.includes(file.type)) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Format File Tidak Didukung',
                                text: 'Format file yang diperbolehkan: PDF, JPG, JPEG, PNG.',
                                confirmButtonText: 'OK'
                            });
                            this.value = '';
                            preview.style.display = 'none';
                            return;
                        }
                        
                        // Show preview with success animation
                        preview.style.animation = 'fadeInScale 0.3s ease-out';
                    } else {
                        preview.style.display = 'none';
                    }
                });
            }
        });

        // Add CSS for ripple effect
        const style = document.createElement('style');
        style.textContent = `
            .ripple-effect {
                position: absolute;
                border-radius: 50%;
                background-color: rgba(102, 126, 234, 0.1);
                transform: scale(0);
                animation: ripple 0.6s linear;
                pointer-events: none;
            }

            @keyframes ripple {
                to {
                    transform: scale(2);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // File preview functionality
        // ...existing code...

        // Form validation enhancements
        function validateForm() {
            const tanggal = document.getElementById('tanggalLkh').value;
            const rkb = document.getElementById('rkbSelect').value;
            const namaKegiatan = document.getElementById('namaKegiatan').value.trim();
            const uraianKegiatan = document.getElementById('uraianKegiatan').value.trim();
            const jumlahRealisasi = document.getElementById('jumlahRealisasi').value.trim();
            const satuanRealisasi = document.getElementById('satuanRealisasi').value;
            
            if (!tanggal || !rkb || !namaKegiatan || !uraianKegiatan || !jumlahRealisasi || !satuanRealisasi) {
                Swal.fire({
                    icon: 'error',
                    title: 'Data Tidak Lengkap',
                    text: 'Mohon lengkapi semua field yang wajib diisi.',
                    confirmButtonText: 'OK'
                });
                return false;
            }
            
            // Check if jumlah_realisasi is a valid number
            if (isNaN(jumlahRealisasi) || jumlahRealisasi <= 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Jumlah Realisasi Tidak Valid',
                    text: 'Jumlah realisasi harus berupa angka positif.',
                    confirmButtonText: 'OK'
                });
                return false;
            }
            
            return true;
        }

        // Enhanced form submission with loading state
        document.getElementById('lkhForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!validateForm()) {
                return;
            }
            
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Menyimpan...';
            submitBtn.disabled = true;
            
            // Submit form after short delay for better UX
            setTimeout(() => {
                this.submit();
            }, 500);
        });
        
        // Reset form function
        function resetForm() {
            document.getElementById('lkhForm').reset();
            document.getElementById('filePreview').style.display = 'none';
            document.getElementById('lampiranDiv').style.display = 'block';
            document.getElementById('submitBtn').innerHTML = 'Simpan';
            document.getElementById('submitBtn').disabled = false;
            
            // Clear current file info
            const currentFileInfo = document.getElementById('currentFileInfo');
            if (currentFileInfo) {
                currentFileInfo.textContent = '';
            }
        }
    </script>
</body>
</html>