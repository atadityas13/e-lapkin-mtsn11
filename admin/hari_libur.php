<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * Sistem Elektronik Laporan Kinerja Harian
 * MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat
 * 
 * File: Admin Hari Libur Management
 * Deskripsi: Halaman admin untuk mengelola hari libur nasional dan cuti bersama
 * 
 * @package    E-Lapkin-MTSN11
 * @author     MTsN 11 Majalengka Development Team
 * @copyright  2025 MTsN 11 Majalengka. All rights reserved.
 * @license    Proprietary License
 * @version    1.0.0
 * 
 * ========================================================
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../template/session_admin.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$id_pegawai_admin = $_SESSION['id_pegawai'];
$success_message = '';
$error_message = '';
$current_year = (int)date('Y');

// Handle actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'add') {
            $tanggal = trim($_POST['tanggal_libur']);
            $nama = trim($_POST['nama_hari_libur']);
            $tipe = isset($_POST['tipe_libur']) ? $_POST['tipe_libur'] : 'nasional';
            $keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : null;
            
            if (empty($tanggal) || empty($nama)) {
                $error_message = 'Tanggal dan Nama Hari Libur harus diisi.';
            } else {
                $result = add_hari_libur($conn, $tanggal, $nama, $tipe, $id_pegawai_admin, $keterangan, 'admin');
                if ($result['success']) {
                    $success_message = $result['message'];
                } else {
                    $error_message = $result['message'];
                }
            }
        } elseif ($action == 'edit') {
            $id = (int)$_POST['id_hari_libur'];
            $nama = trim($_POST['nama_hari_libur']);
            $tipe = isset($_POST['tipe_libur']) ? $_POST['tipe_libur'] : 'nasional';
            $keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : null;
            
            if (empty($nama)) {
                $error_message = 'Nama Hari Libur harus diisi.';
            } else {
                $result = update_hari_libur($conn, $id, $nama, $tipe, $keterangan);
                if ($result['success']) {
                    $success_message = $result['message'];
                } else {
                    $error_message = $result['message'];
                }
            }
        } elseif ($action == 'delete') {
            $id = (int)$_POST['id_hari_libur'];
            $result = delete_hari_libur($conn, $id);
            if ($result['success']) {
                $success_message = $result['message'];
            } else {
                $error_message = $result['message'];
            }
        } elseif ($action == 'sync_api') {
            $tahun = isset($_POST['tahun_sync']) ? (int)$_POST['tahun_sync'] : $current_year;
            $result = sync_hari_libur_dari_api($conn, $tahun, $id_pegawai_admin);
            if ($result['success']) {
                $success_message = $result['message'];
            } else {
                $error_message = $result['message'];
            }
        }
    }
}

// Filter berdasarkan tahun
$filter_year = isset($_GET['tahun']) ? (int)$_GET['tahun'] : $current_year;
$haris_libur = get_all_hari_libur($conn, $filter_year);

// Get available years untuk dropdown
$stmt_years = $conn->prepare("SELECT DISTINCT YEAR(tanggal_libur) as tahun FROM hari_libur ORDER BY tahun DESC");
$stmt_years->execute();
$result_years = $stmt_years->get_result();
$available_years = [];
while ($row = $result_years->fetch_assoc()) {
    $available_years[] = $row['tahun'];
}
$stmt_years->close();

// Ensure current year is in list
if (!in_array($current_year, $available_years)) {
    array_push($available_years, $current_year);
    sort($available_years, SORT_NUMERIC);
}
if (!in_array($current_year + 1, $available_years)) {
    $available_years[] = $current_year + 1;
}

$edit_mode = false;
$edit_hari = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $edit_id = (int)$_GET['id'];
    $stmt_edit = $conn->prepare("SELECT * FROM hari_libur WHERE id_hari_libur = ?");
    $stmt_edit->bind_param("i", $edit_id);
    $stmt_edit->execute();
    $result_edit = $stmt_edit->get_result();
    if ($edit_hari = $result_edit->fetch_assoc()) {
        $edit_mode = true;
    }
    $stmt_edit->close();
}

// Map tipe libur ke label
$tipe_libur_labels = [
    'nasional' => 'Hari Libur Nasional',
    'cuti_bersama' => 'Cuti Bersama',
    'custom' => 'Custom'
];

// Map sumber ke label
$sumber_labels = [
    'api' => 'Dari API',
    'admin' => 'Input Manual'
];

// Map warna untuk badge
$tipe_libur_colors = [
    'nasional' => 'warning',
    'cuti_bersama' => 'info',
    'custom' => 'secondary'
];

$sumber_colors = [
    'api' => 'success',
    'admin' => 'primary'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Hari Libur Nasional - E-Lapkin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/sbadmin/css/styles.css" rel="stylesheet">
</head>
<body class="sb-nav-fixed">
    <?php include __DIR__ . '/../template/topbar.php'; ?>
    <div id="layoutSidenav">
        <?php include __DIR__ . '/../template/menu_admin.php'; ?>
        <div id="layoutSidenav_content">
            <main class="container-fluid px-4">
                <div class="d-flex justify-content-between align-items-start pt-4">
                    <div>
                        <h1 class="mt-4 mb-2"><i class="fas fa-calendar-alt me-2"></i>Kelola Hari Libur Nasional</h1>
                        <p class="text-muted">Kelola hari libur nasional dan cuti bersama untuk LKH</p>
                    </div>
                </div>

                <!-- Messages -->
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                                <!-- Sync History -->
                                <div class="card mt-4">
                                    <div class="card-header bg-secondary text-white">
                                        <h5 class="card-title mb-0"><i class="fas fa-history me-2"></i>Riwayat Sinkronisasi Tahun <?php echo $filter_year; ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <?php 
                                        $sync_history = get_sync_history($conn, $filter_year, 10);
                                        if (empty($sync_history)): 
                                        ?>
                                            <div class="alert alert-info mb-0">
                                                <i class="fas fa-info-circle me-1"></i>Tidak ada riwayat sinkronisasi untuk tahun <?php echo $filter_year; ?>.
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th><i class="fas fa-calendar"></i> Tanggal Sync</th>
                                                            <th><i class="fas fa-plus-circle"></i> Ditambahkan</th>
                                                            <th><i class="fas fa-check"></i> Status</th>
                                                            <th><i class="fas fa-user"></i> Disync oleh</th>
                                                            <th><i class="fas fa-note-sticky"></i> Pesan</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($sync_history as $log): ?>
                                                            <tr>
                                                                <td><small><?php echo date('d M Y H:i', strtotime($log['synced_at'])); ?></small></td>
                                                                <td><span class="badge bg-primary"><?php echo $log['count_added']; ?></span></td>
                                                                <td>
                                                                    <span class="badge bg-<?php echo ($log['status'] == 'success') ? 'success' : 'danger'; ?>">
                                                                        <?php echo ucfirst($log['status']); ?>
                                                                    </span>
                                                                </td>
                                                                <td><small><?php echo $log['synced_by_name'] ? htmlspecialchars($log['synced_by_name']) : 'Sistem'; ?></small></td>
                                                                <td><small class="text-muted"><?php echo htmlspecialchars(substr($log['message'], 0, 50)); ?><?php echo strlen($log['message']) > 50 ? '...' : ''; ?></small></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row gap-2 mb-3">
                    <div class="col-auto">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahHariLibur">
                            <i class="fas fa-plus me-2"></i>Tambah Hari Libur
                        </button>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalSyncApi">
                            <i class="fas fa-sync-alt me-2"></i>Sync dari API
                        </button>
                    </div>
                </div>

                <!-- Filter Tahun -->
                <div class="card mb-3">
                    <div class="card-body">
                        <form method="get" class="d-flex gap-2 align-items-center">
                            <label for="tahun_filter" class="form-label mb-0">Filter Tahun:</label>
                            <select class="form-select" id="tahun_filter" name="tahun" style="max-width: 150px;" onchange="this.form.submit()">
                                <?php 
                                $min_year = max($current_year - 2, 2020);
                                $max_year = $current_year + 3;
                                for ($y = $max_year; $y >= $min_year; $y--):
                                ?>
                                    <option value="<?php echo $y; ?>" <?php echo ($y == $filter_year) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </form>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-list me-2"></i>Daftar Hari Libur Tahun <?php echo $filter_year; ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($haris_libur)): ?>
                            <div class="alert alert-info" role="alert">
                                <i class="fas fa-info-circle me-2"></i>Tidak ada data hari libur untuk tahun <?php echo $filter_year; ?>.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="fas fa-calendar me-1"></i>Tanggal</th>
                                            <th><i class="fas fa-heading me-1"></i>Nama Hari Libur</th>
                                            <th><i class="fas fa-tag me-1"></i>Tipe</th>
                                            <th><i class="fas fa-source me-1"></i>Sumber</th>
                                            <th><i class="fas fa-comment-alt me-1"></i>Keterangan</th>
                                            <th><i class="fas fa-cog me-1"></i>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($haris_libur as $hari): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo get_nama_hari_indo($hari['tanggal_libur']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($hari['nama_hari_libur']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo isset($tipe_libur_colors[$hari['tipe_libur']]) ? $tipe_libur_colors[$hari['tipe_libur']] : 'secondary'; ?>">
                                                        <?php echo isset($tipe_libur_labels[$hari['tipe_libur']]) ? $tipe_libur_labels[$hari['tipe_libur']] : $hari['tipe_libur']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo isset($sumber_colors[$hari['sumber']]) ? $sumber_colors[$hari['sumber']] : 'secondary'; ?>">
                                                        <?php echo isset($sumber_labels[$hari['sumber']]) ? $sumber_labels[$hari['sumber']] : $hari['sumber']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($hari['keterangan'])): ?>
                                                        <small class="text-muted"><?php echo htmlspecialchars($hari['keterangan']); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($hari['sumber'] == 'admin'): ?>
                                                        <a href="hari_libur.php?action=edit&id=<?php echo $hari['id_hari_libur']; ?>&tahun=<?php echo $filter_year; ?>" class="btn btn-sm btn-warning me-1">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>
                                                        <form action="hari_libur.php" method="POST" style="display:inline-block;">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id_hari_libur" value="<?php echo $hari['id_hari_libur']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus hari libur ini?')">
                                                                <i class="fas fa-trash"></i> Hapus
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Data Otomatis</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
            <?php include __DIR__ . '/../template/footer.php'; ?>
        </div>
    </div>

    <!-- Modal Tambah Hari Libur -->
    <div class="modal fade" id="modalTambahHariLibur" tabindex="-1">
        <div class="modal-dialog">
            <form action="hari_libur.php" method="POST" class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Tambah Hari Libur</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="tanggal_libur_modal" class="form-label">Tanggal Hari Libur</label>
                        <input type="date" class="form-control" id="tanggal_libur_modal" name="tanggal_libur" required>
                    </div>
                    <div class="mb-3">
                        <label for="nama_hari_libur_modal" class="form-label">Nama Hari Libur</label>
                        <input type="text" class="form-control" id="nama_hari_libur_modal" name="nama_hari_libur" placeholder="Contoh: Hari Raya Idul Fitri" required>
                    </div>
                    <div class="mb-3">
                        <label for="tipe_libur_modal" class="form-label">Tipe Hari Libur</label>
                        <select class="form-select" id="tipe_libur_modal" name="tipe_libur">
                            <option value="nasional">Hari Libur Nasional</option>
                            <option value="cuti_bersama">Cuti Bersama</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="keterangan_modal" class="form-label">Keterangan (Opsional)</label>
                        <textarea class="form-control" id="keterangan_modal" name="keterangan" rows="2" placeholder="Masukkan keterangan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Tambah Hari Libur</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Sync dari API -->
    <div class="modal fade" id="modalSyncApi" tabindex="-1">
        <div class="modal-dialog">
            <form action="hari_libur.php" method="POST" class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-sync-alt me-2"></i>Sync Hari Libur dari API</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="sync_api">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Informasi:</strong> Sinkronisasi akan mengambil data hari libur nasional dari API libur.deno.dev
                    </div>
                    <div class="mb-3">
                        <label for="tahun_sync_modal" class="form-label">Pilih Tahun untuk Sync</label>
                        <select class="form-select" id="tahun_sync_modal" name="tahun_sync">
                            <?php 
                            for ($y = $current_year; $y <= $current_year + 2; $y++):
                            ?>
                                <option value="<?php echo $y; ?>" <?php echo ($y == $current_year) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                        <small class="text-muted d-block mt-2">Pilih tahun yang ingin disinkronisasi. Data yang sudah ada tidak akan diubah.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Sync Sekarang</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Hari Libur -->
    <?php if ($edit_mode && $edit_hari): ?>
    <div class="d-print-none">
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="card-title mb-0"><i class="fas fa-edit me-2"></i>Edit Hari Libur</h5>
            </div>
            <div class="card-body">
                <form action="hari_libur.php" method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id_hari_libur" value="<?php echo htmlspecialchars($edit_hari['id_hari_libur']); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label"><strong>Tanggal:</strong></label>
                        <p><?php echo get_nama_hari_indo($edit_hari['tanggal_libur']); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nama_hari_libur_edit" class="form-label">Nama Hari Libur</label>
                        <input type="text" class="form-control" id="nama_hari_libur_edit" name="nama_hari_libur" value="<?php echo htmlspecialchars($edit_hari['nama_hari_libur']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tipe_libur_edit" class="form-label">Tipe Hari Libur</label>
                        <select class="form-select" id="tipe_libur_edit" name="tipe_libur">
                            <option value="nasional" <?php echo ($edit_hari['tipe_libur'] == 'nasional') ? 'selected' : ''; ?>>Hari Libur Nasional</option>
                            <option value="cuti_bersama" <?php echo ($edit_hari['tipe_libur'] == 'cuti_bersama') ? 'selected' : ''; ?>>Cuti Bersama</option>
                            <option value="custom" <?php echo ($edit_hari['tipe_libur'] == 'custom') ? 'selected' : ''; ?>>Custom</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="keterangan_edit" class="form-label">Keterangan (Opsional)</label>
                        <textarea class="form-control" id="keterangan_edit" name="keterangan" rows="2"><?php echo htmlspecialchars($edit_hari['keterangan'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Simpan Perubahan
                        </button>
                        <a href="hari_libur.php?tahun=<?php echo $filter_year; ?>" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>

                <!-- Last Sync Info -->
                <?php 
                $last_sync_info = get_last_sync_info($conn, $filter_year);
                ?>
                <div class="card mb-3">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Sinkronisasi</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($last_sync_info): ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Terakhir Sync:</strong> <span class="badge bg-<?php echo ($last_sync_info['status'] == 'success') ? 'success' : 'danger'; ?>"><?php echo ucfirst($last_sync_info['status']); ?></span></p>
                                    <p><small class="text-muted"><?php echo date('d F Y H:i:s', strtotime($last_sync_info['synced_at'])); ?></small></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Hari Libur Ditambahkan:</strong> <span class="badge bg-primary"><?php echo $last_sync_info['count_added']; ?></span></p>
                                    <?php if ($last_sync_info['synced_by_name']): ?>
                                        <p><small class="text-muted">Oleh: <?php echo htmlspecialchars($last_sync_info['synced_by_name']); ?></small></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($last_sync_info['message'])): ?>
                                <hr>
                                <p><small class="text-muted"><i class="fas fa-comment-alt me-1"></i><?php echo htmlspecialchars($last_sync_info['message']); ?></small></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-exclamation-triangle me-1"></i>Belum pernah melakukan sinkronisasi untuk tahun <?php echo $filter_year; ?>.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
