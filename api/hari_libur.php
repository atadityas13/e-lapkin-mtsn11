<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * File: API Endpoint - Hari Libur Integration
 * Deskripsi: API endpoint untuk integrasi hari libur dari libur.deno.dev
 * 
 * @package    E-Lapkin-MTSN11
 * @author     MTsN 11 Majalengka Development Team
 * @copyright  2025 MTsN 11 Majalengka. All rights reserved.
 * @license    Proprietary License
 * @version    1.0.0
 * 
 * ========================================================
 */

// Allow only AJAX requests
header('Content-Type: application/json');

// Prevent direct access
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/hari_libur_helper.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'get_all':
        get_all_holidays();
        break;
    
    case 'get_by_year':
        get_holidays_by_year();
        break;
    
    case 'check_date':
        check_holiday_status();
        break;
    
    case 'fetch_from_api':
        fetch_from_external_api();
        break;
    
    case 'sync':
        sync_holidays();
        break;
    
    case 'add':
        add_holiday();
        break;
    
    case 'delete':
        delete_holiday();
        break;
    
    case 'update':
        update_holiday();
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * GET /api/hari_libur.php?action=get_all
 * Get semua hari libur
 */
function get_all_holidays() {
    global $conn;
    
    try {
        $holidays = get_all_hari_libur($conn);
        echo json_encode([
            'success' => true,
            'data' => $holidays,
            'count' => count($holidays)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . htmlspecialchars($e->getMessage())
        ]);
    }
}

/**
 * GET /api/hari_libur.php?action=get_by_year&tahun=2025
 * Get hari libur berdasarkan tahun
 */
function get_holidays_by_year() {
    global $conn;
    
    if (!isset($_GET['tahun'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parameter tahun diperlukan']);
        return;
    }
    
    $tahun = (int)$_GET['tahun'];
    
    try {
        $holidays = get_all_hari_libur($conn, $tahun);
        echo json_encode([
            'success' => true,
            'tahun' => $tahun,
            'data' => $holidays,
            'count' => count($holidays)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . htmlspecialchars($e->getMessage())
        ]);
    }
}

/**
 * GET /api/hari_libur.php?action=check_date&tanggal=2025-01-01
 * Check apakah tanggal adalah hari libur atau weekend
 */
function check_holiday_status() {
    global $conn;
    
    if (!isset($_GET['tanggal'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parameter tanggal diperlukan']);
        return;
    }
    
    $tanggal = $_GET['tanggal'];
    
    try {
        $result = is_hari_libur_atau_weekend($tanggal, $conn);
        echo json_encode([
            'success' => true,
            'tanggal' => $tanggal,
            'is_holiday' => $result['is_holiday'],
            'type' => $result['type'],
            'name' => $result['name']
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . htmlspecialchars($e->getMessage())
        ]);
    }
}

/**
 * GET /api/hari_libur.php?action=fetch_from_api&tahun=2025
 * Fetch hari libur dari libur.deno.dev API
 */
function fetch_from_external_api() {
    if (!isset($_GET['tahun'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parameter tahun diperlukan']);
        return;
    }
    
    $tahun = (int)$_GET['tahun'];
    
    try {
        $result = fetch_hari_libur_dari_api($tahun);
        echo json_encode([
            'success' => $result['success'],
            'message' => $result['message'],
            'tahun' => $tahun,
            'count' => count($result['data']),
            'data' => $result['data']
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . htmlspecialchars($e->getMessage())
        ]);
    }
}

/**
 * POST /api/hari_libur.php?action=sync
 * Sync hari libur dari API ke database
 */
function sync_holidays() {
    global $conn, $_SESSION;
    
    // Check if admin
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only admin can sync holidays']);
        return;
    }
    
    if (!isset($_POST['tahun'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parameter tahun diperlukan']);
        return;
    }
    
    $tahun = (int)$_POST['tahun'];
    $id_pegawai = $_SESSION['id_pegawai'];
    
    try {
        $result = sync_hari_libur_dari_api($conn, $tahun, $id_pegawai);
        echo json_encode([
            'success' => $result['success'],
            'message' => $result['message'],
            'tahun' => $tahun,
            'count_added' => $result['count_added']
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . htmlspecialchars($e->getMessage())
        ]);
    }
}

/**
 * POST /api/hari_libur.php?action=add
 * Add hari libur baru (admin only)
 */
function add_holiday() {
    global $conn, $_SESSION;
    
    // Check if admin
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only admin can add holidays']);
        return;
    }
    
    $required = ['tanggal_libur', 'nama_hari_libur'];
    foreach ($required as $param) {
        if (!isset($_POST[$param])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Parameter $param diperlukan"]);
            return;
        }
    }
    
    $tanggal = $_POST['tanggal_libur'];
    $nama = $_POST['nama_hari_libur'];
    $tipe = isset($_POST['tipe_libur']) ? $_POST['tipe_libur'] : 'nasional';
    $keterangan = isset($_POST['keterangan']) ? $_POST['keterangan'] : null;
    $id_pegawai = $_SESSION['id_pegawai'];
    
    try {
        $result = add_hari_libur($conn, $tanggal, $nama, $tipe, $id_pegawai, $keterangan, 'admin');
        echo json_encode([
            'success' => $result['success'],
            'message' => $result['message']
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . htmlspecialchars($e->getMessage())
        ]);
    }
}

/**
 * DELETE /api/hari_libur.php?action=delete
 * Delete hari libur (admin only)
 */
function delete_holiday() {
    global $conn, $_SESSION;
    
    // Check if admin
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only admin can delete holidays']);
        return;
    }
    
    if (!isset($_POST['id_hari_libur'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parameter id_hari_libur diperlukan']);
        return;
    }
    
    $id = (int)$_POST['id_hari_libur'];
    
    try {
        $result = delete_hari_libur($conn, $id);
        echo json_encode([
            'success' => $result['success'],
            'message' => $result['message']
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . htmlspecialchars($e->getMessage())
        ]);
    }
}

/**
 * PUT /api/hari_libur.php?action=update
 * Update hari libur (admin only)
 */
function update_holiday() {
    global $conn, $_SESSION;
    
    // Check if admin
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only admin can update holidays']);
        return;
    }
    
    $required = ['id_hari_libur', 'nama_hari_libur'];
    foreach ($required as $param) {
        if (!isset($_POST[$param])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Parameter $param diperlukan"]);
            return;
        }
    }
    
    $id = (int)$_POST['id_hari_libur'];
    $nama = $_POST['nama_hari_libur'];
    $tipe = isset($_POST['tipe_libur']) ? $_POST['tipe_libur'] : 'nasional';
    $keterangan = isset($_POST['keterangan']) ? $_POST['keterangan'] : null;
    
    try {
        $result = update_hari_libur($conn, $id, $nama, $tipe, $keterangan);
        echo json_encode([
            'success' => $result['success'],
            'message' => $result['message']
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . htmlspecialchars($e->getMessage())
        ]);
    }
}

?>
