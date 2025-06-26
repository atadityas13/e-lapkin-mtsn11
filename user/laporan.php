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
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../template/session_user.php';

// Periksa apakah pengguna sudah login, jika tidak, arahkan kembali ke halaman login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

$id_pegawai_login = $_SESSION['id_pegawai'];
$nama_pegawai_login = $_SESSION['nama'];
$role_pegawai_login = $_SESSION['role'];
$is_admin = ($role_pegawai_login === 'admin');

$success_message = '';
$error_message = '';

// Default bulan dan tahun untuk laporan
$current_month = (int)date('m');
$current_year = (int)date('Y');

$filter_month = isset($_GET['month']) ? (int)$_GET['month'] : $current_month;
$filter_year = isset($_GET['year']) ? (int)$_GET['year'] : $current_year;

// Pastikan nilai bulan valid
if ($filter_month < 1 || $filter_month > 12) {
    $filter_month = $current_month;
}

// Ambil periode aktif tahun dari pegawai (mengikuti RKB)
function get_periode_aktif($conn, $id_pegawai) {
    $stmt = $conn->prepare("SELECT tahun_aktif FROM pegawai WHERE id_pegawai = ?");
    $stmt->bind_param("i", $id_pegawai);
    $stmt->execute();
    $stmt->bind_result($tahun_aktif);
    $stmt->fetch();
    $stmt->close();
    return [
        'tahun' => $tahun_aktif ?: (int)date('Y')
    ];
}
$periode_aktif = get_periode_aktif($conn, $id_pegawai_login);
$periode_tahun = $periode_aktif['tahun'];

// Data untuk dropdown bulan (sudah ada)
$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Fungsi untuk mendapatkan nama hari dalam Bahasa Indonesia
function get_day_name($date_string) {
    $timestamp = strtotime($date_string);
    $day_of_week = date('N', $timestamp); // 1 (for Monday) through 7 (for Sunday)
    $day_names = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu'
    ];
    return $day_names[$day_of_week];
}

// --- START: LOGIC UNTUK MENGUMPULKAN DATA DENGAN HIERARKI UNTUK JAVASCRIPT & HTML ---
$data_for_display = []; // Array untuk menampung data yang sudah diproses untuk tampilan HTML dan PDF
$no_global = 1; // Counter global untuk kolom No

for ($bulan_num = 1; $bulan_num <= 12; $bulan_num++) {
    $rhks_in_month = [];
    $stmt_rhk_month = $conn->prepare("SELECT DISTINCT rhk.id_rhk, rhk.nama_rhk
                                     FROM rhk
                                     JOIN rkb ON rhk.id_rhk = rkb.id_rhk
                                     WHERE rhk.id_pegawai = ? AND rkb.bulan = ? AND rkb.tahun = ?
                                     ORDER BY rhk.id_rhk ASC");
    $stmt_rhk_month->bind_param("iii", $id_pegawai_login, $bulan_num, $periode_tahun);
    $stmt_rhk_month->execute();
    $result_rhk_month = $stmt_rhk_month->get_result();

    $rhk_count_in_month = $result_rhk_month->num_rows;

    if ($rhk_count_in_month == 0) {
        // Jika tidak ada RHK/RKB untuk bulan ini, tambahkan satu baris "belum ada data"
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
            'lampiran' => 'Nihil',
            'rowspan_bulan' => 1, // Default rowspan
            'rowspan_rhk' => 1,
            'rowspan_rkb' => 1,
        ];
        continue; // Lanjut ke bulan berikutnya
    }

    $first_rhk_in_month = true;
    while ($rhk_item = $result_rhk_month->fetch_assoc()) {
        $rkbs_in_rhk = [];
        $stmt_rkb = $conn->prepare("SELECT rkb.id_rkb, rkb.uraian_kegiatan, rkb.kuantitas AS target_kuantitas, rkb.satuan AS target_satuan
                                    FROM rkb
                                    WHERE rkb.id_rhk = ? AND rkb.bulan = ? AND rkb.tahun = ? AND rkb.id_pegawai = ?
                                    ORDER BY rkb.id_rkb ASC");
        $stmt_rkb->bind_param("iiii", $rhk_item['id_rhk'], $bulan_num, $periode_tahun, $id_pegawai_login);
        $stmt_rkb->execute();
        $result_rkb = $stmt_rkb->get_result();

        $rkb_count_in_rhk = $result_rkb->num_rows;

        $first_rkb_in_rhk = true;
        while ($rkb_item_detail = $result_rkb->fetch_assoc()) {
            $lkhs_for_rkb = [];
            $stmt_lkh = $conn->prepare("SELECT id_lkh, tanggal_lkh, nama_kegiatan_harian, uraian_kegiatan_lkh, lampiran
                                        FROM lkh
                                        WHERE id_rkb = ?
                                        ORDER BY tanggal_lkh ASC");
            $stmt_lkh->bind_param("i", $rkb_item_detail['id_rkb']);
            $stmt_lkh->execute();
            $result_lkh = $stmt_lkh->get_result();
            
            $lkh_count_in_rkb = $result_lkh->num_rows;

            if ($lkh_count_in_rkb == 0) {
                // Baris tanpa LKH
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
                    'lampiran' => 'Nihil',
                    'rowspan_bulan' => 1,
                    'rowspan_rhk' => 1,
                    'rowspan_rkb' => 1,
                ];
            } else {
                $first_lkh_in_rkb = true;
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
                        'lampiran' => $lampiran_status,
                        'lampiran_file' => $lkh_row['lampiran'], // Add actual filename
                        'id_lkh' => $lkh_row['id_lkh'], // Add LKH ID
                        'rowspan_bulan' => 1,
                        'rowspan_rhk' => 1,
                        'rowspan_rkb' => 1,
                    ];
                    $first_lkh_in_rkb = false;
                }
            }
            $stmt_lkh->close();
            $first_rkb_in_rhk = false;
        }
        $stmt_rkb->close();
        $first_rhk_in_month = false;
    }
    $stmt_rhk_month->close();
}

// --- Tahap 2: Menghitung Rowspan untuk setiap kelompok data ---
// Kita akan membalik array untuk memudahkan perhitungan rowspan dari bawah ke atas.
// Atau, kita bisa melacak index awal dan akhir dari setiap kelompok.
// Cara yang lebih rapi adalah melacak jumlah baris unik untuk setiap penggabungan.

$final_data_for_pdf = [];
$current_month_name = '';
$current_rhk_id = ''; // Gunakan ID untuk melacak RHK, bukan nama
$current_rkb_id = ''; // Gunakan ID untuk melacak RKB, bukan uraian

// Data yang sudah dikelompokkan
$grouped_data = []; // Misalnya: Bulan -> RHK -> RKB -> LKH[]
$no_pdf_counter = 1;

foreach ($data_for_display as $row) {
    $bulan_key = $row['bulan'];
    $rhk_key = $row['rhk_terkait']; // Menggunakan nama RHK untuk sementara
    $rkb_key = $row['uraian_kegiatan_rkb']; // Menggunakan uraian RKB untuk sementara

    if (!isset($grouped_data[$bulan_key])) {
        $grouped_data[$bulan_key] = [];
    }
    if (!isset($grouped_data[$bulan_key][$rhk_key])) {
        $grouped_data[$bulan_key][$rhk_key] = [];
    }
    if (!isset($grouped_data[$bulan_key][$rhk_key][$rkb_key])) {
        $grouped_data[$bulan_key][$rhk_key][$rkb_key] = [];
    }
    $grouped_data[$bulan_key][$rhk_key][$rkb_key][] = $row;
}

foreach ($grouped_data as $bulan_name => $rhks) {
    $bulan_rowspan = 0;
    foreach ($rhks as $rhk_name => $rkbs) {
        $rhk_rowspan = 0;
        foreach ($rkbs as $rkb_uraian => $lkh_items) {
            $rkb_rowspan = count($lkh_items);
            if ($rkb_rowspan == 0) { // Jika tidak ada LKH, itu berarti 1 baris untuk RKB tanpa LKH
                $rkb_rowspan = 1;
            }
            
            $rhk_rowspan += $rkb_rowspan; // Total rowspan RHK adalah jumlah rowspan RKB di dalamnya
            $bulan_rowspan += $rkb_rowspan; // Total rowspan Bulan adalah jumlah rowspan RKB di dalamnya

            $first_rkb_of_group = true;
            foreach ($lkh_items as $idx => $lkh_item) {
                $new_row = [];
                // Kolom No akan diisi untuk setiap baris LKH, bukan di-merge.
                $new_row[] = $no_pdf_counter++; // Setiap baris LKH dapat nomor sendiri untuk kerapian

                if ($first_rkb_of_group) {
                    // Isi data RKB hanya di baris pertama LKH-nya
                    $new_row[] = $bulan_name; // Placeholder, akan di-override di loop terluar
                    $new_row[] = $rhk_name; // Placeholder, akan di-override di loop terluar
                    $new_row[] = $rkb_uraian;
                    $new_row[] = $lkh_item['target_kuantitas'];
                    $new_row[] = $lkh_item['target_satuan'];
                    $new_row[] = $lkh_item['tanggal_lkh'];
                    $new_row[] = $lkh_item['nama_kegiatan_harian'];
                    $new_row[] = $lkh_item['uraian_kegiatan_lkh'];
                    $new_row[] = $lkh_item['lampiran'];
                    $first_rkb_of_group = false;
                } else {
                    // Kosongkan kolom RKB untuk baris LKH berikutnya dari RKB yang sama
                    $new_row[] = ''; // Bulan
                    $new_row[] = ''; // RHK Terkait
                    $new_row[] = ''; // Uraian Kegiatan RKB
                    $new_row[] = ''; // Target Kuantitas
                    $new_row[] = ''; // Target Satuan
                    $new_row[] = $lkh_item['tanggal_lkh'];
                    $new_row[] = $lkh_item['nama_kegiatan_harian'];
                    $new_row[] = $lkh_item['uraian_kegiatan_lkh'];
                    $new_row[] = $lkh_item['lampiran'];
                }
                $final_data_for_pdf[] = $new_row;
            }

            // Jika ada RKB tanpa LKH, tambahkan baris khusus
            if ($rkb_rowspan == 1 && empty($lkh_items)) {
                 $new_row = [];
                 $new_row[] = $no_pdf_counter++;
                 $new_row[] = $bulan_name;
                 $new_row[] = $rhk_name;
                 $new_row[] = $rkb_uraian;
                 $new_row[] = $rkbs[$rkb_uraian][0]['target_kuantitas']; // Ambil dari data asli
                 $new_row[] = $rkbs[$rkb_uraian][0]['target_satuan']; // Ambil dari data asli
                 $new_row[] = 'Belum ada realisasi.';
                 $new_row[] = '';
                 $new_row[] = '';
                 $new_row[] = 'Nihil';
                 $final_data_for_pdf[] = $new_row;
            }
        }
    }
}


// --- Tahap 3: Aplikasikan rowspan untuk Kolom Bulan, RHK, dan RKB pada data final ---
// Ini adalah bagian yang paling tricky dan seringkali lebih mudah dilakukan saat build data
// daripada saat iterating kembali. Kita akan memproses ulang data_for_display
// untuk menyiapkan format jspdf-autotable yang sebenarnya.

$pdf_body_data = [];
$current_no = 1;

// Ini adalah struktur yang akan kita gunakan untuk melacak rowspan
$month_start_index = -1;
$rhk_start_index = -1;
$rkb_start_index = -1;

$prev_month = '';
$prev_rhk = '';
$prev_rkb_uraian = '';

// Re-loop data_for_display, tapi dengan penanganan rowspan
foreach ($grouped_data as $bulan_name => $rhks) {
    $month_entry_index = count($pdf_body_data); // Catat indeks awal bulan ini
    $first_row_in_month = true;

    foreach ($rhks as $rhk_name => $rkbs) {
        $rhk_entry_index = count($pdf_body_data); // Catat indeks awal RHK ini
        $first_row_in_rhk = true;

        foreach ($rkbs as $rkb_uraian => $lkh_items) {
            $rkb_entry_index = count($pdf_body_data); // Catat indeks awal RKB ini
            $first_row_in_rkb = true;

            if (empty($lkh_items)) {
                // Kasus RKB tanpa LKH
                $pdf_body_data[] = [
                    $current_no++,
                    $bulan_name, // Ini akan di-replace nanti jika bukan baris pertama bulan
                    $rhk_name,   // Ini akan di-replace nanti jika bukan baris pertama rhk
                    $rkb_uraian,
                    $rkbs[$rkb_uraian][0]['target_kuantitas'], // Ambil dari data_for_display awal
                    $rkbs[$rkb_uraian][0]['target_satuan'],   // Ambil dari data_for_display awal
                    'Belum ada realisasi.',
                    '',
                    '',
                    'Nihil'
                ];
                $first_row_in_rkb = false; // Baris RKB ini sudah diproses
            } else {
                foreach ($lkh_items as $lkh_item) {
                    $row_to_add = [
                        $current_no++,
                        $bulan_name, // Placeholder, akan di-override
                        $rhk_name,   // Placeholder, akan di-override
                        $rkb_uraian, // Placeholder, akan di-override
                        $lkh_item['target_kuantitas'],
                        $lkh_item['target_satuan'],
                        $lkh_item['tanggal_lkh'],
                        $lkh_item['nama_kegiatan_harian'],
                        $lkh_item['uraian_kegiatan_lkh'],
                        $lkh_item['lampiran']
                    ];
                    $pdf_body_data[] = $row_to_add;
                    $first_row_in_rkb = false;
                }
            }
        }
    }
}

// Sekarang kita punya data mentah. Kita perlu menghitung rowspan secara manual dari belakang atau dengan state.
// Pendekatan yang lebih bersih untuk `jspdf-autotable` rowspan adalah menentukan `rowspan` di baris pertama
// dari kelompok yang digabungkan, dan string kosong untuk baris berikutnya.

$final_pdf_data_rows = [];
$last_bulan = null;
$last_rhk = null;
$last_rkb = null;

// Iterate from the original $data_for_display again to build the actual PDF rows with rowSpans
// This needs a more careful state management
$previous_bulan = null;
$previous_rhk_terkait = null;
$previous_uraian_rkb = null;

$no_counter = 1;
foreach ($data_for_display as $i => $row) {
    $currentRow = [];

    // Kolom No (tidak di-merge)
    $currentRow[] = $no_counter++;

    // Kolom Bulan
    if ($row['bulan'] !== $previous_bulan) {
        // Ini baris pertama untuk Bulan baru
        $rowSpanBulan = 0;
        // Hitung rowspan untuk Bulan ini
        foreach ($data_for_display as $j => $future_row) {
            if ($j >= $i && $future_row['bulan'] === $row['bulan']) {
                $rowSpanBulan++;
            } else if ($j >= $i && $future_row['bulan'] !== $row['bulan']) {
                break; // Bulan berubah, berhenti menghitung
            }
        }
        $currentRow[] = [ 'content' => $row['bulan'], 'rowSpan' => $rowSpanBulan ];
        $previous_bulan = $row['bulan'];
        $previous_rhk_terkait = null; // Reset RHK dan RKB ketika bulan berubah
        $previous_uraian_rkb = null;
    } else {
        $currentRow[] = ''; // Merged cell
    }

    // Kolom RHK Terkait
    if ($row['rhk_terkait'] !== $previous_rhk_terkait || $row['bulan'] !== $previous_bulan) { // Jika RHK berubah atau Bulan berubah (reset RHK)
        $rowSpanRHK = 0;
        // Hitung rowspan untuk RHK ini (hanya dalam scope bulan yang sama)
        foreach ($data_for_display as $j => $future_row) {
            if ($j >= $i && $future_row['bulan'] === $row['bulan'] && $future_row['rhk_terkait'] === $row['rhk_terkait']) {
                $rowSpanRHK++;
            } else if ($j >= $i && ($future_row['bulan'] !== $row['bulan'] || $future_row['rhk_terkait'] !== $row['rhk_terkait'])) {
                break;
            }
        }
        $currentRow[] = [ 'content' => $row['rhk_terkait'], 'rowSpan' => $rowSpanRHK ];
        $previous_rhk_terkait = $row['rhk_terkait'];
        $previous_uraian_rkb = null; // Reset RKB ketika RHK berubah
    } else {
        $currentRow[] = ''; // Merged cell
    }

    // Kolom Uraian Kegiatan RKB
    if ($row['uraian_kegiatan_rkb'] !== $previous_uraian_rkb || $row['rhk_terkait'] !== $previous_rhk_terkait || $row['bulan'] !== $previous_bulan) {
        $rowSpanRKB = 0;
        // Hitung rowspan untuk RKB ini (hanya dalam scope RHK yang sama)
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
        $currentRow[] = ''; // Uraian RKB
        $currentRow[] = ''; // Kuantitas
        $currentRow[] = ''; // Satuan
    }

    // Kolom LKH (tidak di-merge)
    $currentRow[] = $row['tanggal_lkh'];
    $currentRow[] = $row['nama_kegiatan_harian'];
    $currentRow[] = $row['uraian_kegiatan_lkh'];
    $currentRow[] = $row['lampiran'];

    $final_pdf_data_rows[] = $currentRow;
}

$json_report_data = json_encode($final_pdf_data_rows);

$page_title = "Laporan Kinerja Tahunan";
include __DIR__ . '/../template/header.php';
include __DIR__ . '/../template/menu_user.php';
include __DIR__ . '/../template/topbar.php';
?>
<div id="layoutSidenav_content">
    <main>
        <div class="container-fluid px-4">
            <h1 class="mt-4">Laporan Kinerja Tahunan</h1>
            <div class="alert alert-info mb-4">
                Menampilkan laporan seluruh bulan pada tahun <b><?php echo $periode_tahun; ?></b> sesuai periode aktif Anda.
            </div>
            <div class="card mb-4" id="print-area">
                <div class="card-header bg-success text-white">
                    <div class="d-flex align-items-center">
                        <span class="flex-grow-1">Laporan Kinerja Pegawai - Tahun <?php echo $periode_tahun; ?></span>
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
                                <td>Nama Pegawai</td>
                                <td><?php echo htmlspecialchars($nama_pegawai_login); ?></td>
                            </tr>
                            <tr>
                                <td>NIP</td>
                                <td><?php echo htmlspecialchars($_SESSION['nip']); ?></td>
                            </tr>
                            <tr>
                                <td>Jabatan</td>
                                <td><?php echo htmlspecialchars($_SESSION['jabatan']); ?></td>
                            </tr>
                            <tr>
                                <td>Unit Kerja</td>
                                <td><?php echo htmlspecialchars($_SESSION['unit_kerja']); ?></td>
                            </tr>
                            <tr>
                                <td>Tahun</td>
                                <td><?php echo $periode_tahun; ?></td>
                            </tr>
                        </tbody>
                    </table>
                    <h5>Daftar Rencana Kegiatan Bulanan (RKB) dan Realisasi Harian (LKH) Tahun <?php echo $periode_tahun; ?></h5>
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
                                // Hitung rowspan untuk setiap kelompok (bulan, rhk, rkb) terlebih dahulu
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

                                // Cetak tabel dengan cell merge yang konsisten
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
                                    // No
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
                                    
                                    // Lampiran dengan link untuk melihat
                                    if (!empty($row_html['lampiran_file']) && $row_html['lampiran'] === 'Ada') {
                                        echo '<td><center><a href="#" class="btn btn-sm btn-info view-attachment" data-file="' . htmlspecialchars($row_html['lampiran_file']) . '" data-lkh-id="' . htmlspecialchars($row_html['id_lkh'] ?? '') . '"><i class="fas fa-eye"></i> Lihat</a></center></td>';
                                    } else {
                                        echo '<td><center>' . htmlspecialchars($row_html['lampiran']) . '</center></td>';
                                    }
                                    echo '</tr>';
                                }

                                if (empty($data_for_display)) {
                                    echo '<tr><td colspan="10" class="text-center text-muted">Belum ada Rencana Kegiatan Bulanan (RKB) atau Laporan Kinerja Harian (LKH) untuk tahun ini.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <?php include __DIR__ . '/../template/footer.php'; ?>
</div>

<!-- Modal untuk melihat lampiran -->
<div class="modal fade" id="attachmentModal" tabindex="-1" aria-labelledby="attachmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="attachmentModalLabel">Lampiran Dokumen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="attachmentContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Memuat lampiran...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <a href="#" id="downloadAttachment" class="btn btn-primary" target="_blank">
                    <i class="fas fa-download"></i> Download
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script>
// Data report dari PHP
const reportData = <?php echo $json_report_data; ?>; // Ini sekarang adalah final_pdf_data_rows dari PHP

// Fungsi untuk melihat lampiran
document.addEventListener('DOMContentLoaded', function() {
    // Event listener untuk tombol lihat lampiran
    document.querySelectorAll('.view-attachment').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const fileName = this.getAttribute('data-file');
            const lkhId = this.getAttribute('data-lkh-id');
            
            if (!fileName) {
                alert('File lampiran tidak ditemukan');
                return;
            }
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('attachmentModal'));
            const modalContent = document.getElementById('attachmentContent');
            const downloadLink = document.getElementById('downloadAttachment');
            
            // Reset content
            modalContent.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Memuat lampiran...</p>
                </div>
            `;
            
            modal.show();
            
            // Set download link - using relative path from user directory
            const filePath = '../uploads/lkh/' + fileName;
            downloadLink.href = filePath;
            
            // Check if file exists by trying to load it
            const testImage = new Image();
            testImage.onload = function() {
                // File exists, proceed with display
                displayAttachment(fileName, filePath, modalContent);
            };
            testImage.onerror = function() {
                // Try to access file anyway in case it's not an image
                displayAttachment(fileName, filePath, modalContent);
            };
            testImage.src = filePath;
        });
    });
    
    function displayAttachment(fileName, filePath, modalContent) {
        // Detect file type and display accordingly
        const fileExtension = fileName.split('.').pop().toLowerCase();
        
        setTimeout(function() {
            if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(fileExtension)) {
                // Display image
                modalContent.innerHTML = `
                    <div class="text-center">
                        <img src="${filePath}" class="img-fluid" alt="Lampiran" style="max-height: 500px;" 
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <div style="display: none;" class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                            <h6>Gambar tidak dapat dimuat</h6>
                            <p class="mb-0">File mungkin tidak ada atau rusak</p>
                        </div>
                        <p class="mt-2 text-muted">File: ${fileName}</p>
                    </div>
                `;
            } else if (fileExtension === 'pdf') {
                // Display PDF
                modalContent.innerHTML = `
                    <div class="text-center">
                        <embed src="${filePath}" type="application/pdf" width="100%" height="500px"
                               onerror="document.getElementById('pdfError').style.display='block'; this.style.display='none';">
                        <div id="pdfError" style="display: none;" class="alert alert-warning">
                            <i class="fas fa-file-pdf fa-2x mb-2"></i>
                            <h6>PDF tidak dapat ditampilkan</h6>
                            <p class="mb-0">Silakan download untuk melihat file PDF</p>
                        </div>
                        <p class="mt-2 text-muted">File: ${fileName}</p>
                    </div>
                `;
            } else if (['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'].includes(fileExtension)) {
                // For office documents, just show download option
                modalContent.innerHTML = `
                    <div class="text-center">
                        <div class="alert alert-info">
                            <i class="fas fa-file-alt fa-3x mb-3"></i>
                            <h5>Dokumen Office</h5>
                            <p>File: ${fileName}</p>
                            <p class="mb-0">Silakan download untuk melihat dokumen ini di aplikasi Office yang sesuai.</p>
                        </div>
                    </div>
                `;
            } else if (['txt', 'csv'].includes(fileExtension)) {
                // Try to display text files
                fetch(filePath)
                    .then(response => {
                        if (!response.ok) throw new Error('File not found');
                        return response.text();
                    })
                    .then(text => {
                        modalContent.innerHTML = `
                            <div>
                                <h6>File: ${fileName}</h6>
                                <div class="border p-3" style="max-height: 400px; overflow-y: auto; font-family: monospace; white-space: pre-wrap; background-color: #f8f9fa;">
                                    ${text.length > 5000 ? text.substring(0, 5000) + '\n\n... (file terlalu panjang, download untuk melihat selengkapnya)' : text}
                                </div>
                            </div>
                        `;
                    })
                    .catch(() => {
                        modalContent.innerHTML = `
                            <div class="text-center">
                                <div class="alert alert-warning">
                                    <i class="fas fa-file-alt fa-2x mb-2"></i>
                                    <h6>File tidak dapat dibaca</h6>
                                    <p>File: ${fileName}</p>
                                    <p class="mb-0">Silakan download untuk melihat isi file</p>
                                </div>
                            </div>
                        `;
                    });
            } else {
                // File type not supported for preview
                modalContent.innerHTML = `
                    <div class="text-center">
                        <div class="alert alert-warning">
                            <i class="fas fa-file fa-3x mb-3"></i>
                            <h5>File tidak dapat ditampilkan</h5>
                            <p>File dengan ekstensi ".${fileExtension}" tidak dapat ditampilkan di browser.</p>
                            <p class="text-muted">File: ${fileName}</p>
                            <p class="mb-0">Silakan download file untuk melihat isinya.</p>
                        </div>
                    </div>
                `;
            }
        }, 500); // Small delay to show loading animation
    }
});

document.getElementById('btnPrintLapkin').addEventListener('click', function() {
    var printContents = document.getElementById('print-area').innerHTML;
    var originalContents = document.body.innerHTML;
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
    window.location.reload();
});

document.getElementById('btnExportPdf').addEventListener('click', function() {
    var { jsPDF } = window.jspdf;
    var doc = new jsPDF({ orientation: "landscape", unit: "pt", format: "A4" });

    // Judul dan info pegawai
    doc.setFontSize(14);
    doc.text("Laporan Kinerja Pegawai - Tahun <?php echo $periode_tahun; ?>", 40, 40);
    var infoY = 60;
    doc.setFontSize(10);
    doc.text("Nama Pegawai : <?php echo addslashes($nama_pegawai_login); ?>", 40, infoY);
    doc.text("NIP            : <?php echo addslashes($_SESSION['nip']); ?>", 40, infoY + 15);
    doc.text("Jabatan        : <?php echo addslashes($_SESSION['jabatan']); ?>", 40, infoY + 30);
    doc.text("Unit Kerja     : <?php echo addslashes($_SESSION['unit_kerja']); ?>", 40, infoY + 45);

    // Header tabel
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
        body: reportData, // Gunakan reportData yang sudah diformat dengan rowspan
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

    doc.save("Laporan_Kinerja_Tahunan_<?php echo $periode_tahun; ?>.pdf");
});
</script>