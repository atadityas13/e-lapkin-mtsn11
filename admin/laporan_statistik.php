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
require_once __DIR__ . '/../template/session_admin.php';
require_once '../config/database.php';

$page_title = "Laporan Statistik";

// Get statistik umum
$stats = [];

// Total Pegawai
$result = $conn->query("SELECT COUNT(*) as total FROM pegawai WHERE role = 'user'");
$stats['total_pegawai'] = $result->fetch_assoc()['total'];

// Total Admin
$result = $conn->query("SELECT COUNT(*) as total FROM pegawai WHERE role = 'admin'");
$stats['total_admin'] = $result->fetch_assoc()['total'];

// Total RHK
$result = $conn->query("SELECT COUNT(*) as total FROM rhk");
$stats['total_rhk'] = $result->fetch_assoc()['total'];

// Total RKB
$result = $conn->query("SELECT COUNT(*) as total FROM rkb");
$stats['total_rkb'] = $result->fetch_assoc()['total'];

// Total LKH
$result = $conn->query("SELECT COUNT(*) as total FROM lkh");
$stats['total_lkh'] = $result->fetch_assoc()['total'];

// Statistik berdasarkan status (hanya RKB dan LKH yang punya status_verval di database)
$result = $conn->query("SELECT status_verval, COUNT(*) as total FROM rkb WHERE status_verval IS NOT NULL GROUP BY status_verval");
$rkb_status = [];
while ($row = $result->fetch_assoc()) {
    $rkb_status[$row['status_verval']] = $row['total'];
}

$result = $conn->query("SELECT status_verval, COUNT(*) as total FROM lkh WHERE status_verval IS NOT NULL GROUP BY status_verval");
$lkh_status = [];
while ($row = $result->fetch_assoc()) {
    $lkh_status[$row['status_verval']] = $row['total'];
}

// Statistik per unit kerja
$result = $conn->query("
    SELECT unit_kerja, COUNT(*) as total 
    FROM pegawai 
    WHERE role = 'user' 
    GROUP BY unit_kerja 
    ORDER BY total DESC
");
$unit_kerja_stats = [];
while ($row = $result->fetch_assoc()) {
    $unit_kerja_stats[] = $row;
}

// Statistik per jabatan
$result = $conn->query("
    SELECT jabatan, COUNT(*) as total 
    FROM pegawai 
    WHERE role = 'user' 
    GROUP BY jabatan 
    ORDER BY total DESC
");
$jabatan_stats = [];
while ($row = $result->fetch_assoc()) {
    $jabatan_stats[] = $row;
}

// Statistik RHK per aspek
$result = $conn->query("
    SELECT aspek, COUNT(*) as total 
    FROM rhk 
    GROUP BY aspek 
    ORDER BY total DESC
");
$aspek_stats = [];
while ($row = $result->fetch_assoc()) {
    $aspek_stats[] = $row;
}

// Statistik satuan yang digunakan
$result = $conn->query("
    SELECT satuan, COUNT(*) as total_rkb
    FROM rkb 
    GROUP BY satuan 
    ORDER BY total_rkb DESC
");
$satuan_rkb_stats = [];
while ($row = $result->fetch_assoc()) {
    $satuan_rkb_stats[] = $row;
}

$result = $conn->query("
    SELECT satuan_realisasi, COUNT(*) as total_lkh
    FROM lkh 
    GROUP BY satuan_realisasi 
    ORDER BY total_lkh DESC
");
$satuan_lkh_stats = [];
while ($row = $result->fetch_assoc()) {
    $satuan_lkh_stats[] = $row;
}

// Produktivitas pegawai (LKH per pegawai)
$result = $conn->query("
    SELECT p.nama, p.jabatan, COUNT(l.id_lkh) as total_lkh,
           COALESCE(SUM(l.jumlah_realisasi), 0) as total_realisasi
    FROM pegawai p
    LEFT JOIN lkh l ON p.id_pegawai = l.id_pegawai
    WHERE p.role = 'user'
    GROUP BY p.id_pegawai
    ORDER BY total_lkh DESC
");
$produktivitas_stats = [];
while ($row = $result->fetch_assoc()) {
    $produktivitas_stats[] = $row;
}

// Statistik per bulan tahun ini
$tahun_ini = date('Y');
$result = $conn->query("
    SELECT 
        MONTH(created_at) as bulan,
        COUNT(*) as total_rhk
    FROM rhk 
    WHERE YEAR(created_at) = $tahun_ini
    GROUP BY MONTH(created_at)
    ORDER BY bulan
");
$monthly_rhk = [];
while ($row = $result->fetch_assoc()) {
    $monthly_rhk[$row['bulan']] = $row['total_rhk'];
}

$result = $conn->query("
    SELECT 
        MONTH(created_at) as bulan,
        COUNT(*) as total_rkb
    FROM rkb 
    WHERE YEAR(created_at) = $tahun_ini
    GROUP BY MONTH(created_at)
    ORDER BY bulan
");
$monthly_rkb = [];
while ($row = $result->fetch_assoc()) {
    $monthly_rkb[$row['bulan']] = $row['total_rkb'];
}

$result = $conn->query("
    SELECT 
        MONTH(tanggal_lkh) as bulan,
        COUNT(*) as total_lkh
    FROM lkh 
    WHERE YEAR(tanggal_lkh) = $tahun_ini
    GROUP BY MONTH(tanggal_lkh)
    ORDER BY bulan
");
$monthly_lkh = [];
while ($row = $result->fetch_assoc()) {
    $monthly_lkh[$row['bulan']] = $row['total_lkh'];
}

// Analisis lampiran LKH
$result = $conn->query("
    SELECT 
        SUM(CASE WHEN lampiran IS NOT NULL AND lampiran != '' THEN 1 ELSE 0 END) as dengan_lampiran,
        SUM(CASE WHEN lampiran IS NULL OR lampiran = '' THEN 1 ELSE 0 END) as tanpa_lampiran
    FROM lkh
");
$lampiran_stats = $result->fetch_assoc();

// Handle null values for lampiran stats
$lampiran_stats['dengan_lampiran'] = (int)($lampiran_stats['dengan_lampiran'] ?? 0);
$lampiran_stats['tanpa_lampiran'] = (int)($lampiran_stats['tanpa_lampiran'] ?? 0);

// Tahun aktif pegawai
$result = $conn->query("
    SELECT tahun_aktif, COUNT(*) as total 
    FROM pegawai 
    WHERE role = 'user' AND tahun_aktif IS NOT NULL
    GROUP BY tahun_aktif 
    ORDER BY tahun_aktif DESC
");
$tahun_aktif_stats = [];
while ($row = $result->fetch_assoc()) {
    $tahun_aktif_stats[] = $row;
}

$months = [
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
    5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Ags',
    9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
];

include __DIR__ . '/../template/header.php';
include __DIR__ . '/../template/menu_admin.php';
include __DIR__ . '/../template/topbar.php';
?>

<div id="layoutSidenav_content">
    <main>
        <div class="container-fluid px-4">
            <h1 class="mt-4 mb-3"><i class="fas fa-chart-bar"></i> Dashboard Statistik E-Lapkin</h1>
            <p class="lead">Analisis komprehensif data sistem pelaporan kinerja pegawai.</p>

            <!-- Statistik Umum -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-primary text-white mb-4 shadow-lg">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="small text-white-50">Total Pegawai</div>
                                    <div class="h2"><?php echo number_format($stats['total_pegawai']); ?></div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <a class="small text-white stretched-link" href="pegawai.php">View Details</a>
                            <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-warning text-white mb-4 shadow-lg">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="small text-white-50">Total RHK</div>
                                    <div class="h2"><?php echo number_format($stats['total_rhk']); ?></div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-target fa-2x"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <a class="small text-white stretched-link" href="rhk.php">View Details</a>
                            <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-success text-white mb-4 shadow-lg">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="small text-white-50">Total RKB</div>
                                    <div class="h2"><?php echo number_format($stats['total_rkb']); ?></div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-calendar-week fa-2x"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <a class="small text-white stretched-link" href="rkb.php">View Details</a>
                            <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-danger text-white mb-4 shadow-lg">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="small text-white-50">Total LKH</div>
                                    <div class="h2"><?php echo number_format($stats['total_lkh']); ?></div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-calendar-day fa-2x"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <a class="small text-white stretched-link" href="lkh.php">View Details</a>
                            <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Chart Trend Bulanan -->
                <div class="col-xl-8">
                    <div class="card mb-4 shadow">
                        <div class="card-header bg-gradient-primary text-white">
                            <i class="fas fa-chart-area me-1"></i>
                            Trend Aktivitas Bulanan <?php echo $tahun_ini; ?>
                        </div>
                        <div class="card-body">
                            <canvas id="myAreaChart" width="100%" height="40"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Produktivitas Pegawai -->
                <div class="col-xl-4">
                    <div class="card mb-4 shadow">
                        <div class="card-header bg-gradient-info text-white">
                            <i class="fas fa-trophy me-1"></i>
                            Top Performer
                        </div>
                        <div class="card-body">
                            <?php if (!empty($produktivitas_stats)): ?>
                                <?php foreach (array_slice($produktivitas_stats, 0, 5) as $index => $pegawai): ?>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="me-3">
                                            <span class="badge bg-<?php echo $index == 0 ? 'warning' : ($index == 1 ? 'secondary' : 'light'); ?> rounded-pill">
                                                <?php echo $index + 1; ?>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold"><?php echo htmlspecialchars($pegawai['nama']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($pegawai['jabatan']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold text-primary"><?php echo $pegawai['total_lkh']; ?> LKH</div>
                                            <small class="text-muted"><?php echo number_format((float)($pegawai['total_realisasi'] ?? 0)); ?> realisasi</small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">Belum ada data produktivitas</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Status Distribution -->
                <div class="col-xl-6">
                    <div class="card mb-4 shadow">
                        <div class="card-header bg-gradient-success text-white">
                            <i class="fas fa-chart-pie me-1"></i>
                            Distribusi Status LKH
                        </div>
                        <div class="card-body">
                            <canvas id="myPieChart" width="100%" height="40"></canvas>
                            <div class="mt-3">
                                <?php foreach ($lkh_status as $status => $count): ?>
                                    <div class="d-flex justify-content-between">
                                        <span><?php echo ucfirst($status); ?></span>
                                        <span class="badge bg-<?php echo $status == 'disetujui' ? 'success' : ($status == 'diajukan' ? 'warning' : 'danger'); ?>">
                                            <?php echo $count; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Aspek RHK -->
                <div class="col-xl-6">
                    <div class="card mb-4 shadow">
                        <div class="card-header bg-gradient-warning text-white">
                            <i class="fas fa-chart-donut me-1"></i>
                            Distribusi Aspek RHK
                        </div>
                        <div class="card-body">
                            <canvas id="aspekChart" width="100%" height="40"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Statistik per Jabatan -->
                <div class="col-xl-6">
                    <div class="card mb-4 shadow">
                        <div class="card-header bg-gradient-secondary text-white">
                            <i class="fas fa-user-tie me-1"></i>
                            Distribusi Pegawai per Jabatan
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Jabatan</th>
                                            <th class="text-center">Jumlah</th>
                                            <th class="text-center">Persentase</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_pegawai = array_sum(array_column($jabatan_stats, 'total'));
                                        foreach ($jabatan_stats as $jabatan): 
                                            $persentase = $total_pegawai > 0 ? round(($jabatan['total'] / $total_pegawai) * 100, 1) : 0;
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($jabatan['jabatan']); ?></td>
                                                <td class="text-center">
                                                    <span class="badge bg-primary"><?php echo $jabatan['total']; ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar" role="progressbar" style="width: <?php echo $persentase; ?>%">
                                                            <?php echo $persentase; ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Analisis Lampiran -->
                <div class="col-xl-6">
                    <div class="card mb-4 shadow">
                        <div class="card-header bg-gradient-info text-white">
                            <i class="fas fa-paperclip me-1"></i>
                            Analisis Lampiran LKH
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded">
                                        <i class="fas fa-file-upload fa-2x text-success mb-2"></i>
                                        <h4 class="text-success"><?php echo number_format($lampiran_stats['dengan_lampiran']); ?></h4>
                                        <small class="text-muted">Dengan Lampiran</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded">
                                        <i class="fas fa-file fa-2x text-warning mb-2"></i>
                                        <h4 class="text-warning"><?php echo number_format($lampiran_stats['tanpa_lampiran']); ?></h4>
                                        <small class="text-muted">Tanpa Lampiran</small>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <h6>Distribusi Satuan yang Digunakan:</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>RKB:</strong>
                                    <?php foreach ($satuan_rkb_stats as $satuan): ?>
                                        <div class="d-flex justify-content-between">
                                            <span><?php echo $satuan['satuan']; ?></span>
                                            <span class="badge bg-info"><?php echo $satuan['total_rkb']; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>LKH:</strong>
                                    <?php foreach ($satuan_lkh_stats as $satuan): ?>
                                        <div class="d-flex justify-content-between">
                                            <span><?php echo $satuan['satuan_realisasi']; ?></span>
                                            <span class="badge bg-success"><?php echo $satuan['total_lkh']; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header bg-gradient-dark text-white">
                            <i class="fas fa-download me-1"></i>
                            Export Laporan Statistik
                        </div>
                        <div class="card-body">
                            <p class="mb-3">Export laporan statistik dalam berbagai format:</p>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-primary" onclick="exportToPDF()">
                                    <i class="fas fa-file-pdf"></i> Export PDF
                                </button>
                                <button type="button" class="btn btn-outline-success" onclick="exportToExcel()">
                                    <i class="fas fa-file-excel"></i> Export Excel
                                </button>
                                <button type="button" class="btn btn-outline-info" onclick="window.print()">
                                    <i class="fas fa-print"></i> Print
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <?php include __DIR__ . '/../template/footer.php'; ?>
</div>

<!-- Charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Data untuk chart trend bulanan
const monthlyData = {
    labels: [<?php 
        for ($i = 1; $i <= 12; $i++) {
            echo "'" . $months[$i] . "'" . ($i < 12 ? ',' : '');
        }
    ?>],
    datasets: [
        {
            label: 'RHK',
            data: [<?php 
                for ($i = 1; $i <= 12; $i++) {
                    echo ($monthly_rhk[$i] ?? 0) . ($i < 12 ? ',' : '');
                }
            ?>],
            backgroundColor: 'rgba(255, 193, 7, 0.2)',
            borderColor: 'rgba(255, 193, 7, 1)',
            borderWidth: 2,
            fill: true
        },
        {
            label: 'RKB',
            data: [<?php 
                for ($i = 1; $i <= 12; $i++) {
                    echo ($monthly_rkb[$i] ?? 0) . ($i < 12 ? ',' : '');
                }
            ?>],
            backgroundColor: 'rgba(40, 167, 69, 0.2)',
            borderColor: 'rgba(40, 167, 69, 1)',
            borderWidth: 2,
            fill: true
        },
        {
            label: 'LKH',
            data: [<?php 
                for ($i = 1; $i <= 12; $i++) {
                    echo ($monthly_lkh[$i] ?? 0) . ($i < 12 ? ',' : '');
                }
            ?>],
            backgroundColor: 'rgba(220, 53, 69, 0.2)',
            borderColor: 'rgba(220, 53, 69, 1)',
            borderWidth: 2,
            fill: true
        }
    ]
};

// Area Chart
const ctx = document.getElementById('myAreaChart');
const myAreaChart = new Chart(ctx, {
    type: 'line',
    data: monthlyData,
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        },
        plugins: {
            legend: {
                display: true
            }
        }
    }
});

// Pie Chart for LKH Status
const pieCtx = document.getElementById('myPieChart');
const myPieChart = new Chart(pieCtx, {
    type: 'pie',
    data: {
        labels: ['Diajukan', 'Disetujui', 'Ditolak'],
        datasets: [{
            data: [
                <?php echo $lkh_status['diajukan'] ?? 0; ?>,
                <?php echo $lkh_status['disetujui'] ?? 0; ?>,
                <?php echo $lkh_status['ditolak'] ?? 0; ?>
            ],
            backgroundColor: [
                'rgba(255, 193, 7, 0.8)',
                'rgba(40, 167, 69, 0.8)',
                'rgba(220, 53, 69, 0.8)'
            ],
            borderColor: [
                'rgba(255, 193, 7, 1)',
                'rgba(40, 167, 69, 1)',
                'rgba(220, 53, 69, 1)'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Aspek RHK Chart
const aspekCtx = document.getElementById('aspekChart');
const aspekChart = new Chart(aspekCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php 
            foreach ($aspek_stats as $index => $aspek) {
                echo "'" . $aspek['aspek'] . "'" . ($index < count($aspek_stats) - 1 ? ',' : '');
            }
        ?>],
        datasets: [{
            data: [<?php 
                foreach ($aspek_stats as $index => $aspek) {
                    echo $aspek['total'] . ($index < count($aspek_stats) - 1 ? ',' : '');
                }
            ?>],
            backgroundColor: [
                'rgba(255, 99, 132, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 205, 86, 0.8)',
                'rgba(75, 192, 192, 0.8)'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Export functions
function exportToPDF() {
    alert('Fitur export PDF sedang dalam pengembangan');
}

function exportToExcel() {
    alert('Fitur export Excel sedang dalam pengembangan');
}
</script>

<style>
.card {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(33, 40, 50, 0.15);
}
.bg-gradient-primary { background: linear-gradient(45deg, #007bff, #0056b3) !important; }
.bg-gradient-success { background: linear-gradient(45deg, #28a745, #20c997) !important; }
.bg-gradient-info { background: linear-gradient(45deg, #17a2b8, #138496) !important; }
.bg-gradient-warning { background: linear-gradient(45deg, #ffc107, #e0a800) !important; }
.bg-gradient-secondary { background: linear-gradient(45deg, #6c757d, #5a6268) !important; }
.bg-gradient-dark { background: linear-gradient(45deg, #343a40, #23272b) !important; }
</style>
