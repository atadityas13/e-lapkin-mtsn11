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
            <a href="/admin/dashboard.php" class="d-flex align-items-center justify-content-center text-decoration-none mb-3">
              <img src="/assets/img/favicon.png" alt="E-LAPKIN Admin" style="width:150px; height:100px;" class="me-2">
            </a>
          </div>
          
          <!-- Dashboard -->
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? ' active' : '' ?>" href="/admin/dashboard.php">
            <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
            Dashboard
          </a>

          <!-- Kinerja -->
          <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseKinerja" aria-expanded="false" aria-controls="collapseKinerja">
            <div class="sb-nav-link-icon"><i class="fas fa-chart-line"></i></div>
            Kinerja
            <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
          </a>
          <div class="collapse" id="collapseKinerja" aria-labelledby="headingKinerja" data-bs-parent="#sidenavAccordion">
            <nav class="sb-sidenav-menu-nested nav">
              <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'rhk.php' ? ' active' : '' ?>" href="/admin/rhk.php">
                <div class="sb-nav-link-icon"><i class="fas fa-tasks"></i></div>
                RHK
              </a>
              <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'rkb.php' ? ' active' : '' ?>" href="/admin/rkb.php">
                <div class="sb-nav-link-icon"><i class="fas fa-calendar-alt"></i></div>
                RKB
              </a>
              <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'lkh.php' ? ' active' : '' ?>" href="/admin/lkh.php">
                <div class="sb-nav-link-icon"><i class="fas fa-book"></i></div>
                LKH
              </a>
            </nav>
          </div>

          <!-- Approval -->
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'approval.php' ? ' active' : '' ?>" href="/admin/approval.php">
            <div class="sb-nav-link-icon"><i class="fas fa-check-circle"></i></div>
            Approval
          </a>

          <!-- Laporan & Statistik -->
          <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseLaporan" aria-expanded="false" aria-controls="collapseLaporan">
            <div class="sb-nav-link-icon"><i class="fas fa-chart-bar"></i></div>
            Laporan & Statistik
            <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
          </a>
          <div class="collapse" id="collapseLaporan" aria-labelledby="headingLaporan" data-bs-parent="#sidenavAccordion">
            <nav class="sb-sidenav-menu-nested nav">
              <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'generate_laporan.php' ? ' active' : '' ?>" href="/admin/generate_laporan.php">
                <div class="sb-nav-link-icon"><i class="fas fa-file-export"></i></div>
                Generate Laporan
              </a>
              <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'laporan_kinerja.php' ? ' active' : '' ?>" href="/admin/laporan_kinerja.php">
                <div class="sb-nav-link-icon"><i class="fas fa-file-alt"></i></div>
                Laporan Kinerja
              </a>
              <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'laporan_statistik.php' ? ' active' : '' ?>" href="/admin/laporan_statistik.php">
                <div class="sb-nav-link-icon"><i class="fas fa-chart-pie"></i></div>
                Statistik
              </a>
            </nav>
          </div>

          <!-- Pengaturan -->
          <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapsePengaturan" aria-expanded="false" aria-controls="collapsePengaturan">
            <div class="sb-nav-link-icon"><i class="fas fa-cogs"></i></div>
            Pengaturan
            <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
          </a>
          <div class="collapse" id="collapsePengaturan" aria-labelledby="headingPengaturan" data-bs-parent="#sidenavAccordion">
            <nav class="sb-sidenav-menu-nested nav">
              <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'manajemen_user.php' ? ' active' : '' ?>" href="/admin/manajemen_user.php">
                <div class="sb-nav-link-icon"><i class="fas fa-users-cog"></i></div>
                Manajemen User
              </a>
              <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'pengaturan.php' ? ' active' : '' ?>" href="/admin/pengaturan.php">
                <div class="sb-nav-link-icon"><i class="fas fa-wrench"></i></div>
                Sistem
              </a>
            </nav>
          </div>

          <div class="sb-sidenav-menu-heading">AKSI</div>

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
// Auto-expand menu based on current page
document.addEventListener('DOMContentLoaded', function() {
    const currentPage = '<?= basename($_SERVER['PHP_SELF']) ?>';
    
    // Auto-expand Kinerja menu
    const kinerjaPagesPages = ['rhk.php', 'rkb.php', 'lkh.php'];
    if (kinerjaPagesPages.includes(currentPage)) {
        const kinerjasMenu = document.getElementById('collapseKinerja');
        const kinerjasToggle = document.querySelector('[data-bs-target="#collapseKinerja"]');
        if (kinerjasMenu && kinerjasToggle) {
            kinerjasMenu.classList.add('show');
            kinerjasToggle.classList.remove('collapsed');
            kinerjasToggle.setAttribute('aria-expanded', 'true');
        }
    }
    
    // Auto-expand Laporan menu
    const laporanPages = ['generate_laporan.php', 'laporan_kinerja.php', 'laporan_statistik.php'];
    if (laporanPages.includes(currentPage)) {
        const laporanMenu = document.getElementById('collapseLaporan');
        const laporanToggle = document.querySelector('[data-bs-target="#collapseLaporan"]');
        if (laporanMenu && laporanToggle) {
            laporanMenu.classList.add('show');
            laporanToggle.classList.remove('collapsed');
            laporanToggle.setAttribute('aria-expanded', 'true');
        }
    }
    
    // Auto-expand Pengaturan menu
    const pengaturanPages = ['manajemen_user.php', 'pengaturan.php'];
    if (pengaturanPages.includes(currentPage)) {
        const pengaturanMenu = document.getElementById('collapsePengaturan');
        const pengaturanToggle = document.querySelector('[data-bs-target="#collapsePengaturan"]');
        if (pengaturanMenu && pengaturanToggle) {
            pengaturanMenu.classList.add('show');
            pengaturanToggle.classList.remove('collapsed');
            pengaturanToggle.setAttribute('aria-expanded', 'true');
        }
    }
    
    // Note: Logout confirmation is now handled by topbar.php
    // This ensures SweetAlert is properly loaded from a single source
});
</script>