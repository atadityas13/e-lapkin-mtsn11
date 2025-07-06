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

// Konfigurasi koneksi database
$host = "localhost"; // Server database Anda (biasanya localhost di Laragon)
$user = "mtsnmaja_ataditya";      // Username default MySQL di Laragon
$pass = "Admin20278893";          // Password default MySQL di Laragon (kosong)
$db_name = "mtsnmaja_e-lapkin"; // Nama database yang baru saja Anda buat

// Buat koneksi baru menggunakan MySQLi
$conn = new mysqli($host, $user, $pass, $db_name);

// Cek apakah ada error koneksi
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Atur character set ke utf8mb4 untuk mendukung berbagai karakter
$conn->set_charset("utf8mb4");

// Tambahan PDO connection untuk compatibility
function getDBConnection() {
    global $host, $user, $pass, $db_name;
    
    try {
        $dsn = "mysql:host={$host};dbname={$db_name};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("Koneksi database gagal. Silakan hubungi administrator.");
    }
}

// Helper database functions
function getPegawaiByNip($nip) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM pegawai WHERE nip = ?");
    $stmt->execute([$nip]);
    return $stmt->fetch();
}

// Catatan: Variabel $conn sekarang berisi objek koneksi ke database.
// Anda bisa menggunakannya di file PHP lain dengan menggunakan `require_once`.
?>
