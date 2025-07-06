<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA - MOBILE LOGIN
 * ========================================================
 * 
 * File: Mobile Login Handler
 * Deskripsi: Proses login khusus untuk aplikasi mobile
 * 
 * @package    E-Lapkin-MTSN11-Mobile
 * @version    1.0.0
 * ========================================================
 */

// Define ABSPATH
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Include mobile security dan database
require_once __DIR__ . '/../config/mobile_security.php';
require_once __DIR__ . '/../config/mobile_database.php';

// Blokir akses non-mobile
block_non_mobile_access();

// Log akses login
log_mobile_access('login_attempt');

// Set mobile headers
set_mobile_headers();

session_start();

// Cek apakah request method adalah POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sendMobileError("Method not allowed", "INVALID_METHOD", 405);
}

// Validasi parameter mobile app
if (!isset($_POST['mobile_app']) || $_POST['mobile_app'] !== '1') {
    sendMobileError("Invalid mobile app request", "INVALID_APP_REQUEST", 400);
}

// Ambil data input
$nip = trim($_POST['nip'] ?? '');
$password = trim($_POST['password'] ?? '');
$app_version = trim($_POST['app_version'] ?? '');

// Validasi input dasar
if (empty($nip) || empty($password)) {
    sendMobileError("NIP dan Password harus diisi", "EMPTY_CREDENTIALS", 400);
}

// Validasi format NIP (minimal 3 karakter)
if (strlen($nip) < 3) {
    sendMobileError("Format NIP tidak valid", "INVALID_NIP_FORMAT", 400);
}

// Validasi password (minimal 6 karakter)
if (strlen($password) < 6) {
    sendMobileError("Password minimal 6 karakter", "INVALID_PASSWORD_FORMAT", 400);
}

try {
    // Ambil data pegawai dari database
    $pegawai_data = getMobilePegawaiData($nip);
    
    if (!$pegawai_data) {
        log_mobile_access('login_failed_user_not_found');
        sendMobileError("NIP tidak ditemukan atau belum disetujui", "USER_NOT_FOUND", 401);
    }
    
    // Verifikasi password
    if (!password_verify($password, $pegawai_data['password'])) {
        log_mobile_access('login_failed_wrong_password');
        sendMobileError("Password salah", "WRONG_PASSWORD", 401);
    }
    
    // Cek status pegawai
    if ($pegawai_data['status'] !== 'approved') {
        log_mobile_access('login_failed_not_approved');
        sendMobileError("Akun Anda belum disetujui admin", "ACCOUNT_NOT_APPROVED", 401);
    }
    
    // Hanya user biasa yang bisa login di mobile (bukan admin)
    if ($pegawai_data['role'] === 'admin') {
        log_mobile_access('login_failed_admin_blocked');
        sendMobileError("Admin tidak dapat menggunakan aplikasi mobile", "ADMIN_NOT_ALLOWED", 403);
    }
    
    // Login berhasil - buat session mobile
    $_SESSION['mobile_loggedin'] = true;
    $_SESSION['mobile_login_time'] = time();
    $_SESSION['mobile_app_version'] = $app_version;
    $_SESSION['mobile_device_info'] = get_mobile_device_info();
    
    // Session data pegawai untuk mobile
    $_SESSION['id_pegawai'] = $pegawai_data['id_pegawai'];
    $_SESSION['nip'] = $pegawai_data['nip'];
    $_SESSION['nama'] = $pegawai_data['nama'];
    $_SESSION['jabatan'] = $pegawai_data['jabatan'];
    $_SESSION['unit_kerja'] = $pegawai_data['unit_kerja'];
    $_SESSION['nip_penilai'] = $pegawai_data['nip_penilai'];
    $_SESSION['nama_penilai'] = $pegawai_data['nama_penilai'];
    $_SESSION['role'] = $pegawai_data['role'];
    $_SESSION['tahun_aktif'] = $pegawai_data['tahun_aktif'];
    $_SESSION['bulan_aktif'] = $pegawai_data['bulan_aktif'];
    
    // Log login berhasil
    log_mobile_access('login_success');
    
    // Siapkan data respons
    $response_data = [
        'user' => [
            'id_pegawai' => $pegawai_data['id_pegawai'],
            'nip' => $pegawai_data['nip'],
            'nama' => $pegawai_data['nama'],
            'jabatan' => $pegawai_data['jabatan'],
            'unit_kerja' => $pegawai_data['unit_kerja'],
            'nama_penilai' => $pegawai_data['nama_penilai'],
            'tahun_aktif' => $pegawai_data['tahun_aktif'],
            'bulan_aktif' => $pegawai_data['bulan_aktif']
        ],
        'session' => [
            'login_time' => date('Y-m-d H:i:s', $_SESSION['mobile_login_time']),
            'app_version' => $app_version
        ],
        'dashboard_url' => '/mobile-app/user/dashboard.php'
    ];
    
    // Kirim respons sukses
    sendMobileResponse($response_data, 'success', 'Login berhasil', 200);
    
} catch (Exception $e) {
    // Log error
    error_log("Mobile login error: " . $e->getMessage());
    log_mobile_access('login_error');
    
    sendMobileError("Terjadi kesalahan sistem. Silakan coba lagi.", "SYSTEM_ERROR", 500);
}
?>
