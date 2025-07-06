<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA - MOBILE SESSION
 * ========================================================
 * 
 * File: Mobile Session Management
 * Deskripsi: Pengelolaan session untuk aplikasi mobile
 * 
 * @package    E-Lapkin-MTSN11-Mobile
 * @version    1.0.0
 * ========================================================
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if mobile user is logged in
if (!isset($_SESSION['mobile_loggedin']) || $_SESSION['mobile_loggedin'] !== true) {
    // Redirect to mobile login
    header("Location: /mobile-app/index.php");
    exit();
}

// Validate session data - menggunakan data yang sama dengan web utama
if (!isset($_SESSION['id_pegawai']) || !isset($_SESSION['nip'])) {
    // Invalid session, destroy and redirect
    session_destroy();
    header("Location: /mobile-app/index.php");
    exit();
}

// Update last activity
$_SESSION['mobile_last_activity'] = time();

// Session timeout (24 hours untuk mobile)
$timeout_duration = 86400; // 24 hours
if (isset($_SESSION['mobile_last_activity']) && 
    (time() - $_SESSION['mobile_last_activity']) > $timeout_duration) {
    // Session expired
    session_destroy();
    header("Location: /mobile-app/index.php?timeout=1");
    exit();
}

/**
 * Get current mobile user info - menggunakan session data yang sama dengan web utama
 */
function getCurrentMobileUser() {
    return [
        'id_pegawai' => $_SESSION['id_pegawai'] ?? null,
        'nama' => $_SESSION['nama'] ?? '',
        'nip' => $_SESSION['nip'] ?? '',
        'jabatan' => $_SESSION['jabatan'] ?? '',
        'unit_kerja' => $_SESSION['unit_kerja'] ?? '',
        'role' => $_SESSION['role'] ?? 'user'
    ];
}

/**
 * Check if user has mobile permission
 */
function hasMobilePermission($permission = '') {
    // Basic permission check - hanya user yang bisa akses mobile
    return isset($_SESSION['mobile_loggedin']) && 
           $_SESSION['mobile_loggedin'] === true && 
           ($_SESSION['role'] ?? '') === 'user';
}
?>
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
