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

// Handle POST data for place and date
$tempat_cetak = isset($_POST['tempat_cetak']) ? $_POST['tempat_cetak'] : 'Cingambul';
$tanggal_cetak = isset($_POST['tanggal_cetak']) ? $_POST['tanggal_cetak'] : date('Y-m-d');

if (!$id_pegawai || !$bulan || !$tahun) {
    header('Location: generate_laporan.php');
    exit;
}

$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

function generate_lkh_pdf($id_pegawai, $bulan, $tahun, $tempat_cetak = 'Cingambul', $tanggal_cetak = null) {
    global $conn, $months;
    
    // Set default date if not provided
    if (!$tanggal_cetak) {
        $tanggal_cetak = date('Y-m-d');
    }
    
    // Format tanggal cetak - Use different variable name to avoid conflict
    $tanggal_signature = date('d', strtotime($tanggal_cetak)) . " " . $months[(int)date('m', strtotime($tanggal_cetak))] . " " . date('Y', strtotime($tanggal_cetak));

    // Data Pegawai dan Penilai
    $stmt = $conn->prepare("SELECT nama, nip, jabatan, unit_kerja, nama_penilai, nip_penilai FROM pegawai WHERE id_pegawai = ?");
    $stmt->bind_param("i", $id_pegawai);
    $stmt->execute();
    $stmt->bind_result($nama_pegawai, $nip, $jabatan, $unit_kerja, $nama_penilai, $nip_penilai);
    $stmt->fetch();
    $stmt->close();

    // Default penilai jika tidak ada data
    if (!$nama_penilai) {
        $nama_penilai = "H. JAJANG GUNAWAN, S.Ag., M.Pd.I";
        $nip_penilai = "196708251992031003";
    }

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
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 8, ' Nama', 1, 0, 'L', true);
    $pdf->Cell(5, 8, ':', 1, 0, 'C', true);
    // Cetak Nama Pegawai tebal
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

    // Table Header
    $pdf->SetFont('Arial', 'B', 9); // Cetak header tabel tebal
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(10, 10, 'No', 1, 0, 'C', true);
    $pdf->Cell(35, 10, 'Hari / Tanggal', 1, 0, 'C', true);
    $pdf->Cell(45, 10, 'Kegiatan', 1, 0, 'C', true);
    $pdf->Cell(65, 10, 'Uraian Tugas Kegiatan/ Tugas Jabatan', 1, 0, 'C', true);
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

        // Calculate total height for all activities on this date
        $total_height = 0;
        $activity_heights = [];
        
        foreach ($activities as $activity) {
            $kegiatan_text = '- ' . $activity['nama_kegiatan_harian'];
            $uraian_text = '- ' . $activity['uraian_kegiatan_lkh'];
            
            $jumlah_kegiatan = ($activity['jumlah_realisasi'] !== null && $activity['satuan_realisasi'] !== null) 
                ? $activity['jumlah_realisasi'] . ' ' . $activity['satuan_realisasi'] 
                : '-';
            $jumlah_text = '- ' . $jumlah_kegiatan;

            // Calculate max height for multi-line cells
            $cell_widths = [10, 35, 45, 65, 25];
            $line_height = 5;
            
            // Calculate lines needed for text wrapping
            $kegiatan_lines = max(1, ceil($pdf->GetStringWidth($kegiatan_text) / ($cell_widths[2] - 4)));
            $uraian_lines = max(1, ceil($pdf->GetStringWidth($uraian_text) / ($cell_widths[3] - 4)));
            $jumlah_lines = max(1, ceil($pdf->GetStringWidth($jumlah_text) / ($cell_widths[4] - 4)));
            
            $max_lines = max($kegiatan_lines, $uraian_lines, $jumlah_lines, 1);
            $row_height = $line_height * $max_lines + 2; // Reduced padding from 4 to 2
            
            $activity_heights[] = $row_height;
            $total_height += $row_height;
        }

        // Check if we need a new page (optimize threshold to better utilize page space)
        if ($pdf->GetY() + $total_height > 267) {
            $pdf->AddPage();
            // Redraw table header on new page
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(10, 10, 'No', 1, 0, 'C', true);
            $pdf->Cell(35, 10, 'Hari / Tanggal', 1, 0, 'C', true);
            $pdf->Cell(45, 10, 'Kegiatan', 1, 0, 'C', true);
            $pdf->Cell(65, 10, 'Uraian Tugas Kegiatan/ Tugas Jabatan', 1, 0, 'C', true);
            $pdf->Cell(25, 10, 'Jumlah', 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 9);
        }

        $start_x = $pdf->GetX();
        $start_y = $pdf->GetY();

        // Draw merged cells for No and Date columns
        $pdf->Cell($cell_widths[0], $total_height, $no++, 1, 0, 'C');
        $pdf->SetXY($start_x + $cell_widths[0], $start_y);
        $pdf->Cell($cell_widths[1], $total_height, "$hari, $tanggal_formatted", 1, 0, 'C');

        // Draw activity rows
        $current_y = $start_y;
        foreach ($activities as $index => $activity) {
            $kegiatan_text = '- ' . $activity['nama_kegiatan_harian'];
            $uraian_text = '- ' . $activity['uraian_kegiatan_lkh'];
            
            $jumlah_kegiatan = ($activity['jumlah_realisasi'] !== null && $activity['satuan_realisasi'] !== null) 
                ? $activity['jumlah_realisasi'] . ' ' . $activity['satuan_realisasi'] 
                : '-';
            $jumlah_text = '- ' . $jumlah_kegiatan;

            $row_height = $activity_heights[$index];
            $is_last_activity = ($index === count($activities) - 1);

            // Draw borders for activity cells
            $kegiatan_x = $start_x + $cell_widths[0] + $cell_widths[1];
            $uraian_x = $kegiatan_x + $cell_widths[2];
            $jumlah_x = $uraian_x + $cell_widths[3];

            // Draw cell borders
            if ($index === 0) {
                // First activity - draw top, left, and right borders
                $pdf->Line($kegiatan_x, $current_y, $kegiatan_x + $cell_widths[2], $current_y); // Top border kegiatan
                $pdf->Line($uraian_x, $current_y, $uraian_x + $cell_widths[3], $current_y); // Top border uraian
                $pdf->Line($jumlah_x, $current_y, $jumlah_x + $cell_widths[4], $current_y); // Top border jumlah
            }
            
            // Left borders
            $pdf->Line($kegiatan_x, $current_y, $kegiatan_x, $current_y + $row_height);
            $pdf->Line($uraian_x, $current_y, $uraian_x, $current_y + $row_height);
            $pdf->Line($jumlah_x, $current_y, $jumlah_x, $current_y + $row_height);
            
            // Right border
            $pdf->Line($jumlah_x + $cell_widths[4], $current_y, $jumlah_x + $cell_widths[4], $current_y + $row_height);
            
            // Bottom border (only for last activity)
            if ($is_last_activity) {
                $pdf->Line($kegiatan_x, $current_y + $row_height, $jumlah_x + $cell_widths[4], $current_y + $row_height);
            }

            // Add content with minimal padding
            $pdf->SetXY($kegiatan_x + 1, $current_y + 1);
            $pdf->MultiCell($cell_widths[2] - 2, 4, $kegiatan_text, 0, 'L');
            
            $pdf->SetXY($uraian_x + 1, $current_y + 1);
            $pdf->MultiCell($cell_widths[3] - 2, 4, $uraian_text, 0, 'L');
            
            $pdf->SetXY($jumlah_x + 1, $current_y + 1);
            $pdf->MultiCell($cell_widths[4] - 2, 4, $jumlah_text, 0, 'L');

            $current_y += $row_height;
        }

        // Move to next row
        $pdf->SetXY($start_x, $start_y + $total_height);
    }

    // Check if we need a new page for signature (optimize for better space usage)
    if ($pdf->GetY() > 267) {
        $pdf->AddPage();
    }

    // Footer Signatures - more compact layout
    $pdf->Ln(10); // Minimal spacing at top of signature block
    $pdf->SetFont('Arial', '', 10);

    // Tanda tangan dengan layout yang lebih kompak
    $left_margin = 25;
    $col_width = 80;
    $gap = 35;

    $pdf->SetX($left_margin);
    // Baris untuk titimangsa (di atas Pegawai yang dinilai)
    $pdf->Cell($col_width, 4, '', 0, 0, 'L'); // Kolom kiri kosong, tinggi dikurangi
    $pdf->Cell($gap); // Jarak antar kolom
    $pdf->Cell($col_width, 4, $tempat_cetak . ", " . $tanggal_signature, 0, 1, 'L'); // Use the correct signature date variable

    // Baris untuk Pejabat Penilai dan Pegawai yang dinilai (sejajar)
    $pdf->SetX($left_margin);
    $pdf->Cell($col_width, 4, 'Pejabat Penilai,', 0, 0, 'L'); // Pejabat Penilai
    $pdf->Cell($gap); // Jarak antar kolom
    $pdf->Cell($col_width, 4, "Pegawai yang dinilai,", 0, 1, 'L'); // Pegawai yang dinilai

    $pdf->Ln(20); // Reduced signature space for compactness

    $pdf->SetFont('Arial', 'BU', 10);
    $pdf->SetX($left_margin);
    $pdf->Cell($col_width, 4, $nama_penilai, 0, 0, 'L'); // Nama Penilai
    $pdf->Cell($gap); // Jarak antar kolom
    $pdf->Cell($col_width, 4, $nama_pegawai, 0, 1, 'L'); // Nama Pegawai

    $pdf->SetFont('Arial', '', 10);
    $pdf->SetX($left_margin);
    $pdf->Cell($col_width, 4, 'NIP. ' . $nip_penilai, 0, 0, 'L'); // NIP Penilai
    $pdf->Cell($gap); // Jarak antar kolom
    $pdf->Cell($col_width, 4, 'NIP. ' . $nip, 0, 1, 'L'); // NIP Pegawai

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
                                if ($aksi === 'generate' && isset($_POST['tempat_cetak']) && isset($_POST['tanggal_cetak'])) {
                                    if (file_exists($pdf_path)) {
                                        unlink($pdf_path);
                                    }
                                    $pdf_file = generate_lkh_pdf($id_pegawai, $bulan, $tahun, $tempat_cetak, $tanggal_cetak);
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
                                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateModal">
                                                <i class="fas fa-cogs"></i> Generate LKH
                                            </button>
                                        <?php elseif (!empty($show_download) && $show_download): ?>
                                            <a href="/<?= $pdf_url ?>" target="_blank" class="btn btn-success btn-sm">
                                                <i class="fas fa-download"></i> Download PDF
                                            </a>
                                            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#regenerateModal">
                                                <i class="fas fa-sync-alt"></i> Regenerate
                                            </button>
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

<!-- Modal Generate -->
<div class="modal fade" id="generateModal" tabindex="-1" aria-labelledby="generateModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="generateModalLabel">Tentukan Tempat dan Tanggal Cetak</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="?id_pegawai=<?= $id_pegawai ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&aksi=generate">
        <div class="modal-body">
          <div class="mb-3">
            <label for="tempat_cetak" class="form-label">Tempat Cetak</label>
            <input type="text" class="form-control" id="tempat_cetak" name="tempat_cetak" value="Cingambul" required>
          </div>
          <div class="mb-3">
            <label for="tanggal_cetak" class="form-label">Tanggal Cetak</label>
            <input type="date" class="form-control" id="tanggal_cetak" name="tanggal_cetak" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Generate LKH</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Regenerate -->
<div class="modal fade" id="regenerateModal" tabindex="-1" aria-labelledby="regenerateModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="regenerateModalLabel">Tentukan Tempat dan Tanggal Cetak</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="?id_pegawai=<?= $id_pegawai ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&aksi=generate">
        <div class="modal-body">
          <div class="mb-3">
            <label for="tempat_cetak_regen" class="form-label">Tempat Cetak</label>
            <input type="text" class="form-control" id="tempat_cetak_regen" name="tempat_cetak" value="Cingambul" required>
          </div>
          <div class="mb-3">
            <label for="tanggal_cetak_regen" class="form-label">Tanggal Cetak</label>
            <input type="date" class="form-control" id="tanggal_cetak_regen" name="tanggal_cetak" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-warning">Regenerate LKH</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
