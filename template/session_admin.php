<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * Sistem Elektronik Laporan Kinerja Harian
 * MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat
 * 
 * File: Main Entry Point
 * Deskripsi: Halaman utama aplikasi - redirect ke login atau dashboard
 * 
 * @package    E-Lapkin-MTSN11
 * @author     MTsN 11 Majalengka Development Team
 * @copyright  2025 MTsN 11 Majalengka. All rights reserved.
 * @license    Proprietary License
 * @version    1.0.0
 * @since      2025-01-01
 * @created    2025-06-25
 * @modified   2025-06-25
 * 
 * DISCLAIMER:
 * Software ini dikembangkan khusus untuk MTsN 11 Majalengka.
 * Dilarang keras menyalin, memodifikasi, atau mendistribusikan
 * tanpa izin tertulis dari MTsN 11 Majalengka.
 * 
 * CONTACT:
 * Website: https://mtsn11majalengka.sch.id
 * Email: mtsn11majalengka@gmail.com
 * Phone: (0233) 8319182
 * Address: Kp. Sindanghurip Desa Maniis Kec. Cingambul, Majalengka, Jawa Barat
 * 
 * ========================================================
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Pastikan session_start() sudah dipanggil sebelum include ini!
// Jika belum dipanggil di awal script yang memanggil session_admin.php, maka panggil di sini:
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Pastikan pengguna sudah login dan memiliki peran 'admin'
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: /login.php");
    exit();
}

// Jika sampai di sini, artinya pengguna adalah admin dan sudah login
$is_admin = true;

// Anda bisa memuat data spesifik admin jika diperlukan,
// namun untuk dashboard admin, fokus utamanya adalah kontrol sistem, bukan detail personal admin.
// Data seperti $id_pegawai, $nama_pegawai, dll. mungkin tetap tersedia dari session login,
// tapi penggunaannya di dashboard admin akan minimal kecuali untuk keperluan identifikasi.
$id_admin = $_SESSION['id_pegawai'];
$nama_admin = $_SESSION['nama'];
// ... (variabel lain yang mungkin relevan dari session untuk admin, jika ada)
?>