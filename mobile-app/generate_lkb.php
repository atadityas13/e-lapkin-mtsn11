<?php
/**
 * E-LAPKIN Mobile LKB Generation
 */

// Start output buffering to catch any unwanted output
ob_start();

session_start();

// Include mobile session config
require_once __DIR__ . '/config/mobile_session.php';
require_once __DIR__ . '/../config/database.php';

// Check mobile login
checkMobileLogin();

// Get user session data
$userData = getMobileSessionData();
$id_pegawai_login = $userData['id_pegawai'];

// Get parameters from URL
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : 0;
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';

// Validate parameters
if (!$bulan || !$tahun || $aksi !== 'generate') {
    header('Location: laporan.php');
    exit();
}

require_once __DIR__ . '/../vendor/fpdf/fpdf.php'; // Pastikan path sesuai

$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Helper function for notifications
function set_mobile_notification($type, $title, $text) {
    $_SESSION['mobile_notification'] = [
        'type' => $type,
        'title' => $title,
        'text' => $text
    ];
}

// Fungsi generate LKB PDF (copy dari web)
function generate_lkb_pdf($id_pegawai, $bulan, $tahun, $tempat_cetak = 'Cingambul', $tanggal_cetak = null) {
    global $conn, $months;

    if (!$tanggal_cetak) {
        $tanggal_cetak = date('Y-m-d');
    }
    $tanggal_signature = date('d', strtotime($tanggal_cetak)) . " " . $months[(int)date('m', strtotime($tanggal_cetak))] . " " . date('Y', strtotime($tanggal_cetak));

    $stmt = $conn->prepare("SELECT nama, nip, jabatan, unit_kerja, nama_penilai, nip_penilai FROM pegawai WHERE id_pegawai = ?");
    $stmt->bind_param("i", $id_pegawai);
    $stmt->execute();
    $stmt->bind_result($nama_pegawai, $nip, $jabatan, $unit_kerja, $nama_penilai, $nip_penilai);
    $stmt->fetch();
    $stmt->close();

    if (!$nama_penilai) {
        $nama_penilai = "H. JAJANG GUNAWAN, S.Ag., M.Pd.I";
        $nip_penilai = "196708251992031003";
    }

    $stmt = $conn->prepare("SELECT uraian_kegiatan, kuantitas, satuan FROM rkb WHERE id_pegawai=? AND bulan=? AND tahun=? ORDER BY id_rkb ASC");
    $stmt->bind_param("iii", $id_pegawai, $bulan, $tahun);
    $stmt->execute();
    $result_rkb = $stmt->get_result();
    $rkb_data = [];
    while ($row = $result_rkb->fetch_assoc()) {
        $rkb_data[] = $row;
    }
    $stmt->close();

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetMargins(20, 20, 20);
    $pdf->SetAutoPageBreak(false);
    $pdf->Image(__DIR__ . '/../assets/img/cover_background.png', 0, 0, $pdf->GetPageWidth(), $pdf->GetPageHeight());
    $pdf->SetFont('Times', 'B', 24);
    $pdf->SetY(40);
    $pdf->Cell(0, 10, 'LAPORAN KINERJA HARIAN', 0, 1, 'C');
    $pdf->SetFont('Times', 'B', 22);
    $pdf->Cell(0, 10, 'BULAN ' . strtoupper($months[$bulan]), 0, 1, 'C');
    $pdf->Cell(0, 10, 'TAHUN ' . $tahun, 0, 1, 'C');
    $pdf->Ln(30);
    $logo_path = __DIR__ . '/../assets/img/logo_kemenag.png';
    if (file_exists($logo_path)) {
        $pdf->Image($logo_path, ($pdf->GetPageWidth() / 2) - 25, $pdf->GetY(), 50, 50);
    } else {
        $pdf->SetFont('Times', 'B', 12);
        $pdf->Cell(0, 50, '[LOGO NOT FOUND]', 0, 1, 'C');
    }
    $pdf->Ln(20);
    $pdf->SetFont('Times', 'B', 16);
    $pdf->SetY($pdf->GetPageHeight() - 100);
    $pdf->Cell(0, 8, $nama_pegawai, 0, 1, 'C');
    $pdf->SetFont('Times', '', 14);
    $pdf->Cell(0, 8, 'NIP. ' . $nip, 0, 1, 'C');
    $pdf->Ln(30);
    $pdf->SetFont('Times', 'B', 18);
    $pdf->Cell(0, 8, 'MTsN 11 MAJALENGKA', 0, 1, 'C');
    $pdf->SetFont('Times', 'B', 16);
    $pdf->Cell(0, 8, 'KEMENTERIAN AGAMA KABUPATEN MAJALENGKA', 0, 1, 'C');

    $pdf->AddPage();
    $bottom_margin_for_content = 15;
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, $bottom_margin_for_content);

    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(0, 8, '     LAPORAN KINERJA BULANAN', 0, 1, 'C');
    $pdf->Cell(0, 8, 'SASARAN KINERJA PEGAWAI', 0, 1, 'C');
    $pdf->Ln(4);

    $pdf->SetFont('Arial', '', 10);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 8, ' Nama', 1, 0, 'L', true);
    $pdf->Cell(5, 8, ':', 1, 0, 'C', true);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, ' ' . $nama_pegawai, 1, 1, 'L');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 8, ' NIP', 1, 0, 'L', true);
    $pdf->Cell(5, 8, ':', 1, 0, 'C', true);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, ' ' . $nip, 1, 1, 'L');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 8, ' Jabatan', 1, 0, 'L', true);
    $pdf->Cell(5, 8, ':', 1, 0, 'C', true);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, ' ' . $jabatan, 1, 1, 'L');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 8, ' Unit Kerja', 1, 0, 'L', true);
    $pdf->Cell(5, 8, ':', 1, 0, 'C', true);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, ' ' . $unit_kerja, 1, 1, 'L');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 8, ' Bulan', 1, 0, 'L', true);
    $pdf->Cell(5, 8, ':', 1, 0, 'C', true);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, ' ' . $months[$bulan] . " " . $tahun, 1, 1, 'L');
    $pdf->Ln(6);

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(10, 10, 'No', 1, 0, 'C', true);
    $pdf->Cell(115, 10, 'Uraian Kegiatan', 1, 0, 'C', true);
    $pdf->Cell(25, 10, 'Jumlah', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'Satuan', 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 9);
    $no_rkb = 1;
    foreach ($rkb_data as $row_rkb) {
        $cell_widths = [10, 115, 25, 30];
        $line_height = 4;
        $uraian_lines = max(1, ceil($pdf->GetStringWidth($row_rkb['uraian_kegiatan']) / ($cell_widths[1] - 4)));
        $kuantitas_lines = max(1, ceil($pdf->GetStringWidth($row_rkb['kuantitas']) / ($cell_widths[2] - 4)));
        $satuan_lines = max(1, ceil($pdf->GetStringWidth($row_rkb['satuan']) / ($cell_widths[3] - 4)));
        $max_lines = max($uraian_lines, $kuantitas_lines, $satuan_lines, 1);
        $row_height = $line_height * $max_lines + 2;
        $current_y = $pdf->GetY();
        $start_x = $pdf->GetX();
        $start_y = $pdf->GetY();
        $page_break_trigger = $pdf->GetPageHeight() - $bottom_margin_for_content;
        if ($current_y + $row_height > $page_break_trigger) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(10, 10, 'No', 1, 0, 'C', true);
            $pdf->Cell(115, 10, 'Uraian Kegiatan', 1, 0, 'C', true);
            $pdf->Cell(25, 10, 'Jumlah', 1, 0, 'C', true);
            $pdf->Cell(30, 10, 'Satuan', 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 9);
            $start_x = $pdf->GetX();
            $start_y = $pdf->GetY();
        }
        $pdf->Rect($start_x, $start_y, $cell_widths[0], $row_height);
        $pdf->Rect($start_x + $cell_widths[0], $start_y, $cell_widths[1], $row_height);
        $pdf->Rect($start_x + $cell_widths[0] + $cell_widths[1], $start_y, $cell_widths[2], $row_height);
        $pdf->Rect($start_x + $cell_widths[0] + $cell_widths[1] + $cell_widths[2], $start_y, $cell_widths[3], $row_height);
        $pdf->SetXY($start_x, $start_y + 1);
        $pdf->Cell($cell_widths[0], $row_height - 2, $no_rkb++, 0, 0, 'C');
        $pdf->SetXY($start_x + $cell_widths[0] + 1, $start_y + 1);
        $pdf->MultiCell($cell_widths[1] - 2, $line_height, $row_rkb['uraian_kegiatan'], 0, 'L');
        $pdf->SetXY($start_x + $cell_widths[0] + $cell_widths[1] + 1, $start_y + 1);
        $pdf->MultiCell($cell_widths[2] - 2, $line_height, $row_rkb['kuantitas'], 0, 'C');
        $pdf->SetXY($start_x + $cell_widths[0] + $cell_widths[1] + $cell_widths[2] + 1, $start_y + 1);
        $pdf->MultiCell($cell_widths[3] - 2, $line_height, $row_rkb['satuan'], 0, 'C');
        $pdf->SetY($start_y + $row_height);
    }

    if ($pdf->GetY() > ($pdf->GetPageHeight() - $bottom_margin_for_content - 50)) {
        $pdf->AddPage();
    }

    $pdf->Ln(10);
    $pdf->SetFont('Arial', '', 10);
    $left_margin = 25;
    $col_width = 80;
    $gap = 35;
    $pdf->SetX($left_margin);
    $pdf->Cell($col_width, 4, '', 0, 0, 'L');
    $pdf->Cell($gap);
    $pdf->Cell($col_width, 4, $tempat_cetak . ", " . $tanggal_signature, 0, 1, 'L');
    $pdf->SetX($left_margin);
    $pdf->Cell($col_width, 4, 'Pejabat Penilai,', 0, 0, 'L');
    $pdf->Cell($gap);
    $pdf->Cell($col_width, 4, "Pegawai yang dinilai,", 0, 1, 'L');
    $pdf->Ln(20);
    $pdf->SetFont('Arial', 'BU', 10);
    $pdf->SetX($left_margin);
    $pdf->Cell($col_width, 4, $nama_penilai, 0, 0, 'L');
    $pdf->Cell($gap);
    $pdf->Cell($col_width, 4, $nama_pegawai, 0, 1, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetX($left_margin);
    $pdf->Cell($col_width, 4, 'NIP. ' . $nip_penilai, 0, 0, 'L');
    $pdf->Cell($gap);
    $pdf->Cell($col_width, 4, 'NIP. ' . $nip, 0, 1, 'L');

    $dir = __DIR__ . "/../generated";
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $nama_file_nip = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nip);
    $filename = "{$dir}/LKB_{$months[$bulan]}_{$tahun}_{$nama_file_nip}.pdf";
    $pdf->Output('F', $filename);

    return $filename;
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tempat_cetak = trim($_POST['tempat_cetak'] ?? 'Cingambul');
    $tanggal_cetak = trim($_POST['tanggal_cetak'] ?? date('Y-m-d'));

    if (empty($tempat_cetak) || empty($tanggal_cetak)) {
        set_mobile_notification('error', 'Gagal', 'Tempat cetak dan tanggal cetak harus diisi.');
        header('Location: laporan.php');
        exit();
    }

    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM rkb WHERE id_pegawai = ? AND bulan = ? AND tahun = ? AND status_verval = 'disetujui'");
    $stmt_check->bind_param("iii", $id_pegawai_login, $bulan, $tahun);
    $stmt_check->execute();
    $stmt_check->bind_result($count_approved);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count_approved == 0) {
        set_mobile_notification('error', 'Gagal', 'RKB belum disetujui untuk periode tersebut.');
        header('Location: laporan.php');
        exit();
    }

    try {
        $pdf_file = generate_lkb_pdf($id_pegawai_login, $bulan, $tahun, $tempat_cetak, $tanggal_cetak);
        set_mobile_notification('success', 'Berhasil', 'LKB berhasil digenerate dan dapat diunduh.');
        header('Location: laporan.php');
        exit();
    } catch (Exception $e) {
        error_log("LKB Generation Error: " . $e->getMessage());
        set_mobile_notification('error', 'Gagal', 'Terjadi kesalahan saat generate LKB. Silakan coba lagi.');
        header('Location: laporan.php');
        exit();
    }
} else {
    header('Location: laporan.php');
    exit();
}
?>
