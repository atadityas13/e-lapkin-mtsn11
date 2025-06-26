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

$page_title = "Dashboard Admin";

// Statistik data utama
$total_pegawai = $conn->query("SELECT COUNT(*) as total FROM pegawai")->fetch_assoc()['total'];

// Cek apakah tabel laporan_kinerja ada
$table_exists = $conn->query("SHOW TABLES LIKE 'laporan_kinerja'")->num_rows > 0;

if ($table_exists) {
    $total_laporan = $conn->query("SELECT COUNT(*) as total FROM laporan_kinerja")->fetch_assoc()['total'];
    $laporan_pending = $conn->query("SELECT COUNT(*) as total FROM laporan_kinerja WHERE status_approval = 'pending'")->fetch_assoc()['total'];
    $laporan_approved = $conn->query("SELECT COUNT(*) as total FROM laporan_kinerja WHERE status_approval = 'approved'")->fetch_assoc()['total'];
    $laporan_rejected = $conn->query("SELECT COUNT(*) as total FROM laporan_kinerja WHERE status_approval = 'rejected'")->fetch_assoc()['total'];
    
    // Laporan terbaru
    $laporan_terbaru = $conn->query("
        SELECT lk.*, p.nama 
        FROM laporan_kinerja lk 
        JOIN pegawai p ON lk.nip = p.nip 
        ORDER BY lk.tanggal_submit DESC 
        LIMIT 5
    ");
} else {
    $total_laporan = 0;
    $laporan_pending = 0;
    $laporan_approved = 0;
    $laporan_rejected = 0;
    $laporan_terbaru = null;
}

// Statistik tambahan
$pegawai_aktif = $conn->query("SELECT COUNT(*) as total FROM pegawai WHERE role = 'user'")->fetch_assoc()['total'];
$admin_count = $conn->query("SELECT COUNT(*) as total FROM pegawai WHERE role = 'admin'")->fetch_assoc()['total'];

// Reset password requests
$reset_requests = 0;
$check_col = $conn->query("SHOW COLUMNS FROM pegawai LIKE 'reset_request'");
if ($check_col && $check_col->num_rows > 0) {
    $reset_requests = $conn->query("SELECT COUNT(*) as total FROM pegawai WHERE reset_request = 1")->fetch_assoc()['total'];
}

include __DIR__ . '/../template/header.php';
include __DIR__ . '/../template/menu_admin.php';
include __DIR__ . '/../template/topbar.php';
?>

<div id="layoutSidenav_content">
    <main>
        <div class="container-fluid px-4">
            <h1 class="mt-4 mb-3"><i class="fas fa-tachometer-alt"></i> Dashboard Admin</h1>
            <p class="lead">Selamat datang di sistem E-Lapkin MTsN 11 Majalengka, <?php echo htmlspecialchars($_SESSION['nama']); ?>!</p>

            <!-- Alert untuk reset password requests -->
            <?php if ($reset_requests > 0): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Ada <?php echo $reset_requests; ?> permintaan reset password yang perlu diproses.
                    <a href="manajemen_user.php" class="alert-link">Lihat sekarang</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistik Cards Row 1 -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">Total Pegawai</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo $total_pegawai; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-white-50">
                            <small>Pegawai Aktif: <?php echo $pegawai_aktif; ?> | Admin: <?php echo $admin_count; ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">Total Laporan</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo $total_laporan; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-file-alt fa-2x"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-white-50">
                            <small>Approved: <?php echo $laporan_approved; ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">Pending Review</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo $laporan_pending; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-white-50">
                            <small><?php echo $reset_requests; ?> Reset Password Requests</small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">Rejected</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo $laporan_rejected; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-times fa-2x"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-white-50">
                            <small>Perlu Perbaikan</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <a href="manajemen_user.php" class="btn btn-outline-primary w-100 mb-2">
                                        <i class="fas fa-user-plus"></i> Tambah Pegawai
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="laporan_kinerja.php?status=pending" class="btn btn-outline-warning w-100 mb-2">
                                        <i class="fas fa-eye"></i> Review Laporan
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="generate_laporan.php" class="btn btn-outline-success w-100 mb-2">
                                        <i class="fas fa-download"></i> Export Data
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="pengaturan.php" class="btn btn-outline-secondary w-100 mb-2">
                                        <i class="fas fa-cogs"></i> Pengaturan
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Laporan Terbaru -->
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-list"></i> Laporan Terbaru</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Pegawai</th>
                                            <th>Periode</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($table_exists && $laporan_terbaru && $laporan_terbaru->num_rows > 0): ?>
                                            <?php while ($row = $laporan_terbaru->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo date('d/m/Y', strtotime($row['tanggal_submit'])); ?></td>
                                                    <td><?php echo htmlspecialchars($row['nama']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['bulan'] . ' ' . $row['tahun']); ?></td>
                                                    <td>
                                                        <?php
                                                        $badge_class = $row['status_approval'] === 'approved' ? 'success' : 
                                                                      ($row['status_approval'] === 'rejected' ? 'danger' : 'warning');
                                                        ?>
                                                        <span class="badge bg-<?php echo $badge_class; ?>">
                                                            <?php echo ucfirst($row['status_approval']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="laporan_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i> Lihat
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center">
                                                    <?php if (!$table_exists): ?>
                                                        Tabel laporan belum tersedia
                                                    <?php else: ?>
                                                        Belum ada laporan
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($table_exists && $total_laporan > 5): ?>
                                <div class="text-center mt-3">
                                    <a href="laporan_kinerja.php" class="btn btn-primary">
                                        <i class="fas fa-arrow-right"></i> Lihat Semua Laporan
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- System Info & Notifications -->
                <div class="col-lg-4">
                    <!-- System Status -->
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-server"></i> Status Sistem</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li><i class="fas fa-check text-success"></i> Database: Connected</li>
                                <li><i class="fas fa-check text-success"></i> Server: Online</li>
                                <li><i class="fas fa-check text-success"></i> Backup: Active</li>
                                <li><i class="fas fa-check text-success"></i> Security: Normal</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Recent Activities -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-history"></i> Aktivitas Terbaru</h5>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-primary"></div>
                                    <div class="timeline-content">
                                        <h6 class="timeline-title">Login Admin</h6>
                                        <p class="timeline-text"><?php echo htmlspecialchars($_SESSION['nama']); ?> masuk ke sistem</p>
                                        <small class="text-muted"><?php echo date('H:i'); ?></small>
                                    </div>
                                </div>
                                <?php if ($laporan_pending > 0): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-warning"></div>
                                    <div class="timeline-content">
                                        <h6 class="timeline-title">Laporan Pending</h6>
                                        <p class="timeline-text"><?php echo $laporan_pending; ?> laporan menunggu review</p>
                                        <small class="text-muted">Hari ini</small>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($reset_requests > 0): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-info"></div>
                                    <div class="timeline-content">
                                        <h6 class="timeline-title">Reset Password</h6>
                                        <p class="timeline-text"><?php echo $reset_requests; ?> permintaan reset password</p>
                                        <small class="text-muted">Perlu tindakan</small>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <?php include __DIR__ . '/../template/footer.php'; ?>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -38px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.timeline-content {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 5px;
}

.timeline-title {
    margin-bottom: 5px;
    font-size: 14px;
}

.timeline-text {
    margin-bottom: 5px;
    font-size: 13px;
}

.card-footer {
    font-size: 12px;
}
</style>