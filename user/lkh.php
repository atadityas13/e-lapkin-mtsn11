<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * Sistem Elektronik Laporan Kinerja Harian
 * MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat
 * 
 * File: Main Entry Point
 * Deskripsi: Halaman utama aplikasi - redirect ke login atau dashboard
 * 
 * @package    E-Lapkin-MTSN11
 * @author     MTsN 11 Majalengka Development Team
 * @copyright  2025 MTsN 11 Majalengka. All rights reserved.
 * @license    Proprietary License
 * @version    1.0.0
 * @since      2025-01-01
 * @created    2025-06-25
 * @modified   2025-06-25
 * 
 * DISCLAIMER:
 * Software ini dikembangkan khusus untuk MTsN 11 Majalengka.
 * Dilarang keras menyalin, memodifikasi, atau mendistribusikan
 * tanpa izin tertulis dari MTsN 11 Majalengka.
 * 
 * CONTACT:
 * Website: https://mtsn11majalengka.sch.id
 * Email: mtsn11majalengka@gmail.com
 * Phone: (0233) 8319182
 * Address: Kp. Sindanghurip Desa Maniis Kec. Cingambul, Majalengka, Jawa Barat
 * 
 * ========================================================
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../template/session_user.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

$id_pegawai_login = $_SESSION['id_pegawai'];
$nama_pegawai_login = $_SESSION['nama'];
$role_pegawai_login = $_SESSION['role'];
$is_admin = ($role_pegawai_login === 'admin');

$success_message = '';
$error_message = '';

$current_date = date('Y-m-d');
$current_month = (int)date('m');
$current_year = (int)date('Y');

// Ambil periode aktif tahun dan bulan dari pegawai (mengikuti RKB)
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

// Ambil status_verval_lkh untuk periode aktif
$status_verval_lkh = '';
$stmt_status = $conn->prepare("SELECT status_verval FROM lkh WHERE id_pegawai = ? AND MONTH(tanggal_lkh) = ? AND YEAR(tanggal_lkh) = ? LIMIT 1");
$stmt_status->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
$stmt_status->execute();
$stmt_status->bind_result($status_verval_lkh);
$stmt_status->fetch();
$stmt_status->close();

// Tambahkan helper SweetAlert
function set_swal($type, $title, $text) {
    $_SESSION['swal'] = [
        'type' => $type,
        'title' => $title,
        'text' => $text
    ];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Tambahkan pengecekan di sini untuk mencegah aksi jika LKH sudah disetujui
    if ($status_verval_lkh == 'disetujui') {
        set_swal('error', 'Tidak Diizinkan', 'LKH periode ini sudah diverifikasi dan tidak dapat diubah.');
        header('Location: lkh.php?month=' . $filter_month . '&year=' . $filter_year);
        exit();
    }

    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == 'add' || $action == 'edit') {
            $tanggal_lkh = trim($_POST['tanggal_lkh']);
            $id_rkb = (int)$_POST['id_rkb'];
            $uraian_kegiatan_lkh = trim($_POST['uraian_kegiatan_lkh']);
            $jumlah_realisasi = trim($_POST['jumlah_realisasi']);
            // Mapping satuan_realisasi angka ke string
            $satuan_map = [
                "1" => "Kegiatan",
                "2" => "JP",
                "3" => "Dokumen",
                "4" => "Laporan",
                "5" => "Hari",
                "6" => "Jam",
                "7" => "Menit",
                "8" => "Unit"
            ];
            $satuan_realisasi = isset($_POST['satuan_realisasi']) && isset($satuan_map[$_POST['satuan_realisasi']])
                ? $satuan_map[$_POST['satuan_realisasi']]
                : '';
            $nama_kegiatan_harian = isset($_POST['nama_kegiatan_harian']) ? trim($_POST['nama_kegiatan_harian']) : '';

            // Handle file upload (only for add action)
            $lampiran = NULL;
            if ($action == 'add' && isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] == UPLOAD_ERR_OK) {
                $file_tmp_name = $_FILES['lampiran']['tmp_name'];
                $file_extension = strtolower(pathinfo($_FILES['lampiran']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    set_swal('error', 'Gagal', 'Format file tidak diizinkan. Hanya PDF, JPG, JPEG, dan PNG yang diperbolehkan.');
                    header('Location: lkh.php?month=' . $filter_month . '&year=' . $filter_year);
                    exit();
                }
                
                // Check file size (max 2MB)
                if ($_FILES['lampiran']['size'] > 2 * 1024 * 1024) {
                    set_swal('error', 'Gagal', 'Ukuran file terlalu besar. Maksimal 2MB.');
                    header('Location: lkh.php?month=' . $filter_month . '&year=' . $filter_year);
                    exit();
                }
                
                $file_name = 'lkh_' . $id_pegawai_login . '_' . date('YmdHis') . '_' . uniqid() . '.' . $file_extension;
                $upload_dir = __DIR__ . '/../uploads/lkh/';
                $file_path = $upload_dir . $file_name;

                // Pastikan direktori upload ada
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                // Pindahkan file yang diunggah
                if (move_uploaded_file($file_tmp_name, $file_path)) {
                    $lampiran = $file_name;
                } else {
                    set_swal('error', 'Gagal', 'Gagal mengunggah lampiran.');
                    header('Location: lkh.php?month=' . $filter_month . '&year=' . $filter_year);
                    exit();
                }
            }

            // Validasi: Nama Kegiatan wajib diisi
            if (empty($tanggal_lkh) || empty($id_rkb) || empty($nama_kegiatan_harian) || empty($uraian_kegiatan_lkh) || empty($jumlah_realisasi) || empty($satuan_realisasi)) {
                set_swal('error', 'Gagal', 'Semua field harus diisi.');
            } else {
                // Validate satuan_realisasi against valid values
                $valid_satuan = ['Kegiatan','JP','Dokumen','Laporan','Hari','Jam','Menit','Unit'];
                if (!in_array($satuan_realisasi, $valid_satuan)) {
                    set_swal('error', 'Gagal', 'Satuan tidak valid. Pilih salah satu: ' . implode(', ', $valid_satuan));
                    header('Location: lkh.php?month=' . date('m', strtotime($tanggal_lkh)) . '&year=' . date('Y', strtotime($tanggal_lkh)));
                    exit();
                }
                
                try {
                    if ($action == 'add') {
                        $stmt = $conn->prepare("INSERT INTO lkh (id_pegawai, id_rkb, tanggal_lkh, uraian_kegiatan_lkh, jumlah_realisasi, satuan_realisasi, nama_kegiatan_harian, lampiran) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("iissssss", $id_pegawai_login, $id_rkb, $tanggal_lkh, $uraian_kegiatan_lkh, $jumlah_realisasi, $satuan_realisasi, $nama_kegiatan_harian, $lampiran);
                    } else { // action == 'edit'
                        $id_lkh = (int)$_POST['id_lkh'];
                        $stmt = $conn->prepare("UPDATE lkh SET id_rkb = ?, tanggal_lkh = ?, uraian_kegiatan_lkh = ?, jumlah_realisasi = ?, satuan_realisasi = ?, nama_kegiatan_harian = ? WHERE id_lkh = ? AND id_pegawai = ?");
                        $stmt->bind_param("isssssii", $id_rkb, $tanggal_lkh, $uraian_kegiatan_lkh, $jumlah_realisasi, $satuan_realisasi, $nama_kegiatan_harian, $id_lkh, $id_pegawai_login);
                    }

                    if ($stmt->execute()) {
                        set_swal('success', ($action == 'add') ? 'Berhasil' : 'Update Berhasil', ($action == 'add') ? "LKH berhasil ditambahkan!" : "LKH berhasil diperbarui!");
                        echo '<script>window.location="lkh.php?month=' . date('m', strtotime($tanggal_lkh)) . '&year=' . date('Y', strtotime($tanggal_lkh)) . '";</script>';
                        exit();
                    } else {
                        if (strpos($stmt->error, 'Data truncated') !== false) {
                            set_swal('error', 'Gagal', 'Data terlalu panjang untuk field satuan. Pilih salah satu opsi yang tersedia.');
                        } else {
                            set_swal('error', 'Gagal', ($action == 'add') ? "Gagal menambahkan LKH: " . $stmt->error : "Gagal memperbarui LKH: " . $stmt->error);
                        }
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'Data truncated') !== false) {
                        set_swal('error', 'Gagal', 'Terjadi kesalahan database: Data satuan tidak sesuai format yang diizinkan.');
                    } else {
                        set_swal('error', 'Gagal', 'Terjadi kesalahan database. Periksa data yang dimasukkan.');
                    }
                    header('Location: lkh.php?month=' . date('m', strtotime($tanggal_lkh)) . '&year=' . date('Y', strtotime($tanggal_lkh)));
                    exit();
                }
            }
        } elseif ($action == 'add_attachment') {
            $id_lkh = (int)$_POST['id_lkh'];
            
            // Handle file upload
            if (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] == UPLOAD_ERR_OK) {
                $file_tmp_name = $_FILES['lampiran']['tmp_name'];
                $file_extension = strtolower(pathinfo($_FILES['lampiran']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    set_swal('error', 'Gagal', 'Format file tidak diizinkan. Hanya PDF, JPG, JPEG, dan PNG yang diperbolehkan.');
                    header('Location: lkh.php?month=' . $filter_month . '&year=' . $filter_year);
                    exit();
                }
                
                // Check file size (max 2MB)
                if ($_FILES['lampiran']['size'] > 2 * 1024 * 1024) {
                    set_swal('error', 'Gagal', 'Ukuran file terlalu besar. Maksimal 2MB.');
                    header('Location: lkh.php?month=' . $filter_month . '&year=' . $filter_year);
                    exit();
                }
                
                $file_name = 'lkh_' . $id_pegawai_login . '_' . date('YmdHis') . '_' . uniqid() . '.' . $file_extension;
                $upload_dir = __DIR__ . '/../uploads/lkh/';
                $file_path = $upload_dir . $file_name;

                // Pastikan direktori upload ada
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                // Pindahkan file yang diunggah
                if (move_uploaded_file($file_tmp_name, $file_path)) {
                    $stmt = $conn->prepare("UPDATE lkh SET lampiran = ? WHERE id_lkh = ? AND id_pegawai = ?");
                    $stmt->bind_param("sii", $file_name, $id_lkh, $id_pegawai_login);
                    
                    if ($stmt->execute()) {
                        set_swal('success', 'Berhasil', 'Lampiran berhasil ditambahkan!');
                    } else {
                        set_swal('error', 'Gagal', 'Gagal menyimpan lampiran ke database.');
                        // Delete uploaded file if database save fails
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                    }
                    $stmt->close();
                } else {
                    set_swal('error', 'Gagal', 'Gagal mengunggah lampiran.');
                }
            } else {
                set_swal('error', 'Gagal', 'Tidak ada file yang dipilih atau terjadi kesalahan upload.');
            }
            
            echo '<script>window.location="lkh.php?month=' . $filter_month . '&year=' . $filter_year . '";</script>';
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
                set_swal('success', 'Berhasil', 'Lampiran berhasil dihapus!');
            } else {
                set_swal('error', 'Gagal', 'Gagal menghapus lampiran.');
            }
            $stmt->close();
            
            echo '<script>window.location="lkh.php?month=' . $filter_month . '&year=' . $filter_year . '";</script>';
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
                // Delete file if exists
                if ($file_to_delete && file_exists(__DIR__ . '/../uploads/lkh/' . $file_to_delete)) {
                    unlink(__DIR__ . '/../uploads/lkh/' . $file_to_delete);
                }
                set_swal('success', 'Berhasil', 'LKH berhasil dihapus!');
            } else {
                set_swal('error', 'Gagal', "Gagal menghapus LKH: " . $stmt->error);
            }
            $stmt->close();
            echo '<script>window.location="lkh.php?month=' . $_POST['month'] . '&year=' . $_POST['year'] . '";</script>';
            exit();
        }
    }
    // Ajukan/batal verval LKH
    if (isset($_POST['ajukan_verval_lkh'])) {
        // Cek kembali status verval sebelum mengajukan
        if ($status_verval_lkh == 'disetujui') {
            set_swal('error', 'Tidak Diizinkan', 'LKH periode ini sudah diverifikasi dan tidak dapat diubah statusnya.');
            header('Location: lkh.php?month=' . $filter_month . '&year=' . $filter_year);
            exit();
        }
        
        // Cek apakah ada data LKH untuk periode ini
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM lkh WHERE id_pegawai = ? AND MONTH(tanggal_lkh) = ? AND YEAR(tanggal_lkh) = ?");
        $stmt_check->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
        $stmt_check->execute();
        $stmt_check->bind_result($count_lkh);
        $stmt_check->fetch();
        $stmt_check->close();
        
        if ($count_lkh == 0) {
            set_swal('error', 'Gagal', 'Tidak dapat mengajukan verval karena belum ada data LKH untuk periode ini. Silakan tambah LKH terlebih dahulu.');
            header('Location: lkh.php?month=' . $filter_month . '&year=' . $filter_year);
            exit();
        }
        
        $stmt = $conn->prepare("UPDATE lkh SET status_verval = 'diajukan' WHERE id_pegawai = ? AND MONTH(tanggal_lkh) = ? AND YEAR(tanggal_lkh) = ?");
        $stmt->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
        if ($stmt->execute()) {
            set_swal('success', 'Berhasil', 'Pengajuan verval LKH berhasil dikirim. Menunggu verifikasi Pejabat Penilai.');
        } else {
            set_swal('error', 'Gagal', 'Gagal mengajukan verval LKH: ' . htmlspecialchars($stmt->error));
        }
        $stmt->close();
        echo '<script>window.location="lkh.php?month=' . $filter_month . '&year=' . $filter_year . '";</script>';
        exit();
    } elseif (isset($_POST['batal_verval_lkh'])) {
        // Cek kembali status verval sebelum membatalkan
        if ($status_verval_lkh == 'disetujui') {
            set_swal('error', 'Tidak Diizinkan', 'LKH periode ini sudah diverifikasi dan tidak dapat diubah statusnya.');
            header('Location: lkh.php?month=' . $filter_month . '&year=' . $filter_year);
            exit();
        }
        $stmt = $conn->prepare("UPDATE lkh SET status_verval = NULL WHERE id_pegawai = ? AND MONTH(tanggal_lkh) = ? AND YEAR(tanggal_lkh) = ?");
        $stmt->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
        if ($stmt->execute()) {
            set_swal('success', 'Dibatalkan', 'Pengajuan verval LKH dibatalkan. Anda dapat mengedit/mengirim ulang.');
        } else {
            set_swal('error', 'Gagal', 'Gagal membatalkan verval LKH: ' . htmlspecialchars($stmt->error));
        }
        $stmt->close();
        echo '<script>window.location="lkh.php?month=' . $filter_month . '&year=' . $filter_year . '";</script>';
        exit();
    }
}

// Pastikan kolom status_verval ada di tabel lkh
function ensure_status_verval_column_lkh($conn) {
    $result = $conn->query("SHOW COLUMNS FROM lkh LIKE 'status_verval'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE lkh ADD COLUMN status_verval ENUM('diajukan','disetujui','ditolak') DEFAULT NULL");
    }
}
ensure_status_verval_column_lkh($conn);

// Pastikan kolom lampiran ada di tabel lkh
function ensure_lampiran_column_lkh($conn) {
    $result = $conn->query("SHOW COLUMNS FROM lkh LIKE 'lampiran'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE lkh ADD COLUMN lampiran VARCHAR(255) DEFAULT NULL");
    }
}
ensure_lampiran_column_lkh($conn);

// Ambil data LKH (tambahkan nama_kegiatan_harian)
$lkhs = [];
$stmt_lkh = $conn->prepare("SELECT lkh.id_lkh, lkh.id_rkb, lkh.tanggal_lkh, lkh.uraian_kegiatan_lkh, lkh.jumlah_realisasi, lkh.satuan_realisasi, lkh.lampiran, lkh.nama_kegiatan_harian, rkb.uraian_kegiatan AS rkb_uraian FROM lkh JOIN rkb ON lkh.id_rkb = rkb.id_rkb WHERE lkh.id_pegawai = ? AND MONTH(lkh.tanggal_lkh) = ? AND YEAR(lkh.tanggal_lkh) = ? ORDER BY lkh.tanggal_lkh ASC");
$stmt_lkh->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
$stmt_lkh->execute();
$result_lkh = $stmt_lkh->get_result();
while ($row = $result_lkh->fetch_assoc()) {
    $lkhs[] = $row;
}
$stmt_lkh->close();

$edit_mode = false;
$edit_lkh = [
    'id_lkh' => '',
    'id_rkb' => '',
    'tanggal_lkh' => $current_date,
    'uraian_kegiatan_lkh' => '',
    'jumlah_realisasi' => '',
    'satuan_realisasi' => '',
    'nama_kegiatan_harian' => '',
    'lampiran' => ''
];
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    // Tambahkan pengecekan status verval di sini juga
    if ($status_verval_lkh == 'disetujui') {
        set_swal('error', 'Tidak Diizinkan', 'LKH periode ini sudah diverifikasi dan tidak dapat diubah.');
        header('Location: lkh.php?month=' . $filter_month . '&year=' . $filter_year);
        exit();
    }

    $id_lkh_edit = (int)$_GET['id'];
    $stmt_get_edit = $conn->prepare("SELECT id_lkh, id_rkb, tanggal_lkh, uraian_kegiatan_lkh, jumlah_realisasi, satuan_realisasi, nama_kegiatan_harian, lampiran FROM lkh WHERE id_lkh = ? AND id_pegawai = ?");
    $stmt_get_edit->bind_param("ii", $id_lkh_edit, $id_pegawai_login);
    $stmt_get_edit->execute();
    $result_get_edit = $stmt_get_edit->get_result();
    if ($result_get_edit->num_rows == 1) {
        $edit_lkh = $result_get_edit->fetch_assoc();
        $edit_mode = true;
    } else {
        $error_message = "LKH tidak ditemukan atau Anda tidak memiliki akses.";
    }
    $stmt_get_edit->close();
}

$rkb_list = [];
$stmt_rkb_list = $conn->prepare("SELECT id_rkb, uraian_kegiatan FROM rkb WHERE id_pegawai = ? AND bulan = ? AND tahun = ? ORDER BY uraian_kegiatan ASC");
$stmt_rkb_list->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
$stmt_rkb_list->execute();
$result_rkb_list = $stmt_rkb_list->get_result();
while ($row = $result_rkb_list->fetch_assoc()) {
    $rkb_list[] = $row;
}
$stmt_rkb_list->close();

// Cek apakah periode RKB belum diatur (tidak ada data RKB untuk periode aktif)
$periode_rkb_belum_diatur = empty($rkb_list);

$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Ambil daftar LKH terdahulu untuk referensi
$previous_lkh_list = [];

// Get unique nama_kegiatan_harian and uraian_kegiatan_lkh with latest data (compatible with ONLY_FULL_GROUP_BY)
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
            MAX(created_at) as max_created_at
        FROM lkh 
        WHERE id_pegawai = ? AND NOT (MONTH(tanggal_lkh) = ? AND YEAR(tanggal_lkh) = ?)
        GROUP BY nama_kegiatan_harian, uraian_kegiatan_lkh
    ) l2 ON l1.nama_kegiatan_harian = l2.nama_kegiatan_harian 
         AND l1.uraian_kegiatan_lkh = l2.uraian_kegiatan_lkh
         AND l1.created_at = l2.max_created_at
    WHERE l1.id_pegawai = ? AND NOT (MONTH(l1.tanggal_lkh) = ? AND YEAR(l1.tanggal_lkh) = ?)
    ORDER BY l1.created_at DESC, l1.nama_kegiatan_harian ASC
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

$page_title = "Manajemen LKH";
include __DIR__ . '/../template/header.php';
include __DIR__ . '/../template/menu_user.php';
include __DIR__ . '/../template/topbar.php';
?>
<div id="layoutSidenav_content">
    <main>
        <div class="container-fluid px-4">
            <h1 class="mt-4">Laporan Kinerja Harian (LKH)</h1>
            <div class="mb-2">
                <span class="fw-semibold">Periode :</span>
                <span class="badge bg-info text-dark"><?= $months[$filter_month] . ' ' . $filter_year ?></span>
            </div>

            <!-- Modal Notifikasi RKB Belum Diatur -->
            <?php if ($periode_rkb_belum_diatur): ?>
            <div class="modal fade" id="modalRkbBelumDiatur" tabindex="-1" aria-labelledby="modalRkbBelumDiaturLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title" id="modalRkbBelumDiaturLabel">
                                <i class="fas fa-exclamation-triangle me-2"></i>RKB Periode Ini Belum Ada
                            </h5>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info mb-3">
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Informasi:</strong> Belum ada data RKB untuk periode <?= $months[$filter_month] . ' ' . $filter_year ?>.
                            </div>
                            <p>LKH (Laporan Kinerja Harian) memerlukan data RKB (Rencana Kinerja Bulanan) terlebih dahulu.</p>
                            <p><strong>Silakan lakukan salah satu dari pilihan berikut:</strong></p>
                            <ul>
                                <li>Buat RKB untuk periode ini terlebih dahulu</li>
                                <li>Atau atur periode bulan yang sudah memiliki data RKB</li>
                            </ul>
                        </div>
                        <div class="modal-footer">
                            <a href="rkb.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>Buat RKB
                            </a>
                            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#modalUbahPeriodeLkh" data-bs-dismiss="modal">
                                <i class="fas fa-calendar-alt me-1"></i>Ubah Periode
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Ubah Periode LKH -->
            <div class="modal fade" id="modalUbahPeriodeLkh" tabindex="-1" aria-labelledby="modalUbahPeriodeLkhLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-info text-white">
                            <h5 class="modal-title" id="modalUbahPeriodeLkhLabel">
                                <i class="fas fa-calendar-alt me-2"></i>Ubah Periode Bulan
                            </h5>
                        </div>
                        <div class="modal-body">
                            <p>Pilih periode bulan yang akan digunakan untuk LKH:</p>
                            <form id="ubahPeriodeLkhForm" action="rkb.php" method="POST">
                                <div class="mb-3">
                                    <label for="bulan_aktif_lkh" class="form-label fw-semibold">Pilih Bulan:</label>
                                    <select class="form-select" id="bulan_aktif_lkh" name="bulan_aktif" required>
                                        <option value="">-- Pilih Bulan --</option>
                                        <?php foreach ($months as $num => $name): ?>
                                            <option value="<?= $num ?>" <?= ($num == $filter_month) ? 'selected' : '' ?>><?= $name ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <input type="hidden" name="set_bulan_aktif" value="1">
                            </form>
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-info-circle me-1"></i>
                                <small><strong>Catatan:</strong> Pastikan periode yang dipilih sudah memiliki data RKB.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="location.reload()">Batal</button>
                            <button type="button" class="btn btn-info" id="btnUbahPeriodeLkh">
                                <i class="fas fa-check me-1"></i>Ubah Periode
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Auto show modal jika RKB belum ada
                    <?php if ($periode_rkb_belum_diatur): ?>
                    var modalRkb = new bootstrap.Modal(document.getElementById('modalRkbBelumDiatur'));
                    modalRkb.show();
                    <?php endif; ?>
                    
                    // Handle ubah periode
                    document.getElementById('btnUbahPeriodeLkh').addEventListener('click', function() {
                        const bulanDipilih = document.getElementById('bulan_aktif_lkh').value;
                        if (!bulanDipilih) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Peringatan',
                                text: 'Silakan pilih bulan terlebih dahulu.',
                                timer: 2000
                            });
                            return;
                        }
                        document.getElementById('ubahPeriodeLkhForm').submit();
                    });
                });
            </script>
            <?php endif; ?>

            <div class="mb-3">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahLkh"
                    <?php echo ($status_verval_lkh == 'diajukan' || $status_verval_lkh == 'disetujui' || $periode_rkb_belum_diatur) ? 'disabled' : ''; ?>>
                    <i class="fas fa-plus me-1"></i> Tambah Laporan Harian
                </button>
                <?php if ($periode_rkb_belum_diatur): ?>
                <div class="form-text text-danger mt-1">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Tidak dapat menambah LKH karena belum ada RKB untuk periode ini.
                </div>
                <?php endif; ?>
            </div>
            <div class="card">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <span>Daftar Laporan Kinerja Harian (LKH) Anda - Bulan <?php echo $months[$filter_month] . ' ' . $filter_year; ?></span>
                    <div class="btn-group">
                        <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalPreviewLkh" <?php echo empty($lkhs) ? 'disabled title="Tidak ada data LKH untuk di-preview"' : ''; ?>>
                            <i class="fas fa-eye me-1"></i> Preview LKH
                        </button>
                        <?php if ($status_verval_lkh == 'diajukan'): ?>
                            <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalBatalVervalLkh">
                                <i class="fas fa-times me-1"></i> Batal Ajukan Verval
                            </button>
                        <?php elseif ($status_verval_lkh == '' || $status_verval_lkh == null || $status_verval_lkh == 'ditolak'): ?>
                            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalAjukanVervalLkh" <?php echo empty($lkhs) ? 'disabled title="Tidak dapat mengajukan verval karena belum ada data LKH"' : ''; ?>>
                                <i class="fas fa-paper-plane me-1"></i> Ajukan Verval LKH
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php
                    if ($status_verval_lkh == 'diajukan') {
                        echo '<div class="alert alert-info mb-3">LKH periode ini sudah diajukan dan menunggu verifikasi Pejabat Penilai.</div>';
                    } elseif ($status_verval_lkh == 'disetujui') {
                        echo '<div class="alert alert-success mb-3">LKH periode ini sudah diverifikasi/validasi oleh Pejabat Penilai.</div>';
                    } elseif ($status_verval_lkh == 'ditolak') {
                        echo '<div class="alert alert-danger mb-3">LKH periode ini ditolak oleh Pejabat Penilai. Silakan perbaiki dan ajukan ulang.</div>';
                    }
                    ?>
                    <?php if (empty($lkhs)): ?>
                        <div class="alert alert-info">Belum ada Laporan Kinerja Harian untuk bulan ini.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Tanggal</th>
                                        <th>RKB Terkait</th>
                                        <th>Nama Kegiatan</th>
                                        <th>Uraian Kegiatan LKH</th>
                                        <th>Realisasi</th>
                                        <th>Lampiran</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1; foreach ($lkhs as $lkh): ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php
                                                // Tampilkan hari dalam bahasa Indonesia
                                                $hariList = [
                                                    'Sun' => 'Minggu',
                                                    'Mon' => 'Senin',
                                                    'Tue' => 'Selasa',
                                                    'Wed' => 'Rabu',
                                                    'Thu' => 'Kamis',
                                                    'Fri' => 'Jumat',
                                                    'Sat' => 'Sabtu'
                                                ];
                                                $tgl = $lkh['tanggal_lkh'];
                                                $hari = $hariList[date('D', strtotime($tgl))];
                                                echo $hari . ', ' . date('d-m-Y', strtotime($tgl));
                                            ?></td>
                                            <td><?php echo htmlspecialchars($lkh['rkb_uraian']); ?></td>
                                            <td><?php echo htmlspecialchars($lkh['nama_kegiatan_harian'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($lkh['uraian_kegiatan_lkh']); ?></td>
                                            <td><?php echo htmlspecialchars($lkh['jumlah_realisasi'] . ' ' . $lkh['satuan_realisasi']); ?></td>
                                            <td>
                                                <?php if (!empty($lkh['lampiran'])): ?>
                                                    <a href="../uploads/lkh/<?php echo htmlspecialchars($lkh['lampiran']); ?>" target="_blank" class="btn btn-sm btn-info mb-1">
                                                        <i class="fas fa-eye me-1"></i>Lihat
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger mb-1" data-bs-toggle="modal" data-bs-target="#modalHapusLampiran<?php echo $lkh['id_lkh']; ?>"
                                                        <?php echo ($status_verval_lkh == 'diajukan' || $status_verval_lkh == 'disetujui') ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-trash me-1"></i>Hapus
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-success mb-1" data-bs-toggle="modal" data-bs-target="#modalTambahLampiran<?php echo $lkh['id_lkh']; ?>"
                                                        <?php echo ($status_verval_lkh == 'diajukan' || $status_verval_lkh == 'disetujui') ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-plus me-1"></i>Tambah
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="lkh.php?action=edit&id=<?php echo $lkh['id_lkh']; ?>&month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>" class="btn btn-sm btn-warning mb-1"
                                                    <?php echo ($status_verval_lkh == 'diajukan' || $status_verval_lkh == 'disetujui') ? 'disabled style="pointer-events:none;opacity:0.6;"' : ''; ?>>Edit</a>
                                                <form action="lkh.php" method="POST" style="display:inline-block;" class="form-hapus-lkh">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id_lkh" value="<?php echo $lkh['id_lkh']; ?>">
                                                    <input type="hidden" name="month" value="<?php echo $filter_month; ?>">
                                                    <input type="hidden" name="year" value="<?php echo $filter_year; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger mb-1" <?php echo ($status_verval_lkh == 'diajukan' || $status_verval_lkh == 'disetujui') ? 'disabled' : ''; ?>>Hapus</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    <?php include __DIR__ . '/../template/footer.php'; ?>
</div>

      <div class="modal fade" id="modalAjukanVervalLkh" tabindex="-1" aria-labelledby="modalAjukanVervalLkhLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <form method="post" class="modal-content">
            <div class="modal-header bg-success text-white">
              <h5 class="modal-title" id="modalAjukanVervalLkhLabel"><i class="fas fa-paper-plane me-2"></i>Ajukan Verval LKH</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
              <div class="mb-2">
                <strong>Ajukan Verval LKH?</strong>
              </div>
              <div class="mb-3">
                LKH akan bisa digenerate setelah di verval oleh Pejabat Penilai.
              </div>
              <?php if (empty($lkhs)): ?>
              <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-1"></i>
                <strong>Peringatan:</strong> Belum ada data LKH untuk periode ini. Silakan tambah LKH terlebih dahulu.
              </div>
              <?php endif; ?>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" name="ajukan_verval_lkh" class="btn btn-success" <?php echo empty($lkhs) ? 'disabled' : ''; ?>>Ajukan</button>
            </div>
          </form>
        </div>
      </div>

      <div class="modal fade" id="modalBatalVervalLkh" tabindex="-1" aria-labelledby="modalBatalVervalLkhLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <form method="post" class="modal-content">
            <div class="modal-header bg-warning text-dark">
              <h5 class="modal-title" id="modalBatalVervalLkhLabel"><i class="fas fa-times me-2"></i>Batal Ajukan Verval LKH</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
              <div class="mb-2">
                <strong>Batalkan pengajuan verval LKH?</strong>
              </div>
              <div>
                Anda dapat mengedit/menghapus/mengirim ulang LKH setelah membatalkan pengajuan verval.
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" name="batal_verval_lkh" class="btn btn-warning">Ya, Batalkan</button>
            </div>
          </form>
        </div>
      </div>

      <div class="modal fade" id="modalTambahLkh" tabindex="-1" aria-labelledby="modalTambahLkhLabel" aria-hidden="true">
        <div class="modal-dialog">
          <form action="lkh.php" method="POST" class="modal-content" enctype="multipart/form-data">
            <div class="modal-header bg-primary text-white">
              <h5 class="modal-title" id="modalTambahLkhLabel"><i class="fas fa-plus me-2"></i>Tambah LKH Baru</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="action" value="add">
              <div class="mb-3">
                <label for="tanggal_lkh_modal" class="form-label">Tanggal Kegiatan</label>
                <input type="date" class="form-control" id="tanggal_lkh_modal" name="tanggal_lkh" value="<?php echo htmlspecialchars($current_date); ?>" required>
              </div>
              <div class="mb-3">
                <label for="id_rkb_modal" class="form-label">Pilih RKB Terkait (Bulan & Tahun Saat Ini)</label>
                <select class="form-select" id="id_rkb_modal" name="id_rkb" required>
                  <option value="">-- Pilih RKB --</option>
                  <?php foreach ($rkb_list as $rkb): ?>
                    <option value="<?php echo htmlspecialchars($rkb['id_rkb']); ?>">
                      <?php echo htmlspecialchars($rkb['uraian_kegiatan']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if (empty($rkb_list)): ?>
                  <div class="form-text text-danger">Anda belum memiliki RKB untuk bulan <?php echo $months[$filter_month] . ' ' . $filter_year; ?>. Silakan <a href="rkb.php">tambah RKB terlebih dahulu</a>.</div>
                <?php endif; ?>
              </div>
              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <label for="nama_kegiatan_harian_modal" class="form-label mb-0">Nama Kegiatan Harian</label>
                  <?php if (!empty($previous_lkh_list)): ?>
                    <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalLkhTerdahulu">
                      <i class="fas fa-history me-1"></i>Lihat LKH Terdahulu
                    </button>
                  <?php endif; ?>
                </div>
                <input type="text" class="form-control" id="nama_kegiatan_harian_modal" name="nama_kegiatan_harian" placeholder="Nama kegiatan harian" required>
              </div>
              <div class="mb-3">
                <label for="uraian_kegiatan_lkh_modal" class="form-label">Uraian Kegiatan LKH</label>
                <textarea class="form-control" id="uraian_kegiatan_lkh_modal" name="uraian_kegiatan_lkh" rows="3" required></textarea>
                <div class="form-text">Jelaskan uraian kegiatan</div>
              </div>
              <div class="mb-3">
                <label for="jumlah_realisasi_modal" class="form-label">Jumlah Realisasi</label>
                <input type="text" class="form-control" id="jumlah_realisasi_modal" name="jumlah_realisasi" placeholder="Contoh: 1, 3" required>
              </div>
              <div class="mb-3">
                <label for="satuan_realisasi_modal" class="form-label">Satuan Realisasi</label>
                <select class="form-select" id="satuan_realisasi_modal" name="satuan_realisasi" required>
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
              <div class="mb-3">
                <label for="lampiran_modal" class="form-label">Lampiran Dokumen (opsional, PDF/JPG/PNG)</label>
                <input type="file" class="form-control" id="lampiran_modal" name="lampiran" accept=".pdf,.jpg,.jpeg,.png">
                <div class="form-text">Ukuran maksimal 2MB.</div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-primary" <?php echo empty($rkb_list) || ($status_verval_lkh == 'diajukan' || $status_verval_lkh == 'disetujui') ? 'disabled' : ''; ?>>Tambah LKH</button>
            </div>
          </form>
        </div>
      </div>

      <?php if ($edit_mode): ?>
      <div class="card mb-4">
        <div class="card-header bg-warning text-dark">
          Edit LKH
        </div>
        <div class="card-body">
          <form action="lkh.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id_lkh" value="<?php echo htmlspecialchars($edit_lkh['id_lkh']); ?>">
            <div class="mb-3">
              <label for="tanggal_lkh" class="form-label">Tanggal Kegiatan</label>
              <input type="date" class="form-control" id="tanggal_lkh" name="tanggal_lkh" value="<?php echo htmlspecialchars($edit_lkh['tanggal_lkh']); ?>" required
                <?php echo ($status_verval_lkh == 'diajukan' || $status_verval_lkh == 'disetujui') ? 'disabled' : ''; ?>>
            </div>
            <div class="mb-3">
              <label for="id_rkb" class="form-label">Pilih RKB Terkait</label>
              <select class="form-select" id="id_rkb" name="id_rkb" required
                <?php echo ($status_verval_lkh == 'diajukan' || $status_verval_lkh == 'disetujui') ? 'disabled' : ''; ?>>
                <option value="">-- Pilih RKB --</option>
                <?php foreach ($rkb_list as $rkb): ?>
                  <option value="<?php echo htmlspecialchars($rkb['id_rkb']); ?>" <?php echo ($edit_lkh['id_rkb'] == $rkb['id_rkb']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($rkb['uraian_kegiatan']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (empty($rkb_list)): ?>
                <div class="form-text text-danger">Anda belum memiliki RKB untuk bulan <?php echo $months[$filter_month] . ' ' . $filter_year; ?>. Silakan <a href="rkb.php">tambah RKB terlebih dahulu</a>.</div>
              <?php endif; ?>
            </div>
            <div class="mb-3">
              <label for="nama_kegiatan_harian" class="form-label">Nama Kegiatan Harian</label>
              <input type="text" class="form-control" id="nama_kegiatan_harian" name="nama_kegiatan_harian" value="<?php echo htmlspecialchars($edit_lkh['nama_kegiatan_harian']); ?>" placeholder="Nama kegiatan harian" required
                <?php echo ($status_verval_lkh == 'diajukan' || $status_verval_lkh == 'disetujui') ? 'disabled' : ''; ?>>
            </div>
            <div class="mb-3">
              <label for="uraian_kegiatan_lkh" class="form-label">Uraian Kegiatan LKH</label>
              <textarea class="form-control" id="uraian_kegiatan_lkh" name="uraian_kegiatan_lkh" rows="3" required
                <?php echo ($status_verval_lkh == 'diajukan' || $status_verval_lkh == 'disetujui') ? 'disabled' : ''; ?>><?php echo htmlspecialchars($edit_lkh['uraian_kegiatan_lkh']); ?></textarea>
            </div>
            <div class="mb-3">
              <label for="jumlah_realisasi" class="form-label">Jumlah Realisasi</label>
              <input type="text" class="form-control" id="jumlah_realisasi" name="jumlah_realisasi" value="<?php echo htmlspecialchars($edit_lkh['jumlah_realisasi']); ?>" placeholder="Contoh: 1, 3" required
                <?php echo ($status_verval_lkh == 'diajukan' || $status_verval_lkh == 'disetujui') ? 'disabled' : ''; ?>>
            </div>
            <div class="mb-3">
              <label for="satuan_realisasi" class="form-label">Satuan Realisasi</label>
              <select class="form-select" id="satuan_realisasi" name="satuan_realisasi" required
                <?php echo ($status_verval_lkh == 'diajukan' || $status_verval_lkh == 'disetujui') ? 'disabled' : ''; ?>>
                <option value="">-- Pilih Satuan --</option>
                <option value="1" <?php echo ($edit_lkh['satuan_realisasi'] == 'Kegiatan') ? 'selected' : ''; ?>>Kegiatan</option>
                <option value="2" <?php echo ($edit_lkh['satuan_realisasi'] == 'JP') ? 'selected' : ''; ?>>JP</option>
                <option value="3" <?php echo ($edit_lkh['satuan_realisasi'] == 'Dokumen') ? 'selected' : ''; ?>>Dokumen</option>
                <option value="4" <?php echo ($edit_lkh['satuan_realisasi'] == 'Laporan') ? 'selected' : ''; ?>>Laporan</option>
                <option value="5" <?php echo ($edit_lkh['satuan_realisasi'] == 'Hari') ? 'selected' : ''; ?>>Hari</option>
                <option value="6" <?php echo ($edit_lkh['satuan_realisasi'] == 'Jam') ? 'selected' : ''; ?>>Jam</option>
                <option value="7" <?php echo ($edit_lkh['satuan_realisasi'] == 'Menit') ? 'selected' : ''; ?>>Menit</option>
                <option value="8" <?php echo ($edit_lkh['satuan_realisasi'] == 'Unit') ? 'selected' : ''; ?>>Unit</option>
              </select>
            </div>
            <button type="submit" class="btn btn-warning" <?php echo empty($rkb_list) || ($status_verval_lkh == 'diajukan' || $status_verval_lkh == 'disetujui') ? 'disabled' : ''; ?>>Update LKH</button>
            <a href="lkh.php?month=<?php echo date('m', strtotime($edit_lkh['tanggal_lkh'])); ?>&year=<?php echo date('Y', strtotime($edit_lkh['tanggal_lkh'])); ?>" class="btn btn-secondary">Batal Edit</a>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <?php $no = 1; foreach ($lkhs as $lkh): ?>
      <!-- Modal Tambah Lampiran -->
      <div class="modal fade" id="modalTambahLampiran<?php echo $lkh['id_lkh']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
          <form action="lkh.php" method="POST" class="modal-content" enctype="multipart/form-data">
            <div class="modal-header bg-success text-white">
              <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Tambah Lampiran</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="action" value="add_attachment">
              <input type="hidden" name="id_lkh" value="<?php echo $lkh['id_lkh']; ?>">
              <div class="mb-3">
                <label for="lampiran_<?php echo $lkh['id_lkh']; ?>" class="form-label">Pilih File Lampiran</label>
                <input type="file" class="form-control" id="lampiran_<?php echo $lkh['id_lkh']; ?>" name="lampiran" accept=".pdf,.jpg,.jpeg,.png" required>
                <div class="form-text">Format yang diizinkan: PDF, JPG, JPEG, PNG. Ukuran maksimal 2MB.</div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-success">Tambah Lampiran</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Modal Hapus Lampiran -->
      <div class="modal fade" id="modalHapusLampiran<?php echo $lkh['id_lkh']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
          <form action="lkh.php" method="POST" class="modal-content">
            <div class="modal-header bg-danger text-white">
              <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Hapus Lampiran</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="action" value="remove_attachment">
              <input type="hidden" name="id_lkh" value="<?php echo $lkh['id_lkh']; ?>">
              <p>Anda yakin ingin menghapus lampiran untuk LKH ini?</p>
              <p class="text-danger"><small>File lampiran akan dihapus secara permanen dan tidak dapat dikembalikan.</small></p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-danger">Hapus Lampiran</button>
            </div>
          </form>
        </div>
      </div>
      <?php endforeach; ?>

      <!-- Modal LKH Terdahulu -->
      <div class="modal fade" id="modalLkhTerdahulu" tabindex="-1" aria-labelledby="modalLkhTerdahuluLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header bg-info text-white">
              <h5 class="modal-title" id="modalLkhTerdahuluLabel">
                <i class="fas fa-history me-2"></i>LKH Terdahulu
              </h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
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
                  <input type="text" class="form-control" id="searchLkhTerdahulu" placeholder="🔍 Cari LKH terdahulu...">
                </div>
                
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                  <table class="table table-hover table-sm">
                    <thead class="table-light">
                      <tr>
                        <th width="25%">Nama Kegiatan</th>
                        <th width="35%">Uraian Kegiatan LKH</th>
                        <th width="15%">Jumlah Terakhir</th>
                        <th width="15%">Satuan Terakhir</th>
                        <th width="10%">Aksi</th>
                      </tr>
                    </thead>
                    <tbody id="lkhTerdahuluTableBody">
                      <?php foreach ($previous_lkh_list as $prev_lkh): ?>
                        <tr class="lkh-row" data-nama="<?php echo htmlspecialchars($prev_lkh['nama_kegiatan_harian']); ?>"
                            data-uraian="<?php echo htmlspecialchars($prev_lkh['uraian_kegiatan_lkh']); ?>"
                            data-jumlah="<?php echo htmlspecialchars($prev_lkh['jumlah_realisasi']); ?>"
                            data-satuan="<?php echo htmlspecialchars($prev_lkh['satuan_realisasi']); ?>"
                            >
                          <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($prev_lkh['nama_kegiatan_harian']); ?></div>
                          </td>
                          <td>
                            <div class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($prev_lkh['uraian_kegiatan_lkh']); ?>">
                              <?php echo htmlspecialchars($prev_lkh['uraian_kegiatan_lkh']); ?>
                            </div>
                          </td>
                          <td class="text-center">
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($prev_lkh['jumlah_realisasi']); ?></span>
                          </td>
                          <td class="text-center">
                            <span class="badge bg-primary"><?php echo htmlspecialchars($prev_lkh['satuan_realisasi']); ?></span>
                          </td>
                          <td class="text-center">
                            <button type="button" class="btn btn-sm btn-success pilih-lkh-btn"
                                    data-nama="<?php echo htmlspecialchars($prev_lkh['nama_kegiatan_harian']); ?>"
                                    data-uraian="<?php echo htmlspecialchars($prev_lkh['uraian_kegiatan_lkh']); ?>"
                                    data-jumlah="<?php echo htmlspecialchars($prev_lkh['jumlah_realisasi']); ?>"
                                    data-satuan="<?php echo htmlspecialchars($prev_lkh['satuan_realisasi']); ?>"
                                    title="Pilih LKH ini">
                              <i class="fas fa-check me-1"></i><small>Gunakan</small>
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                
                <div id="noDataMessageLkh" class="text-center py-3 d-none">
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

      <!-- Modal Preview LKH -->
      <div class="modal fade" id="modalPreviewLkh" tabindex="-1" aria-labelledby="modalPreviewLkhLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
          <div class="modal-content">
            <div class="modal-header bg-info text-white">
              <h5 class="modal-title" id="modalPreviewLkhLabel">
                <i class="fas fa-eye me-2"></i>Preview Laporan Kinerja Harian (LKH)
              </h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <h6 class="fw-bold">Periode: <?php echo $months[$filter_month] . ' ' . $filter_year; ?></h6>
                <h6 class="fw-bold">Nama Pegawai: <?php echo htmlspecialchars($nama_pegawai_login); ?></h6>
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
                  <table class="table table-bordered table-striped">
                    <thead class="table-primary">
                      <tr class="text-center">
                        <th width="3%">No</th>
                        <th width="10%">Hari / Tanggal</th>
                        <th width="30%">Kegiatan</th>
                        <th width="35%">Uraian Tugas Kegiatan/ Tugas Jabatan</th>
                        <th width="7%">Jumlah</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php 
                      $no = 1; 
                      $hariList = [
                          'Sun' => 'Minggu',
                          'Mon' => 'Senin',
                          'Tue' => 'Selasa',
                          'Wed' => 'Rabu',
                          'Thu' => 'Kamis',
                          'Fri' => 'Jumat',
                          'Sat' => 'Sabtu'
                      ];
                      
                      foreach ($lkh_grouped as $date => $lkh_items): 
                        $hari = $hariList[date('D', strtotime($date))];
                        $tanggal_formatted = $hari . ', ' . date('d-m-Y', strtotime($date));
                        $first_item = true;
                      ?>
                        <?php foreach ($lkh_items as $lkh): ?>
                          <tr>
                            <?php if ($first_item): ?>
                              <td class="text-center" rowspan="<?php echo count($lkh_items); ?>">
                                <?php echo $no++; ?>
                              </td>
                              <td class="text-center" rowspan="<?php echo count($lkh_items); ?>">
                                <?php echo $tanggal_formatted; ?>
                              </td>
                            <?php endif; ?>
                            <td>
                              <div>- <?php echo htmlspecialchars($lkh['nama_kegiatan_harian'] ?? ''); ?></div>
                            </td>
                            <td>- <?php echo htmlspecialchars($lkh['uraian_kegiatan_lkh']); ?></td>
                            <td class="text-center">
                              <span class="badge bg-primary">- <?php echo htmlspecialchars($lkh['jumlah_realisasi'] . ' ' . $lkh['satuan_realisasi']); ?></span>
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
                    <div class="col-md-6">
                      <small class="text-muted">
                        <strong>Total LKH:</strong> <?php echo count($lkhs); ?> kegiatan
                      </small>
                    </div>
                    <div class="col-md-6 text-end">
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

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // SweetAlert untuk pesan status
    <?php if (isset($_SESSION['swal'])): ?>
        Swal.fire({
            icon: '<?php echo $_SESSION['swal']['type']; ?>',
            title: '<?php echo $_SESSION['swal']['title']; ?>',
            text: '<?php echo $_SESSION['swal']['text']; ?>',
            timer: 2000,
            showConfirmButton: false
        });
        <?php unset($_SESSION['swal']); ?>
    <?php endif; ?>

    // SweetAlert konfirmasi hapus LKH
    document.querySelectorAll('.form-hapus-lkh').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            // Tambahkan pengecekan status_verval_lkh di sisi klien (JS)
            const statusVervalLkh = "<?php echo $status_verval_lkh; ?>";
            if (statusVervalLkh === 'diajukan' || statusVervalLkh === 'disetujui') {
                Swal.fire({
                    icon: 'error',
                    title: 'Tidak Diizinkan',
                    text: 'LKH periode ini sudah diajukan atau diverifikasi dan tidak dapat dihapus.'
                });
                return; // Hentikan proses penghapusan
            }

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
                    form.submit();
                }
            });
        });
    });
    
    // JavaScript untuk fitur LKH terdahulu
    document.querySelectorAll('.pilih-lkh-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        const nama = this.getAttribute('data-nama');
        const uraian = this.getAttribute('data-uraian');
        const jumlah = this.getAttribute('data-jumlah');
        const satuan = this.getAttribute('data-satuan');
        
        // Isi form di modal tambah LKH
        document.getElementById('nama_kegiatan_harian_modal').value = nama;
        document.getElementById('uraian_kegiatan_lkh_modal').value = uraian;
        document.getElementById('jumlah_realisasi_modal').value = jumlah;
        
        // Set satuan dropdown berdasarkan mapping
        const satuanMap = {
          'Kegiatan': '1',
          'JP': '2',
          'Dokumen': '3',
          'Laporan': '4',
          'Hari': '5',
          'Jam': '6',
          'Menit': '7',
          'Unit': '8'
        };
        const satuanValue = satuanMap[satuan] || '';
        document.getElementById('satuan_realisasi_modal').value = satuanValue;
        
        // Tutup hanya modal LKH terdahulu, bukan modal tambah LKH
        const modalLkhTerdahulu = bootstrap.Modal.getInstance(document.getElementById('modalLkhTerdahulu'));
        if (modalLkhTerdahulu) {
          modalLkhTerdahulu.hide();
        }
        
        // Show success message
        Swal.fire({
          icon: 'success',
          title: 'LKH Terpilih!',
          text: 'Data LKH terdahulu berhasil disalin ke form.',
          timer: 1500,
          showConfirmButton: false
        });
        
        // Pastikan modal tambah LKH tetap terbuka
        setTimeout(function() {
          const modalTambahLkh = bootstrap.Modal.getInstance(document.getElementById('modalTambahLkh'));
          if (!modalTambahLkh || !modalTambahLkh._isShown) {
            const newModalTambahLkh = new bootstrap.Modal(document.getElementById('modalTambahLkh'));
            newModalTambahLkh.show();
          }
        }, 100);
      });
    });
    
    // Event listener untuk modal LKH terdahulu ketika ditutup
    const modalLkhTerdahuluElement = document.getElementById('modalLkhTerdahulu');
    if (modalLkhTerdahuluElement) {
      modalLkhTerdahuluElement.addEventListener('hidden.bs.modal', function() {
        // Pastikan modal tambah LKH tetap terbuka setelah modal LKH terdahulu ditutup
        setTimeout(function() {
          const modalTambahLkh = bootstrap.Modal.getInstance(document.getElementById('modalTambahLkh'));
          if (!modalTambahLkh || !modalTambahLkh._isShown) {
            const newModalTambahLkh = new bootstrap.Modal(document.getElementById('modalTambahLkh'));
            newModalTambahLkh.show();
          }
        }, 100);
      });
    }
    
    // Search functionality untuk LKH terdahulu
    const searchInputLkh = document.getElementById('searchLkhTerdahulu');
    const tableBodyLkh = document.getElementById('lkhTerdahuluTableBody');
    const noDataMessageLkh = document.getElementById('noDataMessageLkh');
    
    if (searchInputLkh && tableBodyLkh) {
      searchInputLkh.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = tableBodyLkh.querySelectorAll('.lkh-row');
        let visibleCount = 0;
        
        rows.forEach(function(row) {
          const nama = row.getAttribute('data-nama').toLowerCase();
          const uraian = row.getAttribute('data-uraian').toLowerCase();
          const jumlah = row.getAttribute('data-jumlah').toLowerCase();
          const satuan = row.getAttribute('data-satuan').toLowerCase();
          
          if (nama.includes(searchTerm) || uraian.includes(searchTerm) || jumlah.includes(searchTerm) || satuan.includes(searchTerm)) {
            row.style.display = '';
            visibleCount++;
          } else {
            row.style.display = 'none';
          }
        });
        
        // Show/hide no data message
        if (noDataMessageLkh) {
          if (visibleCount === 0 && searchTerm.length > 0) {
            noDataMessageLkh.classList.remove('d-none');
          } else {
            noDataMessageLkh.classList.add('d-none');
          }
        }
      });
    }
    
    // Reset form ketika modal tambah LKH ditutup (hanya reset jika benar-benar ditutup oleh user)
    const modalTambahLkhElement = document.getElementById('modalTambahLkh');
    if (modalTambahLkhElement) {
      modalTambahLkhElement.addEventListener('hidden.bs.modal', function(e) {
        // Cek apakah modal LKH terdahulu sedang terbuka
        const modalLkhTerdahulu = bootstrap.Modal.getInstance(document.getElementById('modalLkhTerdahulu'));
        if (!modalLkhTerdahulu || !modalLkhTerdahulu._isShown) {
          // Reset form hanya jika modal LKH terdahulu tidak terbuka
          this.querySelector('form').reset();
          // Reset tanggal ke current date
          const tanggalInput = this.querySelector('input[name="tanggal_lkh"]');
          if (tanggalInput) {
            tanggalInput.value = '<?php echo $current_date; ?>';
          }
        }
      });
    }
});
</script>
