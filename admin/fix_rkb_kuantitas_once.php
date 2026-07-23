<?php
/**
 * One-time fix: hitung ulang semua RKB.kuantitas dari SUM(LKH.jumlah_realisasi).
 * Buka sekali di browser saat sudah login sebagai admin, lalu hapus file ini.
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/rkb_kuantitas_helper.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'Akses ditolak. Login sebagai admin terlebih dahulu.';
    exit;
}

$updated = sync_all_rkb_kuantitas_from_lkh($conn);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Fix Kuantitas RKB</title>
</head>
<body style="font-family: sans-serif; max-width: 640px; margin: 40px auto; padding: 0 16px;">
    <h1>Fix Kuantitas RKB</h1>
    <p>Selesai. Jumlah baris RKB yang terpengaruh: <strong><?= (int) $updated ?></strong>.</p>
    <p>Silakan buka RKB/LKB lalu cek kuantitas (mis. Mengajar 3 JP harus tampil 3 JP).</p>
    <p style="color:#b91c1c"><strong>Penting:</strong> hapus file <code>admin/fix_rkb_kuantitas_once.php</code> setelah dipakai.</p>
    <p><a href="dashboard.php">Kembali ke dashboard</a></p>
</body>
</html>
