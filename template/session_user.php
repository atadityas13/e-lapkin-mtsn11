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

// Include database configuration
require_once __DIR__ . '/../config/database.php';

// Pastikan session_start() sudah dipanggil sebelum include ini!
$id_pegawai = $_SESSION['id_pegawai'];
$nip_pegawai = $_SESSION['nip'];
$nama_pegawai = $_SESSION['nama'];
$jabatan_pegawai = $_SESSION['jabatan'];
$unit_kerja_pegawai = $_SESSION['unit_kerja'];
$nip_penilai_pegawai = $_SESSION['nip_penilai'];
$nama_penilai_pegawai = $_SESSION['nama_penilai'];
$role_pegawai = $_SESSION['role'];
$is_admin = ($role_pegawai === 'admin');
?>
