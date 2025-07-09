<?php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['id_pegawai'])) {
    echo json_encode([
        'logged_in' => true,
        'user_id' => $_SESSION['id_pegawai'],
        'name' => $_SESSION['nama'] ?? '',
        'nip' => $_SESSION['nip'] ?? '',
        'jabatan' => $_SESSION['jabatan'] ?? ''
    ]);
} else {
    echo json_encode([
        'logged_in' => false,
        'user_id' => null
    ]);
}
?>
