<?php
session_start();

// Include mobile session config
require_once __DIR__ . '/config/mobile_session.php';
require_once __DIR__ . '/../config/database.php';

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

// Build yearly data
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

// Format date for signature
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

// Create simple HTML for PDF conversion
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Kinerja Tahunan ' . $year . '</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 5px; text-align: left; vertical-align: top; }
        th { background-color: #f0f0f0; font-weight: bold; text-align: center; }
        .employee-info td:first-child { background-color: #f0f0f0; font-weight: bold; width: 120px; }
        .signature-area { margin-top: 30px; display: flex; justify-content: space-between; }
        .signature-box { text-align: center; width: 45%; font-size: 9px; }
        .signature-line { border-bottom: 1px solid #000; margin: 40px auto 5px auto; width: 150px; }
        h1 { text-align: center; font-size: 14px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>LAPORAN KINERJA TAHUNAN ' . $year . '</h1>
    
    <!-- Employee Information -->
    <table class="employee-info">
        <tr>
            <td><strong>Nama Pegawai</strong></td>
            <td>' . htmlspecialchars($nama_pegawai_login) . '</td>
        </tr>
        <tr>
            <td><strong>NIP</strong></td>
            <td>' . htmlspecialchars($emp_data['nip']) . '</td>
        </tr>
        <tr>
            <td><strong>Jabatan</strong></td>
            <td>' . htmlspecialchars($emp_data['jabatan']) . '</td>
        </tr>
        <tr>
            <td><strong>Unit Kerja</strong></td>
            <td>' . htmlspecialchars($emp_data['unit_kerja']) . '</td>
        </tr>
        <tr>
            <td><strong>Tahun Laporan</strong></td>
            <td>' . $year . '</td>
        </tr>
    </table>
    
    <!-- Report Table -->
    <table>
        <thead>
            <tr>
                <th rowspan="2">No</th>
                <th rowspan="2">Bulan</th>
                <th rowspan="2">RHK Terkait</th>
                <th rowspan="2">Uraian Kegiatan RKB</th>
                <th colspan="2">Target RKB</th>
                <th colspan="4">Realisasi LKH</th>
            </tr>
            <tr>
                <th>Kuantitas</th>
                <th>Satuan</th>
                <th>Tanggal LKH</th>
                <th>Nama Kegiatan</th>
                <th>Uraian LKH</th>
                <th>Lampiran</th>
            </tr>
        </thead>
        <tbody>';

if (empty($data_for_display)) {
    $html .= '<tr><td colspan="10" style="text-align: center;">Belum ada data untuk tahun ' . $year . '</td></tr>';
} else {
    foreach ($data_for_display as $row) {
        $html .= '<tr>';
        $html .= '<td style="text-align: center;">' . $row['no'] . '</td>';
        $html .= '<td style="text-align: center;">' . $row['bulan'] . '</td>';
        $html .= '<td>' . htmlspecialchars($row['rhk_terkait']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['uraian_kegiatan_rkb']) . '</td>';
        $html .= '<td style="text-align: center;">' . htmlspecialchars($row['target_kuantitas']) . '</td>';
        $html .= '<td style="text-align: center;">' . htmlspecialchars($row['target_satuan']) . '</td>';
        $html .= '<td style="text-align: center;">' . htmlspecialchars($row['tanggal_lkh']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['nama_kegiatan_harian']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['uraian_kegiatan_lkh']) . '</td>';
        $html .= '<td style="text-align: center;">' . htmlspecialchars($row['lampiran']) . '</td>';
        $html .= '</tr>';
    }
}

$html .= '
        </tbody>
    </table>
    
    <!-- Signature Area -->
    <div class="signature-area">
        <div class="signature-box">
            <p><strong>Pejabat Penilai</strong></p>
            <div class="signature-line"></div>
            <p><strong>' . htmlspecialchars($emp_data['nama_penilai'] ?: '(..................................)') . '</strong><br>
            NIP. ' . htmlspecialchars($emp_data['nip_penilai'] ?: '.................................') . '</p>
        </div>
        <div class="signature-box">
            <p>Cingambul, ' . format_date_indonesia(date('Y-m-d')) . '<br>
            <strong>Pegawai Yang Dinilai</strong></p>
            <div class="signature-line"></div>
            <p><strong>' . htmlspecialchars($nama_pegawai_login) . '</strong><br>
            NIP. ' . htmlspecialchars($emp_data['nip']) . '</p>
        </div>
    </div>
</body>
</html>';

// Generate filename
$nip_pegawai = preg_replace('/[^A-Za-z0-9_\-]/', '_', $emp_data['nip']);
$filename = "Laporan_Tahunan_{$year}_{$nip_pegawai}.pdf";

// Try to use TCPDF if available, otherwise use DomPDF or mPDF
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    
    // Try TCPDF first
    if (class_exists('TCPDF')) {
        $pdf = new TCPDF('L', 'mm', 'A4');
        $pdf->SetCreator('E-LAPKIN Mobile');
        $pdf->SetAuthor($nama_pegawai_login);
        $pdf->SetTitle('Laporan Kinerja Tahunan ' . $year);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $pdf->Output($filename, 'D');
        exit;
    }
    
    // Try DomPDF
    if (class_exists('Dompdf\Dompdf')) {
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $dompdf->output();
        exit;
    }
}

// Fallback: serve as HTML with PDF MIME type
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $html;
exit;
?>
