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

// Handle POST data for place and date
$tempat_cetak = isset($_POST['tempat_cetak']) ? $_POST['tempat_cetak'] : 'Cingambul';
$tanggal_cetak = isset($_POST['tanggal_cetak']) ? $_POST['tanggal_cetak'] : date('Y-m-d');

$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

function generate_lkb_pdf($id_pegawai, $bulan, $tahun, $tempat_cetak = 'Cingambul', $tanggal_cetak = null) {
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

    // --- COVER PAGE ---
    $pdf->AddPage();
    // For cover, we might temporarily set different margins, but keep auto page break off.
    // We will reset margins and turn auto page break ON for content pages.
    $pdf->SetMargins(20, 20, 20); // Adjust margins for cover if needed, example wider margins
    $pdf->SetAutoPageBreak(false); // No auto page break for cover

    // If you have a background image for the cover, uncomment and adjust path:
    $pdf->Image('../assets/img/cover_background.png', 0, 0, $pdf->GetPageWidth(), $pdf->GetPageHeight());

    // LAPORAN KINERJA HARIAN
    $pdf->SetFont('Times', 'B', 24);
    $pdf->SetY(40); // Adjust Y position
    $pdf->Cell(0, 10, 'LAPORAN KINERJA HARIAN', 0, 1, 'C');

    // BULAN [MONTH]
    $pdf->SetFont('Times', 'B', 20);
    $pdf->Cell(0, 10, 'BULAN ' . strtoupper($months[$bulan]), 0, 1, 'C');

    // TAHUN [YEAR]
    $pdf->Cell(0, 10, 'TAHUN ' . $tahun, 0, 1, 'C');
    $pdf->Ln(20); // Space after title

    // Logo (adjust path and position as needed)
    $logo_path = '../assets/img/logo_kemenag.png'; // Make sure this path is correct
    if (file_exists($logo_path)) {
        $pdf->Image($logo_path, ($pdf->GetPageWidth() / 2) - 25, $pdf->GetY(), 50, 50); // Centered, 50x50mm
    } else {
        // Fallback if logo not found (e.g., text placeholder)
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 50, '[LOGO NOT FOUND]', 0, 1, 'C');
    }
    $pdf->Ln(20); // Space after logo

    // Nama dan NIP Pegawai
    $pdf->SetFont('Times', 'B', 14);
    $pdf->SetY($pdf->GetPageHeight() - 100); // Position relative to bottom
    $pdf->Cell(0, 8, $nama_pegawai, 0, 1, 'C');
    $pdf->SetFont('Times', '', 12);
    $pdf->Cell(0, 8, 'NIP. ' . $nip, 0, 1, 'C');
    $pdf->Ln(30);

    // MTSN 11 MAJALENGKA
    $pdf->SetFont('Times', 'B', 16);
    $pdf->Cell(0, 8, 'MTsN 11 MAJALENGKA', 0, 1, 'C');

    // KEMENTERIAN AGAMA KABUPATEN MAJALENGKA
    $pdf->SetFont('Times', 'B', 14);
    $pdf->Cell(0, 8, 'KEMENTERIAN AGAMA KABUPATEN MAJALENGKA', 0, 1, 'C');

    // --- END OF COVER PAGE ---

    // Now, add the content pages (your original LKB content)
    $pdf->AddPage();

    // Reset margins and set auto page break for subsequent pages (content pages)
    $bottom_margin_for_content = 15; // Define your desired bottom margin for content pages
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, $bottom_margin_for_content); // This is key!

    // Header
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(0, 8, '     LAPORAN KINERJA BULANAN', 0, 1, 'C');
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

    // Tabel Sasaran Kinerja Pegawai (RKB)
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

        // Calculate lines needed for text wrapping
        // Note: For MultiCell, FPDF automatically handles page breaks if SetAutoPageBreak is true
        // We only need to calculate the height for the current row to draw the borders correctly.
        $uraian_lines = max(1, ceil($pdf->GetStringWidth($row_rkb['uraian_kegiatan']) / ($cell_widths[1] - 4)));
        $kuantitas_lines = max(1, ceil($pdf->GetStringWidth($row_rkb['kuantitas']) / ($cell_widths[2] - 4)));
        $satuan_lines = max(1, ceil($pdf->GetStringWidth($row_rkb['satuan']) / ($cell_widths[3] - 4)));

        $max_lines = max($uraian_lines, $kuantitas_lines, $satuan_lines, 1);
        $row_height = $line_height * $max_lines + 2;

        // Store current Y position before drawing MultiCells
        $current_y = $pdf->GetY();

        // Check if adding this row would exceed the page break trigger
        // FPDF's internal mechanism for page breaks when AutoPageBreak is ON
        // will trigger before the content is drawn if it will overflow.
        // We ensure there's enough space for the _next_ row, or that the current row fits.
        // If the current MultiCell operations trigger a page break, FPDF will handle it.
        // We mainly need to draw the cells and borders.

        // Draw cells with consistent height for borders
        // Start X and Y for the row
        $start_x = $pdf->GetX();
        $start_y = $pdf->GetY();

        // Check if there is enough space for the full row, including borders and content, before drawing.
        // This is a more proactive check for tables with variable row heights.
        // If not enough space, add a new page.
        // This effectively simulates the old check but without relying on protected members.
        // This check is often omitted if you trust FPDF's auto page break with MultiCell.
        // However, for complex tables where you draw borders *before* MultiCells, this can be useful.
        $page_break_trigger = $pdf->GetPageHeight() - $bottom_margin_for_content; // FPDF's internal page break trigger calculation
        if ($current_y + $row_height > $page_break_trigger) {
            $pdf->AddPage();
            // Redraw table header on new page
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(10, 10, 'No', 1, 0, 'C', true);
            $pdf->Cell(115, 10, 'Uraian Kegiatan', 1, 0, 'C', true);
            $pdf->Cell(25, 10, 'Jumlah', 1, 0, 'C', true);
            $pdf->Cell(30, 10, 'Satuan', 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 9);
            $start_x = $pdf->GetX(); // Reset start_x and start_y for new page
            $start_y = $pdf->GetY();
        }

        // Draw fixed-height cell for 'No'
        $pdf->Cell($cell_widths[0], $row_height, $no_rkb++, 0, 0, 'C');

        // Draw borders for MultiCell areas
        $pdf->Rect($start_x + $cell_widths[0], $start_y, $cell_widths[1], $row_height);
        $pdf->Rect($start_x + $cell_widths[0] + $cell_widths[1], $start_y, $cell_widths[2], $row_height);
        $pdf->Rect($start_x + $cell_widths[0] + $cell_widths[1] + $cell_widths[2], $start_y, $cell_widths[3], $row_height);

        // Add content with padding using MultiCell. FPDF will handle page breaks for MultiCell.
        // We set the Y position back to where the row started for the MultiCells.
        $pdf->SetXY($start_x + $cell_widths[0] + 1, $start_y + 1);
        $pdf->MultiCell($cell_widths[1] - 2, $line_height, $row_rkb['uraian_kegiatan'], 0, 'L');

        // Capture Y after first MultiCell to align next MultiCells
        $y_after_first_mc = $pdf->GetY();

        // For the other cells, we need to go back to the starting Y of the row
        // and adjust X. Then after drawing, set Y to the max Y of the row.
        $pdf->SetXY($start_x + $cell_widths[0] + $cell_widths[1] + 1, $start_y + 1);
        $pdf->MultiCell($cell_widths[2] - 2, $line_height, $row_rkb['kuantitas'], 0, 'C');

        $y_after_second_mc = $pdf->GetY();

        $pdf->SetXY($start_x + $cell_widths[0] + $cell_widths[1] + $cell_widths[2] + 1, $start_y + 1);
        $pdf->MultiCell($cell_widths[3] - 2, $line_height, $row_rkb['satuan'], 0, 'C');

        // After drawing all MultiCells for the row, set the cursor to the maximum Y reached by any MultiCell
        // This ensures the next row starts correctly below the tallest cell.
        $pdf->SetY(max($y_after_first_mc, $y_after_second_mc, $pdf->GetY()));
    }

    // Check if we need a new page for signature block
    // We assume the signature block needs about 50mm height.
    if ($pdf->GetY() > ($pdf->GetPageHeight() - $bottom_margin_for_content - 50)) {
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
                                if ($aksi === 'generate' && isset($_POST['tempat_cetak']) && isset($_POST['tanggal_cetak'])) {
                                    // Hapus file lama jika ada
                                    if (file_exists($pdf_path)) {
                                        unlink($pdf_path);
                                    }
                                    $pdf_file = generate_lkb_pdf($id_pegawai, $bulan, $tahun, $tempat_cetak, $tanggal_cetak);
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
                                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateModal">
                                                <i class="fas fa-cogs"></i> Generate LKB
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
                            <a href="generate_lkb-lkh.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <?php include __DIR__ . '/../template/footer.php'; ?>
</div>

<div class="modal fade" id="generateModal" tabindex="-1" aria-labelledby="generateModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="generateModalLabel">Tentukan Tempat dan Tanggal Cetak</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&aksi=generate">
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
          <button type="submit" class="btn btn-primary">Generate LKB</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="regenerateModal" tabindex="-1" aria-labelledby="regenerateModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="regenerateModalLabel">Tentukan Tempat dan Tanggal Cetak</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&aksi=generate">
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
          <button type="submit" class="btn btn-warning">Regenerate LKB</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>