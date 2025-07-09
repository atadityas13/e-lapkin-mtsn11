<?php
// Remove session_start() and includes since they're handled by the parent file
if (!isset($userData)) {
    // Include necessary files only if not already included
    session_start();
    require_once __DIR__ . '/config/mobile_session.php';
    require_once __DIR__ . '/../config/database.php';
    checkMobileLogin();
    $userData = getMobileSessionData();
    $id_pegawai_login = $userData['id_pegawai'];
    $nama_pegawai_login = $userData['nama'];
}

// Function to get available years with data
function getAvailableYears($conn, $id_pegawai) {
    $stmt = $conn->prepare("
        SELECT DISTINCT rkb.tahun 
        FROM rkb 
        JOIN rhk ON rkb.id_rhk = rhk.id_rhk 
        WHERE rhk.id_pegawai = ? 
        ORDER BY rkb.tahun DESC
    ");
    $stmt->bind_param("i", $id_pegawai);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $years = [];
    while ($row = $result->fetch_assoc()) {
        $years[] = $row['tahun'];
    }
    $stmt->close();
    
    return $years;
}

// Get available years
$available_years = getAvailableYears($conn, $id_pegawai_login);

// Get year parameter - default to latest available year
$year = isset($_GET['year']) ? (int)$_GET['year'] : (count($available_years) > 0 ? $available_years[0] : (int)date('Y'));

// Check if this is a PDF download request
$is_pdf_download = isset($_GET['download']) && $_GET['download'] === 'pdf';
$is_generate_file = isset($_GET['generate']) && $_GET['generate'] === 'file';

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

// Function to get day name in Indonesian
function get_day_name($date_string) {
    $timestamp = strtotime($date_string);
    $day_of_week = date('N', $timestamp);
    $day_names = [
        1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
        5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'
    ];
    return $day_names[$day_of_week];
}

// Build yearly data - only months with actual data
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

    // Skip months with no data
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

        while ($rkb_item_detail = $result_rkb->fetch_assoc()) {
            $stmt_lkh = $conn->prepare("
                SELECT id_lkh, tanggal_lkh, nama_kegiatan_harian, uraian_kegiatan_lkh, lampiran
                FROM lkh
                WHERE id_rkb = ?
                ORDER BY tanggal_lkh ASC
            ");
            $stmt_lkh->bind_param("i", $rkb_item_detail['id_rkb']);
            $stmt_lkh->execute();
            $result_lkh = $stmt_lkh->get_result();
            
            if ($result_lkh->num_rows == 0) {
                $data_for_display[] = [
                    'no' => $no_global++,
                    'bulan' => $months[$bulan_num],
                    'rhk_terkait' => htmlspecialchars($rhk_item['nama_rhk']),
                    'uraian_kegiatan_rkb' => htmlspecialchars($rkb_item_detail['uraian_kegiatan']),
                    'target_kuantitas' => htmlspecialchars($rkb_item_detail['target_kuantitas']),
                    'target_satuan' => htmlspecialchars($rkb_item_detail['target_satuan']),
                    'tanggal_lkh' => 'Belum ada realisasi',
                    'nama_kegiatan_harian' => '',
                    'uraian_kegiatan_lkh' => '',
                    'lampiran' => 'Nihil',
                ];
            } else {
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
                        'lampiran_file' => $lkh_row['lampiran'],
                        'id_lkh' => $lkh_row['id_lkh'],
                    ];
                }
            }
            $stmt_lkh->close();
        }
        $stmt_rkb->close();
    }
    $stmt_rhk_month->close();
}

// Format date for signature - matching main web logic
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

// If PDF download is requested, generate PDF
if ($is_pdf_download) {
    ob_start();
}
?>

<style>
.mobile-yearly-report {
    font-size: 11px;
    line-height: 1.3;
}

.mobile-yearly-report .table {
    font-size: 10px;
}

.mobile-yearly-report .table th,
.mobile-yearly-report .table td {
    padding: 4px;
    border: 1px solid #000;
    vertical-align: middle;
}

.mobile-yearly-report .table th {
    background-color: #f8f9fa;
    font-weight: bold;
    text-align: center;
}

.mobile-yearly-report .employee-info {
    margin-bottom: 20px;
}

.mobile-yearly-report .employee-info td {
    padding: 6px 8px;
    border: 1px solid #000;
}

.mobile-yearly-report .employee-info td:first-child {
    background-color: #f0f0f0;
    font-weight: bold;
    width: 120px;
}

.mobile-yearly-report .signature-area {
    margin-top: 30px;
    display: flex;
    justify-content: space-between;
}

.mobile-yearly-report .signature-box {
    text-align: center;
    width: 45%;
    font-size: 10px;
}

.mobile-yearly-report .signature-line {
    border-bottom: 1px solid #000;
    margin: 40px auto 5px auto;
    width: 150px;
}

.year-controls {
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.btn-group-mobile {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.btn-mobile {
    padding: 8px 12px;
    font-size: 12px;
    border: 1px solid #007bff;
    background: #007bff;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    display: inline-block;
}

.btn-mobile:hover {
    background: #0056b3;
    color: white;
    text-decoration: none;
}

.btn-mobile.btn-secondary {
    background: #6c757d;
    border-color: #6c757d;
}

.btn-mobile.btn-success {
    background: #28a745;
    border-color: #28a745;
}

/* Print styles matching main web application */
@media print {
    .year-controls, .no-print {
        display: none !important;
    }
    
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 15mm;
        color: #000;
        background: white;
    }
    
    .mobile-yearly-report {
        font-size: 10px;
        line-height: 1.2;
    }
    
    .mobile-yearly-report .table {
        font-size: 9px;
        border-collapse: collapse;
        width: 100%;
    }
    
    .mobile-yearly-report .table th,
    .mobile-yearly-report .table td {
        padding: 4px;
        border: 1px solid #000 !important;
        vertical-align: middle;
        word-wrap: break-word;
    }
    
    .mobile-yearly-report .table th {
        background-color: #e0e0e0 !important;
        font-weight: bold;
        text-align: center;
    }
    
    .mobile-yearly-report .employee-info td {
        padding: 6px 8px;
        border: 1px solid #000 !important;
    }
    
    .mobile-yearly-report .signature-area {
        page-break-inside: avoid;
        margin-top: 30px;
        display: flex;
        justify-content: space-between;
    }
    
    .mobile-yearly-report .signature-box {
        width: 45%;
        font-size: 10px;
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
}

@media (max-width: 576px) {
    .mobile-yearly-report .table {
        font-size: 8px;
    }
    
    .mobile-yearly-report .table th,
    .mobile-yearly-report .table td {
        padding: 2px;
    }
    
    .mobile-yearly-report .signature-area {
        flex-direction: column;
        gap: 20px;
    }
    
    .mobile-yearly-report .signature-box {
        width: 100%;
    }
    
    .year-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .btn-group-mobile {
        justify-content: center;
    }
}
</style>

<?php if (!$is_pdf_download && !$is_generate_file): ?>
<!-- Year Selection and Controls -->
<div class="year-controls no-print">
    <div style="flex: 1;">
        <label for="year-select" style="font-weight: bold; margin-right: 10px;">Pilih Tahun:</label>
        <select id="year-select" class="form-control" style="display: inline-block; width: auto; min-width: 100px;" onchange="changeYear()">
            <?php if (empty($available_years)): ?>
                <option value="">Tidak ada data</option>
            <?php else: ?>
                <?php foreach ($available_years as $available_year): ?>
                    <option value="<?= $available_year ?>" <?= $available_year == $year ? 'selected' : '' ?>>
                        <?= $available_year ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
    </div>
    
    <?php if (!empty($available_years) && !empty($data_for_display)): ?>
    <div class="btn-group-mobile">
        <button onclick="printReport()" class="btn-mobile">
            📄 Cetak
        </button>
        <button onclick="generateAndDownload()" class="btn-mobile btn-success" id="downloadBtn">
            📥 Download
        </button>
    </div>
    <?php endif; ?>
</div>

<script>
function changeYear() {
    const yearSelect = document.getElementById('year-select');
    const selectedYear = yearSelect.value;
    if (selectedYear) {
        window.location.href = '?year=' + selectedYear;
    }
}

function printReport() {
    // Simple mobile print function
    window.print();
}

function generateAndDownload() {
    const downloadBtn = document.getElementById('downloadBtn');
    const originalText = downloadBtn.innerHTML;
    
    // Show loading state
    downloadBtn.innerHTML = '⏳ Membuat PDF...';
    downloadBtn.disabled = true;
    
    // Generate PDF file directly
    fetch('?year=<?= $year ?>&generate=pdf')
        .then(response => {
            if (response.ok) {
                return response.blob();
            }
            throw new Error('Failed to generate PDF');
        })
        .then(blob => {
            // Create download link for PDF
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `Laporan_Tahunan_<?= $year ?>_<?= preg_replace('/[^A-Za-z0-9]/', '_', $nama_pegawai_login) ?>_${new Date().getTime()}.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
            
            // Show success message
            downloadBtn.innerHTML = '✅ Berhasil!';
            showNotification('success', `PDF berhasil dibuat dan didownload!\nFile: ${link.download}`);
            
            setTimeout(() => {
                downloadBtn.innerHTML = originalText;
                downloadBtn.disabled = false;
            }, 3000);
        })
        .catch(error => {
            console.error('Error:', error);
            const errorMsg = 'Terjadi kesalahan saat membuat PDF';
            alert(errorMsg);
            showNotification('error', errorMsg);
            downloadBtn.innerHTML = originalText;
            downloadBtn.disabled = false;
        });
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function showNotification(type, message) {
    // Create notification element
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#28a745' : '#dc3545'};
        color: white;
        padding: 15px 20px;
        border-radius: 5px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 10000;
        max-width: 350px;
        font-size: 14px;
        line-height: 1.4;
        white-space: pre-line;
    `;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 5000);
    
    // Click to close
    notification.addEventListener('click', () => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    });
}
</script>
<?php endif; ?>

<div class="mobile-yearly-report">
    <!-- Employee Information -->
    <div class="employee-info">
        <table class="table table-bordered mb-3">
            <tbody>
                <tr>
                    <td><strong>Nama Pegawai</strong></td>
                    <td><?= htmlspecialchars($nama_pegawai_login) ?></td>
                </tr>
                <tr>
                    <td><strong>NIP</strong></td>
                    <td><?= htmlspecialchars($emp_data['nip']) ?></td>
                </tr>
                <tr>
                    <td><strong>Jabatan</strong></td>
                    <td><?= htmlspecialchars($emp_data['jabatan']) ?></td>
                </tr>
                <tr>
                    <td><strong>Unit Kerja</strong></td>
                    <td><?= htmlspecialchars($emp_data['unit_kerja']) ?></td>
                </tr>
                <tr>
                    <td><strong>Tahun Laporan</strong></td>
                    <td><?= $year ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Report Table -->
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 30px;">No</th>
                    <th rowspan="2" style="width: 60px;">Bulan</th>
                    <th rowspan="2" style="width: 100px;">RHK Terkait</th>
                    <th rowspan="2" style="width: 120px;">Uraian Kegiatan RKB</th>
                    <th colspan="2">Target RKB</th>
                    <th colspan="4">Realisasi LKH</th>
                </tr>
                <tr>
                    <th style="width: 50px;">Kuantitas</th>
                    <th style="width: 50px;">Satuan</th>
                    <th style="width: 70px;">Tanggal LKH</th>
                    <th style="width: 100px;">Nama Kegiatan</th>
                    <th style="width: 120px;">Uraian LKH</th>
                    <th style="width: 50px;">Lampiran</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (empty($available_years)) {
                    echo '<tr><td colspan="10" class="text-center text-muted">Belum ada data tersedia</td></tr>';
                } elseif (empty($data_for_display)) {
                    echo '<tr><td colspan="10" class="text-center text-muted">Belum ada data untuk tahun ' . $year . '</td></tr>';
                } else {
                    // Calculate rowspan for grouping
                    $rowspan_map = [];
                    $total_rows = count($data_for_display);
                    
                    for ($i = 0; $i < $total_rows; $i++) {
                        $row = $data_for_display[$i];
                        $bulan = $row['bulan'];
                        $rhk = $row['rhk_terkait'];
                        $rkb = $row['uraian_kegiatan_rkb'];

                        // Calculate month rowspan
                        if (!isset($rowspan_map['bulan'][$bulan])) {
                            $rowspan_map['bulan'][$bulan] = 0;
                            for ($j = $i; $j < $total_rows; $j++) {
                                if ($data_for_display[$j]['bulan'] === $bulan) {
                                    $rowspan_map['bulan'][$bulan]++;
                                }
                            }
                        }
                        
                        // Calculate RHK rowspan
                        $rhk_key = $bulan . '||' . $rhk;
                        if (!isset($rowspan_map['rhk'][$rhk_key])) {
                            $rowspan_map['rhk'][$rhk_key] = 0;
                            for ($j = $i; $j < $total_rows; $j++) {
                                if ($data_for_display[$j]['bulan'] === $bulan && $data_for_display[$j]['rhk_terkait'] === $rhk) {
                                    $rowspan_map['rhk'][$rhk_key]++;
                                }
                            }
                        }
                        
                        // Calculate RKB rowspan
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

                    // Display table rows with simple rowspan logic
                    $no_counter = 1;
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
                        echo '<td class="text-center">' . $no_counter++ . '</td>';

                        // Month column with rowspan
                        if (!isset($printed_bulan[$bulan])) {
                            echo '<td rowspan="' . $rowspan_map['bulan'][$bulan] . '" class="text-center align-middle"><small>' . $bulan . '</small></td>';
                            $printed_bulan[$bulan] = true;
                        }

                        // RHK column with rowspan
                        if (!isset($printed_rhk[$rhk_key])) {
                            echo '<td rowspan="' . $rowspan_map['rhk'][$rhk_key] . '" class="align-middle"><small>' . $rhk . '</small></td>';
                            $printed_rhk[$rhk_key] = true;
                        }

                        // RKB columns with rowspan
                        if (!isset($printed_rkb[$rkb_key])) {
                            echo '<td rowspan="' . $rowspan_map['rkb'][$rkb_key] . '" class="align-middle"><small>' . $rkb . '</small></td>';
                            echo '<td rowspan="' . $rowspan_map['rkb'][$rkb_key] . '" class="text-center align-middle"><small>' . $row_html['target_kuantitas'] . '</small></td>';
                            echo '<td rowspan="' . $rowspan_map['rkb'][$rkb_key] . '" class="text-center align-middle"><small>' . $row_html['target_satuan'] . '</small></td>';
                            $printed_rkb[$rkb_key] = true;
                        }

                        // LKH columns (no rowspan)
                        echo '<td class="text-center"><small>' . $row_html['tanggal_lkh'] . '</small></td>';
                        echo '<td class="text-center"><small>' . $row_html['nama_kegiatan_harian'] . '</small></td>';
                        echo '<td class="text-center"><small>' . $row_html['uraian_kegiatan_lkh'] . '</small></td>';
                        
                        // Lampiran
                        if (!empty($row_html['lampiran_file']) && $row_html['lampiran'] === '1 Dokumen') {
                            echo '<td class="text-center"><small><a href="../uploads/lkh/' . $row_html['lampiran_file'] . '" target="_blank" class="btn btn-sm btn-info">👁️ Lihat</a></small></td>';
                        } else {
                            echo '<td class="text-center"><small>' . $row_html['lampiran'] . '</small></td>';
                        }
                        echo '</tr>';
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
    
    <!-- Signature Area -->
    <div class="signature-area">
        <div class="signature-box">
            <p><strong>Pejabat Penilai</strong></p>
            <div class="signature-line"></div>
            <p><strong><?= htmlspecialchars($emp_data['nama_penilai'] ?: '(..................................)') ?></strong><br>
            NIP. <?= htmlspecialchars($emp_data['nip_penilai'] ?: '.................................') ?></p>
        </div>
        <div class="signature-box">
            <p>Cingambul, <?= format_date_indonesia(date('Y-m-d')) ?><br>
            <strong>Pegawai Yang Dinilai</strong></p>
            <div class="signature-line"></div>
            <p><strong><?= htmlspecialchars($nama_pegawai_login) ?></strong><br>
            NIP. <?= htmlspecialchars($emp_data['nip']) ?></p>
        </div>
    </div>
</div>

<?php
// Handle PDF generation request
if ($is_generate_file && $_GET['generate'] === 'pdf') {
    try {
        // Include FPDF library
        require_once __DIR__ . '/../vendor/fpdf/fpdf.php';
        
        // Create new PDF instance
        $pdf = new FPDF('L', 'mm', 'A4'); // Landscape orientation
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        
        // Header
        $pdf->Cell(0, 10, 'MTsN 11 MAJALENGKA', 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 8, 'LAPORAN KINERJA PEGAWAI TAHUNAN', 0, 1, 'C');
        $pdf->Cell(0, 8, 'TAHUN ' . $year, 0, 1, 'C');
        $pdf->Ln(5);
        
        // Employee Information
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(40, 6, 'Nama Pegawai', 1, 0, 'L');
        $pdf->Cell(0, 6, $nama_pegawai_login, 1, 1, 'L');
        $pdf->Cell(40, 6, 'NIP', 1, 0, 'L');
        $pdf->Cell(0, 6, $emp_data['nip'] ?? '', 1, 1, 'L');
        $pdf->Cell(40, 6, 'Jabatan', 1, 0, 'L');
        $pdf->Cell(0, 6, $emp_data['jabatan'] ?? '', 1, 1, 'L');
        $pdf->Cell(40, 6, 'Unit Kerja', 1, 0, 'L');
        $pdf->Cell(0, 6, $emp_data['unit_kerja'] ?? '', 1, 1, 'L');
        $pdf->Cell(40, 6, 'Tahun Laporan', 1, 0, 'L');
        $pdf->Cell(0, 6, $year, 1, 1, 'L');
        $pdf->Ln(5);
        
        // Table Header
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(10, 12, 'No', 1, 0, 'C');
        $pdf->Cell(20, 12, 'Bulan', 1, 0, 'C');
        $pdf->Cell(40, 12, 'RHK Terkait', 1, 0, 'C');
        $pdf->Cell(40, 12, 'Uraian Kegiatan RKB', 1, 0, 'C');
        $pdf->Cell(15, 6, 'Target RKB', 1, 0, 'C');
        $pdf->Cell(50, 6, 'Realisasi LKH', 1, 0, 'C');
        $pdf->Ln();
        
        // Sub header
        $pdf->Cell(10, 6, '', 0, 0, 'C'); // No
        $pdf->Cell(20, 6, '', 0, 0, 'C'); // Bulan
        $pdf->Cell(40, 6, '', 0, 0, 'C'); // RHK
        $pdf->Cell(40, 6, '', 0, 0, 'C'); // RKB
        $pdf->Cell(8, 6, 'Qty', 1, 0, 'C');
        $pdf->Cell(7, 6, 'Sat', 1, 0, 'C');
        $pdf->Cell(18, 6, 'Tanggal', 1, 0, 'C');
        $pdf->Cell(18, 6, 'Kegiatan', 1, 0, 'C');
        $pdf->Cell(10, 6, 'Lamp', 1, 1, 'C');
        
        // Table Data
        $pdf->SetFont('Arial', '', 7);
        if (!empty($data_for_display)) {
            $no_counter = 1;
            $current_bulan = '';
            $current_rhk = '';
            $current_rkb = '';
            
            foreach ($data_for_display as $row) {
                // Check if we need a new page
                if ($pdf->GetY() > 180) {
                    $pdf->AddPage();
                    // Repeat header on new page
                    $pdf->SetFont('Arial', 'B', 8);
                    $pdf->Cell(10, 6, 'No', 1, 0, 'C');
                    $pdf->Cell(20, 6, 'Bulan', 1, 0, 'C');
                    $pdf->Cell(40, 6, 'RHK Terkait', 1, 0, 'C');
                    $pdf->Cell(40, 6, 'Uraian RKB', 1, 0, 'C');
                    $pdf->Cell(8, 6, 'Qty', 1, 0, 'C');
                    $pdf->Cell(7, 6, 'Sat', 1, 0, 'C');
                    $pdf->Cell(18, 6, 'Tanggal', 1, 0, 'C');
                    $pdf->Cell(18, 6, 'Kegiatan', 1, 0, 'C');
                    $pdf->Cell(10, 6, 'Lamp', 1, 1, 'C');
                    $pdf->SetFont('Arial', '', 7);
                }
                
                $pdf->Cell(10, 6, $no_counter++, 1, 0, 'C');
                
                // Only show bulan if different from previous
                if ($current_bulan !== $row['bulan']) {
                    $pdf->Cell(20, 6, substr($row['bulan'], 0, 8), 1, 0, 'C');
                    $current_bulan = $row['bulan'];
                } else {
                    $pdf->Cell(20, 6, '', 1, 0, 'C');
                }
                
                // Only show RHK if different from previous
                if ($current_rhk !== $row['rhk_terkait']) {
                    $pdf->Cell(40, 6, substr($row['rhk_terkait'], 0, 35), 1, 0, 'L');
                    $current_rhk = $row['rhk_terkait'];
                } else {
                    $pdf->Cell(40, 6, '', 1, 0, 'L');
                }
                
                // Only show RKB if different from previous
                if ($current_rkb !== $row['uraian_kegiatan_rkb']) {
                    $pdf->Cell(40, 6, substr($row['uraian_kegiatan_rkb'], 0, 35), 1, 0, 'L');
                    $pdf->Cell(8, 6, $row['target_kuantitas'], 1, 0, 'C');
                    $pdf->Cell(7, 6, substr($row['target_satuan'], 0, 5), 1, 0, 'C');
                    $current_rkb = $row['uraian_kegiatan_rkb'];
                } else {
                    $pdf->Cell(40, 6, '', 1, 0, 'L');
                    $pdf->Cell(8, 6, '', 1, 0, 'C');
                    $pdf->Cell(7, 6, '', 1, 0, 'C');
                }
                
                // LKH data
                $pdf->Cell(18, 6, substr($row['tanggal_lkh'], 0, 12), 1, 0, 'C');
                $pdf->Cell(18, 6, substr($row['nama_kegiatan_harian'], 0, 15), 1, 0, 'L');
                $pdf->Cell(10, 6, $row['lampiran'] === '1 Dokumen' ? '1 Doc' : 'Nihil', 1, 1, 'C');
            }
        } else {
            $pdf->Cell(0, 6, 'Belum ada data untuk tahun ' . $year, 1, 1, 'C');
        }
        
        // Signature area
        $pdf->Ln(10);
        $pdf->SetFont('Arial', '', 10);
        
        // Left signature (Pejabat Penilai)
        $pdf->Cell(140, 6, 'Pejabat Penilai', 0, 0, 'C');
        $pdf->Cell(140, 6, 'Cingambul, ' . format_date_indonesia(date('Y-m-d')), 0, 1, 'C');
        $pdf->Cell(140, 6, '', 0, 0, 'C');
        $pdf->Cell(140, 6, 'Pegawai Yang Dinilai', 0, 1, 'C');
        $pdf->Ln(15);
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(140, 6, $emp_data['nama_penilai'] ?? '(.................................)', 0, 0, 'C');
        $pdf->Cell(140, 6, $nama_pegawai_login, 0, 1, 'C');
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(140, 6, 'NIP. ' . ($emp_data['nip_penilai'] ?? '.................................'), 0, 0, 'C');
        $pdf->Cell(140, 6, 'NIP. ' . ($emp_data['nip'] ?? ''), 0, 1, 'C');
        
        // Generate filename
        $safe_name = preg_replace('/[^A-Za-z0-9_]/', '_', $nama_pegawai_login);
        $filename = "Laporan_Tahunan_{$year}_{$safe_name}_" . date('Ymd_His') . ".pdf";
        
        // Set headers for PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Output PDF
        $pdf->Output('D', $filename);
        exit;
        
    } catch (Exception $e) {
        // If FPDF fails, return error
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Gagal membuat PDF: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Update the old file generation to use PDF generation
if ($is_generate_file && $_GET['generate'] === 'file') {
    // Redirect to PDF generation instead
    header('Location: ?year=' . $year . '&generate=pdf');
    exit;
}