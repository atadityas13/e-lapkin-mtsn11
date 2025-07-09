<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA - MOBILE APP VERSION
 * ========================================================
 * 
 * Annual Report PDF Generator for Mobile App
 * 
 * @package    E-Lapkin-MTSN11
 * @version    1.0.0
 * ========================================================
 */

// Start output buffering to prevent any unwanted output
ob_start();

session_start();

// Include mobile session and database
require_once __DIR__ . '/config/mobile_session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/fpdf/fpdf.php';

// Check mobile login
checkMobileLogin();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("HTTP/1.1 405 Method Not Allowed");
    exit("Method not allowed");
}

// Get user session data
$userData = getMobileSessionData();
$id_pegawai_login = $userData['id_pegawai'];
$nama_pegawai_login = $userData['nama'];

// Get year parameter
$selected_year = isset($_POST['year']) ? (int)$_POST['year'] : (int)date('Y');

// Get periode aktif
$stmt = $conn->prepare("SELECT tahun_aktif FROM pegawai WHERE id_pegawai = ?");
$stmt->bind_param("i", $id_pegawai_login);
$stmt->execute();
$stmt->bind_result($tahun_aktif);
$stmt->fetch();
$stmt->close();
$periode_tahun = $tahun_aktif ?: (int)date('Y');

// Get pejabat penilai info
$stmt = $conn->prepare("SELECT nama_penilai, nip_penilai FROM pegawai WHERE id_pegawai = ?");
$stmt->bind_param("i", $id_pegawai_login);
$stmt->execute();
$stmt->bind_result($nama_pejabat, $nip_pejabat);
$stmt->fetch();
$stmt->close();

$pejabat_penilai = [
    'nama' => $nama_pejabat ?: '(...................................)',
    'nip' => $nip_pejabat ?: '.................................'
];

// Month names
$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Collect data for PDF (same logic as desktop version)
$data_for_display = [];
$no_global = 1;

for ($bulan_num = 1; $bulan_num <= 12; $bulan_num++) {
    $stmt_rhk_month = $conn->prepare("SELECT DISTINCT rhk.id_rhk, rhk.nama_rhk
                                     FROM rhk
                                     JOIN rkb ON rhk.id_rhk = rkb.id_rhk
                                     WHERE rhk.id_pegawai = ? AND rkb.bulan = ? AND rkb.tahun = ?
                                     ORDER BY rhk.id_rhk ASC");
    $stmt_rhk_month->bind_param("iii", $id_pegawai_login, $bulan_num, $selected_year);
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
            'lampiran' => 'Nihil',
        ];
        continue;
    }

    while ($rhk_item = $result_rhk_month->fetch_assoc()) {
        $stmt_rkb = $conn->prepare("SELECT rkb.id_rkb, rkb.uraian_kegiatan, rkb.kuantitas AS target_kuantitas, rkb.satuan AS target_satuan
                                    FROM rkb
                                    WHERE rkb.id_rhk = ? AND rkb.bulan = ? AND rkb.tahun = ? AND rkb.id_pegawai = ?
                                    ORDER BY rkb.id_rkb ASC");
        $stmt_rkb->bind_param("iiii", $rhk_item['id_rhk'], $bulan_num, $selected_year, $id_pegawai_login);
        $stmt_rkb->execute();
        $result_rkb = $stmt_rkb->get_result();

        while ($rkb_item_detail = $result_rkb->fetch_assoc()) {
            $stmt_lkh = $conn->prepare("SELECT id_lkh, tanggal_lkh, nama_kegiatan_harian, uraian_kegiatan_lkh, lampiran
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
                    'rhk_terkait' => $rhk_item['nama_rhk'],
                    'uraian_kegiatan_rkb' => $rkb_item_detail['uraian_kegiatan'],
                    'target_kuantitas' => $rkb_item_detail['target_kuantitas'],
                    'target_satuan' => $rkb_item_detail['target_satuan'],
                    'tanggal_lkh' => 'Belum ada realisasi.',
                    'nama_kegiatan_harian' => '',
                    'uraian_kegiatan_lkh' => '',
                    'lampiran' => 'Nihil',
                ];
            } else {
                while ($lkh_row = $result_lkh->fetch_assoc()) {
                    $day_names = [
                        1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
                        5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'
                    ];
                    $day_of_week = date('N', strtotime($lkh_row['tanggal_lkh']));
                    $tanggal_lkh_formatted = $day_names[$day_of_week] . ', ' . date('d-m-Y', strtotime($lkh_row['tanggal_lkh']));
                    $lampiran_status = !empty($lkh_row['lampiran']) ? '1 Dokumen' : 'Nihil';

                    $data_for_display[] = [
                        'no' => $no_global++,
                        'bulan' => $months[$bulan_num],
                        'rhk_terkait' => $rhk_item['nama_rhk'],
                        'uraian_kegiatan_rkb' => $rkb_item_detail['uraian_kegiatan'],
                        'target_kuantitas' => $rkb_item_detail['target_kuantitas'],
                        'target_satuan' => $rkb_item_detail['target_satuan'],
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

// Create PDF using FPDF
class LaporanTahunanPDF extends FPDF {
    private $nama_pegawai;
    private $nip_pegawai;
    private $jabatan_pegawai;
    private $unit_kerja;
    private $tahun_laporan;
    private $pejabat_penilai;
    
    public function __construct($orientation = 'L', $unit = 'mm', $size = 'A4') {
        parent::__construct($orientation, $unit, $size);
    }
    
    public function setEmployeeInfo($nama, $nip, $jabatan, $unit_kerja, $tahun, $pejabat) {
        $this->nama_pegawai = $nama;
        $this->nip_pegawai = $nip;
        $this->jabatan_pegawai = $jabatan;
        $this->unit_kerja = $unit_kerja;
        $this->tahun_laporan = $tahun;
        $this->pejabat_penilai = $pejabat;
    }
    
    function Header() {
        // Main title
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'E-Lapkin MTsN 11 Majalengka | Laporan Kinerja Tahunan - ' . $this->tahun_laporan, 0, 1, 'C');
        $this->Ln(5);
        
        // Employee information section
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, 'A. DATA PEGAWAI', 0, 1, 'L');
        $this->Ln(2);
        
        // Employee info table - exact match to desktop
        $this->SetFont('Arial', '', 10);
        
        $this->Cell(50, 6, 'Nama', 1, 0, 'L');
        $this->Cell(80, 6, ': ' . $this->nama_pegawai, 1, 1, 'L');
        
        $this->Cell(50, 6, 'NIP', 1, 0, 'L');
        $this->Cell(80, 6, ': ' . $this->nip_pegawai, 1, 1, 'L');
        
        $this->Cell(50, 6, 'Jabatan', 1, 0, 'L');
        $this->Cell(80, 6, ': ' . $this->jabatan_pegawai, 1, 1, 'L');
        
        $this->Cell(50, 6, 'Unit Kerja', 1, 0, 'L');
        $this->Cell(80, 6, ': ' . $this->unit_kerja, 1, 1, 'L');
        
        $this->Cell(50, 6, 'Tahun Laporan', 1, 0, 'L');
        $this->Cell(80, 6, ': ' . $this->tahun_laporan, 1, 1, 'L');
        
        $this->Ln(8);
        
        // Section B title
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, 'B. LAPORAN KINERJA PEGAWAI', 0, 1, 'L');
        $this->Ln(3);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . ' - E-Lapkin MTsN 11 Majalengka (Mobile App)', 0, 0, 'C');
    }
    
    function addTableHeader() {
        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(200, 220, 255);
        
        // Complex header structure to match desktop
        // Row 1 - Main headers with merging
        $this->Cell(10, 12, 'No', 1, 0, 'C', true);
        $this->Cell(20, 12, 'Bulan', 1, 0, 'C', true);
        $this->Cell(25, 12, 'RHK Terkait', 1, 0, 'C', true);
        $this->Cell(30, 12, 'Uraian Kegiatan RKB', 1, 0, 'C', true);
        $this->Cell(25, 6, 'Target RKB', 1, 0, 'C', true);
        $this->Cell(157, 6, 'Realisasi LKH', 1, 1, 'C', true);
        
        // Row 2 - Sub headers
        $this->Cell(10, 0, '', 0, 0); // No (merged)
        $this->Cell(20, 0, '', 0, 0); // Bulan (merged)
        $this->Cell(25, 0, '', 0, 0); // RHK (merged)
        $this->Cell(30, 0, '', 0, 0); // Uraian RKB (merged)
        $this->Cell(12, 6, 'Kuantitas', 1, 0, 'C', true);
        $this->Cell(13, 6, 'Satuan', 1, 0, 'C', true);
        $this->Cell(25, 6, 'Tanggal LKH', 1, 0, 'C', true);
        $this->Cell(35, 6, 'Nama Kegiatan Harian', 1, 0, 'C', true);
        $this->Cell(50, 6, 'Uraian Kegiatan LKH', 1, 0, 'C', true);
        $this->Cell(22, 6, 'Lampiran', 1, 0, 'C', true);
        $this->Cell(25, 6, 'Keterangan', 1, 1, 'C', true);
    }
    
    function addSignatureArea() {
        $this->Ln(15);
        
        // Date and location
        $this->SetFont('Arial', '', 10);
        $months_indo = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $today = date('d') . ' ' . $months_indo[(int)date('m')] . ' ' . date('Y');
        $this->Cell(0, 6, 'Cingambul, ' . $today, 0, 1, 'R');
        $this->Ln(8);
        
        // Signature section
        $this->Cell(135, 6, 'Pejabat Penilai,', 0, 0, 'C');
        $this->Cell(135, 6, 'Pegawai Yang Dinilai,', 0, 1, 'C');
        $this->Ln(20);
        
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(135, 6, $this->pejabat_penilai['nama'], 0, 0, 'C');
        $this->Cell(135, 6, $this->nama_pegawai, 0, 1, 'C');
        
        $this->SetFont('Arial', '', 9);
        $this->Cell(135, 6, 'NIP. ' . $this->pejabat_penilai['nip'], 0, 0, 'C');
        $this->Cell(135, 6, 'NIP. ' . $this->nip_pegawai, 0, 1, 'C');
    }
}

// Clear any output buffer
ob_clean();

// Generate filename - save in generated/temp as requested
$clean_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nama_pegawai_login);
$filename = 'mobile_laporan_tahunan_' . $clean_name . '_' . $selected_year . '_' . date('YmdHis') . '.pdf';
$temp_dir = __DIR__ . '/../generated/temp/';

// Create temp directory if not exists
if (!is_dir($temp_dir)) {
    mkdir($temp_dir, 0755, true);
}

$file_path = $temp_dir . $filename;

// Create PDF
$pdf = new LaporanTahunanPDF('L', 'mm', 'A4');
$pdf->setEmployeeInfo(
    $nama_pegawai_login,
    $userData['nip'],
    $userData['jabatan'],
    $userData['unit_kerja'],
    $selected_year,
    $pejabat_penilai
);

$pdf->AddPage();
$pdf->addTableHeader();

// Add data to PDF with exact desktop merging logic
$pdf->SetFont('Arial', '', 7);
$previous_bulan = '';
$previous_rhk = '';
$previous_rkb_uraian = '';
$previous_target_kuantitas = '';
$previous_target_satuan = '';

foreach ($data_for_display as $index => $row) {
    // Check if we need a new page
    if ($pdf->GetY() > 175) {
        $pdf->AddPage();
        $pdf->addTableHeader();
    }
    
    $show_bulan = ($row['bulan'] !== $previous_bulan);
    $show_rhk = ($row['rhk_terkait'] !== $previous_rhk || $show_bulan);
    $show_rkb = ($row['uraian_kegiatan_rkb'] !== $previous_rkb_uraian || 
                 $row['target_kuantitas'] !== $previous_target_kuantitas || 
                 $row['target_satuan'] !== $previous_target_satuan || 
                 $show_rhk);
    
    // No
    $pdf->Cell(10, 6, $row['no'], 1, 0, 'C');
    
    // Bulan
    if ($show_bulan) {
        $pdf->Cell(20, 6, $row['bulan'], 1, 0, 'C');
        $previous_bulan = $row['bulan'];
    } else {
        $pdf->Cell(20, 6, '', 1, 0, 'C');
    }
    
    // RHK Terkait
    if ($show_rhk) {
        $rhk_text = strlen($row['rhk_terkait']) > 20 ? substr($row['rhk_terkait'], 0, 20) . '...' : $row['rhk_terkait'];
        $pdf->Cell(25, 6, $rhk_text, 1, 0, 'C');
        $previous_rhk = $row['rhk_terkait'];
    } else {
        $pdf->Cell(25, 6, '', 1, 0, 'C');
    }
    
    // Uraian Kegiatan RKB
    if ($show_rkb) {
        $rkb_text = strlen($row['uraian_kegiatan_rkb']) > 25 ? substr($row['uraian_kegiatan_rkb'], 0, 25) . '...' : $row['uraian_kegiatan_rkb'];
        $pdf->Cell(30, 6, $rkb_text, 1, 0, 'L');
        $previous_rkb_uraian = $row['uraian_kegiatan_rkb'];
    } else {
        $pdf->Cell(30, 6, '', 1, 0, 'L');
    }
    
    // Target Kuantitas
    if ($show_rkb) {
        $pdf->Cell(12, 6, $row['target_kuantitas'], 1, 0, 'C');
        $previous_target_kuantitas = $row['target_kuantitas'];
    } else {
        $pdf->Cell(12, 6, '', 1, 0, 'C');
    }
    
    // Target Satuan
    if ($show_rkb) {
        $satuan_text = strlen($row['target_satuan']) > 8 ? substr($row['target_satuan'], 0, 8) . '...' : $row['target_satuan'];
        $pdf->Cell(13, 6, $satuan_text, 1, 0, 'C');
        $previous_target_satuan = $row['target_satuan'];
    } else {
        $pdf->Cell(13, 6, '', 1, 0, 'C');
    }
    
    // Realisasi LKH data (never merged)
    $tanggal_text = strlen($row['tanggal_lkh']) > 15 ? substr($row['tanggal_lkh'], 0, 15) . '...' : $row['tanggal_lkh'];
    $pdf->Cell(25, 6, $tanggal_text, 1, 0, 'C');
    
    $nama_kegiatan_text = strlen($row['nama_kegiatan_harian']) > 22 ? substr($row['nama_kegiatan_harian'], 0, 22) . '...' : $row['nama_kegiatan_harian'];
    $pdf->Cell(35, 6, $nama_kegiatan_text, 1, 0, 'L');
    
    $uraian_lkh_text = strlen($row['uraian_kegiatan_lkh']) > 35 ? substr($row['uraian_kegiatan_lkh'], 0, 35) . '...' : $row['uraian_kegiatan_lkh'];
    $pdf->Cell(50, 6, $uraian_lkh_text, 1, 0, 'L');
    
    $pdf->Cell(22, 6, $row['lampiran'], 1, 0, 'C');
    $pdf->Cell(25, 6, '-', 1, 1, 'C'); // Keterangan column
}

// Add signature area on last page
$pdf->addSignatureArea();

// Save PDF
$pdf->Output('F', $file_path);

// Schedule file deletion after 3 minutes
$delete_time = time() + (3 * 60);
$delete_script = "<?php
if (file_exists('$file_path') && time() >= $delete_time) {
    unlink('$file_path');
    unlink(__FILE__);
}
?>";

$delete_file = $temp_dir . 'delete_mobile_' . basename($filename, '.pdf') . '.php';
file_put_contents($delete_file, $delete_script);

// Set headers for download - match desktop naming convention
$clean_filename = 'Laporan Kinerja Tahunan_' . preg_replace('/[^0-9]/', '', $userData['nip']) . ' - ' . $selected_year . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $clean_filename . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Output file
readfile($file_path);

// Clean up old mobile-app files (5 minutes old)
$files = glob($temp_dir . 'mobile_*.pdf');
$now = time();
foreach ($files as $file) {
    if (is_file($file) && ($now - filemtime($file)) > 300) {
        unlink($file);
    }
}

// Clean up old mobile delete scripts
$delete_files = glob($temp_dir . 'delete_mobile_*.php');
foreach ($delete_files as $file) {
    if (is_file($file) && ($now - filemtime($file)) > 600) {
        unlink($file);
    }
}

exit();
?>