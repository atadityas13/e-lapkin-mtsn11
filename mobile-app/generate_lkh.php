<?php
/**
 * E-LAPKIN Mobile LKH Generation
 */

// Start output buffering to catch any unwanted output
ob_start();

session_start();

// Include mobile session config
require_once __DIR__ . '/config/mobile_session.php';
require_once __DIR__ . '/../config/database.php';

// Check mobile login
checkMobileLogin();

// Get user session data
$userData = getMobileSessionData();
$id_pegawai_login = $userData['id_pegawai'];

// Get parameters from URL
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : 0;
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';

// Validate parameters
if (!$bulan || !$tahun || $aksi !== 'generate') {
    header('Location: laporan.php');
    exit();
}

// Helper function for notifications
function set_mobile_notification($type, $title, $text) {
    $_SESSION['mobile_notification'] = [
        'type' => $type,
        'title' => $title,
        'text' => $text
    ];
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tempat_cetak = trim($_POST['tempat_cetak'] ?? 'Cingambul');
    $tanggal_cetak = trim($_POST['tanggal_cetak'] ?? date('Y-m-d'));
    
    // Validate required fields
    if (empty($tempat_cetak) || empty($tanggal_cetak)) {
        set_mobile_notification('error', 'Gagal', 'Tempat cetak dan tanggal cetak harus diisi.');
        header('Location: laporan.php');
        exit();
    }
    
    // Check if LKH exists and is approved for this period
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM lkh WHERE id_pegawai = ? AND MONTH(tanggal_lkh) = ? AND YEAR(tanggal_lkh) = ? AND status_verval = 'disetujui'");
    $stmt_check->bind_param("iii", $id_pegawai_login, $bulan, $tahun);
    $stmt_check->execute();
    $stmt_check->bind_result($count_approved);
    $stmt_check->fetch();
    $stmt_check->close();
    
    if ($count_approved == 0) {
        set_mobile_notification('error', 'Gagal', 'LKH belum disetujui untuk periode tersebut.');
        header('Location: laporan.php');
        exit();
    }
    
    try {
        // Include the web version's LKH generation script
        // Set required variables for the web script
        $_POST['tempat_cetak'] = $tempat_cetak;
        $_POST['tanggal_cetak'] = $tanggal_cetak;
        $_GET['bulan'] = $bulan;
        $_GET['tahun'] = $tahun;
        $_GET['aksi'] = 'generate';
        $_SESSION['id_pegawai'] = $id_pegawai_login;
        $_SESSION['loggedin'] = true;
        
        // Clear output buffer before including the generation script
        ob_clean();
        
        // Include the web version's generate_lkh.php
        include __DIR__ . '/../user/generate_lkh.php';
        
        // If we reach here, generation was successful
        set_mobile_notification('success', 'Berhasil', 'LKH berhasil digenerate dan dapat diunduh.');
        header('Location: laporan.php');
        exit();
        
    } catch (Exception $e) {
        error_log("LKH Generation Error: " . $e->getMessage());
        set_mobile_notification('error', 'Gagal', 'Terjadi kesalahan saat generate LKH. Silakan coba lagi.');
        header('Location: laporan.php');
        exit();
    }
} else {
    // If not POST request, redirect back to laporan
    header('Location: laporan.php');
    exit();
}
?>
