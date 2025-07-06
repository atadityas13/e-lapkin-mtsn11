<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA - MOBILE APP SECURITY
 * ========================================================
 * 
 * File: Mobile Security Configuration
 * Deskripsi: Pengamanan khusus untuk aplikasi mobile Android
 * 
 * @package    E-Lapkin-MTSN11-Mobile
 * @version    1.0.0
 * ========================================================
 */
date_default_timezone_set('UTC');
// Prevent direct access
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

/**
 * Konfigurasi User Agent yang diizinkan untuk aplikasi mobile
 */
define('MOBILE_APP_USER_AGENT', 'E-LAPKIN-MTSN11-Mobile-App/1.0');
define('MOBILE_APP_VERSION', '1.0.0');
define('MOBILE_APP_NAME', 'E-LAPKIN Mobile');
define('MOBILE_APP_SECRET_KEY', 'MTSN11-MOBILE-KEY-2025');
define('MOBILE_APP_PACKAGE', 'id.sch.mtsn11majalengka.elapkin');

/**
 * Fungsi untuk memeriksa apakah request berasal dari aplikasi mobile yang valid
 */
function is_valid_mobile_app() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $mobile_token = $_SERVER['HTTP_X_MOBILE_TOKEN'] ?? '';
    $app_package = $_SERVER['HTTP_X_APP_PACKAGE'] ?? '';
    
    // Cek user agent khusus aplikasi
    $valid_user_agent = strpos($user_agent, MOBILE_APP_USER_AGENT) !== false;
    
    // Cek mobile token (hash dari secret key + tanggal)
    $expected_token = md5(MOBILE_APP_SECRET_KEY . date('Y-m-d'));
    $valid_token = ($mobile_token === $expected_token);
    
    // Cek package name
    $valid_package = ($app_package === MOBILE_APP_PACKAGE);
    
    // Untuk development, izinkan akses dari localhost dengan user agent yang benar
    if (isset($_SERVER['HTTP_HOST']) && 
        (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
         strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false ||
         strpos($_SERVER['HTTP_HOST'], '192.168.') !== false)) {
        return $valid_user_agent; // Minimal user agent harus benar
    }
    
    // Untuk production, semua validasi harus benar
    return $valid_user_agent && $valid_token && $valid_package;
}

/**
 * Fungsi untuk memblokir akses dari browser biasa
 */
function block_non_mobile_access() {
    if (!is_valid_mobile_app()) {
        // Log attempted unauthorized access
        log_mobile_access('unauthorized_access_attempt');
        
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
        
        // Tampilkan halaman khusus untuk akses yang ditolak
        echo '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akses Ditolak - E-LAPKIN Mobile</title>
    <style>
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        .icon {
            font-size: 64px;
            color: #e74c3c;
            margin-bottom: 20px;
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 24px;
        }
        p {
            color: #7f8c8d;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .app-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
            color: #495057;
        }
        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: #3498db;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 36px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">E</div>
        <div class="icon">🚫</div>
        <h1>Akses Ditolak</h1>
        <p>Halaman ini hanya dapat diakses melalui <strong>Aplikasi Mobile E-LAPKIN</strong> resmi MTsN 11 Majalengka.</p>
        
        <div class="app-info">
            <strong>Cara Mengakses:</strong><br>
            1. Download aplikasi E-LAPKIN Mobile<br>
            2. Install di perangkat Android Anda<br>
            3. Login menggunakan akun yang sama
        </div>
        
        <p style="font-size: 12px; color: #95a5a6;">
            Jika Anda mengakses melalui aplikasi resmi dan tetap melihat pesan ini, 
            silakan hubungi administrator sistem.
        </p>
    </div>
</body>
</html>';
        exit();
    }
}

/**
 * Fungsi khusus untuk validasi development mode
 */
function is_development_mode() {
    return (isset($_SERVER['HTTP_HOST']) && 
           (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
            strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false ||
            strpos($_SERVER['HTTP_HOST'], '192.168.') !== false));
}

/**
 * Generate mobile token untuk development
 */
function get_mobile_token() {
    return md5(MOBILE_APP_SECRET_KEY . date('Y-m-d'));
}

/**
 * Fungsi untuk mendapatkan informasi perangkat mobile
 */
function get_mobile_device_info() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    return [
        'user_agent' => $user_agent,
        'is_mobile_app' => is_valid_mobile_app(),
        'timestamp' => date('Y-m-d H:i:s'),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
}

/**
 * Log akses mobile untuk monitoring
 */
function log_mobile_access($action = 'access') {
    $log_file = __DIR__ . '/../logs/mobile_access.log';
    $log_dir = dirname($log_file);
    
    // Buat folder logs jika belum ada
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $device_info = get_mobile_device_info();
    $log_entry = [
        'timestamp' => $device_info['timestamp'],
        'action' => $action,
        'ip_address' => $device_info['ip_address'],
        'user_agent' => $device_info['user_agent'],
        'is_valid_app' => $device_info['is_mobile_app'],
        'url' => $_SERVER['REQUEST_URI'] ?? ''
    ];
    
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Header khusus untuk respons mobile
 */
function set_mobile_headers() {
    header('X-Mobile-App: ' . MOBILE_APP_NAME);
    header('X-Mobile-Version: ' . MOBILE_APP_VERSION);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
}
?>
