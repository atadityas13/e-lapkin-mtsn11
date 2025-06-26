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
require_once __DIR__ . '/../template/session_admin.php';
require_once '../config/database.php';

$page_title = "Generate LKB & LKH";

// Filter pegawai
$selected_id_pegawai = $_GET['id_pegawai'] ?? '';

// Get data pegawai untuk filter
$pegawai_query = "SELECT id_pegawai, nip, nama, jabatan, unit_kerja FROM pegawai WHERE role = 'user' ORDER BY nama";
$pegawai_result = $conn->query($pegawai_query);

// Get data laporan berdasarkan pegawai yang dipilih
$selected_pegawai = null;
$years = [];
$nama_file_nip = '';

if ($selected_id_pegawai) {
    // Get data pegawai yang dipilih
    $stmt_pegawai = $conn->prepare("SELECT * FROM pegawai WHERE id_pegawai = ?");
    $stmt_pegawai->bind_param("i", $selected_id_pegawai);
    $stmt_pegawai->execute();
    $selected_pegawai = $stmt_pegawai->get_result()->fetch_assoc();
    $stmt_pegawai->close();
    
    if ($selected_pegawai) {
        // Ambil periode aktif dari tabel pegawai
        if ($selected_pegawai['tahun_aktif']) {
            $periode_mulai = $periode_akhir = (int)$selected_pegawai['tahun_aktif'];
        } else {
            $periode_mulai = $periode_akhir = (int)date('Y');
        }
        
        // Inisialisasi $years berdasarkan periode aktif
        for ($y = $periode_akhir; $y >= $periode_mulai; $y--) {
            $years[] = $y;
        }
        
        // Bersihkan NIP dari karakter yang tidak valid untuk nama file
        $nama_file_nip = preg_replace('/[^A-Za-z0-9_\-]/', '_', $selected_pegawai['nip']);
    }
}

$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Function untuk cek file PDF sudah ada atau belum
function lkb_pdf_exists($id_pegawai, $bulan, $tahun, $nama_file_nip_param, $months_param) {
    $filename = "../generated/LKB_{$months_param[$bulan]}_{$tahun}_{$nama_file_nip_param}.pdf";
    return file_exists($filename);
}

function lkh_pdf_exists($id_pegawai, $bulan, $tahun, $nama_file_nip_param, $months_param) {
    $filename = "../generated/LKH_{$months_param[$bulan]}_{$tahun}_{$nama_file_nip_param}.pdf";
    return file_exists($filename);
}

include __DIR__ . '/../template/header.php';
include __DIR__ . '/../template/menu_admin.php';
include __DIR__ . '/../template/topbar.php';
?>

<div id="layoutSidenav_content">
    <main>
        <div class="container-fluid px-4">
            <h1 class="mt-4 mb-3"><i class="fas fa-file-pdf"></i> Generate LKB & LKH</h1>

            <!-- Filter Pegawai -->
            <div class="card shadow-lg mb-4 border-0">
                <div class="card-header bg-gradient-info text-white border-0">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="mb-0">
                                <i class="fas fa-user-search me-2"></i>
                                Pilih Pegawai
                            </h5>
                            <small class="opacity-75">Cari dan pilih pegawai untuk generate LKB & LKH</small>
                        </div>
                    </div>
                </div>
                <div class="card-body bg-light">
                    <form method="GET" id="formPilihPegawai">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-9">
                                <label class="form-label fw-bold text-primary">
                                    <i class="fas fa-user-tie me-1"></i>
                                    Pegawai (Total: <?php echo $pegawai_result->num_rows; ?> pegawai)
                                </label>
                                
                                <select name="id_pegawai" class="form-select form-select-lg border-primary" required>
                                    <option value="">-- Pilih Pegawai --</option>
                                    <?php 
                                    if ($pegawai_result->num_rows > 0) {
                                        $pegawai_result->data_seek(0);
                                        while ($pegawai = $pegawai_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $pegawai['id_pegawai']; ?>" 
                                                <?php echo ($selected_id_pegawai == $pegawai['id_pegawai']) ? 'selected' : ''; ?>>
                                            👤 <?php echo htmlspecialchars($pegawai['nama']); ?> 
                                            - <?php echo htmlspecialchars($pegawai['jabatan']); ?> 
                                            (<?php echo htmlspecialchars($pegawai['unit_kerja']); ?>) 
                                            - NIP: <?php echo htmlspecialchars($pegawai['nip']); ?>
                                        </option>
                                    <?php 
                                        endwhile;
                                    } else {
                                        echo '<option value="">Tidak ada pegawai ditemukan</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary btn-lg w-100 shadow">
                                    <i class="fas fa-search me-2"></i>
                                    <span class="fw-bold">Lihat Data</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($selected_pegawai): ?>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-primary text-white">
                                <i class="fas fa-file-alt"></i> Daftar LKB - <?php echo htmlspecialchars($selected_pegawai['nama']); ?>
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
                                                $stmt->bind_param("iii", $selected_id_pegawai, $bulan, $tahun);
                                                $stmt->execute();
                                                $stmt->store_result();
                                                $count_rkb = $stmt->num_rows;
                                                $id_rkb = null;
                                                $status_verval_rkb = null;
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
                                                    // Sudah disetujui, cek file PDF LKB
                                                    $pdf_exists_lkb = lkb_pdf_exists($selected_id_pegawai, $bulan, $tahun, $nama_file_nip, $months);
                                                    $lkb_filename_for_download = "LKB_{$months[$bulan]}_{$tahun}_{$nama_file_nip}.pdf";
                                                    echo '<tr>
                                                            <td>' . $months[$bulan] . '</td>
                                                            <td>' . $tahun . '</td>
                                                            <td class="text-center">';
                                                    if ($pdf_exists_lkb) {
                                                        echo '<a href="../generated/' . $lkb_filename_for_download . '" class="btn btn-success btn-sm" target="_blank">
                                                                    <i class="fas fa-download"></i> Download
                                                                </a>
                                                                <a href="generate_lkb.php?id_pegawai=' . $selected_id_pegawai . '&bulan=' . $bulan . '&tahun=' . $tahun . '&aksi=generate" class="btn btn-warning btn-sm ms-1">
                                                                    <i class="fas fa-sync"></i> Generate Ulang
                                                                </a>';
                                                    } else {
                                                        echo '<a href="generate_lkb.php?id_pegawai=' . $selected_id_pegawai . '&bulan=' . $bulan . '&tahun=' . $tahun . '&aksi=generate" class="btn btn-primary btn-sm">
                                                                    <i class="fas fa-cogs"></i> Generate LKB
                                                                </a>';
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
                                <i class="fas fa-file-alt"></i> Daftar LKH - <?php echo htmlspecialchars($selected_pegawai['nama']); ?>
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
                                                $stmt->bind_param("iii", $selected_id_pegawai, $bulan, $tahun);
                                                $stmt->execute();
                                                $stmt->store_result();
                                                $count_lkh = $stmt->num_rows;
                                                $id_lkh = null;
                                                $status_verval_lkh = null;
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
                                                    // Sudah disetujui, cek file PDF LKH
                                                    $pdf_exists_lkh = lkh_pdf_exists($selected_id_pegawai, $bulan, $tahun, $nama_file_nip, $months);
                                                    $lkh_filename_for_download = "LKH_{$months[$bulan]}_{$tahun}_{$nama_file_nip}.pdf";
                                                    echo '<tr>
                                                            <td>' . $months[$bulan] . '</td>
                                                            <td>' . $tahun . '</td>
                                                            <td class="text-center">';
                                                    if ($pdf_exists_lkh) {
                                                        echo '<a href="../generated/' . $lkh_filename_for_download . '" class="btn btn-success btn-sm" target="_blank">
                                                                    <i class="fas fa-download"></i> Download
                                                                </a>
                                                                <a href="generate_lkh.php?id_pegawai=' . $selected_id_pegawai . '&bulan=' . $bulan . '&tahun=' . $tahun . '&aksi=generate" class="btn btn-warning btn-sm ms-1">
                                                                    <i class="fas fa-sync"></i> Generate Ulang
                                                                </a>';
                                                    } else {
                                                        echo '<a href="generate_lkh.php?id_pegawai=' . $selected_id_pegawai . '&bulan=' . $bulan . '&tahun=' . $tahun . '&aksi=generate" class="btn btn-primary btn-sm">
                                                                    <i class="fas fa-cogs"></i> Generate LKH
                                                                </a>';
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
            <?php else: ?>
                <!-- Pesan ketika belum memilih pegawai -->
                <div class="card shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-file-pdf fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">Silakan pilih pegawai untuk generate LKB & LKH</h5>
                        <p class="text-muted">Gunakan filter di atas untuk memilih pegawai yang akan digenerate LKB & LKH-nya.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <?php include __DIR__ . '/../template/footer.php'; ?>
</div>

<!-- Custom CSS -->
<style>
.bg-gradient-info { background: linear-gradient(45deg, #17a2b8, #138496) !important; }
</style>