<?php
/**
 * E-LAPKIN Mobile - simpan tanda tangan pegawai (upload file PNG dari signature pad).
 * Dikirim sebagai file multipart agar tidak diblokir firewall hosting (base64 panjang di POST → 403).
 */

session_start();

require_once __DIR__ . '/config/mobile_session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ttd_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['mobile_loggedin']) || $_SESSION['mobile_loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Sesi berakhir. Silakan login ulang.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['ttd']) || $_FILES['ttd']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'File tanda tangan tidak diterima.']);
    exit;
}

$id_pegawai = (int) (getMobileSessionData()['id_pegawai'] ?? 0);

if ($id_pegawai <= 0 || !simpan_ttd_pegawai_file($conn, $id_pegawai, $_FILES['ttd']['tmp_name'])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Tanda tangan tidak valid atau gagal disimpan.']);
    exit;
}

echo json_encode(['ok' => true]);
