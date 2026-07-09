<?php
/**
 * Authenticated PDF download/preview for mobile & Ta'lim embed.
 */

session_start();

require_once __DIR__ . '/config/mobile_session.php';
require_once __DIR__ . '/../config/database.php';

checkMobileLogin();

$userData = getMobileSessionData();
$idPegawai = (int) $userData['id_pegawai'];

$filename = basename((string) ($_GET['file'] ?? ''));
$inline = isset($_GET['inline']) && $_GET['inline'] === '1';

if ($filename === '' || !preg_match('/^(LKB|LKH)_[A-Za-z]+_\d{4}_[A-Za-z0-9_\-]+\.pdf$/', $filename)) {
    http_response_code(400);
    exit('File tidak valid.');
}

$stmt = $conn->prepare('SELECT nip FROM pegawai WHERE id_pegawai = ?');
$stmt->bind_param('i', $idPegawai);
$stmt->execute();
$stmt->bind_result($nipPegawai);
$stmt->fetch();
$stmt->close();

$namaFileNip = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $nipPegawai);
if ($namaFileNip === '' || !str_contains($filename, "_{$namaFileNip}.pdf")) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$path = realpath(__DIR__ . '/../generated/' . $filename);
$generatedDir = realpath(__DIR__ . '/../generated');

if ($path === false || $generatedDir === false || !str_starts_with($path, $generatedDir) || !is_file($path)) {
    http_response_code(404);
    exit('File tidak ditemukan.');
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

readfile($path);
exit;
