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

$page_title = "Laporan Kinerja Pegawai";
$message = '';
$message_type = '';

// Filter pegawai
$selected_id_pegawai = $_GET['id_pegawai'] ?? '';
$filter_tahun = $_GET['tahun'] ?? '';

// Get data pegawai untuk filter
$pegawai_query = "SELECT id_pegawai, nip, nama, jabatan, unit_kerja FROM pegawai WHERE role = 'user' ORDER BY nama";
$pegawai_result = $conn->query($pegawai_query);

if (!$pegawai_result) {
    die('Error in pegawai query: ' . $conn->error);
}

// Get data laporan berdasarkan pegawai yang dipilih
$selected_pegawai = null;
$data_for_display = [];
$final_pdf_data_rows = [];

if ($selected_id_pegawai) {
    // Get data pegawai yang dipilih
    $stmt_pegawai = $conn->prepare("SELECT * FROM pegawai WHERE id_pegawai = ?");
    $stmt_pegawai->bind_param("i", $selected_id_pegawai);
    $stmt_pegawai->execute();
    $selected_pegawai = $stmt_pegawai->get_result()->fetch_assoc();
    $stmt_pegawai->close();
    
    if ($selected_pegawai) {
        // Set default tahun jika belum dipilih
        if (!$filter_tahun) {
            $filter_tahun = $selected_pegawai['tahun_aktif'] ?? date('Y');
        }
        
        // Data untuk dropdown bulan
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        // Fungsi untuk mendapatkan nama hari dalam Bahasa Indonesia
        function get_day_name($date_string) {
            $timestamp = strtotime($date_string);
            $day_of_week = date('N', $timestamp);
            $day_names = [
                1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
                5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'
            ];
            return $day_names[$day_of_week];
        }

        // Generate data laporan
        $no_global = 1;

        for ($bulan_num = 1; $bulan_num <= 12; $bulan_num++) {
            $stmt_rhk_month = $conn->prepare("SELECT DISTINCT rhk.id_rhk, rhk.nama_rhk
                                             FROM rhk
                                             JOIN rkb ON rhk.id_rhk = rkb.id_rhk
                                             WHERE rhk.id_pegawai = ? AND rkb.bulan = ? AND rkb.tahun = ?
                                             ORDER BY rhk.id_rhk ASC");
            $stmt_rhk_month->bind_param("iii", $selected_id_pegawai, $bulan_num, $filter_tahun);
            $stmt_rhk_month->execute();
            $result_rhk_month = $stmt_rhk_month->get_result();

            $rhk_count_in_month = $result_rhk_month->num_rows;

            if ($rhk_count_in_month == 0) {
                $data_for_display[] = [
                    'no' => $no_global++,
                    'bulan' => $months[$bulan_num],
                    'rhk_terkait' => 'Belum ada RHK.',
                    'uraian_kegiatan_rkb' => '',
                    'target_kuantitas' => '',
                    'target_satuan' => '',
                    'tanggal_lkh' => 'Belum ada realisasi.',
                    'nama_kegiatan_harian' => '',
                    'uraian_kegiatan_lkh' => '',
                    'lampiran' => 'Nihil'
                ];
                continue;
            }

            while ($rhk_item = $result_rhk_month->fetch_assoc()) {
                $stmt_rkb = $conn->prepare("SELECT rkb.id_rkb, rkb.uraian_kegiatan, rkb.kuantitas AS target_kuantitas, rkb.satuan AS target_satuan
                                            FROM rkb
                                            WHERE rkb.id_rhk = ? AND rkb.bulan = ? AND rkb.tahun = ? AND rkb.id_pegawai = ?
                                            ORDER BY rkb.id_rkb ASC");
                $stmt_rkb->bind_param("iiii", $rhk_item['id_rhk'], $bulan_num, $filter_tahun, $selected_id_pegawai);
                $stmt_rkb->execute();
                $result_rkb = $stmt_rkb->get_result();

                while ($rkb_item_detail = $result_rkb->fetch_assoc()) {
                    $stmt_lkh = $conn->prepare("SELECT tanggal_lkh, nama_kegiatan_harian, uraian_kegiatan_lkh, lampiran
                                                FROM lkh
                                                WHERE id_rkb = ?
                                                ORDER BY tanggal_lkh ASC");
                    $stmt_lkh->bind_param("i", $rkb_item_detail['id_rkb']);
                    $stmt_lkh->execute();
                    $result_lkh = $stmt_lkh->get_result();
                    
                    $lkh_count_in_rkb = $result_lkh->num_rows;

                    if ($lkh_count_in_rkb == 0) {
                        $data_for_display[] = [
                            'no' => $no_global++,
                            'bulan' => $months[$bulan_num],
                            'rhk_terkait' => htmlspecialchars($rhk_item['nama_rhk']),
                            'uraian_kegiatan_rkb' => htmlspecialchars($rkb_item_detail['uraian_kegiatan']),
                            'target_kuantitas' => htmlspecialchars($rkb_item_detail['target_kuantitas']),
                            'target_satuan' => htmlspecialchars($rkb_item_detail['target_satuan']),
                            'tanggal_lkh' => 'Belum ada realisasi.',
                            'nama_kegiatan_harian' => '',
                            'uraian_kegiatan_lkh' => '',
                            'lampiran' => 'Nihil'
                        ];
                    } else {
                        while ($lkh_row = $result_lkh->fetch_assoc()) {
                            $tanggal_lkh_formatted = get_day_name($lkh_row['tanggal_lkh']) . ', ' . date('d-m-Y', strtotime($lkh_row['tanggal_lkh']));
                            $lampiran_status = !empty($lkh_row['lampiran']) ? 'Ada' : 'Nihil';

                            $data_for_display[] = [
                                'no' => $no_global++,
                                'bulan' => $months[$bulan_num],
                                'rhk_terkait' => htmlspecialchars($rhk_item['nama_rhk']),
                                'uraian_kegiatan_rkb' => htmlspecialchars($rkb_item_detail['uraian_kegiatan']),
                                'target_kuantitas' => htmlspecialchars($rkb_item_detail['target_kuantitas']),
                                'target_satuan' => htmlspecialchars($rkb_item_detail['target_satuan']),
                                'tanggal_lkh' => htmlspecialchars($tanggal_lkh_formatted),
                                'nama_kegiatan_harian' => htmlspecialchars($lkh_row['nama_kegiatan_harian'] ?? ''),
                                'uraian_kegiatan_lkh' => htmlspecialchars($lkh_row['uraian_kegiatan_lkh']),
                                'lampiran' => $lampiran_status
                            ];
                        }
                    }
                    $stmt_lkh->close();
                }
                $stmt_rkb->close();
            }
            $stmt_rhk_month->close();
        }

        // Generate data untuk PDF dengan rowspan
        $final_pdf_data_rows = [];
        $previous_bulan = null;
        $previous_rhk_terkait = null;
        $previous_uraian_rkb = null;
        $no_counter = 1;

        foreach ($data_for_display as $i => $row) {
            $currentRow = [];
            $currentRow[] = $no_counter++;

            // Kolom Bulan
            if ($row['bulan'] !== $previous_bulan) {
                $rowSpanBulan = 0;
                foreach ($data_for_display as $j => $future_row) {
                    if ($j >= $i && $future_row['bulan'] === $row['bulan']) {
                        $rowSpanBulan++;
                    } else if ($j >= $i && $future_row['bulan'] !== $row['bulan']) {
                        break;
                    }
                }
                $currentRow[] = [ 'content' => $row['bulan'], 'rowSpan' => $rowSpanBulan ];
                $previous_bulan = $row['bulan'];
                $previous_rhk_terkait = null;
                $previous_uraian_rkb = null;
            } else {
                $currentRow[] = '';
            }

            // Kolom RHK Terkait
            if ($row['rhk_terkait'] !== $previous_rhk_terkait || $row['bulan'] !== $previous_bulan) {
                $rowSpanRHK = 0;
                foreach ($data_for_display as $j => $future_row) {
                    if ($j >= $i && $future_row['bulan'] === $row['bulan'] && $future_row['rhk_terkait'] === $row['rhk_terkait']) {
                        $rowSpanRHK++;
                    } else if ($j >= $i && ($future_row['bulan'] !== $row['bulan'] || $future_row['rhk_terkait'] !== $row['rhk_terkait'])) {
                        break;
                    }
                }
                $currentRow[] = [ 'content' => $row['rhk_terkait'], 'rowSpan' => $rowSpanRHK ];
                $previous_rhk_terkait = $row['rhk_terkait'];
                $previous_uraian_rkb = null;
            } else {
                $currentRow[] = '';
            }

            // Kolom Uraian Kegiatan RKB
            if ($row['uraian_kegiatan_rkb'] !== $previous_uraian_rkb || $row['rhk_terkait'] !== $previous_rhk_terkait || $row['bulan'] !== $previous_bulan) {
                $rowSpanRKB = 0;
                foreach ($data_for_display as $j => $future_row) {
                    if ($j >= $i && $future_row['bulan'] === $row['bulan'] && $future_row['rhk_terkait'] === $row['rhk_terkait'] && $future_row['uraian_kegiatan_rkb'] === $row['uraian_kegiatan_rkb']) {
                        $rowSpanRKB++;
                    } else if ($j >= $i && ($future_row['bulan'] !== $row['bulan'] || $future_row['rhk_terkait'] !== $row['rhk_terkait'] || $future_row['uraian_kegiatan_rkb'] !== $row['uraian_kegiatan_rkb'])) {
                        break;
                    }
                }
                $currentRow[] = [ 'content' => $row['uraian_kegiatan_rkb'], 'rowSpan' => $rowSpanRKB ];
                $currentRow[] = [ 'content' => $row['target_kuantitas'], 'rowSpan' => $rowSpanRKB ];
                $currentRow[] = [ 'content' => $row['target_satuan'], 'rowSpan' => $rowSpanRKB ];
                $previous_uraian_rkb = $row['uraian_kegiatan_rkb'];
            } else {
                $currentRow[] = '';
                $currentRow[] = '';
                $currentRow[] = '';
            }

            // Kolom LKH
            $currentRow[] = $row['tanggal_lkh'];
            $currentRow[] = $row['nama_kegiatan_harian'];
            $currentRow[] = $row['uraian_kegiatan_lkh'];
            $currentRow[] = $row['lampiran'];

            $final_pdf_data_rows[] = $currentRow;
        }
    }
}

$json_report_data = json_encode($final_pdf_data_rows);

include __DIR__ . '/../template/header.php';
include __DIR__ . '/../template/menu_admin.php';
include __DIR__ . '/../template/topbar.php';
?>

<div id="layoutSidenav_content">
    <main>
        <div class="container-fluid px-4">
            <h1 class="mt-4 mb-3"><i class="fas fa-chart-line"></i> Laporan Kinerja Pegawai</h1>
            <p class="lead">Kelola dan lihat laporan kinerja tahunan untuk setiap pegawai.</p>

            <!-- Filter Pegawai -->
            <div class="card shadow-lg mb-4 border-0">
                <div class="card-header bg-gradient-info text-white border-0">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="mb-0">
                                <i class="fas fa-user-search me-2"></i>
                                Pilih Pegawai
                            </h5>
                            <small class="opacity-75">Cari dan pilih pegawai untuk melihat laporan kinerja</small>
                        </div>
                        <div class="col-auto">
                            <div class="avatar-group">
                                <div class="avatar avatar-sm bg-white text-info rounded-circle">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
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
                                
                                <!-- Search Input -->
                                <div class="position-relative mb-2">
                                    <input type="text" 
                                           id="searchPegawai" 
                                           class="form-control form-control-lg border-primary" 
                                           placeholder="🔍 Ketik untuk mencari nama, NIP, jabatan, atau unit kerja..."
                                           style="box-shadow: 0 0.125rem 0.25rem rgba(0, 123, 255, 0.075);">
                                    <div class="position-absolute top-50 end-0 translate-middle-y me-3">
                                        <i class="fas fa-search text-muted"></i>
                                    </div>
                                </div>
                                
                                <!-- Select Dropdown -->
                                <select name="id_pegawai" 
                                        class="form-select form-select-lg border-primary d-none" 
                                        id="selectPegawai" 
                                        required 
                                        style="box-shadow: 0 0.125rem 0.25rem rgba(0, 123, 255, 0.075);">
                                    <option value="">-- Pilih Pegawai --</option>
                                    <?php 
                                    if ($pegawai_result->num_rows > 0) {
                                        $pegawai_result->data_seek(0);
                                        while ($pegawai = $pegawai_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $pegawai['id_pegawai']; ?>" 
                                                <?php echo ($selected_id_pegawai == $pegawai['id_pegawai']) ? 'selected' : ''; ?>
                                                data-nama="<?php echo htmlspecialchars($pegawai['nama']); ?>"
                                                data-jabatan="<?php echo htmlspecialchars($pegawai['jabatan']); ?>"
                                                data-unit="<?php echo htmlspecialchars($pegawai['unit_kerja']); ?>"
                                                data-nip="<?php echo htmlspecialchars($pegawai['nip']); ?>"
                                                data-search="<?php echo strtolower(htmlspecialchars($pegawai['nama'] . ' ' . $pegawai['nip'] . ' ' . $pegawai['jabatan'] . ' ' . $pegawai['unit_kerja'])); ?>">
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
                                
                                <!-- Search Results -->
                                <div id="searchResults" class="search-results d-none">
                                    <!-- Results will be populated by JavaScript -->
                                </div>
                                
                                <!-- Selected Pegawai Display -->
                                <div id="selectedPegawai" class="selected-pegawai <?php echo $selected_pegawai ? '' : 'd-none'; ?>">
                                    <?php if ($selected_pegawai): ?>
                                        <div class="card bg-success text-white border-0 shadow-sm">
                                            <div class="card-body p-3">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1">
                                                            <i class="fas fa-user-check me-2"></i>
                                                            <?php echo htmlspecialchars($selected_pegawai['nama']); ?>
                                                        </h6>
                                                        <small class="opacity-75">
                                                            <?php echo htmlspecialchars($selected_pegawai['jabatan']); ?> - 
                                                            <?php echo htmlspecialchars($selected_pegawai['unit_kerja']); ?> - 
                                                            NIP: <?php echo htmlspecialchars($selected_pegawai['nip']); ?>
                                                        </small>
                                                    </div>
                                                    <button type="button" class="btn btn-light btn-sm" onclick="clearSelection()">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" 
                                        class="btn btn-primary btn-lg w-100 shadow" 
                                        id="btnLihatLaporan"
                                        <?php echo !$selected_pegawai ? 'disabled' : ''; ?>
                                        style="background: linear-gradient(45deg, #007bff, #0056b3);">
                                    <i class="fas fa-chart-line me-2"></i>
                                    <span class="fw-bold">Lihat Laporan</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($selected_pegawai): ?>
                <!-- Filter Tahun -->
                <div class="card shadow-lg mb-4 border-0">
                    <div class="card-header bg-gradient-warning text-white border-0">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-alt me-2"></i>
                            Filter Periode Laporan
                        </h5>
                    </div>
                    <div class="card-body bg-light">
                        <form method="GET" class="row g-3 align-items-end">
                            <input type="hidden" name="id_pegawai" value="<?php echo $selected_id_pegawai; ?>">
                            
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-primary">
                                    <i class="fas fa-calendar-year me-1"></i>
                                    Tahun
                                </label>
                                <select name="tahun" class="form-select border-primary">
                                    <?php for ($year = date('Y'); $year >= 2020; $year--): ?>
                                        <option value="<?php echo $year; ?>" 
                                                <?php echo ($filter_tahun == $year) ? 'selected' : ''; ?>>
                                            📅 <?php echo $year; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-warning w-100 shadow">
                                    <i class="fas fa-filter me-2"></i>
                                    Filter Laporan
                                </button>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="alert alert-info mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Menampilkan laporan kinerja untuk <strong><?php echo htmlspecialchars($selected_pegawai['nama']); ?></strong> 
                                    pada tahun <strong><?php echo $filter_tahun; ?></strong>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Laporan -->
                <div class="card mb-4" id="print-area">
                    <div class="card-header bg-success text-white">
                        <div class="d-flex align-items-center">
                            <span class="flex-grow-1">
                                <i class="fas fa-chart-line me-2"></i>
                                Laporan Kinerja Pegawai - Tahun <?php echo $filter_tahun; ?>
                            </span>
                            <button class="btn btn-sm btn-light d-print-none me-2" id="btnPrintLapkin">
                                <i class="fas fa-print"></i> Cetak Laporan
                            </button>
                            <button class="btn btn-sm btn-danger d-print-none" id="btnExportPdf">
                                <i class="fas fa-file-pdf"></i> Export PDF
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-striped mb-4">
                            <thead>
                                <tr>
                                    <th colspan="2">Informasi Pegawai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td width="20%">Nama Pegawai</td>
                                    <td><?php echo htmlspecialchars($selected_pegawai['nama']); ?></td>
                                </tr>
                                <tr>
                                    <td>NIP</td>
                                    <td><?php echo htmlspecialchars($selected_pegawai['nip']); ?></td>
                                </tr>
                                <tr>
                                    <td>Jabatan</td>
                                    <td><?php echo htmlspecialchars($selected_pegawai['jabatan']); ?></td>
                                </tr>
                                <tr>
                                    <td>Unit Kerja</td>
                                    <td><?php echo htmlspecialchars($selected_pegawai['unit_kerja']); ?></td>
                                </tr>
                                <tr>
                                    <td>Tahun</td>
                                    <td><?php echo $filter_tahun; ?></td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <h5>Daftar Rencana Kegiatan Bulanan (RKB) dan Realisasi Harian (LKH) Tahun <?php echo $filter_tahun; ?></h5>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle" id="laporanTahunanTable">
                                <thead>
                                    <tr>
                                        <th rowspan="2"><center>No</center></th>
                                        <th rowspan="2"><center>Bulan</center></th>
                                        <th rowspan="2"><center>RHK Terkait</center></th>
                                        <th rowspan="2"><center>Uraian Kegiatan RKB</center></th>
                                        <th colspan="2"><center>Target RKB</center></th>
                                        <th colspan="4"><center>Realisasi LKH</center></th>
                                    </tr>
                                    <tr>
                                        <th><center>Kuantitas</center></th>
                                        <th><center>Satuan</center></th>
                                        <th><center>Tanggal LKH</center></th>
                                        <th><center>Nama Kegiatan Harian</center></th>
                                        <th><center>Uraian Kegiatan LKH</center></th>
                                        <th><center>Lampiran</center></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (!empty($data_for_display)) {
                                        // Hitung rowspan untuk setiap kelompok
                                        $rowspan_map = [];
                                        $total_rows = count($data_for_display);
                                        
                                        for ($i = 0; $i < $total_rows; $i++) {
                                            $row = $data_for_display[$i];
                                            $bulan = $row['bulan'];
                                            $rhk = $row['rhk_terkait'];
                                            $rkb = $row['uraian_kegiatan_rkb'];

                                            // Hitung rowspan bulan
                                            if (!isset($rowspan_map['bulan'][$bulan])) {
                                                $rowspan_map['bulan'][$bulan] = 0;
                                                for ($j = $i; $j < $total_rows; $j++) {
                                                    if ($data_for_display[$j]['bulan'] === $bulan) {
                                                        $rowspan_map['bulan'][$bulan]++;
                                                    }
                                                }
                                            }
                                            
                                            // Hitung rowspan rhk dalam bulan
                                            $rhk_key = $bulan . '||' . $rhk;
                                            if (!isset($rowspan_map['rhk'][$rhk_key])) {
                                                $rowspan_map['rhk'][$rhk_key] = 0;
                                                for ($j = $i; $j < $total_rows; $j++) {
                                                    if ($data_for_display[$j]['bulan'] === $bulan && $data_for_display[$j]['rhk_terkait'] === $rhk) {
                                                        $rowspan_map['rhk'][$rhk_key]++;
                                                    }
                                                }
                                            }
                                            
                                            // Hitung rowspan rkb dalam rhk dan bulan
                                            $rkb_key = $bulan . '||' . $rhk . '||' . $rkb;
                                            if (!isset($rowspan_map['rkb'][$rkb_key])) {
                                                $rowspan_map['rkb'][$rkb_key] = 0;
                                                for ($j = $i; $j < $total_rows; $j++) {
                                                    if (
                                                        $data_for_display[$j]['bulan'] === $bulan &&
                                                        $data_for_display[$j]['rhk_terkait'] === $rhk &&
                                                        $data_for_display[$j]['uraian_kegiatan_rkb'] === $rkb
                                                    ) {
                                                        $rowspan_map['rkb'][$rkb_key]++;
                                                    }
                                                }
                                            }
                                        }

                                        // Cetak tabel
                                        $no_rkb_global_html = 1;
                                        $printed_bulan = [];
                                        $printed_rhk = [];
                                        $printed_rkb = [];
                                        
                                        for ($i = 0; $i < $total_rows; $i++) {
                                            $row_html = $data_for_display[$i];
                                            $bulan = $row_html['bulan'];
                                            $rhk = $row_html['rhk_terkait'];
                                            $rkb = $row_html['uraian_kegiatan_rkb'];
                                            $rhk_key = $bulan . '||' . $rhk;
                                            $rkb_key = $bulan . '||' . $rhk . '||' . $rkb;
                                            
                                            echo '<tr>';
                                            echo '<td><center>' . $no_rkb_global_html++ . '</center></td>';

                                            // Bulan
                                            if (!isset($printed_bulan[$bulan])) {
                                                echo '<td rowspan="' . $rowspan_map['bulan'][$bulan] . '"><center>' . htmlspecialchars($bulan) . '</center></td>';
                                                $printed_bulan[$bulan] = true;
                                            }

                                            // RHK
                                            if (!isset($printed_rhk[$rhk_key])) {
                                                echo '<td rowspan="' . $rowspan_map['rhk'][$rhk_key] . '">' . htmlspecialchars($rhk) . '</td>';
                                                $printed_rhk[$rhk_key] = true;
                                            }

                                            // RKB, Target Kuantitas, Target Satuan
                                            if (!isset($printed_rkb[$rkb_key])) {
                                                echo '<td rowspan="' . $rowspan_map['rkb'][$rkb_key] . '">' . htmlspecialchars($rkb) . '</td>';
                                                echo '<td rowspan="' . $rowspan_map['rkb'][$rkb_key] . '"><center>' . htmlspecialchars($row_html['target_kuantitas']) . '</center></td>';
                                                echo '<td rowspan="' . $rowspan_map['rkb'][$rkb_key] . '"><center>' . htmlspecialchars($row_html['target_satuan']) . '</center></td>';
                                                $printed_rkb[$rkb_key] = true;
                                            }

                                            // LKH
                                            echo '<td>' . htmlspecialchars($row_html['tanggal_lkh']) . '</td>';
                                            echo '<td>' . htmlspecialchars($row_html['nama_kegiatan_harian'] ?? '') . '</td>';
                                            echo '<td>' . htmlspecialchars($row_html['uraian_kegiatan_lkh']) . '</td>';
                                            
                                            // Lampiran with view functionality
                                            if ($row_html['lampiran'] === 'Ada') {
                                                // Find the actual LKH record to get lampiran filename
                                                $lkh_lampiran_query = $conn->prepare("
                                                    SELECT l.id_lkh, l.lampiran 
                                                    FROM lkh l 
                                                    JOIN rkb r ON l.id_rkb = r.id_rkb 
                                                    WHERE l.tanggal_lkh = ? AND l.uraian_kegiatan_lkh = ? AND l.id_pegawai = ?
                                                    ORDER BY l.id_lkh DESC LIMIT 1
                                                ");
                                                // Extract date from formatted string
                                                $date_parts = explode(', ', $row_html['tanggal_lkh']);
                                                if (count($date_parts) == 2) {
                                                    $date_formatted = date('Y-m-d', strtotime($date_parts[1]));
                                                    $lkh_lampiran_query->bind_param("ssi", $date_formatted, $row_html['uraian_kegiatan_lkh'], $selected_id_pegawai);
                                                    $lkh_lampiran_query->execute();
                                                    $lkh_lampiran_data = $lkh_lampiran_query->get_result()->fetch_assoc();
                                                    $lkh_lampiran_query->close();
                                                    
                                                    if ($lkh_lampiran_data && !empty($lkh_lampiran_data['lampiran'])) {
                                                        echo '<td class="text-center">';
                                                        echo '<div class="btn-group-vertical" role="group">';
                                                        echo '<button type="button" class="btn btn-sm btn-info mb-1" ';
                                                        echo 'onclick="viewLampiran(\'' . htmlspecialchars($lkh_lampiran_data['lampiran']) . '\', \'' . htmlspecialchars($row_html['nama_kegiatan_harian']) . '\')" ';
                                                        echo 'title="Lihat Lampiran">';
                                                        echo '<i class="fas fa-eye"></i> Lihat';
                                                        echo '</button>';
                                                        echo '<a href="../uploads/lkh/' . htmlspecialchars($lkh_lampiran_data['lampiran']) . '" ';
                                                        echo 'class="btn btn-sm btn-success" download title="Download Lampiran">';
                                                        echo '<i class="fas fa-download"></i> Download';
                                                        echo '</a>';
                                                        echo '</div>';
                                                        echo '</td>';
                                                    } else {
                                                        echo '<td class="text-center"><span class="badge bg-secondary"><i class="fas fa-minus"></i> Tidak ada</span></td>';
                                                    }
                                                } else {
                                                    echo '<td class="text-center"><span class="badge bg-secondary"><i class="fas fa-minus"></i> Tidak ada</span></td>';
                                                }
                                            } else {
                                                echo '<td class="text-center"><span class="badge bg-secondary"><i class="fas fa-minus"></i> Tidak ada</span></td>';
                                            }
                                            
                                            echo '</tr>';
                                        }
                                    } else {
                                        echo '<tr><td colspan="10" class="text-center text-muted">Belum ada data laporan untuk pegawai ini.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Pesan ketika belum memilih pegawai -->
                <div class="card shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-chart-line fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">Silakan pilih pegawai untuk melihat laporan kinerja</h5>
                        <p class="text-muted">Gunakan filter di atas untuk memilih pegawai yang akan dilihat laporan kinerjanya.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <?php include __DIR__ . '/../template/footer.php'; ?>
</div>

<!-- Modal untuk melihat lampiran -->
<div class="modal fade" id="modalViewLampiran" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="modalLampiranTitle">
                    <i class="fas fa-file-alt me-2"></i>Lampiran LKH
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="lampiranContent" class="text-center">
                    <!-- Content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Tutup
                </button>
                <a id="downloadLink" href="#" class="btn btn-success d-none" download>
                    <i class="fas fa-download me-1"></i>Download File
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
@keyframes slideInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

.bg-gradient-info { background: linear-gradient(45deg, #17a2b8, #138496) !important; }
.bg-gradient-warning { background: linear-gradient(45deg, #ffc107, #e0a800) !important; }

.search-results {
    max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6;
    border-radius: 0.375rem; background: white; z-index: 1000;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.search-result-item {
    padding: 12px 16px; border-bottom: 1px solid #f8f9fa;
    cursor: pointer; transition: all 0.2s ease;
}

.search-result-item:hover { background-color: #f8f9fa; border-left: 4px solid #007bff; }
.search-highlight { background-color: #fff3cd; font-weight: bold; padding: 1px 3px; border-radius: 2px; }

/* Lampiran styles */
.lampiran-preview {
    max-width: 100%;
    max-height: 400px;
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.file-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.btn-group-vertical .btn {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.modal-body iframe {
    border: 1px solid #dee2e6;
    border-radius: 8px;
}

/* File type colors */
.fa-file-pdf { color: #dc3545; }
.fa-file-word { color: #0d6efd; }
.fa-file-excel { color: #198754; }
.fa-file-image { color: #fd7e14; }

/* Responsive table adjustments */
@media (max-width: 768px) {
    .table th, .table td {
        font-size: 0.8rem;
        padding: 0.5rem 0.25rem;
    }
    
    .btn-group-vertical .btn {
        font-size: 0.7rem;
        padding: 0.2rem 0.4rem;
    }
}
</style>

<!-- Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

<script>
const reportData = <?php echo $json_report_data; ?>;

// Search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchPegawai');
    const searchResults = document.getElementById('searchResults');
    const selectPegawai = document.getElementById('selectPegawai');
    const selectedPegawaiDiv = document.getElementById('selectedPegawai');
    const btnLihatLaporan = document.getElementById('btnLihatLaporan');
    
    if (!searchInput || !searchResults || !selectPegawai || !selectedPegawaiDiv || !btnLihatLaporan) {
        return;
    }
    
    let selectedPegawaiData = <?php echo $selected_pegawai ? json_encode($selected_pegawai) : 'null'; ?>;
    
    if (!selectedPegawaiData) {
        searchInput.classList.remove('d-none');
        selectedPegawaiDiv.classList.add('d-none');
    } else {
        searchInput.classList.add('d-none');
        selectedPegawaiDiv.classList.remove('d-none');
    }
    
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        
        if (query.length < 2) {
            searchResults.classList.add('d-none');
            return;
        }
        
        const options = selectPegawai.querySelectorAll('option[data-search]');
        const results = [];
        
        options.forEach(option => {
            const searchText = option.getAttribute('data-search');
            if (searchText && searchText.includes(query)) {
                results.push({
                    id: option.value,
                    nama: option.getAttribute('data-nama'),
                    jabatan: option.getAttribute('data-jabatan'),
                    unit: option.getAttribute('data-unit'),
                    nip: option.getAttribute('data-nip')
                });
            }
        });
        
        displaySearchResults(results, query);
    });
    
    function displaySearchResults(results, query) {
        let html = '';
        
        if (results.length === 0) {
            html = '<div class="no-results p-4 text-center text-muted"><i class="fas fa-search mb-2"></i><br>Tidak ada pegawai yang ditemukan</div>';
        } else {
            results.forEach((result, index) => {
                html += `
                    <div class="search-result-item" data-id="${result.id}" data-index="${index}">
                        <div class="fw-bold text-primary">${result.nama}</div>
                        <small class="text-muted">${result.jabatan} - ${result.unit} - NIP: ${result.nip}</small>
                    </div>
                `;
            });
        }
        
        searchResults.innerHTML = html;
        searchResults.classList.remove('d-none');
        
        searchResults.querySelectorAll('.search-result-item').forEach(item => {
            item.addEventListener('click', function() {
                selectPegawaiFromSearch(this.getAttribute('data-id'));
            });
        });
    }
    
    function selectPegawaiFromSearch(id) {
        const option = selectPegawai.querySelector(`option[value="${id}"]`);
        if (option) {
            selectPegawai.value = id;
            
            const pegawaiData = {
                nama: option.getAttribute('data-nama'),
                jabatan: option.getAttribute('data-jabatan'),
                unit: option.getAttribute('data-unit'),
                nip: option.getAttribute('data-nip')
            };
            
            searchInput.classList.add('d-none');
            searchResults.classList.add('d-none');
            selectedPegawaiDiv.classList.remove('d-none');
            btnLihatLaporan.disabled = false;
            
            selectedPegawaiDiv.innerHTML = `
                <div class="card bg-success text-white border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><i class="fas fa-user-check me-2"></i>${pegawaiData.nama}</h6>
                                <small class="opacity-75">${pegawaiData.jabatan} - ${pegawaiData.unit} - NIP: ${pegawaiData.nip}</small>
                            </div>
                            <button type="button" class="btn btn-light btn-sm" onclick="clearSelection()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }
    }
    
    // Hide results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.classList.add('d-none');
        }
    });
});

function clearSelection() {
    window.location.href = window.location.pathname;
}

// Global function declarations (similar to lkh.php)
window.viewLampiran = function(namaFile, namaKegiatan) {
    try {
        const modalElement = document.getElementById('modalViewLampiran');
        const modal = new bootstrap.Modal(modalElement, {
            backdrop: 'static',
            keyboard: true,
            focus: true
        });
        
        const modalTitle = document.getElementById('modalLampiranTitle');
        const lampiranContent = document.getElementById('lampiranContent');
        const downloadLink = document.getElementById('downloadLink');
        
        // Set title
        modalTitle.innerHTML = `<i class="fas fa-file-alt me-2"></i>Lampiran: ${namaKegiatan}`;
        
        // Set download link and show it
        const filePath = `../uploads/lkh/${namaFile}`;
        downloadLink.href = filePath;
        downloadLink.download = namaFile;
        downloadLink.classList.remove('d-none');
        
        // Get file extension
        const fileExt = namaFile.split('.').pop().toLowerCase();
        
        // Show loading
        lampiranContent.innerHTML = `
            <div class="d-flex justify-content-center align-items-center" style="height: 200px;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <span class="ms-3">Memuat lampiran...</span>
            </div>
        `;
        
        // Remove aria-hidden before showing modal
        modalElement.removeAttribute('aria-hidden');
        
        // Show modal
        modal.show();
        
        // Add event listener for when modal is shown
        modalElement.addEventListener('shown.bs.modal', function() {
            // Ensure focus management
            const closeButton = modalElement.querySelector('.btn-close');
            if (closeButton) {
                closeButton.focus();
            }
        });
        
        // Add event listener for when modal is hidden
        modalElement.addEventListener('hidden.bs.modal', function() {
            // Reset aria-hidden when modal is closed
            modalElement.setAttribute('aria-hidden', 'true');
            // Hide download link when modal closes
            downloadLink.classList.add('d-none');
        });
        
        // Load content based on file type
        setTimeout(() => {
            try {
                if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(fileExt)) {
                    // Image file
                    lampiranContent.innerHTML = `
                        <div class="card border-0">
                            <img src="${filePath}" class="card-img-top lampiran-preview" alt="Lampiran LKH" 
                                 style="max-height: 500px; object-fit: contain;" 
                                 onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'text-center p-4\\'><i class=\\'fas fa-exclamation-triangle fa-3x text-warning\\'></i><h6 class=\\'mt-3\\'>File tidak dapat dimuat</h6><p class=\\'text-muted\\'>File mungkin sudah tidak ada atau rusak</p></div>';">
                            <div class="card-body">
                                <p class="card-text">
                                    <small class="text-muted">
                                        <i class="fas fa-image me-1"></i>Gambar - ${namaFile}
                                    </small>
                                </p>
                            </div>
                        </div>
                    `;
                } else if (fileExt === 'pdf') {
                    // PDF file
                    lampiranContent.innerHTML = `
                        <div class="card border-0">
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <i class="fas fa-file-pdf fa-5x text-danger"></i>
                                    <h5 class="mt-3">File PDF</h5>
                                    <p class="text-muted">${namaFile}</p>
                                </div>
                                <div class="d-grid gap-2">
                                    <a href="${filePath}" target="_blank" class="btn btn-primary" 
                                       rel="noopener noreferrer">
                                        <i class="fas fa-external-link-alt me-2"></i>Buka di Tab Baru
                                    </a>
                                    <button type="button" class="btn btn-outline-primary" 
                                            onclick="window.loadPDFPreview('${filePath}')">
                                        <i class="fas fa-eye me-2"></i>Preview di Modal
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                } else if (['doc', 'docx'].includes(fileExt)) {
                    // Word document
                    lampiranContent.innerHTML = `
                        <div class="card border-0">
                            <div class="card-body text-center">
                                <i class="fas fa-file-word fa-5x text-primary"></i>
                                <h5 class="mt-3">Dokumen Word</h5>
                                <p class="text-muted">${namaFile}</p>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    File dokumen Word tidak dapat dipratinjau. Silakan download untuk membuka.
                                </div>
                            </div>
                        </div>
                    `;
                } else if (['xls', 'xlsx'].includes(fileExt)) {
                    // Excel file
                    lampiranContent.innerHTML = `
                        <div class="card border-0">
                            <div class="card-body text-center">
                                <i class="fas fa-file-excel fa-5x text-success"></i>
                                <h5 class="mt-3">File Excel</h5>
                                <p class="text-muted">${namaFile}</p>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    File Excel tidak dapat dipratinjau. Silakan download untuk membuka.
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    // Other file types
                    lampiranContent.innerHTML = `
                        <div class="card border-0">
                            <div class="card-body text-center">
                                <i class="fas fa-file fa-5x text-secondary"></i>
                                <h5 class="mt-3">File Lampiran</h5>
                                <p class="text-muted">${namaFile}</p>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    File ini tidak dapat dipratinjau. Silakan download untuk membuka.
                                </div>
                            </div>
                        </div>
                    `;
                }
            } catch (loadError) {
                console.error('Error loading content:', loadError);
                lampiranContent.innerHTML = `
                    <div class="text-center p-4">
                        <i class="fas fa-exclamation-triangle fa-3x text-danger"></i>
                        <h6 class="mt-3">Error</h6>
                        <p class="text-muted">Gagal memuat konten file</p>
                    </div>
                `;
            }
        }, 200);
        
    } catch (error) {
        console.error('Error in viewLampiran:', error);
        alert('Terjadi kesalahan saat membuka lampiran');
    }
};

window.loadPDFPreview = function(filePath) {
    try {
        const lampiranContent = document.getElementById('lampiranContent');
        
        lampiranContent.innerHTML = `
            <div class="card border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Preview PDF</h6>
                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                onclick="window.viewLampiran('${filePath.split('/').pop()}', 'File PDF')">
                            <i class="fas fa-arrow-left me-1"></i>Kembali
                        </button>
                    </div>
                    <div class="embed-responsive" style="height: 500px;">
                        <iframe src="${filePath}" 
                                class="w-100 h-100 border-0" 
                                style="border-radius: 8px;"
                                title="PDF Preview"
                                loading="lazy"
                                onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=\\'text-center p-4\\'><i class=\\'fas fa-exclamation-triangle fa-3x text-warning\\'></i><h6 class=\\'mt-3\\'>PDF tidak dapat dimuat</h6><p class=\\'text-muted\\'>Silakan gunakan tombol download untuk mengunduh file</p></div>';">
                            <p>Browser Anda tidak mendukung pratinjau PDF. 
                               <a href="${filePath}" target="_blank" rel="noopener noreferrer">Klik di sini untuk membuka file.</a>
                            </p>
                        </iframe>
                    </div>
                </div>
            </div>
        `;
    } catch (error) {
        console.error('Error in loadPDFPreview:', error);
    }
};

// Print function
document.getElementById('btnPrintLapkin').addEventListener('click', function() {
    var printContents = document.getElementById('print-area').innerHTML;
    var originalContents = document.body.innerHTML;
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
    window.location.reload();
});

// Export PDF function
document.getElementById('btnExportPdf').addEventListener('click', function() {
    var { jsPDF } = window.jspdf;
    var doc = new jsPDF({ orientation: "landscape", unit: "pt", format: "A4" });

    doc.setFontSize(14);
    doc.text("Laporan Kinerja Pegawai - Tahun <?php echo $filter_tahun; ?>", 40, 40);
    
    var infoY = 60;
    doc.setFontSize(10);
    doc.text("Nama Pegawai : <?php echo addslashes($selected_pegawai['nama'] ?? ''); ?>", 40, infoY);
    doc.text("NIP          : <?php echo addslashes($selected_pegawai['nip'] ?? ''); ?>", 40, infoY + 15);
    doc.text("Jabatan      : <?php echo addslashes($selected_pegawai['jabatan'] ?? ''); ?>", 40, infoY + 30);
    doc.text("Unit Kerja   : <?php echo addslashes($selected_pegawai['unit_kerja'] ?? ''); ?>", 40, infoY + 45);

    var tableHeaders = [
        [
            { content: "No", rowSpan: 2, styles: { halign: 'center', valign: 'middle' } },
            { content: "Bulan", rowSpan: 2, styles: { halign: 'center', valign: 'middle' } },
            { content: "RHK Terkait", rowSpan: 2, styles: { halign: 'center', valign: 'middle' } },
            { content: "Uraian Kegiatan RKB", rowSpan: 2, styles: { halign: 'center', valign: 'middle' } },
            { content: "Target RKB", colSpan: 2, styles: { halign: 'center' } },
            { content: "Realisasi LKH", colSpan: 4, styles: { halign: 'center' } }
        ],
        [
            { content: "Kuantitas", styles: { halign: 'center' } },
            { content: "Satuan", styles: { halign: 'center' } },
            { content: "Tanggal LKH", styles: { halign: 'center' } },
            { content: "Nama Kegiatan Harian", styles: { halign: 'center' } },
            { content: "Uraian Kegiatan LKH", styles: { halign: 'center' } },
            { content: "Lampiran", styles: { halign: 'center' } }
        ]
    ];

    doc.autoTable({
        head: tableHeaders,
        body: reportData,
        startY: infoY + 60,
        styles: { fontSize: 9, cellPadding: 3, overflow: 'linebreak' },
        headStyles: { fillColor: [40, 167, 69], halign: 'center', valign: 'middle' },
        bodyStyles: { valign: 'top' },
        theme: 'grid',
        margin: { left: 20, right: 20 },
        tableWidth: 'auto',
        didDrawPage: function (data) {
            var pageCount = doc.internal.getNumberOfPages();
            doc.setFontSize(8);
            doc.text('Halaman ' + pageCount, doc.internal.pageSize.getWidth() - 60, doc.internal.pageSize.getHeight() - 10);
        }
    });

    doc.save("Laporan_Kinerja_<?php echo $selected_pegawai['nama'] ?? 'Pegawai'; ?>_<?php echo $filter_tahun; ?>.pdf");
});
</script>
