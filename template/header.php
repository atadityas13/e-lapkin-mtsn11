<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * Sistem Elektronik Laporan Kinerja Harian
 * MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat
 * 
 * File: Global Header Template
 * Deskripsi: Template header untuk semua halaman aplikasi
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

// Set default meta variables if not set
$meta_title = $meta_title ?? ($page_title ?? 'E-Lapkin MTSN 11 Majalengka');
$meta_description = $meta_description ?? 'Sistem Elektronik Laporan Kinerja Harian MTsN 11 Majalengka';
$meta_keywords = $meta_keywords ?? 'e-lapkin, mtsn 11 majalengka, laporan kinerja, sistem informasi, madrasah';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="<?= htmlspecialchars($meta_description) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($meta_keywords) ?>">
    <meta name="author" content="MTSN 11 Majalengka">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'E-LAPKIN' ?></title>

    <!-- Favicon -->
    <link rel="icon" href="/assets/img/favicon.png" type="image/png">

    <!-- SB Admin CSS -->
    <link rel="stylesheet" href="/assets/sbadmin/css/styles.css">

    <!-- Font Awesome (CDN, gunakan ini jika tidak ada di lokal SB Admin) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <!-- Optional: Custom CSS -->
    <!-- <link href="/assets/sbadmin/css/custom.css" rel="stylesheet"> -->
</head>
<body class="sb-nav-fixed">
