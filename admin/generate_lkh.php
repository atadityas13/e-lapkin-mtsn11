<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * Sistem Elektronik Laporan Kinerja Harian
 * MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat
 * 
 * File: Admin Generate LKH
 * Deskripsi: Halaman generate LKH untuk admin
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
require_once '../vendor/fpdf/fpdf.php'; // Pastikan path sesuai lokasi fpdf.php Anda

// Validasi parameter
$id_pegawai = $_GET['id_pegawai'] ?? '';
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date('n');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');
$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';

if (!$id_pegawai || !$bulan || !$tahun) {
    header('Location: generate_laporan.php');
    exit;
}

$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

function generate_lkh_pdf($id_pegawai, $bulan, $tahun) {
    global $conn, $months;

    // Data Pegawai
    $stmt = $conn->prepare("SELECT nama, nip, jabatan, unit_kerja FROM pegawai WHERE id_pegawai = ?");
    $stmt->bind_param("i", $id_pegawai);
    $stmt->execute();
    $stmt->bind_result($nama_pegawai, $nip, $jabatan, $unit_kerja);
    $stmt->fetch();
    $stmt->close();

    // Data LKH - simplified query without kegiatan_harian table
    $stmt = $conn->prepare("
        SELECT tanggal_lkh, uraian_kegiatan_lkh, 
               jumlah_realisasi, satuan_realisasi
        FROM lkh
        WHERE id_pegawai = ? AND MONTH(tanggal_lkh) = ? AND YEAR(tanggal_lkh) = ?
        ORDER BY tanggal_lkh ASC
    ");
    $stmt->bind_param("iii", $id_pegawai, $bulan, $tahun);
    $stmt->execute();
    $result_lkh = $stmt->get_result();
    $lkh_data = [];
    while ($row = $result_lkh->fetch_assoc()) {
        $lkh_data[] = $row;
    }
    $stmt->close();

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();

    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);

    // Header
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(0, 8, 'LAPORAN KINERJA HARIAN', 0, 1, 'C');
    $pdf->Ln(4);

    // Biodata Pegawai dalam bentuk tabel
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(40, 8, 'Nama', 1, 0, 'L', true);
    $pdf->Cell(5, 8, ':', 1, 0, 'C', true);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, $nama_pegawai, 1, 1, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(40, 8, 'NIP', 1, 0, 'L', true);
    $pdf->Cell(5, 8, ':', 1, 0, 'C', true);
    $pdf->Cell(0, 8, $nip, 1, 1, 'L');
    $pdf->Cell(40, 8, 'Jabatan', 1, 0, 'L', true);
    $pdf->Cell(5, 8, ':', 1, 0, 'C', true);
    $pdf->Cell(0, 8, $jabatan, 1, 1, 'L');
    $pdf->Cell(40, 8, 'Unit Kerja', 1, 0, 'L', true);
    $pdf->Cell(5, 8, ':', 1, 0, 'C', true);
    $pdf->Cell(0, 8, $unit_kerja, 1, 1, 'L');
    $pdf->Cell(40, 8, 'Bulan', 1, 0, 'L', true);
    $pdf->Cell(5, 8, ':', 1, 0, 'C', true);
    $pdf->Cell(0, 8, $months[$bulan] . " " . $tahun, 1, 1, 'L');
    $pdf->Ln(6);

    // Tabel LKH
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(200, 220, 255);
    $pdf->Cell(10, 10, 'No', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'Tanggal', 1, 0, 'C', true);
    $pdf->Cell(120, 10, 'Uraian Kegiatan', 1, 0, 'C', true);
    $pdf->Cell(20, 10, 'Realisasi', 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 9);
    $no = 1;
    $hari_indo = [
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
    ];

    foreach ($lkh_data as $row) {
        $tanggal = date('d-m-Y', strtotime($row['tanggal_lkh']));
        $jumlah_realisasi = ($row['jumlah_realisasi'] && $row['satuan_realisasi']) 
                          ? $row['jumlah_realisasi'] . ' ' . $row['satuan_realisasi'] 
                          : '-';

        $cell_widths = [10, 30, 120, 20];
        $line_height = 6;
        
        $uraian_lines = $pdf->GetStringWidth($row['uraian_kegiatan_lkh']) > $cell_widths[2] 
                       ? ceil($pdf->GetStringWidth($row['uraian_kegiatan_lkh']) / ($cell_widths[2] - 2)) : 1;
        
        $max_lines = max($uraian_lines, 1);
        $row_height = $line_height * $max_lines;

        $x = $pdf->GetX();
        $y = $pdf->GetY();

        $pdf->MultiCell($cell_widths[0], $row_height, $no++, 1, 'C');
        $pdf->SetXY($x + $cell_widths[0], $y);
        $pdf->MultiCell($cell_widths[1], $row_height, $tanggal, 1, 'C');
        $pdf->SetXY($x + $cell_widths[0] + $cell_widths[1], $y);
        $pdf->MultiCell($cell_widths[2], $row_height, $row['uraian_kegiatan_lkh'], 1, 'L');
        $pdf->SetXY($x + $cell_widths[0] + $cell_widths[1] + $cell_widths[2], $y);
        $pdf->Cell($cell_widths[3], $row_height, $jumlah_realisasi, 1, 0, 'C');
        $pdf->Ln($row_height);
    }

    $pdf->Ln(10);

    // Footer Signatures
    $pdf->SetFont('Arial', '', 10);
    $nama_penilai = "H. JAJANG GUNAWAN, S.Ag., M.Pd.I";
    $nip_penilai = "196708251992031003";

    $left_margin = 15;
    $col_width = 80;
    $gap = 30;

    $pdf->SetX($left_margin);
    $pdf->Cell($col_width, 5, '', 0, 0, 'L');
    $pdf->Cell($gap);
    $pdf->Cell($col_width, 5, "Cingambul, " . date("d") . " " . $months[$bulan] . " " . $tahun, 0, 1, 'L');

    $pdf->SetX($left_margin);
    $pdf->Cell($col_width, 5, 'Pejabat Penilai,', 0, 0, 'L');
    $pdf->Cell($gap);
    $pdf->Cell($col_width, 5, "Pegawai yang dinilai,", 0, 1, 'L');

    $pdf->Ln(17);

    $pdf->SetFont('Arial', 'BU', 10);
    $pdf->SetX($left_margin);
    $pdf->Cell($col_width, 5, $nama_penilai, 0, 0, 'L');
    $pdf->Cell($gap);
    $pdf->Cell($col_width, 5, $nama_pegawai, 0, 1, 'L');

    $pdf->SetFont('Arial', '', 10);
    $pdf->SetX($left_margin);
    $pdf->Cell($col_width, 5, 'NIP. ' . $nip_penilai, 0, 0, 'L');
    $pdf->Cell($gap);
    $pdf->Cell($col_width, 5, 'NIP. ' . $nip, 0, 1, 'L');

    // Simpan PDF
    $dir = "../generated";
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $nama_file_nip = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nip);
    $filename = "{$dir}/LKH_{$months[$bulan]}_{$tahun}_{$nama_file_nip}.pdf";
    $pdf->Output('F', $filename);

    return $filename;
}

$page_title = "Generate LKH";
include '../template/header.php';
include '../template/menu_admin.php';
include '../template/topbar.php';
?>
<div id="layoutSidenav_content">
    <main>
        <div class="container-fluid px-4">
            <h1 class="mt-4">Generate LKH</h1>
            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-info text-white">
                            <i class="fas fa-file-alt"></i> Generate LKH Bulan <?= $months[$bulan] . ' ' . $tahun ?>
                        </div>
                        <div class="card-body">
                            <?php
                            // Cek data LKH
                            $stmt_lkh = $conn->prepare("SELECT COUNT(*) FROM lkh WHERE id_pegawai=? AND MONTH(tanggal_lkh)=? AND YEAR(tanggal_lkh)=?");
                            $stmt_lkh->bind_param("iii", $id_pegawai, $bulan, $tahun);
                            $stmt_lkh->execute();
                            $stmt_lkh->bind_result($count_lkh);
                            $stmt_lkh->fetch();
                            $stmt_lkh->close();

                            // Ambil NIP untuk nama file
                            $stmt_nama = $conn->prepare("SELECT nip FROM pegawai WHERE id_pegawai = ?");
                            $stmt_nama->bind_param("i", $id_pegawai);
                            $stmt_nama->execute();
                            $stmt_nama->bind_result($nip_pegawai);
                            $stmt_nama->fetch();
                            $stmt_nama->close();
                            
                            $nama_file_nip = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nip_pegawai);
                            $pdf_path = "../generated/LKH_{$months[$bulan]}_{$tahun}_{$nama_file_nip}.pdf";

                            $show_generate = false;
                            $show_download = false;

                            if ($count_lkh == 0) {
                                echo '<div class="alert alert-danger">Data LKH bulan ini belum ada.</div>';
                            } else {
                                if ($aksi === 'generate') {
                                    if (file_exists($pdf_path)) {
                                        unlink($pdf_path);
                                    }
                                    $pdf_file = generate_lkh_pdf($id_pegawai, $bulan, $tahun);
                                    $pdf_url = str_replace('../', '', $pdf_file);
                                    echo "
                                        <script>
                                            document.addEventListener('DOMContentLoaded', function() {
                                                Swal.fire({
                                                    icon: 'success',
                                                    title: 'Berhasil!',
                                                    text: 'LKH berhasil digenerate.',
                                                    showConfirmButton: false,
                                                    timer: 1800
                                                });
                                            });
                                        </script>
                                    ";
                                    $show_generate = false;
                                    $show_download = true;
                                } elseif (file_exists($pdf_path)) {
                                    $pdf_url = str_replace('../', '', $pdf_path);
                                    $show_generate = false;
                                    $show_download = true;
                                } else {
                                    $show_generate = true;
                                    $show_download = false;
                                }
                            }
                            ?>
                            <hr>
                            <table class="table table-bordered">
                                <tr>
                                    <th>Aksi</th>
                                    <td>
                                        <?php if (!empty($show_generate) && $show_generate): ?>
                                            <a href="?id_pegawai=<?= $id_pegawai ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&aksi=generate" class="btn btn-primary">
                                                <i class="fas fa-cogs"></i> Generate LKH
                                            </a>
                                        <?php elseif (!empty($show_download) && $show_download): ?>
                                            <a href="/<?= $pdf_url ?>" target="_blank" class="btn btn-success btn-sm">
                                                <i class="fas fa-download"></i> Download PDF
                                            </a>
                                            <a href="?id_pegawai=<?= $id_pegawai ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&aksi=generate" class="btn btn-warning btn-sm">
                                                <i class="fas fa-sync-alt"></i> Regenerate
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                            <a href="generate_laporan.php?id_pegawai=<?= $id_pegawai ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <?php include __DIR__ . '/../template/footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </div>
    </main>
    <?php include __DIR__ . '/../template/footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
