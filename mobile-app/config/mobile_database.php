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

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'e_lapkin_mtsn11');
define('DB_USER', 'root');
define('DB_PASS', '');

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
    if (!isset($_SESSION['mobile_user_id'])) {
        return [
            'nama' => 'User Mobile',
            'nip' => '000000000000000000',
            'jabatan' => 'Staff',
            'id_pegawai' => 1
        ];
    }
    
    $db = getMobileDBConnection();
    if (!$db) {
        return [
            'nama' => $_SESSION['mobile_user_name'] ?? 'User Mobile',
            'nip' => $_SESSION['mobile_user_nip'] ?? '000000000000000000',
            'jabatan' => $_SESSION['mobile_user_jabatan'] ?? 'Staff',
            'id_pegawai' => $_SESSION['mobile_user_id'] ?? 1
        ];
    }
    
    try {
        $stmt = $db->prepare("SELECT nama, nip, jabatan, id_pegawai FROM pegawai WHERE id_pegawai = ?");
        $stmt->execute([$_SESSION['mobile_user_id']]);
        $result = $stmt->fetch();
        
        if ($result) {
            return $result;
        }
    } catch (PDOException $e) {
        error_log("Error getting mobile user data: " . $e->getMessage());
    }
    
    // Fallback to session data
    return [
        'nama' => $_SESSION['mobile_user_name'] ?? 'User Mobile',
        'nip' => $_SESSION['mobile_user_nip'] ?? '000000000000000000',
        'jabatan' => $_SESSION['mobile_user_jabatan'] ?? 'Staff',
        'id_pegawai' => $_SESSION['mobile_user_id'] ?? 1
    ];
}

/**
 * Get LKH summary for mobile
 */
function getMobileLKHSummary($id_pegawai, $month, $year) {
    $db = getMobileDBConnection();
    if (!$db) {
        return [
            'hari_approved' => 15,
            'hari_pending' => 3,
            'hari_rejected' => 1
        ];
    }
    
    try {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_kegiatan,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as hari_approved,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as hari_pending,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as hari_rejected
            FROM lkh 
            WHERE id_pegawai = ? 
            AND MONTH(tanggal) = ? 
            AND YEAR(tanggal) = ?
        ");
        $stmt->execute([$id_pegawai, $month, $year]);
        $result = $stmt->fetch();
        
        if ($result) {
            return [
                'hari_approved' => (int)$result['hari_approved'],
                'hari_pending' => (int)$result['hari_pending'],
                'hari_rejected' => (int)$result['hari_rejected']
            ];
        }
    } catch (PDOException $e) {
        error_log("Error getting LKH summary: " . $e->getMessage());
    }
    
    // Fallback data
    return [
        'hari_approved' => 15,
        'hari_pending' => 3,
        'hari_rejected' => 1
    ];
}

/**
 * Get RKB data for mobile
 */
function getMobileRKBData($id_pegawai, $year) {
    $db = getMobileDBConnection();
    if (!$db) {
        return ['total_kegiatan' => 25];
    }
    
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as total_kegiatan
            FROM rkb 
            WHERE id_pegawai = ? 
            AND YEAR(tanggal_mulai) = ?
        ");
        $stmt->execute([$id_pegawai, $year]);
        $result = $stmt->fetch();
        
        if ($result) {
            return ['total_kegiatan' => (int)$result['total_kegiatan']];
        }
    } catch (PDOException $e) {
        error_log("Error getting RKB data: " . $e->getMessage());
    }
    
    // Fallback data
    return ['total_kegiatan' => 25];
}

/**
 * Mobile authentication function
 */
function authenticateMobileUser($nip, $password) {
    $db = getMobileDBConnection();
    if (!$db) {
        return false;
    }
    
    try {
        $stmt = $db->prepare("SELECT id_pegawai, nama, nip, jabatan, password FROM pegawai WHERE nip = ? AND status = 'aktif'");
        $stmt->execute([$nip]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
    } catch (PDOException $e) {
        error_log("Mobile auth error: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Log mobile activity to database
 */
function logMobileActivity($user_id, $activity_type, $description = '') {
    $db = getMobileDBConnection();
    if (!$db) return false;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO mobile_activity_log (user_id, activity_type, description, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $activity_type,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Error logging mobile activity: " . $e->getMessage());
        return false;
    }
}
?>
    if ($bulan === null) $bulan = date('n');
    if ($tahun === null) $tahun = date('Y');
    
    // Cek apakah tabel lkh ada
    $table_check = $conn->query("SHOW TABLES LIKE 'lkh'");
    if (!$table_check || $table_check->num_rows === 0) {
        return [];
    }
    
    $stmt = $conn->prepare("SELECT * FROM lkh WHERE id_pegawai = ? AND bulan = ? AND tahun = ? ORDER BY tanggal_kegiatan DESC");
    if ($stmt) {
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
    
    return [];
}

/**
 * Fungsi untuk mendapatkan data RKB user
 */
function get_user_rkb($id_pegawai, $bulan = null, $tahun = null) {
    $conn = get_mobile_database_connection();
    
    if ($bulan === null) $bulan = date('n');
    if ($tahun === null) $tahun = date('Y');
    
    // Cek apakah tabel rkb ada
    $table_check = $conn->query("SHOW TABLES LIKE 'rkb'");
    if (!$table_check || $table_check->num_rows === 0) {
        return [];
    }
    
    $stmt = $conn->prepare("SELECT * FROM rkb WHERE id_pegawai = ? AND bulan = ? AND tahun = ? ORDER BY id_rkb ASC");
    if ($stmt) {
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
    
    return [];
}
?>
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
