<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA - MOBILE APP DATABASE
 * ========================================================
 * 
 * File: Mobile Database Configuration
 * Deskripsi: Konfigurasi database khusus untuk mobile app
 * 
 * @package    E-Lapkin-MTSN11-Mobile
 * @version    1.0.0
 * ========================================================
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Include database utama
require_once __DIR__ . '/../../config/database.php';

/**
 * Fungsi untuk mendapatkan koneksi database yang sama dengan aplikasi utama
 */
function get_mobile_database_connection() {
    global $conn;
    return $conn;
}

/**
 * Fungsi untuk validasi user khusus mobile (hanya user biasa, bukan admin)
 */
function validate_mobile_user($nip, $password) {
    $conn = get_mobile_database_connection();
    
    // Hanya izinkan user dengan role 'user', bukan admin
    $stmt = $conn->prepare("SELECT id_pegawai, nip, password, nama, jabatan, unit_kerja, nip_penilai, nama_penilai, role, foto_profil, status FROM pegawai WHERE nip = ? AND role = 'user'");
    
    if ($stmt === false) {
        return ['error' => 'Terjadi kesalahan sistem.'];
    }
    
    $stmt->bind_param("s", $nip);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $pegawai = $result->fetch_assoc();
        
        // Verifikasi password
        if (password_verify($password, $pegawai['password'])) {
            // Cek status approval
            $status = $pegawai['status'] ?? 'approved';
            
            if ($status === 'pending') {
                return ['error' => 'Akun Anda masih menunggu persetujuan admin.'];
            } elseif ($status === 'rejected') {
                return ['error' => 'Akun Anda ditolak oleh admin.'];
            } elseif ($status === 'approved' || empty($status)) {
                // Login berhasil
                unset($pegawai['password']); // Jangan kembalikan password
                return ['success' => true, 'user' => $pegawai];
            }
        }
    }
    
    $stmt->close();
    return ['error' => 'NIP atau password salah.'];
}

/**
 * Fungsi untuk mendapatkan data RHK user
 */
function get_user_rhk($id_pegawai, $tahun = null) {
    $conn = get_mobile_database_connection();
    
    if ($tahun === null) {
        $tahun = date('Y');
    }
    
    // Cek apakah tabel rhk ada
    $table_check = $conn->query("SHOW TABLES LIKE 'rhk'");
    if ($table_check->num_rows === 0) {
        return [];
    }
    
    $stmt = $conn->prepare("SELECT * FROM rhk WHERE id_pegawai = ? AND tahun = ? ORDER BY id_rhk ASC");
    $stmt->bind_param("ii", $id_pegawai, $tahun);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rhk_data = [];
    while ($row = $result->fetch_assoc()) {
        $rhk_data[] = $row;
    }
    
    $stmt->close();
    return $rhk_data;
}

/**
 * Fungsi untuk mendapatkan data RKB user
 */
function get_user_rkb($id_pegawai, $bulan = null, $tahun = null) {
    $conn = get_mobile_database_connection();
    
    if ($bulan === null) {
        $bulan = date('n');
    }
    if ($tahun === null) {
        $tahun = date('Y');
    }
    
    // Cek apakah tabel rkb ada
    $table_check = $conn->query("SHOW TABLES LIKE 'rkb'");
    if ($table_check->num_rows === 0) {
        return [];
    }
    
    $stmt = $conn->prepare("SELECT * FROM rkb WHERE id_pegawai = ? AND bulan = ? AND tahun = ? ORDER BY id_rkb ASC");
    $stmt->bind_param("iii", $id_pegawai, $bulan, $tahun);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rkb_data = [];
    while ($row = $result->fetch_assoc()) {
        $rkb_data[] = $row;
    }
    
    $stmt->close();
    return $rkb_data;
}

/**
 * Fungsi untuk mendapatkan data LKH user
 */
function get_user_lkh($id_pegawai, $bulan = null, $tahun = null) {
    $conn = get_mobile_database_connection();
    
    if ($bulan === null) {
        $bulan = date('n');
    }
    if ($tahun === null) {
        $tahun = date('Y');
    }
    
    // Cek apakah tabel lkh ada
    $table_check = $conn->query("SHOW TABLES LIKE 'lkh'");
    if ($table_check->num_rows === 0) {
        return [];
    }
    
    $stmt = $conn->prepare("SELECT * FROM lkh WHERE id_pegawai = ? AND bulan = ? AND tahun = ? ORDER BY tanggal_kegiatan DESC");
    $stmt->bind_param("iii", $id_pegawai, $bulan, $tahun);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $lkh_data = [];
    while ($row = $result->fetch_assoc()) {
        $lkh_data[] = $row;
    }
    
    $stmt->close();
    return $lkh_data;
}

/**
 * Fungsi untuk mendapatkan statistik dashboard user mobile
 */
function get_mobile_dashboard_stats($id_pegawai) {
    $conn = get_mobile_database_connection();
    
    $current_year = date('Y');
    $current_month = date('n');
    
    // Cek tabel ada atau tidak
    $table_checks = [
        'rhk' => $conn->query("SHOW TABLES LIKE 'rhk'")->num_rows > 0,
        'rkb' => $conn->query("SHOW TABLES LIKE 'rkb'")->num_rows > 0,
        'lkh' => $conn->query("SHOW TABLES LIKE 'lkh'")->num_rows > 0
    ];
    
    $stats = [
        'rhk_count' => 0,
        'rkb_count' => 0,
        'lkh_count' => 0,
        'lkh_this_month' => 0
    ];
    
    // Hitung RHK
    if ($table_checks['rhk']) {
        $result = $conn->query("SELECT COUNT(*) as total FROM rhk WHERE id_pegawai = $id_pegawai AND tahun = $current_year");
        if ($result) {
            $stats['rhk_count'] = $result->fetch_assoc()['total'];
        }
    }
    
    // Hitung RKB
    if ($table_checks['rkb']) {
        $result = $conn->query("SELECT COUNT(*) as total FROM rkb WHERE id_pegawai = $id_pegawai AND tahun = $current_year");
        if ($result) {
            $stats['rkb_count'] = $result->fetch_assoc()['total'];
        }
    }
    
    // Hitung LKH
    if ($table_checks['lkh']) {
        $result = $conn->query("SELECT COUNT(*) as total FROM lkh WHERE id_pegawai = $id_pegawai");
        if ($result) {
            $stats['lkh_count'] = $result->fetch_assoc()['total'];
        }
        
        $result = $conn->query("SELECT COUNT(*) as total FROM lkh WHERE id_pegawai = $id_pegawai AND bulan = $current_month AND tahun = $current_year");
        if ($result) {
            $stats['lkh_this_month'] = $result->fetch_assoc()['total'];
        }
    }
    
    return $stats;
}

/**
 * Fungsi untuk get data pegawai mobile yang optimized
 */
function getMobilePegawaiData($nip) {
    $conn = get_mobile_database_connection();
    
    $stmt = $conn->prepare("
        SELECT 
            id_pegawai, nip, password, nama, jabatan, unit_kerja, 
            nip_penilai, nama_penilai, role, status, foto_profil
        FROM pegawai 
        WHERE nip = ? AND role = 'user' AND (status = 'approved' OR status IS NULL)
    ");
    
    if ($stmt) {
        $stmt->bind_param("s", $nip);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        return $data;
    }
    
    return false;
}
?>
}

/**
 * Fungsi untuk mendapatkan ringkasan LKH mobile
 */
function getMobileLKHSummary($id_pegawai, $month = null, $year = null) {
    global $conn;
    
    if (!$month) $month = date('m');
    if (!$year) $year = date('Y');
    
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_hari,
            SUM(CASE WHEN status_verval = 'approved' THEN 1 ELSE 0 END) as hari_approved,
            SUM(CASE WHEN status_verval = 'pending' THEN 1 ELSE 0 END) as hari_pending,
            SUM(CASE WHEN status_verval = 'rejected' THEN 1 ELSE 0 END) as hari_rejected
        FROM lkh 
        WHERE id_pegawai = ? 
        AND MONTH(tanggal_lkh) = ? 
        AND YEAR(tanggal_lkh) = ?
    ");
    
    if ($stmt) {
        $stmt->bind_param("iii", $id_pegawai, $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        return $data;
    }
    
    return [
        'total_hari' => 0,
        'hari_approved' => 0,
        'hari_pending' => 0,
        'hari_rejected' => 0
    ];
}

/**
 * Fungsi untuk mendapatkan data RKB mobile
 */
function getMobileRKBData($id_pegawai, $year = null) {
    global $conn;
    
    if (!$year) $year = date('Y');
    
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_kegiatan,
            SUM(CASE WHEN status_verval = 'approved' THEN 1 ELSE 0 END) as kegiatan_approved,
            AVG(target_kuantitas) as avg_target
        FROM rkb 
        WHERE id_pegawai = ? AND tahun = ?
    ");
    
    if ($stmt) {
        $stmt->bind_param("ii", $id_pegawai, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        return $data;
    }
    
    return [
        'total_kegiatan' => 0,
        'kegiatan_approved' => 0,
        'avg_target' => 0
    ];
}

/**
 * API Response Helper untuk Mobile
 */
function sendMobileResponse($data, $status = 'success', $message = '', $http_code = 200) {
    http_response_code($http_code);
    set_mobile_headers();
    header('Content-Type: application/json');
    
    $response = [
        'status' => $status,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s'),
        'app_version' => MOBILE_APP_VERSION
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit();
}

/**
 * Error Response untuk Mobile
 */
function sendMobileError($message, $code = 'GENERAL_ERROR', $http_code = 400) {
    sendMobileResponse(null, 'error', $message, $http_code);
}
?>
