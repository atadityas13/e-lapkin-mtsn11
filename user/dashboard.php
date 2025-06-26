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

session_start();
require_once __DIR__ . '/../template/session_user.php';
$page_title = "Dashboard";
include __DIR__ . '/../template/header.php';
include __DIR__ . '/../template/menu_user.php';
include __DIR__ . '/../template/topbar.php';
?>

<div id="layoutSidenav_content">
  <main>
    <div class="container-fluid px-4">
      <h1 class="mt-4">Dashboard</h1>

      <!-- Baris 2 Kolom: Selamat Datang & Info LKH -->
      <div class="row mb-4">
        <!-- Kolom Selamat Datang -->
        <div class="col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-header bg-primary text-white">
              <h3 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i> Selamat Datang di E-LAPKIN</h3>
            </div>
            <div class="card-body">
              <p class="lead mb-3">Aplikasi <b>E-LAPKIN</b> digunakan untuk pengelolaan Laporan Kinerja Pegawai di lingkungan MTsN 11 Majalengka.</p>
              <ul class="list-group list-group-flush mb-3">
                <li class="list-group-item"><i class="fas fa-check-circle text-success me-2"></i> Input RHK, RKB, dan LKH secara digital</li>
                <li class="list-group-item"><i class="fas fa-check-circle text-success me-2"></i> Rekap dan cetak laporan kinerja bulanan</li>
                <li class="list-group-item"><i class="fas fa-check-circle text-success me-2"></i> Penilaian kinerja lebih efektif dan efisien</li>
              </ul>
              <?php if ($is_admin): ?>
                <div class="alert alert-warning mt-4 mb-0" role="alert">
                  <h5 class="alert-heading"><i class="fas fa-shield-alt me-2"></i>Anda adalah Administrator</h5>
                  <p class="mb-0">Anda memiliki akses untuk mengelola data pegawai. Menu "Kelola Pegawai" hanya terlihat oleh Anda.</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Kolom Info LKH -->
        <div class="col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-header bg-info text-white">
              <h3 class="card-title mb-0"><i class="fas fa-pencil-alt me-2"></i> Aktivitas Hari Ini</h3>
            </div>
            <div class="card-body d-flex flex-column justify-content-between">
              <div>
                <p class="mb-3 fs-5">Sudahkah Anda mengisi kegiatan hari ini?</p>
                <p class="text-muted">Pastikan Anda melaporkan aktivitas harian Anda secara rutin untuk menjaga akurasi kinerja.</p>
              </div>
              <div>
                <a href="lkh.php" class="btn btn-primary w-100">
                  <i class="fas fa-book me-1"></i> Isi LKH Hari Ini
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- Tambahkan style khusus -->
      <style>
        .icon-circle {
          width: 42px;
          height: 42px;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          flex-shrink: 0;
        }
      </style>
      <!-- Detail Pegawai -->
      <div class="card shadow-sm">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="fas fa-id-card me-2"></i>Detail Pegawai</h5>
        </div>
        <div class="card-body">
          <div class="row g-3">

            <!-- KIRI -->
            <div class="col-md-6 d-flex align-items-start">
              <div class="icon-circle bg-success text-white me-3">
                <i class="fas fa-user fs-5"></i>
              </div>
              <div>
                <div class="fw-bold">Nama</div>
                <div class="text-muted"><?= htmlspecialchars($nama_pegawai) ?></div>
              </div>
            </div>

            <div class="col-md-6 d-flex align-items-start">
              <div class="icon-circle bg-primary text-white me-3">
                <i class="fas fa-building fs-5"></i>
              </div>
              <div>
                <div class="fw-bold">Unit Kerja</div>
                <div class="text-muted"><?= htmlspecialchars($unit_kerja_pegawai) ?></div>
              </div>
            </div>

            <div class="col-md-6 d-flex align-items-start">
              <div class="icon-circle bg-info text-white me-3">
                <i class="fas fa-address-card fs-5"></i>
              </div>
              <div>
                <div class="fw-bold">NIP</div>
                <div class="text-muted"><?= htmlspecialchars($nip_pegawai) ?></div>
              </div>
            </div>

            <div class="col-md-6 d-flex align-items-start">
              <div class="icon-circle bg-dark text-white me-3">
                <i class="fas fa-user-tie fs-5"></i>
              </div>
              <div>
                <div class="fw-bold">Pejabat Penilai</div>
                <div class="text-muted"><?= htmlspecialchars($nama_penilai_pegawai ?: '-') ?></div>
              </div>
            </div>

            <div class="col-md-6 d-flex align-items-start">
              <div class="icon-circle bg-warning text-white me-3">
                <i class="fas fa-briefcase fs-5"></i>
              </div>
              <div>
                <div class="fw-bold">Jabatan</div>
                <div class="text-muted"><?= htmlspecialchars($jabatan_pegawai) ?></div>
              </div>
            </div>

            <div class="col-md-6 d-flex align-items-start">
              <div class="icon-circle bg-secondary text-white me-3">
                <i class="fas fa-user-check fs-5"></i>
              </div>
              <div>
                <div class="fw-bold">NIP Penilai</div>
                <div class="text-muted"><?= htmlspecialchars($nip_penilai_pegawai ?: '-') ?></div>
              </div>
            </div>

          </div>
        </div>
      </div>
      <!-- End Detail Pegawai -->

    </div>
  </main>
  <?php include __DIR__ . '/../template/footer.php'; ?>
</div>
    </div>
  </div>
</div>

