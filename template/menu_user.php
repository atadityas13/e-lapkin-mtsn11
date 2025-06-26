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
    exit('Direct access not allowed.');
}
?>

<div id="layoutSidenav">
  <div id="layoutSidenav_nav">
    <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
      <div class="sb-sidenav-menu">
        <div class="nav">
          <div class="sb-sidenav-menu-heading text-center">
            <a href="/user/dashboard.php" class="d-flex align-items-center justify-content-center text-decoration-none mb-3">
              <img src="/assets/img/favicon.png" alt="E-LAPKIN Logo" style="width:150px; height:100px;" class="me-2">
            </a>
          </div>
          
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? ' active' : '' ?>" href="/user/dashboard.php">
            <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
            Dashboard
          </a>

          <div class="sb-sidenav-menu-heading">KINERJA</div>

          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'rhk.php' ? ' active' : '' ?>" href="/user/rhk.php">
            <div class="sb-nav-link-icon"><i class="fas fa-tasks"></i></div>
            Indikator RHK
          </a>
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'rkb.php' ? ' active' : '' ?>" href="/user/rkb.php">
            <div class="sb-nav-link-icon"><i class="fas fa-calendar-alt"></i></div>
            Rencana Kinerja Bulanan
          </a>
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'lkh.php' ? ' active' : '' ?>" href="/user/lkh.php">
            <div class="sb-nav-link-icon"><i class="fas fa-book"></i></div>
            Laporan Kinerja Harian
          </a>

          <div class="sb-sidenav-menu-heading">LAPORAN</div>

          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'laporan.php' ? ' active' : '' ?>" href="/user/laporan.php">
            <div class="sb-nav-link-icon"><i class="fas fa-chart-line"></i></div>
            Laporan Kinerja Tahunan
          </a>
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'generate_lkb-lkh.php' ? ' active' : '' ?>" href="/user/generate_lkb-lkh.php">
            <div class="sb-nav-link-icon"><i class="fas fa-file-export"></i></div>
            Generate LKB & LKH
          </a>

          <div class="sb-sidenav-menu-heading">PROFIL</div>

          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'profil.php' ? ' active' : '' ?>" href="/user/profil.php">
            <div class="sb-nav-link-icon"><i class="fas fa-user-circle"></i></div>
            Profil Saya
          </a>

          <a class="nav-link logout-btn" href="/logout.php">
            <div class="sb-nav-link-icon"><i class="fas fa-sign-out-alt"></i></div>
            Logout
          </a>
        </div>
      </div>
    </nav>
  </div>
  <!-- Konten utama dibuka di file lain dengan <div id="layoutSidenav_content"> -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add logout confirmation for sidebar
    setTimeout(function() {
        document.querySelectorAll('.logout-btn, a[href*="logout.php"]').forEach(function(logoutLink) {
            logoutLink.addEventListener('click', function(e) {
                e.preventDefault();
                const href = this.getAttribute('href');
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Konfirmasi Logout',
                        text: 'Apakah Anda yakin ingin keluar dari sistem?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Ya, Logout',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = href;
                        }
                    });
                } else {
                    // Fallback if SweetAlert not loaded
                    if (confirm('Apakah Anda yakin ingin keluar dari sistem?')) {
                        window.location.href = href;
                    }
                }
            });
        });
    }, 100);
});
</script>