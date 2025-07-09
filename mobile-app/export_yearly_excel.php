<?php
session_start();

// Include mobile session config
require_once __DIR__ . '/config/mobile_session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php'; // For PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Check mobile login
checkMobileLogin();

// Get user session data
$userData = getMobileSessionData();
$id_pegawai_login = $userData['id_pegawai'];
$nama_pegawai_login = $userData['nama'];

// Get year parameter
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Get employee information
$stmt_emp = $conn->prepare("SELECT nip, jabatan, unit_kerja, nama_penilai, nip_penilai FROM pegawai WHERE id_pegawai = ?");
$stmt_emp->bind_param("i", $id_pegawai_login);
$stmt_emp->execute();
$emp_data = $stmt_emp->get_result()->fetch_assoc();
$stmt_emp->close();

// Build yearly data (reuse logic from generate_yearly_report.php)
$data_for_display = [];
$no_global = 1;

for ($bulan_num = 1; $bulan_num <= 12; $bulan_num++) {
    $stmt_rhk_month = $conn->prepare("
        SELECT DISTINCT rhk.id_rhk, rhk.nama_rhk
        FROM rhk
        JOIN rkb ON rhk.id_rhk = rkb.id_rhk
        WHERE rhk.id_pegawai = ? AND rkb.bulan = ? AND rkb.tahun = ?
        ORDER BY rhk.id_rhk ASC
    ");
    $stmt_rhk_month->bind_param("iii", $id_pegawai_login, $bulan_num, $year);
    $stmt_rhk_month->execute();
    $result_rhk_month = $stmt_rhk_month->get_result();

    if ($result_rhk_month->num_rows == 0) {
        $stmt_rhk_month->close();
        continue;
    }

    while ($rhk_item = $result_rhk_month->fetch_assoc()) {
        $stmt_rkb = $conn->prepare("
            SELECT rkb.id_rkb, rkb.uraian_kegiatan, rkb.kuantitas AS target_kuantitas, rkb.satuan AS target_satuan
            FROM rkb
            WHERE rkb.id_rhk = ? AND rkb.bulan = ? AND rkb.tahun = ? AND rkb.id_pegawai = ?
            ORDER BY rkb.id_rkb ASC
        ");
        $stmt_rkb->bind_param("iiii", $rhk_item['id_rhk'], $bulan_num, $year, $id_pegawai_login);
        $stmt_rkb->execute();
        $result_rkb = $stmt_rkb->get_result();

        while ($rkb_item = $result_rkb->fetch_assoc()) {
            $stmt_lkh = $conn->prepare("
                SELECT id_lkh, tanggal_lkh, nama_kegiatan_harian, uraian_kegiatan_lkh, lampiran
                FROM lkh
                WHERE id_rkb = ?
                ORDER BY tanggal_lkh ASC
            ");
            $stmt_lkh->bind_param("i", $rkb_item['id_rkb']);
            $stmt_lkh->execute();
            $result_lkh = $stmt_lkh->get_result();
            
            if ($result_lkh->num_rows == 0) {
                $data_for_display[] = [
                    'no' => $no_global++,
                    'bulan' => $months[$bulan_num],
                    'rhk_terkait' => $rhk_item['nama_rhk'],
                    'uraian_kegiatan_rkb' => $rkb_item['uraian_kegiatan'],
                    'target_kuantitas' => $rkb_item['target_kuantitas'],
                    'target_satuan' => $rkb_item['target_satuan'],
                    'tanggal_lkh' => 'Belum ada realisasi',
                    'nama_kegiatan_harian' => '',
                    'uraian_kegiatan_lkh' => '',
                    'lampiran' => 'Nihil',
                ];
            } else {
                while ($lkh_row = $result_lkh->fetch_assoc()) {
                    $tanggal_lkh_formatted = date('d-m-Y', strtotime($lkh_row['tanggal_lkh']));
                    $lampiran_status = !empty($lkh_row['lampiran']) ? '1 Dokumen' : 'Nihil';

                    $data_for_display[] = [
                        'no' => $no_global++,
                        'bulan' => $months[$bulan_num],
                        'rhk_terkait' => $rhk_item['nama_rhk'],
                        'uraian_kegiatan_rkb' => $rkb_item['uraian_kegiatan'],
                        'target_kuantitas' => $rkb_item['target_kuantitas'],
                        'target_satuan' => $rkb_item['target_satuan'],
                        'tanggal_lkh' => $tanggal_lkh_formatted,
                        'nama_kegiatan_harian' => $lkh_row['nama_kegiatan_harian'] ?? '',
                        'uraian_kegiatan_lkh' => $lkh_row['uraian_kegiatan_lkh'],
                        'lampiran' => $lampiran_status,
                    ];
                }
            }
            $stmt_lkh->close();
        }
        $stmt_rkb->close();
    }
    $stmt_rhk_month->close();
}

// Create Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set title
$sheet->setTitle('Laporan Tahunan ' . $year);

// Employee information
$sheet->setCellValue('A1', 'LAPORAN KINERJA TAHUNAN ' . $year);
$sheet->setCellValue('A3', 'Nama Pegawai');
$sheet->setCellValue('B3', $nama_pegawai_login);
$sheet->setCellValue('A4', 'NIP');
$sheet->setCellValue('B4', $emp_data['nip']);
$sheet->setCellValue('A5', 'Jabatan');
$sheet->setCellValue('B5', $emp_data['jabatan']);
$sheet->setCellValue('A6', 'Unit Kerja');
$sheet->setCellValue('B6', $emp_data['unit_kerja']);

// Headers
$headers = ['No', 'Bulan', 'RHK Terkait', 'Uraian Kegiatan RKB', 'Target Kuantitas', 'Target Satuan', 'Tanggal LKH', 'Nama Kegiatan Harian', 'Uraian LKH', 'Lampiran'];
$col = 1;
foreach ($headers as $header) {
    $sheet->setCellValueByColumnAndRow($col, 8, $header);
    $col++;
}

// Data
$row = 9;
foreach ($data_for_display as $data) {
    $col = 1;
    foreach ($data as $value) {
        $sheet->setCellValueByColumnAndRow($col, $row, $value);
        $col++;
    }
    $row++;
}

// Auto-size columns
foreach (range('A', 'J') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

// Generate filename
$nip_pegawai = preg_replace('/[^A-Za-z0-9_\-]/', '_', $emp_data['nip']);
$filename = "Laporan_Tahunan_{$year}_{$nip_pegawai}.xlsx";

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Create writer and output
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
