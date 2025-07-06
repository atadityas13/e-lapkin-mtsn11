<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA - MOBILE DATABASE CONFIG
 * ========================================================
 * 
 * File: Mobile Database Configuration
 * Deskripsi: Konfigurasi database untuk aplikasi mobile
 * 
 * @package    E-Lapkin-MTSN11-Mobile
 * @version    1.0.0
 * ========================================================
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    die('Direct access denied');
}

// Database configuration - harus sama dengan web utama
define('DB_HOST', 'localhost');
define('DB_NAME', 'mtsnmaja_e-lapkin');
define('DB_USER', 'mtsnmaja_ataditya');
define('DB_PASS', 'Admin021398');

/**
 * Get database connection
 */
function getMobileDBConnection() {
    static $connection = null;
    
    if ($connection === null) {
        try {
            $connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Mobile DB Connection Error: " . $e->getMessage());
            return false;
        }
    }
    
    return $connection;
}

/**
 * Get mobile user data
 */
function getMobileUserData() {
    if (!isset($_SESSION['mobile_loggedin']) || $_SESSION['mobile_loggedin'] !== true) {
        return [
            'nama' => 'User Mobile',
            'nip' => '000000000000000000',
            'jabatan' => 'Staff',
            'id_pegawai' => 1
        ];
    }
    
    return [
        'id_pegawai' => $_SESSION['id_pegawai'] ?? 1,
        'nip' => $_SESSION['nip'] ?? '000000000000000000',
        'nama' => $_SESSION['nama'] ?? 'User Mobile',
        'jabatan' => $_SESSION['jabatan'] ?? 'Staff',
        'unit_kerja' => $_SESSION['unit_kerja'] ?? '',
        'nip_penilai' => $_SESSION['nip_penilai'] ?? '',
        'nama_penilai' => $_SESSION['nama_penilai'] ?? '',
        'role' => $_SESSION['role'] ?? 'user',
        'tahun_aktif' => $_SESSION['tahun_aktif'] ?? date('Y'),
        'bulan_aktif' => $_SESSION['bulan_aktif'] ?? date('n'),
        'login_time' => $_SESSION['mobile_login_time'] ?? time(),
        'last_activity' => $_SESSION['mobile_last_activity'] ?? time()
    ];
}

/**
 * Mobile authentication function - integrated with main web app
 */
function authenticateMobileUser($nip, $password) {
    $db = getMobileDBConnection();
    if (!$db) {
        return false;
    }
    
    try {
        // Query pegawai dengan role user saja (admin tidak boleh login mobile)
        $stmt = $db->prepare("SELECT * FROM pegawai WHERE nip = ? AND role = 'user' LIMIT 1");
        $stmt->execute([$nip]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Set session mobile
            $_SESSION['mobile_loggedin'] = true;
            $_SESSION['mobile_login_time'] = time();
            $_SESSION['mobile_last_activity'] = time();
            $_SESSION['mobile_app_version'] = MOBILE_APP_VERSION ?? '1.0.0';
            $_SESSION['mobile_device_info'] = get_mobile_device_info();
            
            // Set session data yang sama dengan web utama
            $_SESSION['id_pegawai'] = $user['id_pegawai'];
            $_SESSION['nip'] = $user['nip'];
            $_SESSION['nama'] = $user['nama'];
            $_SESSION['jabatan'] = $user['jabatan'];
            $_SESSION['unit_kerja'] = $user['unit_kerja'];
            $_SESSION['nip_penilai'] = $user['nip_penilai'];
            $_SESSION['nama_penilai'] = $user['nama_penilai'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['tahun_aktif'] = $user['tahun_aktif'] ?? date('Y');
            $_SESSION['bulan_aktif'] = $user['bulan_aktif'] ?? date('n');
            
            return $user;
        }
    } catch (PDOException $e) {
        error_log("Mobile Auth Error: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Get LKH summary for mobile - integrated with main database
 */
function getMobileLKHSummary($id_pegawai, $month, $year) {
    $db = getMobileDBConnection();
    if (!$db) {
        return [
            'total_hari' => 0,
            'hari_approved' => 0,
            'hari_pending' => 0,
            'hari_rejected' => 0
        ];
    }
    
    try {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_hari,
                SUM(CASE WHEN status_verval = 'disetujui' THEN 1 ELSE 0 END) as hari_approved,
                SUM(CASE WHEN status_verval = 'menunggu' OR status_verval IS NULL THEN 1 ELSE 0 END) as hari_pending,
                SUM(CASE WHEN status_verval = 'ditolak' THEN 1 ELSE 0 END) as hari_rejected
            FROM lkh 
            WHERE id_pegawai = ? AND MONTH(tanggal_lkh) = ? AND YEAR(tanggal_lkh) = ?
        ");
        $stmt->execute([$id_pegawai, $month, $year]);
        $result = $stmt->fetch();
        
        if ($result) {
            return $result;
        }
    } catch (PDOException $e) {
        error_log("Error getting mobile LKH summary: " . $e->getMessage());
    }
    
    // Fallback data
    return [
        'total_hari' => 15,
        'hari_approved' => 12,
        'hari_pending' => 2,
        'hari_rejected' => 1
    ];
}

/**
 * Get RKB data for mobile - integrated with main database
 */
function getMobileRKBData($id_pegawai, $year = null) {
    $db = getMobileDBConnection();
    if (!$year) $year = date('Y');
    
    if (!$db) {
        return ['total_kegiatan' => 25];
    }
    
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as total_kegiatan
            FROM rkb 
            WHERE id_pegawai = ? AND tahun = ?
        ");
        $stmt->execute([$id_pegawai, $year]);
        $result = $stmt->fetch();
        
        if ($result) {
            return $result;
        }
    } catch (PDOException $e) {
        error_log("Error getting mobile RKB data: " . $e->getMessage());
    }
    
    // Fallback data
    return ['total_kegiatan' => 25];
}

/**
 * Get mobile user recent activities
 */
function getMobileRecentActivities($id_pegawai, $limit = 5) {
    $db = getMobileDBConnection();
    if (!$db) {
        return [];
    }
    
    try {
        $stmt = $db->prepare("
            SELECT 
                'lkh' as type,
                CONCAT('LKH - ', nama_kegiatan_harian) as activity,
                tanggal_lkh as date,
                COALESCE(status_verval, 'menunggu') as status
            FROM lkh 
            WHERE id_pegawai = ? 
            ORDER BY tanggal_lkh DESC 
            LIMIT ?
        ");
        $stmt->execute([$id_pegawai, $limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting mobile recent activities: " . $e->getMessage());
        return [];
    }
}

/**
 * Get pegawai data for mobile login - integrated with main web app
 */
function getMobilePegawaiData($nip) {
    $db = getMobileDBConnection();
    if (!$db) {
        return false;
    }
    
    try {
        // Query pegawai dengan status approved dan role user saja
        $stmt = $db->prepare("
            SELECT 
                id_pegawai, nip, nama, jabatan, unit_kerja, 
                nip_penilai, nama_penilai, password, role, 
                status, tahun_aktif, bulan_aktif
            FROM pegawai 
            WHERE nip = ? AND status = 'approved' AND role = 'user'
            LIMIT 1
        ");
        $stmt->execute([$nip]);
        $result = $stmt->fetch();
        
        return $result ?: false;
    } catch (PDOException $e) {
        error_log("Error getting mobile pegawai data: " . $e->getMessage());
        return false;
    }
}

/**
 * Error Response untuk Mobile
 */
function sendMobileError($message, $code = 'GENERAL_ERROR', $http_code = 400) {
    sendMobileResponse(null, 'error', $message, $http_code);
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
?>
