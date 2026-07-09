<?php
/**
 * E-LAPKIN Mobile LKH Generation
 */

ob_start();

session_start();

require_once __DIR__ . '/config/mobile_session.php';
require_once __DIR__ . '/../config/database.php';

checkMobileLogin();

$userData = getMobileSessionData();
$id_pegawai_login = (int) $userData['id_pegawai'];

$bulan = isset($_GET['bulan']) ? (int) $_GET['bulan'] : 0;
$tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : 0;
$aksi = isset($_GET['aksi']) ? (string) $_GET['aksi'] : '';

$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];

function set_mobile_notification($type, $title, $text) {
    $_SESSION['mobile_notification'] = [
        'type' => $type,
        'title' => $title,
        'text' => $text,
    ];
}

function mobile_lkh_pdf_path(mysqli $conn, int $idPegawai, int $bulan, int $tahun, array $months): string
{
    $stmt = $conn->prepare('SELECT nip FROM pegawai WHERE id_pegawai = ?');
    $stmt->bind_param('i', $idPegawai);
    $stmt->execute();
    $stmt->bind_result($nipPegawai);
    $stmt->fetch();
    $stmt->close();

    $namaFileNip = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $nipPegawai);

    return __DIR__ . "/../generated/LKH_{$months[$bulan]}_{$tahun}_{$namaFileNip}.pdf";
}

if (!$bulan || !$tahun || $aksi !== 'generate') {
    talimRedirectLocation('laporan.php?tab=lkh');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    talimRedirectLocation('laporan.php?tab=lkh');
}

$tempat_cetak = trim($_POST['tempat_cetak'] ?? 'Cingambul');
$tanggal_cetak = trim($_POST['tanggal_cetak'] ?? date('Y-m-d'));

if ($tempat_cetak === '' || $tanggal_cetak === '') {
    set_mobile_notification('error', 'Gagal', 'Tempat cetak dan tanggal cetak harus diisi.');
    talimRedirectLocation('laporan.php?tab=lkh');
}

$directGenerate = function_exists('talimCanDirectGenerate') && talimCanDirectGenerate();
$approvalSql = $directGenerate ? '' : " AND status_verval = 'disetujui'";
$stmtCheck = $conn->prepare(
    'SELECT COUNT(*) FROM lkh WHERE id_pegawai = ? AND MONTH(tanggal_lkh) = ? AND YEAR(tanggal_lkh) = ?' . $approvalSql
);
$stmtCheck->bind_param('iii', $id_pegawai_login, $bulan, $tahun);
$stmtCheck->execute();
$stmtCheck->bind_result($countApproved);
$stmtCheck->fetch();
$stmtCheck->close();

if ((int) $countApproved === 0) {
    set_mobile_notification(
        'error',
        'Gagal',
        $directGenerate ? 'Data LKH belum tersedia untuk periode tersebut.' : 'LKH belum disetujui untuk periode tersebut.'
    );
    talimRedirectLocation('laporan.php?tab=lkh');
}

$_POST['tempat_cetak'] = $tempat_cetak;
$_POST['tanggal_cetak'] = $tanggal_cetak;
$_GET['bulan'] = $bulan;
$_GET['tahun'] = $tahun;
$_GET['aksi'] = 'generate';
$_SESSION['id_pegawai'] = $id_pegawai_login;
$_SESSION['loggedin'] = true;

$expectedPdfPath = mobile_lkh_pdf_path($conn, $id_pegawai_login, $bulan, $tahun, $months);

try {
    define('MOBILE_LKH_GENERATE_ONLY', true);
    ob_clean();
    include __DIR__ . '/../user/generate_lkh.php';

    if (!is_file($expectedPdfPath)) {
        throw new RuntimeException('File PDF LKH tidak ditemukan setelah generate.');
    }

    set_mobile_notification('success', 'Berhasil', 'LKH berhasil digenerate dan dapat diunduh.');
} catch (Throwable $e) {
    error_log('LKH Generation Error: ' . $e->getMessage());
    set_mobile_notification('error', 'Gagal', 'Terjadi kesalahan saat generate LKH. Silakan coba lagi.');
}

talimRedirectLocation('laporan.php?tab=lkh');
