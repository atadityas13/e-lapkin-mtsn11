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

// Ambil informasi pejabat penilai dari database
function get_pejabat_penilai($conn, $id_pegawai) {
    $stmt = $conn->prepare("SELECT nama_penilai, nip_penilai FROM pegawai WHERE id_pegawai = ?");
    $stmt->bind_param("i", $id_pegawai);
    $stmt->execute();
    $stmt->bind_result($nama_pejabat, $nip_pejabat);
    $stmt->fetch();
    $stmt->close();
    
    return [
        'nama' => $nama_pejabat ?: '(...................................)',
        'nip' => $nip_pejabat ?: '.................................'
    ];
}

$periode_aktif = get_periode_aktif($conn, $id_pegawai_login);
$periode_tahun = $periode_aktif['tahun'];

$pejabat_penilai = get_pejabat_penilai($conn, $id_pegawai_login);

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

// Fungsi untuk format tanggal Indonesia
function format_date_indonesia($date_string) {
    $months_indo = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $day = date('d', strtotime($date_string));
    $month = (int)date('m', strtotime($date_string));
    $year = date('Y', strtotime($date_string));
    
    return $day . ' ' . $months_indo[$month] . ' ' . $year;
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
                    $lampiran_status = !empty($lkh_row['lampiran']) ? '1 Dokumen' : 'Nihil';

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

<!-- Custom CSS for print and PDF -->
<style>
@media print {
    .d-print-none {
        display: none !important;
    }
    
    .print-header {
        text-align: center;
        margin-bottom: 30px;
        border-bottom: 2px solid #000;
        padding-bottom: 20px;
    }
    
    .print-header h1 {
        font-size: 18px;
        font-weight: bold;
        margin: 0;
        text-transform: uppercase;
    }
    
    .print-header h2 {
        font-size: 16px;
        margin: 5px 0;
    }
    
    .print-header h3 {
        font-size: 14px;
        margin: 5px 0;
        font-weight: normal;
    }
    
    .employee-info {
        margin-bottom: 25px;
    }
    
    .employee-info table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .employee-info td {
        padding: 5px 10px;
        border: 1px solid #000;
        font-size: 12px;
    }
    
    .report-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 10px;
    }
    
    .report-table th,
    .report-table td {
        border: 1px solid #000;
        padding: 5px;
        text-align: center;
        vertical-align: middle;
    }
    
    .report-table th {
        background-color: #f0f0f0;
        font-weight: bold;
        text-align: center;
    }
    
    .report-table .text-center {
        text-align: center;
    }
    
    .page-break {
        page-break-before: always;
    }
    
    .print-footer {
        margin-top: 30px;
        border-top: 1px solid #000;
        padding-top: 20px;
    }
    
    .signature-area {
        display: flex;
        justify-content: space-between;
        margin-top: 40px;
    }
    
    .signature-box {
        text-align: center;
        width: 200px;
    }
    
    .signature-line {
        border-bottom: 1px solid #000;
        margin-top: 60px;
        margin-bottom: 5px;
    }
}

.print-preview {
    background: white;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
    margin: 20px auto;
    padding: 40px;
    max-width: 1000px;
}
</style>

<div id="layoutSidenav_content">
    <main>
        <div class="container-fluid px-4">
            <h1 class="mt-4">Laporan Kinerja Tahunan</h1>
            <div class="alert alert-info mb-4 d-print-none">
                Menampilkan laporan seluruh bulan pada tahun <b><?php echo $periode_tahun; ?></b> sesuai periode aktif Anda.
            </div>
            
            <!-- Control Buttons -->
            <div class="card mb-4 d-print-none">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h5 class="card-title">Cetak Laporan</h5>
                            <p class="card-text">Klik Preview Cetak untuk cetak laporan tahun ini.</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <button class="btn btn-primary" id="btnPreviewPrint">
                                <i class="fas fa-eye"></i> Preview Cetak
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Print Area -->
            <div class="card mb-4" id="print-area">
                <!-- Print Header (only visible when printing) -->
                <div class="print-header d-none d-print-block">
                    <h1>MTsN 11 MAJALENGKA</h1>
                    <h2>LAPORAN KINERJA PEGAWAI TAHUNAN</h2>
                    <h3>TAHUN <?php echo $periode_tahun; ?></h3>
                </div>
                
                <div class="card-header bg-success text-white d-print-none">
                    <div class="d-flex align-items-center">
                        <span class="flex-grow-1">Laporan Kinerja Pegawai - Tahun <?php echo $periode_tahun; ?></span>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Employee Information -->
                    <div class="employee-info">
                        <table class="table table-bordered table-striped mb-4">
                            <thead class="d-print-none">
                                <tr>
                                    <th colspan="2">Informasi Pegawai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="width: 150px;"><strong>Nama Pegawai</strong></td>
                                    <td><?php echo htmlspecialchars($nama_pegawai_login); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>NIP</strong></td>
                                    <td><?php echo htmlspecialchars($_SESSION['nip']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Jabatan</strong></td>
                                    <td><?php echo htmlspecialchars($_SESSION['jabatan']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Unit Kerja</strong></td>
                                    <td><?php echo htmlspecialchars($_SESSION['unit_kerja']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Tahun Laporan</strong></td>
                                    <td><?php echo $periode_tahun; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <h5 class="d-print-none">Daftar Rencana Kegiatan Bulanan (RKB) dan Realisasi Harian (LKH) Tahun <?php echo $periode_tahun; ?></h5>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle report-table" id="laporanTahunanTable">
                            <thead>
                                <tr>
                                    <th rowspan="2" style="width: 40px;"><center>No</center></th>
                                    <th rowspan="2" style="width: 80px;"><center>Bulan</center></th>
                                    <th rowspan="2" style="width: 150px;"><center>RHK Terkait</center></th>
                                    <th rowspan="2" style="width: 200px;"><center>Uraian Kegiatan RKB</center></th>
                                    <th colspan="2"><center>Target RKB</center></th>
                                    <th colspan="4"><center>Realisasi LKH</center></th>
                                </tr>
                                <tr>
                                    <th style="width: 80px;"><center>Kuantitas</center></th>
                                    <th style="width: 80px;"><center>Satuan</center></th>
                                    <th style="width: 100px;"><center>Tanggal LKH</center></th>
                                    <th style="width: 150px;"><center>Nama Kegiatan Harian</center></th>
                                    <th style="width: 200px;"><center>Uraian Kegiatan LKH</center></th>
                                    <th style="width: 80px;"><center>Lampiran</center></th>
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
                                        echo '<td rowspan="' . $rowspan_map['rhk'][$rhk_key] . '" style="text-align: center; vertical-align: middle;">' . htmlspecialchars($rhk) . '</td>';
                                        $printed_rhk[$rhk_key] = true;
                                    }

                                    // RKB, Target Kuantitas, Target Satuan
                                    if (!isset($printed_rkb[$rkb_key])) {
                                        echo '<td rowspan="' . $rowspan_map['rkb'][$rkb_key] . '" style="text-align: center; vertical-align: middle;">' . htmlspecialchars($rkb) . '</td>';
                                        echo '<td rowspan="' . $rowspan_map['rkb'][$rkb_key] . '"><center>' . htmlspecialchars($row_html['target_kuantitas']) . '</center></td>';
                                        echo '<td rowspan="' . $rowspan_map['rkb'][$rkb_key] . '"><center>' . htmlspecialchars($row_html['target_satuan']) . '</center></td>';
                                        $printed_rkb[$rkb_key] = true;
                                    }

                                    // LKH
                                    echo '<td style="text-align: center;">' . htmlspecialchars($row_html['tanggal_lkh']) . '</td>';
                                    echo '<td style="text-align: center; vertical-align: middle;">' . htmlspecialchars($row_html['nama_kegiatan_harian'] ?? '') . '</td>';
                                    echo '<td style="text-align: center; vertical-align: middle;">' . htmlspecialchars($row_html['uraian_kegiatan_lkh']) . '</td>';
                                    
                                    // Lampiran dengan link untuk melihat
                                    if (!empty($row_html['lampiran_file']) && $row_html['lampiran'] === '1 Dokumen') {
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
                    
                    <!-- Print Footer -->
                    <div class="print-footer d-none d-print-block">
                        <div class="row">
                            <div class="col-12">
                                <p><strong>Catatan:</strong></p>
                                <ul>
                                    <li>Laporan ini merupakan rekapitulasi kegiatan selama tahun <?php echo $periode_tahun; ?></li>
                                    <li>Data yang ditampilkan berdasarkan RKB (Rencana Kinerja Bulanan) dan LKH (Laporan Kinerja Harian)</li>
                                    <li>Laporan dicetak untuk dipergunakan sebagaimana mestinya.</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="signature-area">
                            <div class="signature-box">
                                <p>Pejabat Penilai</p>
                                <p><strong><?php echo htmlspecialchars($pejabat_penilai['nama']); ?></strong><br>
                                <div class="signature-line"></div>
                                NIP. <?php echo htmlspecialchars($pejabat_penilai['nip']); ?></p>
                            </div>
                            <div class="signature-box">
                                <p>Cingambul, <?php echo format_date_indonesia(date('Y-m-d')); ?><br>
                                Pegawai Yang Dinilai</p>
                                <p><strong><?php echo htmlspecialchars($nama_pegawai_login); ?></strong><br>
                                <div class="signature-line"></div>
                                NIP. <?php echo htmlspecialchars($_SESSION['nip']); ?></p>
                            </div>
                        </div>
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

    // Enhanced print preview functionality
    const btnPreviewPrint = document.getElementById('btnPreviewPrint');
    if (btnPreviewPrint) {
        btnPreviewPrint.addEventListener('click', function() {
            console.log('Preview button clicked'); // Debug log
            
            // Get the print area content
            const printArea = document.getElementById('print-area');
            if (!printArea) {
                alert('Area cetak tidak ditemukan');
                return;
            }
            
            // Clone the content to avoid modifying original
            const printContent = printArea.cloneNode(true);
            
            // Remove elements that shouldn't be printed
            const elementsToRemove = printContent.querySelectorAll('.d-print-none');
            elementsToRemove.forEach(element => element.remove());
            
            // Show elements that should only be visible in print
            const elementsToShow = printContent.querySelectorAll('.d-none.d-print-block');
            elementsToShow.forEach(element => {
                element.classList.remove('d-none');
                element.style.display = 'block';
            });
            
            // Update signature area to correct positioning
            const signatureArea = printContent.querySelector('.signature-area');
            if (signatureArea) {
                signatureArea.innerHTML = `
                    <div class="signature-box">
                        <p>Pejabat Penilai</p>
                        <div class="signature-line"></div>
                        <p><strong><?php echo htmlspecialchars($pejabat_penilai['nama']); ?></strong><br>
                        NIP. <?php echo htmlspecialchars($pejabat_penilai['nip']); ?></p>
                    </div>
                    <div class="signature-box">
                        <p>Cingambul, <?php echo format_date_indonesia(date('Y-m-d')); ?><br>
                        Pegawai</p>
                        <div class="signature-line"></div>
                        <p><strong><?php echo htmlspecialchars($nama_pegawai_login); ?></strong><br>
                        NIP. <?php echo htmlspecialchars($_SESSION['nip']); ?></p>
                    </div>
                `;
            }
            
            // Convert attachment buttons to text for print
            const attachmentCells = printContent.querySelectorAll('td a.view-attachment');
            attachmentCells.forEach(function(attachmentLink) {
                const cell = attachmentLink.parentElement.parentElement;
                cell.innerHTML = '<center>1 Dokumen</center>';
            });
            
            // Create the print window
            const printWindow = window.open('', '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
            
            if (!printWindow) {
                alert('Pop-up diblokir. Silakan izinkan pop-up untuk preview cetak.');
                return;
            }
            
            const printHtml = `
                <!DOCTYPE html>
                <html lang="id">
                <head>
                    <meta charset="utf-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1">
                    <title>E-Lapkin MTsN 11 Majalengka | Laporan Kinerja Tahunan - <?php echo $periode_tahun; ?></title>
                    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
                    <style>
                        * {
                            box-sizing: border-box;
                        }
                        
                        body { 
                            font-family: Arial, sans-serif; 
                            margin: 0;
                            padding: 20px;
                            font-size: 12px;
                            line-height: 1.4;
                            background-color: #f5f5f5;
                        }
                        
                        .container {
                            max-width: 1200px;
                            margin: 0 auto;
                            background: white;
                            padding: 30px;
                            box-shadow: 0 0 10px rgba(0,0,0,0.1);
                        }
                        
                        .print-header { 
                            text-align: center; 
                            margin-bottom: 30px; 
                            border-bottom: 2px solid #000; 
                            padding-bottom: 20px; 
                        }
                        
                        .print-header h1 { 
                            font-size: 18px; 
                            font-weight: bold; 
                            margin: 0; 
                            text-transform: uppercase; 
                            letter-spacing: 1px;
                        }
                        
                        .print-header h2 { 
                            font-size: 16px; 
                            margin: 8px 0; 
                            font-weight: bold;
                        }
                        
                        .print-header h3 { 
                            font-size: 14px; 
                            margin: 5px 0; 
                            font-weight: normal; 
                        }
                        
                        .employee-info table { 
                            width: 100%; 
                            border-collapse: collapse; 
                            margin-bottom: 25px; 
                        }
                        
                        .employee-info td { 
                            padding: 8px 12px; 
                            border: 1px solid #000; 
                            font-size: 12px; 
                            vertical-align: top;
                        }
                        
                        .employee-info td:first-child { 
                            background-color: #f0f0f0; 
                            font-weight: bold; 
                            width: 150px; 
                        }
                        
                        .report-table { 
                            width: 100%; 
                            border-collapse: collapse; 
                            font-size: 10px; 
                            margin-top: 10px;
                        }
                        
                        .report-table th, .report-table td { 
                            border: 1px solid #000; 
                            padding: 6px; 
                            text-align: center; 
                            vertical-align: middle; 
                            word-wrap: break-word;
                        }
                        
                        .report-table th { 
                            background-color: #e0e0e0; 
                            font-weight: bold; 
                            text-align: center; 
                            font-size: 10px;
                        }
                        
                        .text-center { 
                            text-align: center; 
                        }
                        
                        .print-footer { 
                            margin-top: 30px; 
                            border-top: 1px solid #000; 
                            padding-top: 20px; 
                            page-break-inside: avoid;
                        }
                        
                        .signature-area { 
                            display: flex; 
                            justify-content: space-between; 
                            margin-top: 40px; 
                        }
                        
                        .signature-box { 
                            text-align: center; 
                            width: 220px; 
                            font-size: 12px;
                        }
                        
                        .signature-line { 
                            border-bottom: 1px solid #000; 
                            margin-top: 60px; 
                            margin-bottom: 5px; 
                        }
                        
                        .print-controls {
                            position: fixed;
                            top: 10px;
                            right: 10px;
                            background: white;
                            padding: 15px;
                            border: 1px solid #ccc;
                            border-radius: 8px;
                            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                            z-index: 1000;
                        }
                        
                        .print-controls button {
                            background: #007bff;
                            color: white;
                            border: none;
                            padding: 10px 16px;
                            border-radius: 4px;
                            cursor: pointer;
                            margin-left: 8px;
                            font-size: 14px;
                        }
                        
                        .print-controls button:hover {
                            background: #0056b3;
                        }
                        
                        .print-controls button.btn-success {
                            background: #28a745;
                        }
                        
                        .print-controls button.btn-success:hover {
                            background: #1e7e34;
                        }
                        
                        .print-controls button.btn-secondary {
                            background: #6c757d;
                        }
                        
                        .print-controls button.btn-secondary:hover {
                            background: #545b62;
                        }
                        
                        .footer-info {
                            position: fixed;
                            bottom: 0;
                            left: 0;
                            right: 0;
                            height: 50px;
                            background: #f8f9fa;
                            border-top: 1px solid #ddd;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            padding: 0 20px;
                            font-size: 11px;
                            color: #666;
                        }
                        
                        @page {
                            margin: 15mm;
                            size: A4 landscape;
                            @bottom-left {
                                content: "Halaman " counter(page) " dari " counter(pages);
                            }
                            @bottom-right {
                                content: "Digenerate melalui E-Lapkin MTsN 11 Majalengka";
                            }
                        }
                        
                        @media print {
                            .print-controls { 
                                display: none !important; 
                            }
                            .footer-info { 
                                display: none !important; 
                            }
                            body { 
                                margin: 0; 
                                padding: 0; 
                                background: white;
                            }
                            .container {
                                box-shadow: none;
                                padding: 0;
                                margin: 0;
                                max-width: none;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="print-controls">
                        <button class="btn-success" onclick="window.print()">
                            <i class="fas fa-print"></i> Cetak Dokumen
                        </button>
                        <button class="btn-secondary" onclick="window.close()">
                            <i class="fas fa-times"></i> Tutup Preview
                        </button>
                    </div>
                    
                    <div class="footer-info">
                        <span><strong>Info:</strong> Halaman akan dinomori otomatis saat dicetak</span>
                        <span><strong>Source:</strong> Digenerate melalui E-Lapkin MTsN 11 Majalengka</span>
                    </div>
                    
                    <div class="container">
                        ${printContent.innerHTML}
                    </div>
                </body>
                </html>
            `;
            
            printWindow.document.write(printHtml);
            printWindow.document.close();
            
            // Focus the new window
            printWindow.focus();
        });
    } else {
        console.error('Preview Print button not found');
    }
});
</script>
