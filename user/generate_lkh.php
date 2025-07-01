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

function generate_lkh_pdf($id_pegawai, $bulan, $tahun) {
    global $conn, $months;

    // Data Pegawai
    $stmt = $conn->prepare("SELECT nama, nip, jabatan, unit_kerja FROM pegawai WHERE id_pegawai = ?");
    $stmt->bind_param("i", $id_pegawai);
    $stmt->execute();
    $stmt->bind_result($nama_pegawai, $nip, $jabatan, $unit_kerja);
    $stmt->fetch();
    $stmt->close();

    // Data LKH - Group by date
    $stmt = $conn->prepare("SELECT tanggal_lkh, nama_kegiatan_harian, uraian_kegiatan_lkh, jumlah_realisasi, satuan_realisasi FROM lkh WHERE id_pegawai=? AND MONTH(tanggal_lkh)=? AND YEAR(tanggal_lkh)=? ORDER BY tanggal_lkh");
    $stmt->bind_param("iii", $id_pegawai, $bulan, $tahun);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Group data by date
    $lkh_data = [];
    while ($row = $result->fetch_assoc()) {
        $date = $row['tanggal_lkh'];
        if (!isset($lkh_data[$date])) {
            $lkh_data[$date] = [];
        }
        $lkh_data[$date][] = $row;
    }
    $stmt->close();

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();

    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);

    // Header
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(0, 8, '    LAPORAN KINERJA HARIAN', 0, 1, 'C');
    $pdf->Cell(0, 8, 'SASARAN KINERJA PEGAWAI', 0, 1, 'C');
    $pdf->Ln(4);

    // Biodata Pegawai dalam bentuk tabel
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(40, 8, 'Nama', 1, 0, 'L', true);
    $pdf->Cell(5, 8, ':', 1, 0, 'C', true);
    // Cetak Nama Pegawai tebal
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

    // Table Header
    $pdf->SetFont('Arial', 'B', 9); // Cetak header tabel tebal
    $pdf->SetFillColor(200, 220, 255);
    $pdf->Cell(10, 10, 'No', 1, 0, 'C', true);
    $pdf->Cell(35, 10, 'Hari / Tanggal', 1, 0, 'C', true);
    $pdf->Cell(35, 10, 'Kegiatan', 1, 0, 'C', true);
    $pdf->Cell(75, 10, 'Uraian Tugas Kegiatan/ Tugas Jabatan', 1, 0, 'C', true);
    $pdf->Cell(25, 10, 'Jumlah', 1, 1, 'C', true);

    // Table Rows
    $pdf->SetFont('Arial', '', 9);
    $no = 1;
    $hari_indo = [
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
    ];

    foreach ($lkh_data as $tanggal => $activities) {
        $hari = $hari_indo[date('l', strtotime($tanggal))];
        $tanggal_formatted = date('d-m-Y', strtotime($tanggal));

        // Combine activities for the same date
        $kegiatan_combined = '';
        $uraian_combined = '';
        $jumlah_combined = '';
        
        foreach ($activities as $index => $activity) {
            $kegiatan_combined .= '- ' . $activity['nama_kegiatan_harian'];
            $uraian_combined .= '- ' . $activity['uraian_kegiatan_lkh'];
            
            $jumlah_kegiatan = ($activity['jumlah_realisasi'] !== null && $activity['satuan_realisasi'] !== null) 
                ? $activity['jumlah_realisasi'] . ' ' . $activity['satuan_realisasi'] 
                : '-';
            $jumlah_combined .= '- ' . $jumlah_kegiatan;
            
            if ($index < count($activities) - 1) {
                $kegiatan_combined .= "\n";
                $uraian_combined .= "\n";
                $jumlah_combined .= "\n";
            }
        }

        // Calculate max height for multi-line cells
        $cell_widths = [10, 35, 35, 75, 25];
        $line_height = 4;
        
        // More accurate line calculation using FPDF's GetStringWidth
        $kegiatan_lines = max(1, substr_count($kegiatan_combined, "\n") + 1);
        $uraian_lines = max(1, substr_count($uraian_combined, "\n") + 1);
        $jumlah_lines = max(1, substr_count($jumlah_combined, "\n") + 1);
        
        // Calculate estimated lines for text wrapping
        $kegiatan_text_lines = ceil($pdf->GetStringWidth($kegiatan_combined) / ($cell_widths[2] - 4));
        $uraian_text_lines = ceil($pdf->GetStringWidth($uraian_combined) / ($cell_widths[3] - 4));
        $jumlah_text_lines = ceil($pdf->GetStringWidth($jumlah_combined) / ($cell_widths[4] - 4));
        
        $max_lines = max($kegiatan_lines, $uraian_lines, $jumlah_lines, $kegiatan_text_lines, $uraian_text_lines, $jumlah_text_lines, 2);
        $row_height = $line_height * $max_lines;

        // Check if we need a new page
        if ($pdf->GetY() + $row_height > 270) {
            $pdf->AddPage();
            // Redraw table header on new page
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(200, 220, 255);
            $pdf->Cell(10, 10, 'No', 1, 0, 'C', true);
            $pdf->Cell(35, 10, 'Hari / Tanggal', 1, 0, 'C', true);
            $pdf->Cell(35, 10, 'Kegiatan', 1, 0, 'C', true);
            $pdf->Cell(75, 10, 'Uraian Tugas Kegiatan/ Tugas Jabatan', 1, 0, 'C', true);
            $pdf->Cell(25, 10, 'Jumlah', 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 9);
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();

        // Draw cells with consistent height
        $pdf->Cell($cell_widths[0], $row_height, $no++, 1, 0, 'C');
        $pdf->SetXY($x + $cell_widths[0], $y);
        $pdf->Cell($cell_widths[1], $row_height, "$hari, $tanggal_formatted", 1, 0, 'L');
        
        // Use temporary positions for MultiCell content
        $pdf->SetXY($x + $cell_widths[0] + $cell_widths[1], $y);
        $kegiatan_y = $pdf->GetY();
        $pdf->MultiCell($cell_widths[2], $line_height, $kegiatan_combined, 0, 'L');
        $kegiatan_end_y = $pdf->GetY();
        
        $pdf->SetXY($x + $cell_widths[0] + $cell_widths[1] + $cell_widths[2], $y);
        $uraian_y = $pdf->GetY();
        $pdf->MultiCell($cell_widths[3], $line_height, $uraian_combined, 0, 'L');
        $uraian_end_y = $pdf->GetY();
        
        $pdf->SetXY($x + $cell_widths[0] + $cell_widths[1] + $cell_widths[2] + $cell_widths[3], $y);
        $jumlah_y = $pdf->GetY();
        $pdf->MultiCell($cell_widths[4], $line_height, $jumlah_combined, 0, 'L');
        $jumlah_end_y = $pdf->GetY();
        
        // Draw borders for MultiCell areas
        $pdf->Rect($x + $cell_widths[0] + $cell_widths[1], $y, $cell_widths[2], $row_height);
        $pdf->Rect($x + $cell_widths[0] + $cell_widths[1] + $cell_widths[2], $y, $cell_widths[3], $row_height);
        $pdf->Rect($x + $cell_widths[0] + $cell_widths[1] + $cell_widths[2] + $cell_widths[3], $y, $cell_widths[4], $row_height);
        
        // Move to next row
        $pdf->SetXY($x, $y + $row_height);
    }

    // Footer Signatures
    $pdf->Ln(10);
    $pdf->SetFont('Arial', '', 10);

    $nama_penilai = "H. JAJANG GUNAWAN, S.Ag., M.Pd.I";
    $nip_penilai = "196708251992031003";

    // Tanda tangan rata kiri namun tetap pada posisinya
$left_margin = 15;
$right_margin = 15;
$page_width = 210 - $left_margin - $right_margin;
$col_width = 80;
$gap = 30;

$pdf->SetX($left_margin);
// Baris untuk titimangsa (di atas Pegawai yang dinilai)
$pdf->Cell($col_width, 5, '', 0, 0, 'L'); // Kolom kiri kosong
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

    // Simpan PDF dengan nama LKH_Bulan_Tahun_NIP.pdf
    $dir = "../generated";
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    // Bersihkan NIP dari karakter yang tidak valid untuk nama file
    $nama_file_nip = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nip);

    $filename = "{$dir}/LKH_{$months[$bulan]}_{$tahun}_{$nama_file_nip}.pdf";
    $pdf->Output('F', $filename);

    return $filename;
}

$page_title = "Generate LKH";
include '../template/header.php';
include '../template/menu_user.php';
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
              // Ambil status LKH bulan ini
              $stmt = $conn->prepare("SELECT id_lkh, status_verval FROM lkh WHERE id_pegawai=? AND MONTH(tanggal_lkh)=? AND YEAR(tanggal_lkh)=?");
              $stmt->bind_param("iii", $id_pegawai, $bulan, $tahun);
              $stmt->execute();
              $stmt->store_result();
              $count_lkh = $stmt->num_rows;
              $id_lkh = null;
              $status_verval = null;
              if ($count_lkh > 0) {
                  $stmt->bind_result($id_lkh, $status_verval);
                  $stmt->fetch();
              }
              $stmt->close();

              // Ambil NIP untuk nama file
              $stmt_nama = $conn->prepare("SELECT nip FROM pegawai WHERE id_pegawai = ?");
              $stmt_nama->bind_param("i", $id_pegawai);
              $stmt_nama->execute();
              $stmt_nama->bind_result($nip_pegawai);
              $stmt_nama->fetch();
              $stmt_nama->close();
              $nama_file_nip = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nip_pegawai);
              $pdf_path = "../generated/LKH_{$months[$bulan]}_{$tahun}_{$nama_file_nip}.pdf";

              if ($count_lkh == 0) {
                  echo '<div class="alert alert-danger">Data LKH bulan ini belum ada.</div>';
                  $show_generate = false;
                  $show_download = false;
              } elseif ($status_verval !== 'disetujui') {
                  echo '<div class="alert alert-warning">LKH bulan ini belum disetujui. Silakan ajukan dan tunggu approval.</div>';
                  $show_generate = false;
                  $show_download = false;
              } else {
                  // Sudah ada data dan sudah disetujui
                  if ($aksi === 'generate') {
                      // Hapus file lama jika ada
                      if (file_exists($pdf_path)) {
                          unlink($pdf_path);
                      }
                      $pdf_file = generate_lkh_pdf($id_pegawai, $bulan, $tahun);
                      $pdf_url = str_replace('../', '', $pdf_file);
                      // SweetAlert success
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
                      <a href="?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&aksi=generate" class="btn btn-primary">
                        <i class="fas fa-cogs"></i> Generate LKH
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

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
