<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA - MOBILE SESSION
 * ========================================================
 * 
 * File: Mobile Session Checker
 * Deskripsi: Pengecekan session khusus untuk aplikasi mobile
 * 
 * @package    E-Lapkin-MTSN11-Mobile
 * @version    1.0.0
 * ========================================================
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Include mobile security
require_once __DIR__ . '/../config/mobile_security.php';

// Blokir akses non-mobile
block_non_mobile_access();

// Pastikan session dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Fungsi untuk memeriksa apakah user sudah login di mobile
 */
function checkMobileLogin() {
    // Cek session mobile login
    if (!isset($_SESSION['mobile_loggedin']) || $_SESSION['mobile_loggedin'] !== true) {
        redirectToMobileLogin("Session tidak ditemukan");
    }
    
    // Cek data user
    $required_session_data = ['id_pegawai', 'nip', 'nama', 'role'];
    foreach ($required_session_data as $key) {
        if (!isset($_SESSION[$key])) {
            redirectToMobileLogin("Data session tidak lengkap");
        }
    }
    
    // Cek apakah user adalah admin (admin tidak boleh akses mobile)
    if ($_SESSION['role'] === 'admin') {
        redirectToMobileLogin("Admin tidak dapat menggunakan aplikasi mobile");
    }
    
    // Cek timeout session (24 jam)
    $session_timeout = 24 * 60 * 60; // 24 jam
    if (isset($_SESSION['mobile_login_time'])) {
        if ((time() - $_SESSION['mobile_login_time']) > $session_timeout) {
            redirectToMobileLogin("Session expired");
        }
    }
    
    // Update last activity
    $_SESSION['mobile_last_activity'] = time();
    
    // Log activity
    log_mobile_access('session_check_passed');
    
    return true;
}

/**
 * Fungsi untuk redirect ke login mobile
 */
function redirectToMobileLogin($reason = '') {
    log_mobile_access('session_check_failed: ' . $reason);
    
    // Clear session
    $mobile_session_keys = [
        'mobile_loggedin', 'mobile_login_time', 'mobile_app_version',
        'mobile_device_info', 'mobile_last_activity', 'id_pegawai',
        'nip', 'nama', 'jabatan', 'unit_kerja', 'nip_penilai',
        'nama_penilai', 'role', 'tahun_aktif', 'bulan_aktif'
    ];
    
    foreach ($mobile_session_keys as $key) {
        unset($_SESSION[$key]);
    }
    
    // Redirect ke login
    header("Location: /mobile-app/index.php?error=" . urlencode($reason));
    exit();
}

/**
 * Fungsi untuk mendapatkan data user mobile yang sudah login
 */
function getMobileUserData() {
    checkMobileLogin();
    
    return [
        'id_pegawai' => $_SESSION['id_pegawai'],
        'nip' => $_SESSION['nip'],
        'nama' => $_SESSION['nama'],
        'jabatan' => $_SESSION['jabatan'] ?? '',
        'unit_kerja' => $_SESSION['unit_kerja'] ?? '',
        'nip_penilai' => $_SESSION['nip_penilai'] ?? '',
        'nama_penilai' => $_SESSION['nama_penilai'] ?? '',
        'role' => $_SESSION['role'],
        'tahun_aktif' => $_SESSION['tahun_aktif'] ?? date('Y'),
        'bulan_aktif' => $_SESSION['bulan_aktif'] ?? date('m'),
        'login_time' => $_SESSION['mobile_login_time'] ?? time(),
        'last_activity' => $_SESSION['mobile_last_activity'] ?? time(),
        'app_version' => $_SESSION['mobile_app_version'] ?? MOBILE_APP_VERSION
    ];
}

/**
 * Fungsi untuk memeriksa permission khusus
 */
function checkMobilePermission($required_role = 'user') {
    $user_data = getMobileUserData();
    
    if ($required_role === 'admin' && $user_data['role'] !== 'admin') {
        sendMobileError("Access denied", "INSUFFICIENT_PERMISSION", 403);
    }
    
    return true;
}

/**
 * Auto-check login jika file ini di-include
 */
if (!function_exists('skip_mobile_session_check')) {
    checkMobileLogin();
}

// Set variabel global untuk compatibility
if (checkMobileLogin()) {
    $mobile_user_data = getMobileUserData();
    
    // Set variabel yang biasa digunakan di file user
    $id_pegawai_login = $mobile_user_data['id_pegawai'];
    $nama_pegawai_login = $mobile_user_data['nama'];
    $role_pegawai_login = $mobile_user_data['role'];
    $nip_pegawai = $mobile_user_data['nip'];
    $jabatan_pegawai = $mobile_user_data['jabatan'];
    $unit_kerja_pegawai = $mobile_user_data['unit_kerja'];
    $nip_penilai_pegawai = $mobile_user_data['nip_penilai'];
    $nama_penilai_pegawai = $mobile_user_data['nama_penilai'];
    $is_admin = ($role_pegawai_login === 'admin');
}
?>
