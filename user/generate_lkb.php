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
require_once '../vendor/fpdf/fpdf.php'; // Pastikan path sesuai lokasi fpdf.php Anda

$id_pegawai = $_SESSION['id_pegawai'];
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date('n');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');
$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';

$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

function generate_lkb_pdf($id_pegawai, $bulan, $tahun) {
    global $conn, $months;

    // Data Pegawai
    $stmt = $conn->prepare("SELECT nama, nip, jabatan, unit_kerja FROM pegawai WHERE id_pegawai = ?");
    $stmt->bind_param("i", $id_pegawai);
    $stmt->execute();
    $stmt->bind_result($nama_pegawai, $nip, $jabatan, $unit_kerja);
    $stmt->fetch();
    $stmt->close();

    // Data RKB (Rencana Kinerja Bulanan)
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

    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);

    // Header
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(0, 8, '    LAPORAN KINERJA BULANAN', 0, 1, 'C');
    $pdf->Cell(0, 8, 'SASARAN KINERJA PEGAWAI', 0, 1, 'C');
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

    // Tabel Sasaran Kinerja Pegawai (RKB) - Tanpa judul "I."
    // $pdf->SetFont('Arial', 'B', 10);
    // $pdf->Cell(0, 10, 'I. SASARAN KINERJA PEGAWAI (Rencana Kinerja Bulanan)', 0, 1, 'L'); // Baris ini dihapus
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(200, 220, 255);
    $pdf->Cell(10, 10, 'No', 1, 0, 'C', true);
    $pdf->Cell(115, 10, 'Uraian Kegiatan', 1, 0, 'C', true);
    $pdf->Cell(35, 10, 'Jumlah', 1, 0, 'C', true);
    $pdf->Cell(20, 10, 'Satuan', 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 9);
    $no_rkb = 1;
    foreach ($rkb_data as $row_rkb) {
        $cell_widths = [10, 115, 35, 20];
        $line_height = 6;
        $uraian_lines = $pdf->GetStringWidth($row_rkb['uraian_kegiatan']) > $cell_widths[1] ? ceil($pdf->GetStringWidth($row_rkb['uraian_kegiatan']) / ($cell_widths[1] - 2)) : 1;
        $max_lines_rkb = max($uraian_lines, 1);
        $row_height_rkb = $line_height * $max_lines_rkb;

        $x = $pdf->GetX();
        $y = $pdf->GetY();

        $pdf->MultiCell($cell_widths[0], $row_height_rkb, $no_rkb++, 1, 'C');
        $pdf->SetXY($x + $cell_widths[0], $y);
        $pdf->MultiCell($cell_widths[1], $row_height_rkb, $row_rkb['uraian_kegiatan'], 1, 'L');
        $pdf->SetXY($x + $cell_widths[0] + $cell_widths[1], $y);
        $pdf->MultiCell($cell_widths[2], $row_height_rkb, $row_rkb['kuantitas'], 1, 'C');
        $pdf->SetXY($x + $cell_widths[0] + $cell_widths[1] + $cell_widths[2], $y);
        $pdf->MultiCell($cell_widths[3], $row_height_rkb, $row_rkb['satuan'], 1, 'C');
        $pdf->Ln($row_height_rkb);
    }
    // $pdf->Ln(5); // Jeda ini mungkin tidak perlu terlalu besar jika tidak ada tabel kedua

    // Tabel Realisasi Kinerja Bulanan (dari LKH) - Seluruh bagian ini dihapus
    /*
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 10, 'II. REALISASI KINERJA BULANAN (diambil dari LKH)', 0, 1, 'L');
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(200, 220, 255);
    $pdf->Cell(10, 10, 'No', 1, 0, 'C', true);
    $pdf->Cell(35, 10, 'Hari / Tanggal', 1, 0, 'C', true);
    $pdf->Cell(40, 10, 'Kegiatan', 1, 0, 'C', true);
    $pdf->Cell(75, 10, 'Uraian Tugas Kegiatan/ Tugas Jabatan', 1, 0, 'C', true);
    $pdf->Cell(20, 10, 'Realisasi', 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 9);
    $no_lkh = 1;
    $hari_indo = [
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
    ];

    foreach ($lkh_data as $row_lkh) {
        $hari = $hari_indo[date('l', strtotime($row_lkh['tanggal_lkh']))];
        $tanggal = date('d-m-Y', strtotime($row_lkh['tanggal_lkh']));

        $cell_widths = [10, 35, 40, 75, 20];
        $line_height = 6;
        $kegiatan_lines = $pdf->GetStringWidth($row_lkh['nama_kegiatan_harian']) > $cell_widths[2] ? ceil($pdf->GetStringWidth($row_lkh['nama_kegiatan_harian']) / ($cell_widths[2] - 2)) : 1;
        $uraian_lines = $pdf->GetStringWidth($row_lkh['uraian_kegiatan_lkh']) > $cell_widths[3] ? ceil($pdf->GetStringWidth($row_lkh['uraian_kegiatan_lkh']) / ($cell_widths[3] - 2)) : 1;
        $max_lines_lkh = max($kegiatan_lines, $uraian_lines, 1);
        $row_height_lkh = $line_height * $max_lines_lkh;

        $x = $pdf->GetX();
        $y = $pdf->GetY();

        $jumlah_kegiatan = ($row_lkh['jumlah_realisasi'] !== null && $row_lkh['satuan_realisasi'] !== null) ? $row_lkh['jumlah_realisasi'] . ' ' . $row_lkh['satuan_realisasi'] : '-';

        $pdf->MultiCell($cell_widths[0], $row_height_lkh, $no_lkh++, 1, 'C');
        $pdf->SetXY($x + $cell_widths[0], $y);
        $pdf->MultiCell($cell_widths[1], $row_height_lkh, "$hari, $tanggal", 1, 'L');
        $pdf->SetXY($x + $cell_widths[0] + $cell_widths[1], $y);
        $pdf->MultiCell($cell_widths[2], $row_height_lkh, $row_lkh['nama_kegiatan_harian'], 1, 'L');
        $pdf->SetXY($x + $cell_widths[0] + $cell_widths[1] + $cell_widths[2], $y);
        $pdf->MultiCell($cell_widths[3], $row_height_lkh, $row_lkh['uraian_kegiatan_lkh'], 1, 'L');
        $pdf->SetXY($x + $cell_widths[0] + $cell_widths[1] + $cell_widths[2] + $cell_widths[3], $y);
        $pdf->Cell($cell_widths[4], $row_height_lkh, $jumlah_kegiatan, 1, 0, 'C');
        $pdf->Ln($row_height_lkh);
    }
    */
    $pdf->Ln(10); // Jeda sebelum tanda tangan

    // Footer Signatures
    $pdf->SetFont('Arial', '', 10);

    $nama_penilai = "H. JAJANG GUNAWAN, S.Ag., M.Pd.I";
    $nip_penilai = "196708251992031003";

    $left_margin = 15;
    $right_margin = 15;
    $page_width = 210 - $left_margin - $right_margin;
    $col_width = 80;
    $gap = 30;

    $pdf->SetX($left_margin);
// Baris untuk titimangsa (di atas Pegawai yang dinilai)
$pdf->Cell($col_width, 5, '', 0, 0, 'L'); // Kolom kiri kosong atau bisa diisi teks lain jika perlu
$pdf->Cell($gap); // Jarak antar kolom
$pdf->Cell($col_width, 5, "Cingambul, " . date("d") . " " . $months[$bulan] . " " . $tahun, 0, 1, 'L'); // Titimangsa

// Baris untuk Pejabat Penilai dan Pegawai yang dinilai (sejajar)
$pdf->SetX($left_margin);
$pdf->Cell($col_width, 5, 'Pejabat Penilai,', 0, 0, 'L'); // Pejabat Penilai
$pdf->Cell($gap); // Jarak antar kolom
$pdf->Cell($col_width, 5, "Pegawai yang dinilai,", 0, 1, 'L'); // Pegawai yang dinilai

$pdf->Ln(17); // Jarak antar baris untuk tanda tangan/nama

$pdf->SetFont('Arial', 'BU', 10);
$pdf->SetX($left_margin);
$pdf->Cell($col_width, 5, $nama_penilai, 0, 0, 'L'); // Nama Penilai
$pdf->Cell($gap); // Jarak antar kolom
$pdf->Cell($col_width, 5, $nama_pegawai, 0, 1, 'L'); // Nama Pegawai

$pdf->SetFont('Arial', '', 10);
$pdf->SetX($left_margin);
$pdf->Cell($col_width, 5, 'NIP. ' . $nip_penilai, 0, 0, 'L'); // NIP Penilai
$pdf->Cell($gap); // Jarak antar kolom
$pdf->Cell($col_width, 5, 'NIP. ' . $nip, 0, 1, 'L'); // NIP Pegawai

    // Simpan PDF dengan nama LKB_Bulan_Tahun_NIP.pdf
    $dir = "../generated";
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $nama_file_nip = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nip);

    $filename = "{$dir}/LKB_{$months[$bulan]}_{$tahun}_{$nama_file_nip}.pdf";
    $pdf->Output('F', $filename);

    return $filename;
}

$page_title = "Generate LKB";
include '../template/header.php';
include '../template/menu_user.php';
include '../template/topbar.php';
?>
<div id="layoutSidenav_content">
    <main>
        <div class="container-fluid px-4">
            <h1 class="mt-4">Generate LKB</h1>
            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-info text-white">
                            <i class="fas fa-file-alt"></i> Generate LKB Bulan <?= $months[$bulan] . ' ' . $tahun ?>
                        </div>
                        <div class="card-body">
                            <?php
                            // Ambil status RKB bulan ini
                            $stmt_rkb_status = $conn->prepare("SELECT id_rkb, status_verval FROM rkb WHERE id_pegawai=? AND bulan=? AND tahun=?");
                            $stmt_rkb_status->bind_param("iii", $id_pegawai, $bulan, $tahun);
                            $stmt_rkb_status->execute();
                            $stmt_rkb_status->store_result();
                            $count_rkb = $stmt_rkb_status->num_rows;
                            $id_rkb = null;
                            $status_verval_rkb = null;
                            if ($count_rkb > 0) {
                                $stmt_rkb_status->bind_result($id_rkb, $status_verval_rkb);
                                $stmt_rkb_status->fetch();
                            }
                            $stmt_rkb_status->close();

                            // Pengecekan LKH tidak lagi diperlukan untuk generate LKB
                            // Karena LKB tidak lagi menampilkan data dari LKH
                            // Namun, jika Anda tetap ingin user harus menyelesaikan LKH sebelum generate LKB,
                            // Anda bisa uncomment bagian ini dan tambahkan ke kondisi if.
                            /*
                            $stmt_lkh_status = $conn->prepare("SELECT COUNT(*) FROM lkh WHERE id_pegawai=? AND MONTH(tanggal_lkh)=? AND YEAR(tanggal_lkh)=? AND status_verval != 'disetujui'");
                            $stmt_lkh_status->bind_param("iii", $id_pegawai, $bulan, $tahun);
                            $stmt_lkh_status->execute();
                            $stmt_lkh_status->bind_result($unapproved_lkh_count);
                            $stmt_lkh_status->fetch();
                            $stmt_lkh_status->close();
                            */
                            $unapproved_lkh_count = 0; // Set 0 karena tidak ada dependency lagi secara tampilan PDF

                            // Ambil NIP untuk nama file
                            $stmt_nama = $conn->prepare("SELECT nip FROM pegawai WHERE id_pegawai = ?");
                            $stmt_nama->bind_param("i", $id_pegawai);
                            $stmt_nama->execute();
                            $stmt_nama->bind_result($nip_pegawai);
                            $stmt_nama->fetch();
                            $stmt_nama->close();
                            $nama_file_nip = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nip_pegawai);
                            $pdf_path = "../generated/LKB_{$months[$bulan]}_{$tahun}_{$nama_file_nip}.pdf";

                            $show_generate = false;
                            $show_download = false;

                            if ($count_rkb == 0) {
                                echo '<div class="alert alert-danger">Data RKB bulan ini belum ada. Mohon input RKB terlebih dahulu.</div>';
                            } elseif ($status_verval_rkb !== 'disetujui') {
                                echo '<div class="alert alert-warning">RKB bulan ini belum disetujui. Silakan ajukan dan tunggu approval.</div>';
                            }
                            // Kondisi ini dihapus karena LKH tidak lagi jadi bagian dari tampilan LKB
                            /* elseif ($unapproved_lkh_count > 0) {
                                echo '<div class="alert alert-warning">Masih ada LKH bulan ini yang belum disetujui. Pastikan semua LKH sudah disetujui sebelum membuat LKB.</div>';
                            } */
                            else {
                                // Sudah ada data RKB dan disetujui
                                if ($aksi === 'generate') {
                                    // Hapus file lama jika ada
                                    if (file_exists($pdf_path)) {
                                        unlink($pdf_path);
                                    }
                                    $pdf_file = generate_lkb_pdf($id_pegawai, $bulan, $tahun);
                                    $pdf_url = str_replace('../', '', $pdf_file);
                                    // SweetAlert success
                                    echo "
                                        <script>
                                            document.addEventListener('DOMContentLoaded', function() {
                                                Swal.fire({
                                                    icon: 'success',
                                                    title: 'Berhasil!',
                                                    text: 'LKB berhasil digenerate.',
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
                                            <a href="?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&aksi=generate" class="btn btn-primary">
                                                <i class="fas fa-cogs"></i> Generate LKB
                                            </a>
                                        <?php elseif (!empty($show_download) && $show_download): ?>
                                            <a href="/<?= $pdf_url ?>" target="_blank" class="btn btn-success btn-sm">
                                                <i class="fas fa-download"></i> Download PDF
                                            </a>
                                            <a href="?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&aksi=generate" class="btn btn-warning btn-sm">
                                                <i class="fas fa-sync-alt"></i> Regenerate
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                            <a href="generate_lkb-lkh.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <?php include __DIR__ . '/../template/footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>