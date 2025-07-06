<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA - MOBILE LOGOUT
 * ========================================================
 * 
 * File: Mobile Logout Handler
 * Deskripsi: Proses logout khusus untuk aplikasi mobile
 * 
 * @package    E-Lapkin-MTSN11-Mobile
 * @version    1.0.0
 * ========================================================
 */

// Define ABSPATH
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Include mobile security
require_once __DIR__ . '/../config/mobile_security.php';

// Blokir akses non-mobile
block_non_mobile_access();

// Log akses logout
log_mobile_access('logout_attempt');

// Set mobile headers
set_mobile_headers();

session_start();

// Cek apakah user sedang login di mobile
if (!isset($_SESSION['mobile_loggedin']) || $_SESSION['mobile_loggedin'] !== true) {
    sendMobileResponse(
        ['redirect_url' => '/mobile-app/index.php'], 
        'info', 
        'Anda sudah logout', 
        200
    );
}

// Simpan data untuk log sebelum dihapus
$user_data = [
    'nip' => $_SESSION['nip'] ?? 'unknown',
    'nama' => $_SESSION['nama'] ?? 'unknown'
];

// Hancurkan semua session mobile
$mobile_session_keys = [
    'mobile_loggedin',
    'mobile_login_time', 
    'mobile_app_version',
    'mobile_device_info',
    'id_pegawai',
    'nip',
    'nama',
    'jabatan',
    'unit_kerja',
    'nip_penilai',
    'nama_penilai',
    'role',
    'tahun_aktif',
    'bulan_aktif'
];

foreach ($mobile_session_keys as $key) {
    unset($_SESSION[$key]);
}

// Jika tidak ada session lain yang aktif, hancurkan session sepenuhnya
if (empty($_SESSION)) {
    // Hapus cookie session
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Hancurkan session
    session_destroy();
}

// Log logout berhasil
log_mobile_access('logout_success');

// Kirim respons sukses
sendMobileResponse(
    [
        'redirect_url' => '/mobile-app/index.php',
        'logged_out_user' => $user_data['nama'],
        'logout_time' => date('Y-m-d H:i:s')
    ], 
    'success', 
    'Logout berhasil', 
    200
);
?>
