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

// Build yearly data similar to desktop version but optimized for mobile
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
                    'rhk_terkait' => htmlspecialchars($rhk_item['nama_rhk']),
                    'uraian_kegiatan_rkb' => htmlspecialchars($rkb_item['uraian_kegiatan']),
                    'target_kuantitas' => htmlspecialchars($rkb_item['target_kuantitas']),
                    'target_satuan' => htmlspecialchars($rkb_item['target_satuan']),
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
                        'rhk_terkait' => htmlspecialchars($rhk_item['nama_rhk']),
                        'uraian_kegiatan_rkb' => htmlspecialchars($rkb_item['uraian_kegiatan']),
                        'target_kuantitas' => htmlspecialchars($rkb_item['target_kuantitas']),
                        'target_satuan' => htmlspecialchars($rkb_item['target_satuan']),
                        'tanggal_lkh' => $tanggal_lkh_formatted,
                        'nama_kegiatan_harian' => htmlspecialchars($lkh_row['nama_kegiatan_harian'] ?? ''),
                        'uraian_kegiatan_lkh' => htmlspecialchars($lkh_row['uraian_kegiatan_lkh']),
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

// If PDF download is requested, generate PDF
if ($is_pdf_download) {
    // Start output buffering to capture HTML
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

@media print {
    .year-controls, .no-print {
        display: none !important;
    }
    
    .mobile-yearly-report {
        font-size: 8px;
    }
    
    .mobile-yearly-report .table {
        font-size: 7px;
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

<?php if (!$is_pdf_download): ?>
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
            📄 Print
        </button>
        <a href="?year=<?= $year ?>&download=pdf" class="btn-mobile btn-success" target="_blank">
            📥 Download PDF
        </a>
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
    // Hide controls and optimize for printing
    const controls = document.querySelector('.year-controls');
    if (controls) controls.style.display = 'none';
    
    // Trigger print
    window.print();
    
    // Restore controls after print dialog
    setTimeout(() => {
        if (controls) controls.style.display = 'flex';
    }, 100);
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
                    // Calculate rowspans
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

                    // Print table rows
                    $printed_bulan = [];
                    $printed_rhk = [];
                    $printed_rkb = [];
                    
                    for ($i = 0; $i < $total_rows; $i++) {
                        $row = $data_for_display[$i];
                        $bulan = $row['bulan'];
                        $rhk = $row['rhk_terkait'];
                        $rkb = $row['uraian_kegiatan_rkb'];
                        $rhk_key = $bulan . '||' . $rhk;
                        $rkb_key = $bulan . '||' . $rhk . '||' . $rkb;
                        
                        echo '<tr>';
                        echo '<td class="text-center">' . $row['no'] . '</td>';

                        // Month column
                        if (!isset($printed_bulan[$bulan])) {
                            echo '<td rowspan="' . $rowspan_map['bulan'][$bulan] . '" class="text-center align-middle"><small>' . $bulan . '</small></td>';
                            $printed_bulan[$bulan] = true;
                        }

                        // RHK column
                        if (!isset($printed_rhk[$rhk_key])) {
                            echo '<td rowspan="' . $rowspan_map['rhk'][$rhk_key] . '" class="align-middle"><small>' . $rhk . '</small></td>';
                            $printed_rhk[$rhk_key] = true;
                        }

                        // RKB columns
                        if (!isset($printed_rkb[$rkb_key])) {
                            echo '<td rowspan="' . $rowspan_map['rkb'][$rkb_key] . '" class="align-middle"><small>' . $rkb . '</small></td>';
                            echo '<td rowspan="' . $rowspan_map['rkb'][$rkb_key] . '" class="text-center align-middle"><small>' . $row['target_kuantitas'] . '</small></td>';
                            echo '<td rowspan="' . $rowspan_map['rkb'][$rkb_key] . '" class="text-center align-middle"><small>' . $row['target_satuan'] . '</small></td>';
                            $printed_rkb[$rkb_key] = true;
                        }

                        // LKH columns
                        echo '<td class="text-center"><small>' . $row['tanggal_lkh'] . '</small></td>';
                        echo '<td><small>' . $row['nama_kegiatan_harian'] . '</small></td>';
                        echo '<td><small>' . $row['uraian_kegiatan_lkh'] . '</small></td>';
                        echo '<td class="text-center"><small>' . $row['lampiran'] . '</small></td>';
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
// If PDF download is requested, process the output
if ($is_pdf_download) {
    $html_content = ob_get_clean();
    
    // Simple PDF generation using browser's print-to-PDF capability
    // For mobile compatibility, we'll use a JavaScript approach
    echo '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Laporan Tahunan ' . $year . ' - ' . htmlspecialchars($nama_pegawai_login) . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            @media print {
                body { margin: 10px; }
                .no-print { display: none !important; }
            }
        </style>
    </head>
    <body>
        <div class="no-print" style="text-align: center; margin-bottom: 20px;">
            <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                📄 Print / Save as PDF
            </button>
            <button onclick="window.close()" style="padding: 10px 20px; font-size: 16px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">
                ✖ Tutup
            </button>
        </div>
        ' . $html_content . '
        <script>
            // Auto-trigger print dialog on mobile
            if (/Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
                setTimeout(() => {
                    window.print();
                }, 1000);
            }
        </script>
    </body>
    </html>';
    exit;
}
?>
