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
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login.php");
    exit();
}
require_once '../config/database.php';

$id_pegawai = $_SESSION['id_pegawai'];
$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$current_year = (int)date('Y');

// Ambil periode aktif dari tabel pegawai (kolom tahun_aktif)
$periode_mulai = $periode_akhir = null;
$stmt_periode = $conn->prepare("SELECT tahun_aktif FROM pegawai WHERE id_pegawai = ?");
$stmt_periode->bind_param("i", $id_pegawai);
$stmt_periode->execute();
$stmt_periode->bind_result($tahun_aktif_pegawai);
$stmt_periode->fetch();
$stmt_periode->close();

if ($tahun_aktif_pegawai) {
    $periode_mulai = $periode_akhir = (int)$tahun_aktif_pegawai;
} else {
    $periode_mulai = $periode_akhir = (int)date('Y');
}

// Inisialisasi $years berdasarkan periode aktif rhk
$years = [];
for ($y = $periode_akhir; $y >= $periode_mulai; $y--) {
    $years[] = $y;
}

// Ambil NIP pegawai dari database untuk digunakan dalam nama file PDF
$nip_pegawai = '';
$stmt_nip = $conn->prepare("SELECT nip FROM pegawai WHERE id_pegawai = ?");
$stmt_nip->bind_param("i", $id_pegawai);
$stmt_nip->execute();
$stmt_nip->bind_result($nip_pegawai_db);
$stmt_nip->fetch();
$stmt_nip->close();

// Bersihkan NIP dari karakter yang tidak valid untuk nama file
$nama_file_nip = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nip_pegawai_db);


// Dummy: Cek file PDF sudah digenerate atau belum (simulasi, sesuaikan dengan implementasi PDF Anda)
function lkb_pdf_exists($id_pegawai, $bulan, $tahun, $nama_file_nip_param, $months_param) {
    // Asumsi: format nama file LKB adalah lkb_Bulan_Tahun_NIP.pdf
    // Jika format Anda berbeda, sesuaikan di sini
    $filename = "../generated/LKB_{$months_param[$bulan]}_{$tahun}_{$nama_file_nip_param}.pdf";
    return file_exists($filename);
}

function lkh_pdf_exists($id_pegawai, $bulan, $tahun, $nama_file_nip_param, $months_param) {
    // Sesuaikan dengan fungsi generate_lkh_pdf Anda
    $filename = "../generated/LKH_{$months_param[$bulan]}_{$tahun}_{$nama_file_nip_param}.pdf";
    return file_exists($filename);
}

$page_title = "Generate LKB & LKH";
include '../template/header.php';
include '../template/menu_user.php';
include '../template/topbar.php';
?>
<div id="layoutSidenav_content">
    <main>
        <div class="container-fluid px-4">
            <h1 class="mt-4">Daftar LKB & LKH</h1>
            <div class="row">
                <div class="col-lg-6">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-file-alt"></i> Daftar LKB
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Bulan</th>
                                            <th>Tahun</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($years as $tahun): ?>
                                        <?php for ($bulan = 1; $bulan <= 12; $bulan++): ?>
                                            <?php
                                            // Cek ada RKB
                                            $stmt = $conn->prepare("SELECT id_rkb, status_verval FROM rkb WHERE id_pegawai=? AND bulan=? AND tahun=?");
                                            $stmt->bind_param("iii", $id_pegawai, $bulan, $tahun);
                                            $stmt->execute();
                                            $stmt->store_result();
                                            $count_rkb = $stmt->num_rows;
                                            $id_rkb = null;
                                            $status_verval_rkb = null; // Ubah nama variabel untuk menghindari konflik
                                            if ($count_rkb > 0) {
                                                $stmt->bind_result($id_rkb, $status_verval_rkb);
                                                $stmt->fetch();
                                            }
                                            $stmt->close();

                                            if ($count_rkb == 0) {
                                                echo '<tr>
                                                        <td>' . $months[$bulan] . '</td>
                                                        <td>' . $tahun . '</td>
                                                        <td class="text-center"><span class="badge bg-secondary">Belum Ada</span></td>
                                                    </tr>';
                                                continue;
                                            }

                                            if ($status_verval_rkb === null || $status_verval_rkb === '' || $status_verval_rkb === 'draft') {
                                                echo '<tr>
                                                        <td>' . $months[$bulan] . '</td>
                                                        <td>' . $tahun . '</td>
                                                        <td class="text-center"><span class="badge bg-secondary">Belum Terkirim</span></td>
                                                    </tr>';
                                                continue;
                                            } elseif ($status_verval_rkb === 'diajukan') {
                                                echo '<tr>
                                                        <td>' . $months[$bulan] . '</td>
                                                        <td>' . $tahun . '</td>
                                                        <td class="text-center"><span class="badge bg-warning text-dark">Menunggu Approval</span></td>
                                                    </tr>';
                                                continue;
                                            } elseif ($status_verval_rkb === 'disetujui') {
                                                // Sudah disetujui, cek file PDF LKB dengan nama baru
                                                $pdf_exists_lkb = lkb_pdf_exists($id_pegawai, $bulan, $tahun, $nama_file_nip, $months);
                                                $lkb_filename_for_download = "LKB_{$months[$bulan]}_{$tahun}_{$nama_file_nip}.pdf"; // Nama file yang benar untuk di-download
                                                echo '<tr>
                                                        <td>' . $months[$bulan] . '</td>
                                                        <td>' . $tahun . '</td>
                                                        <td class="text-center">';
                                                if ($pdf_exists_lkb) {
                                                    echo '<a href="../generated/' . $lkb_filename_for_download . '" class="btn btn-success btn-sm" target="_blank">
                                                                <i class="fas fa-download"></i> Download
                                                            </a>
                                                            <button type="button" class="btn btn-warning btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#generateLkbModal" 
                                                                data-bulan="' . $bulan . '" data-tahun="' . $tahun . '">
                                                                <i class="fas fa-sync"></i> Generate Ulang
                                                            </button>';
                                                } else {
                                                    // Ganti dengan tombol modal
                                                    echo '<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#generateLkbModal" 
                                                                data-bulan="' . $bulan . '" data-tahun="' . $tahun . '">
                                                                <i class="fas fa-cogs"></i> Generate LKB
                                                            </button>';
                                                }
                                                echo '</td></tr>';
                                            }
                                            ?>
                                        <?php endfor; ?>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <small class="text-muted d-block mt-2">* LKB dapat digenerate jika RKB sudah diapprove pada bulan tersebut.</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-info text-white">
                            <i class="fas fa-file-alt"></i> Daftar LKH
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Bulan</th>
                                            <th>Tahun</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($years as $tahun): ?>
                                        <?php for ($bulan = 1; $bulan <= 12; $bulan++): ?>
                                            <?php
                                            // Cek ada LKH
                                            $stmt = $conn->prepare("SELECT id_lkh, status_verval FROM lkh WHERE id_pegawai=? AND MONTH(tanggal_lkh)=? AND YEAR(tanggal_lkh)=?");
                                            $stmt->bind_param("iii", $id_pegawai, $bulan, $tahun);
                                            $stmt->execute();
                                            $stmt->store_result();
                                            $count_lkh = $stmt->num_rows;
                                            $id_lkh = null;
                                            $status_verval_lkh = null; // Ubah nama variabel untuk menghindari konflik
                                            if ($count_lkh > 0) {
                                                $stmt->bind_result($id_lkh, $status_verval_lkh);
                                                $stmt->fetch();
                                            }
                                            $stmt->close();

                                            if ($count_lkh == 0) {
                                                echo '<tr>
                                                        <td>' . $months[$bulan] . '</td>
                                                        <td>' . $tahun . '</td>
                                                        <td class="text-center"><span class="badge bg-secondary">Belum Ada</span></td>
                                                    </tr>';
                                                continue;
                                            }

                                            if ($status_verval_lkh === null || $status_verval_lkh === '' || $status_verval_lkh === 'draft') {
                                                echo '<tr>
                                                        <td>' . $months[$bulan] . '</td>
                                                        <td>' . $tahun . '</td>
                                                        <td class="text-center"><span class="badge bg-secondary">Belum Terkirim</span></td>
                                                    </tr>';
                                                continue;
                                            } elseif ($status_verval_lkh === 'diajukan') {
                                                echo '<tr>
                                                        <td>' . $months[$bulan] . '</td>
                                                        <td>' . $tahun . '</td>
                                                        <td class="text-center"><span class="badge bg-warning text-dark">Menunggu Approval</span></td>
                                                    </tr>';
                                                continue;
                                            } elseif ($status_verval_lkh === 'disetujui') {
                                                // Sudah disetujui, cek file PDF LKH dengan nama baru
                                                $pdf_exists_lkh = lkh_pdf_exists($id_pegawai, $bulan, $tahun, $nama_file_nip, $months);
                                                $lkh_filename_for_download = "LKH_{$months[$bulan]}_{$tahun}_{$nama_file_nip}.pdf"; // Nama file yang benar untuk di-download
                                                echo '<tr>
                                                        <td>' . $months[$bulan] . '</td>
                                                        <td>' . $tahun . '</td>
                                                        <td class="text-center">';
                                                if ($pdf_exists_lkh) {
                                                    echo '<a href="../generated/' . $lkh_filename_for_download . '" class="btn btn-success btn-sm" target="_blank">
                                                                <i class="fas fa-download"></i> Download
                                                            </a>
                                                            <button type="button" class="btn btn-warning btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#generateLkhModal" 
                                                                data-bulan="' . $bulan . '" data-tahun="' . $tahun . '">
                                                                <i class="fas fa-sync"></i> Generate Ulang
                                                            </button>';
                                                } else {
                                                    // Ganti dengan tombol modal
                                                    echo '<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#generateLkhModal" 
                                                                data-bulan="' . $bulan . '" data-tahun="' . $tahun . '">
                                                                <i class="fas fa-cogs"></i> Generate LKH
                                                            </button>';
                                                }
                                                echo '</td></tr>';
                                            }
                                            ?>
                                        <?php endfor; ?>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <small class="text-muted d-block mt-2">* LKH dapat digenerate jika LKH sudah diapprove pada bulan tersebut.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <?php include __DIR__ . '/../template/footer.php'; ?>
</div>

<!-- Modal Generate LKB -->
<div class="modal fade" id="generateLkbModal" tabindex="-1" aria-labelledby="generateLkbModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="generateLkbModalLabel">Tentukan Tempat dan Tanggal Cetak LKB</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" id="generateLkbForm" action="">
        <div class="modal-body">
          <div class="mb-3">
            <label for="tempat_cetak_lkb" class="form-label">Tempat Cetak</label>
            <input type="text" class="form-control" id="tempat_cetak_lkb" name="tempat_cetak" value="Cingambul" required>
          </div>
          <div class="mb-3">
            <label for="tanggal_cetak_lkb" class="form-label">Tanggal Cetak</label>
            <input type="date" class="form-control" id="tanggal_cetak_lkb" name="tanggal_cetak" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Generate LKB</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Generate LKH -->
<div class="modal fade" id="generateLkhModal" tabindex="-1" aria-labelledby="generateLkhModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="generateLkhModalLabel">Tentukan Tempat dan Tanggal Cetak LKH</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" id="generateLkhForm" action="">
        <div class="modal-body">
          <div class="mb-3">
            <label for="tempat_cetak_lkh" class="form-label">Tempat Cetak</label>
            <input type="text" class="form-control" id="tempat_cetak_lkh" name="tempat_cetak" value="Cingambul" required>
          </div>
          <div class="mb-3">
            <label for="tanggal_cetak_lkh" class="form-label">Tanggal Cetak</label>
            <input type="date" class="form-control" id="tanggal_cetak_lkh" name="tanggal_cetak" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Generate LKH</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Script untuk mengisi data bulan dan tahun ke modal sebelum ditampilkan
var generateLkbModal = document.getElementById('generateLkbModal');
generateLkbModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget; // Tombol yang mengaktifkan modal
    var bulan = button.getAttribute('data-bulan');
    var tahun = button.getAttribute('data-tahun');

    var modalBulan = generateLkbModal.querySelector('#bulan');
    var modalTahun = generateLkbModal.querySelector('#tahun');
    modalBulan.value = bulan;
    modalTahun.value = tahun;
});

// Set form action when LKB modal is opened
document.getElementById('generateLkbModal').addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var bulan = button.getAttribute('data-bulan');
    var tahun = button.getAttribute('data-tahun');
    var form = document.getElementById('generateLkbForm');
    form.action = 'generate_lkb.php?bulan=' + bulan + '&tahun=' + tahun + '&aksi=generate';
});

// Set form action when LKH modal is opened
document.getElementById('generateLkhModal').addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var bulan = button.getAttribute('data-bulan');
    var tahun = button.getAttribute('data-tahun');
    var form = document.getElementById('generateLkhForm');
    form.action = 'generate_lkh.php?bulan=' + bulan + '&tahun=' + tahun + '&aksi=generate';
});
</script>
