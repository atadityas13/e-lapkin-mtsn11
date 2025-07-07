<?php
/**
 * E-LAPKIN Mobile RHK Management
 */

// Start output buffering to catch any unwanted output
ob_start();

session_start();

// Include mobile session config
require_once __DIR__ . '/config/mobile_session.php';
require_once __DIR__ . '/../config/database.php';

// Check mobile login (only validate session, not headers for dashboard)
checkMobileLogin();

// Get user session data
$userData = getMobileSessionData();
$id_pegawai_login = $userData['id_pegawai'];
$nama_pegawai_login = $userData['nama'];

$current_date = date('Y-m-d');
$current_month = (int)date('m');
$current_year = (int)date('Y');

// Get active period from user settings
function get_periode_aktif($conn, $id_pegawai) {
    $stmt = $conn->prepare("SELECT tahun_aktif, bulan_aktif FROM pegawai WHERE id_pegawai = ?");
    $stmt->bind_param("i", $id_pegawai);
    $stmt->execute();
    $stmt->bind_result($tahun_aktif, $bulan_aktif);
    $stmt->fetch();
    $stmt->close();
    return [
        'tahun' => $tahun_aktif ?: (int)date('Y'),
        'bulan' => $bulan_aktif ?: (int)date('m')
    ];
}

$periode_aktif = get_periode_aktif($conn, $id_pegawai_login);
$filter_year = $periode_aktif['tahun'];

// Get RHK verification status
$status_verval_rhk = '';
$stmt_status = $conn->prepare("SELECT status_verval FROM rhk WHERE id_pegawai = ? AND tahun = ? LIMIT 1");
$stmt_status->bind_param("ii", $id_pegawai_login, $filter_year);
$stmt_status->execute();
$stmt_status->bind_result($status_verval_rhk);
$stmt_status->fetch();
$stmt_status->close();

// Helper function for SweetAlert-like notifications
function set_mobile_notification($type, $title, $text) {
    $_SESSION['mobile_notification'] = [
        'type' => $type,
        'title' => $title,
        'text' => $text
    ];
}

// Handle POST requests
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Prevent actions if RHK is already approved
    if ($status_verval_rhk == 'disetujui') {
        set_mobile_notification('error', 'Tidak Diizinkan', 'RHK periode ini sudah diverifikasi dan tidak dapat diubah.');
        header('Location: rhk.php');
        exit();
    }

    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == 'add' || $action == 'edit') {
            $uraian_kegiatan = trim($_POST['uraian_kegiatan']);
            $target = (int)$_POST['target'];
            
            // Map satuan from number to string
            $satuan_map = [
                "1" => "Kegiatan", "2" => "JP", "3" => "Dokumen", "4" => "Laporan",
                "5" => "Hari", "6" => "Jam", "7" => "Menit", "8" => "Unit"
            ];
            $satuan = isset($_POST['satuan']) && isset($satuan_map[$_POST['satuan']])
                ? $satuan_map[$_POST['satuan']] : '';

            // Validation
            if (empty($uraian_kegiatan) || empty($target) || empty($satuan)) {
                set_mobile_notification('error', 'Gagal', 'Semua field harus diisi.');
            } else {
                try {
                    if ($action == 'add') {
                        $stmt = $conn->prepare("INSERT INTO rhk (id_pegawai, uraian_kegiatan, target, satuan, tahun) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("isisi", $id_pegawai_login, $uraian_kegiatan, $target, $satuan, $filter_year);
                    } else {
                        $id_rhk = (int)$_POST['id_rhk'];
                        $stmt = $conn->prepare("UPDATE rhk SET uraian_kegiatan = ?, target = ?, satuan = ? WHERE id_rhk = ? AND id_pegawai = ?");
                        $stmt->bind_param("sisii", $uraian_kegiatan, $target, $satuan, $id_rhk, $id_pegawai_login);
                    }

                    if ($stmt->execute()) {
                        set_mobile_notification('success', ($action == 'add') ? 'Berhasil' : 'Update Berhasil', ($action == 'add') ? "RHK berhasil ditambahkan!" : "RHK berhasil diperbarui!");
                    } else {
                        set_mobile_notification('error', 'Gagal', ($action == 'add') ? "Gagal menambahkan RHK: " . $stmt->error : "Gagal memperbarui RHK: " . $stmt->error);
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    set_mobile_notification('error', 'Gagal', 'Terjadi kesalahan database. Periksa data yang dimasukkan.');
                }
            }
            header('Location: rhk.php');
            exit();
            
        } elseif ($action == 'delete') {
            $id_rhk_to_delete = (int)$_POST['id_rhk'];
            
            $stmt = $conn->prepare("DELETE FROM rhk WHERE id_rhk = ? AND id_pegawai = ?");
            $stmt->bind_param("ii", $id_rhk_to_delete, $id_pegawai_login);

            if ($stmt->execute()) {
                set_mobile_notification('success', 'Berhasil', 'RHK berhasil dihapus!');
            } else {
                set_mobile_notification('error', 'Gagal', "Gagal menghapus RHK: " . $stmt->error);
            }
            $stmt->close();
            header('Location: rhk.php');
            exit();
        }
    }
    
    // Submit/cancel RHK verification
    if (isset($_POST['ajukan_verval_rhk'])) {
        if ($status_verval_rhk == 'disetujui') {
            set_mobile_notification('error', 'Tidak Diizinkan', 'RHK periode ini sudah diverifikasi dan tidak dapat diubah statusnya.');
            header('Location: rhk.php');
            exit();
        }
        
        // Check if there's RHK data for this period
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM rhk WHERE id_pegawai = ? AND tahun = ?");
        $stmt_check->bind_param("ii", $id_pegawai_login, $filter_year);
        $stmt_check->execute();
        $stmt_check->bind_result($count_rhk);
        $stmt_check->fetch();
        $stmt_check->close();
        
        if ($count_rhk == 0) {
            set_mobile_notification('error', 'Gagal', 'Tidak dapat mengajukan verval karena belum ada data RHK untuk periode ini.');
            header('Location: rhk.php');
            exit();
        }
        
        $stmt = $conn->prepare("UPDATE rhk SET status_verval = 'diajukan' WHERE id_pegawai = ? AND tahun = ?");
        $stmt->bind_param("ii", $id_pegawai_login, $filter_year);
        if ($stmt->execute()) {
            set_mobile_notification('success', 'Berhasil', 'Pengajuan verval RHK berhasil dikirim. Menunggu verifikasi Pejabat Penilai.');
        } else {
            set_mobile_notification('error', 'Gagal', 'Gagal mengajukan verval RHK: ' . htmlspecialchars($stmt->error));
        }
        $stmt->close();
        header('Location: rhk.php');
        exit();
        
    } elseif (isset($_POST['batal_verval_rhk'])) {
        if ($status_verval_rhk == 'disetujui') {
            set_mobile_notification('error', 'Tidak Diizinkan', 'RHK periode ini sudah diverifikasi dan tidak dapat diubah statusnya.');
            header('Location: rhk.php');
            exit();
        }
        
        $stmt = $conn->prepare("UPDATE rhk SET status_verval = NULL WHERE id_pegawai = ? AND tahun = ?");
        $stmt->bind_param("ii", $id_pegawai_login, $filter_year);
        if ($stmt->execute()) {
            set_mobile_notification('success', 'Dibatalkan', 'Pengajuan verval RHK dibatalkan. Anda dapat mengedit/mengirim ulang.');
        } else {
            set_mobile_notification('error', 'Gagal', 'Gagal membatalkan verval RHK: ' . htmlspecialchars($stmt->error));
        }
        $stmt->close();
        header('Location: rhk.php');
        exit();
    }
}

// Ensure status_verval column exists
function ensure_status_verval_column_rhk($conn) {
    $result = $conn->query("SHOW COLUMNS FROM rhk LIKE 'status_verval'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE rhk ADD COLUMN status_verval ENUM('diajukan','disetujui','ditolak') DEFAULT NULL");
    }
}
ensure_status_verval_column_rhk($conn);

// Get RHK data for current year
$rhks = [];
$stmt_rhk = $conn->prepare("
    SELECT id_rhk, uraian_kegiatan, target, satuan
    FROM rhk 
    WHERE id_pegawai = ? AND tahun = ? 
    ORDER BY id_rhk DESC
");
$stmt_rhk->bind_param("ii", $id_pegawai_login, $filter_year);
$stmt_rhk->execute();
$result_rhk = $stmt_rhk->get_result();
while ($row = $result_rhk->fetch_assoc()) {
    $rhks[] = $row;
}
$stmt_rhk->close();

// Get previous RHK list for reference
$previous_rhk_list = [];
$stmt_previous_rhk = $conn->prepare("
    SELECT 
        r1.uraian_kegiatan,
        r1.target,
        r1.satuan
    FROM rhk r1
    INNER JOIN (
        SELECT 
            uraian_kegiatan,
            MAX(id_rhk) as max_id_rhk
        FROM rhk 
        WHERE id_pegawai = ? AND tahun != ?
        GROUP BY uraian_kegiatan
    ) r2 ON r1.uraian_kegiatan = r2.uraian_kegiatan
         AND r1.id_rhk = r2.max_id_rhk
    WHERE r1.id_pegawai = ? AND r1.tahun != ?
    ORDER BY r1.id_rhk DESC, r1.uraian_kegiatan ASC
    LIMIT 20
");

$stmt_previous_rhk->bind_param("iiii", $id_pegawai_login, $filter_year, $id_pegawai_login, $filter_year);
$stmt_previous_rhk->execute();
$result_previous_rhk = $stmt_previous_rhk->get_result();

while ($row = $result_previous_rhk->fetch_assoc()) {
    $previous_rhk_list[] = [
        'uraian_kegiatan' => $row['uraian_kegiatan'],
        'target' => $row['target'],
        'satuan' => $row['satuan']
    ];
}

$stmt_previous_rhk->close();

// Clear any unwanted output before HTML
ob_clean();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RHK - E-Lapkin Mobile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-top: 1px solid rgba(0,0,0,0.1);
            z-index: 1000;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
        }
        .bottom-nav .nav-item {
            flex: 1;
            text-align: center;
        }
        .bottom-nav .nav-link {
            padding: 12px 8px;
            color: #6c757d;
            text-decoration: none;
            display: block;
            font-size: 11px;
            font-weight: 500;
            transition: all 0.3s ease;
            border-radius: 12px;
            margin: 4px;
        }
        .bottom-nav .nav-link.active {
            color: #0d6efd;
            background: linear-gradient(135deg, rgba(13, 110, 253, 0.1), rgba(13, 110, 253, 0.05));
            transform: translateY(-2px);
        }
        .bottom-nav .nav-link i {
            font-size: 18px;
            margin-bottom: 4px;
            transition: transform 0.2s ease;
        }
        .bottom-nav .nav-link.active i {
            transform: scale(1.1);
        }
        body {
            padding-bottom: 80px;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f2ff 100%);
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 20px;
        }
        .btn {
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-1px);
        }
        .nav-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 0;
        }
        .btn-primary-large {
            padding: 10px 20px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
            transition: all 0.3s ease;
        }
        .btn-primary-large:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(13, 110, 253, 0.4);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="navbar nav-header">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center text-white" href="dashboard.php">
                <i class="fas fa-arrow-left me-2"></i>
                <span>Rencana Hasil Kerja</span>
            </a>
            <div class="d-flex align-items-center text-white">
                <small><?= htmlspecialchars($userData['nama']) ?></small>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-3 pt-3">
        <!-- Period Info -->
        <div class="card">
            <div class="card-body">
                <h6 class="card-title mb-3">
                    <i class="fas fa-calendar-alt text-primary me-2"></i>
                    Periode: Tahun <?= $filter_year ?>
                </h6>
                
                <!-- Status Alert -->
                <?php if ($status_verval_rhk == 'diajukan'): ?>
                    <div class="alert alert-info alert-dismissible">
                        <i class="fas fa-clock me-2"></i>
                        RHK periode ini sudah diajukan dan menunggu verifikasi Pejabat Penilai.
                    </div>
                <?php elseif ($status_verval_rhk == 'disetujui'): ?>
                    <div class="alert alert-success alert-dismissible">
                        <i class="fas fa-check-circle me-2"></i>
                        RHK periode ini sudah diverifikasi/validasi oleh Pejabat Penilai.
                    </div>
                <?php elseif ($status_verval_rhk == 'ditolak'): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <i class="fas fa-times-circle me-2"></i>
                        RHK periode ini ditolak oleh Pejabat Penilai. Silakan perbaiki dan ajukan ulang.
                    </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="mb-3">
                    <div class="d-flex gap-2 flex-wrap mb-2">
                        <button class="btn btn-info btn-sm" onclick="showPreviewModal()" 
                            <?= empty($rhks) ? 'disabled' : '' ?>>
                            <i class="fas fa-eye me-1"></i>Preview RHK
                        </button>
                        
                        <?php if ($status_verval_rhk == 'diajukan'): ?>
                            <button class="btn btn-warning btn-sm" onclick="confirmCancelVerval()">
                                <i class="fas fa-times me-1"></i>Batal Ajukan
                            </button>
                        <?php elseif ($status_verval_rhk == '' || $status_verval_rhk == null || $status_verval_rhk == 'ditolak'): ?>
                            <button class="btn btn-success btn-sm" onclick="confirmSubmitVerval()" 
                                <?= empty($rhks) ? 'disabled' : '' ?>>
                                <i class="fas fa-paper-plane me-1"></i>Ajukan Verval
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <button class="btn btn-primary btn-primary-large" onclick="showAddModal()"
                            <?= ($status_verval_rhk == 'diajukan' || $status_verval_rhk == 'disetujui') ? 'disabled' : '' ?>>
                            <i class="fas fa-plus me-2"></i>Tambah RHK
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- RHK List -->
        <?php if (empty($rhks)): ?>
            <div class="card text-center">
                <div class="card-body py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h6 class="text-muted">Belum ada RHK tahun ini</h6>
                    <p class="text-muted">Mulai buat rencana hasil kerja Anda</p>
                    <button class="btn btn-primary btn-primary-large" onclick="showAddModal()">
                        <i class="fas fa-plus me-2"></i>Tambah RHK
                    </button>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($rhks as $rhk): ?>
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="flex-grow-1">
                                <h6 class="card-title mb-2"><?= htmlspecialchars($rhk['uraian_kegiatan']) ?></h6>
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-success me-2">
                                        Target: <?= htmlspecialchars($rhk['target'] . ' ' . $rhk['satuan']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="editRhk(<?= $rhk['id_rhk'] ?>, '<?= htmlspecialchars($rhk['uraian_kegiatan']) ?>', '<?= htmlspecialchars($rhk['target']) ?>', '<?= htmlspecialchars($rhk['satuan']) ?>')" 
                                           <?= ($status_verval_rhk == 'diajukan' || $status_verval_rhk == 'disetujui') ? 'style="display:none;"' : '' ?>>
                                            <i class="fas fa-edit me-2"></i>Edit
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item text-danger" href="#" onclick="deleteRhk(<?= $rhk['id_rhk'] ?>)"
                                           <?= ($status_verval_rhk == 'diajukan' || $status_verval_rhk == 'disetujui') ? 'style="display:none;"' : '' ?>>
                                            <i class="fas fa-trash me-2"></i>Hapus
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Bottom Navigation -->
    <div class="bottom-nav">
        <div class="d-flex">
            <div class="nav-item">
                <a href="rhk.php" class="nav-link active">
                    <i class="fas fa-tasks d-block"></i>
                    <small>RHK</small>
                </a>
            </div>
            <div class="nav-item">
                <a href="rkb.php" class="nav-link">
                    <i class="fas fa-calendar d-block"></i>
                    <small>RKB</small>
                </a>
            </div>
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home d-block"></i>
                    <small>Beranda</small>
                </a>
            </div>
            <div class="nav-item">
                <a href="lkh.php" class="nav-link">
                    <i class="fas fa-list d-block"></i>
                    <small>LKH</small>
                </a>
            </div>
            <div class="nav-item">
                <a href="laporan.php" class="nav-link">
                    <i class="fas fa-file-alt d-block"></i>
                    <small>Laporan</small>
                </a>
            </div>
        </div>
    </div>

    <!-- Add/Edit RHK Modal -->
    <div class="modal fade" id="rhkModal" tabindex="-1">
        <div class="modal-dialog">
            <form id="rhkForm" method="POST">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="rhkModalTitle">Tambah RHK</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="rhkAction" value="add">
                        <input type="hidden" name="id_rhk" id="rhkId">
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Uraian Kegiatan</label>
                                <?php if (!empty($previous_rhk_list)): ?>
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="showPreviousRhk()">
                                        <i class="fas fa-history me-1"></i>RHK Terdahulu
                                    </button>
                                <?php endif; ?>
                            </div>
                            <textarea class="form-control" name="uraian_kegiatan" id="uraianKegiatan" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Target</label>
                            <input type="number" class="form-control" name="target" id="target" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Satuan</label>
                            <select class="form-select" name="satuan" id="satuan" required>
                                <option value="">-- Pilih Satuan --</option>
                                <option value="1">Kegiatan</option>
                                <option value="2">JP</option>
                                <option value="3">Dokumen</option>
                                <option value="4">Laporan</option>
                                <option value="5">Hari</option>
                                <option value="6">Jam</option>
                                <option value="7">Menit</option>
                                <option value="8">Unit</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Preview RHK Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-eye me-2"></i>Preview Rencana Hasil Kerja (RHK)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <h6 class="fw-bold">Periode: Tahun <?= $filter_year ?></h6>
                        <h6 class="fw-bold">Nama Pegawai: <?= htmlspecialchars($userData['nama']) ?></h6>
                    </div>
                    
                    <?php if (empty($rhks)): ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0">Belum ada data RHK untuk periode ini.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-sm">
                                <thead class="table-primary">
                                    <tr class="text-center">
                                        <th width="10%">No</th>
                                        <th width="70%">Uraian Kegiatan</th>
                                        <th width="20%">Target</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1; 
                                    foreach ($rhks as $rhk): ?>
                                        <tr>
                                            <td class="text-center"><?= $no++ ?></td>
                                            <td><small><?= htmlspecialchars($rhk['uraian_kegiatan']) ?></small></td>
                                            <td class="text-center">
                                                <span class="badge bg-primary">
                                                    <small><?= htmlspecialchars($rhk['target'] . ' ' . $rhk['satuan']) ?></small>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted">
                                        <strong>Total RHK:</strong> <?= count($rhks) ?> kegiatan
                                    </small>
                                </div>
                                <div class="col-6 text-end">
                                    <small class="text-muted">
                                        <strong>Status:</strong> 
                                        <?php 
                                        if ($status_verval_rhk == 'diajukan') {
                                            echo '<span class="badge bg-warning">Menunggu Verifikasi</span>';
                                        } elseif ($status_verval_rhk == 'disetujui') {
                                            echo '<span class="badge bg-success">Disetujui</span>';
                                        } elseif ($status_verval_rhk == 'ditolak') {
                                            echo '<span class="badge bg-danger">Ditolak</span>';
                                        } else {
                                            echo '<span class="badge bg-secondary">Belum Diajukan</span>';
                                        }
                                        ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Previous RHK Modal -->
    <div class="modal fade" id="previousRhkModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-history me-2"></i>RHK Terdahulu
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-1"></i>
                        Pilih salah satu RHK terdahulu untuk mengisi form otomatis. Data akan disalin ke form tambah RHK.
                    </div>
                    
                    <?php if (empty($previous_rhk_list)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Belum ada data RHK terdahulu yang dapat dijadikan referensi.</p>
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <input type="text" class="form-control" id="searchPreviousRhk" placeholder="🔍 Cari RHK terdahulu...">
                        </div>
                        
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($previous_rhk_list as $index => $prev_rhk): ?>
                                <div class="card mb-2 previous-rhk-item" 
                                     data-uraian="<?= htmlspecialchars($prev_rhk['uraian_kegiatan']) ?>"
                                     data-target="<?= htmlspecialchars($prev_rhk['target']) ?>"
                                     data-satuan="<?= htmlspecialchars($prev_rhk['satuan']) ?>">
                                    <div class="card-body p-3">
                                        <h6 class="card-title mb-2"><?= htmlspecialchars($prev_rhk['uraian_kegiatan']) ?></h6>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge bg-secondary me-1"><?= htmlspecialchars($prev_rhk['target']) ?></span>
                                                <span class="badge bg-primary"><?= htmlspecialchars($prev_rhk['satuan']) ?></span>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-success" 
                                                    onclick="selectPreviousRhk('<?= htmlspecialchars($prev_rhk['uraian_kegiatan']) ?>', '<?= htmlspecialchars($prev_rhk['target']) ?>', '<?= htmlspecialchars($prev_rhk['satuan']) ?>')">
                                                <i class="fas fa-check me-1"></i>Gunakan
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div id="noDataPrevious" class="text-center py-3 d-none">
                            <i class="fas fa-search fa-2x text-muted mb-2"></i>
                            <p class="text-muted">Tidak ada RHK yang sesuai dengan pencarian.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Forms for Actions -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id_rhk" id="deleteId">
    </form>

    <form id="vervalForm" method="POST" style="display: none;">
        <input type="hidden" name="ajukan_verval_rhk" id="vervalAction">
    </form>

    <form id="cancelVervalForm" method="POST" style="display: none;">
        <input type="hidden" name="batal_verval_rhk" value="1">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Show notifications
        <?php if (isset($_SESSION['mobile_notification'])): ?>
            Swal.fire({
                icon: '<?= $_SESSION['mobile_notification']['type'] ?>',
                title: '<?= $_SESSION['mobile_notification']['title'] ?>',
                text: '<?= $_SESSION['mobile_notification']['text'] ?>',
                timer: 3000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['mobile_notification']); ?>
        <?php endif; ?>

        function showAddModal() {
            document.getElementById('rhkModalTitle').textContent = 'Tambah RHK';
            document.getElementById('rhkAction').value = 'add';
            document.getElementById('rhkId').value = '';
            document.getElementById('rhkForm').reset();
            document.getElementById('submitBtn').textContent = 'Simpan';
            new bootstrap.Modal(document.getElementById('rhkModal')).show();
        }

        function editRhk(id, uraian, target, satuan) {
            document.getElementById('rhkModalTitle').textContent = 'Edit RHK';
            document.getElementById('rhkAction').value = 'edit';
            document.getElementById('rhkId').value = id;
            document.getElementById('uraianKegiatan').value = uraian;
            document.getElementById('target').value = target;
            
            // Set satuan dropdown
            const satuanMap = {
                'Kegiatan': '1', 'JP': '2', 'Dokumen': '3', 'Laporan': '4',
                'Hari': '5', 'Jam': '6', 'Menit': '7', 'Unit': '8'
            };
            document.getElementById('satuan').value = satuanMap[satuan] || '';
            
            document.getElementById('submitBtn').textContent = 'Update';
            new bootstrap.Modal(document.getElementById('rhkModal')).show();
        }

        function showPreviewModal() {
            new bootstrap.Modal(document.getElementById('previewModal')).show();
        }

        function showPreviousRhk() {
            new bootstrap.Modal(document.getElementById('previousRhkModal')).show();
        }

        function selectPreviousRhk(uraian, target, satuan) {
            document.getElementById('uraianKegiatan').value = uraian;
            document.getElementById('target').value = target;
            
            // Set satuan dropdown
            const satuanMap = {
                'Kegiatan': '1', 'JP': '2', 'Dokumen': '3', 'Laporan': '4',
                'Hari': '5', 'Jam': '6', 'Menit': '7', 'Unit': '8'
            };
            document.getElementById('satuan').value = satuanMap[satuan] || '';
            
            // Close previous RHK modal
            bootstrap.Modal.getInstance(document.getElementById('previousRhkModal')).hide();
            
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'RHK Terpilih!',
                text: 'Data RHK terdahulu berhasil disalin ke form.',
                timer: 1500,
                showConfirmButton: false
            });
            
            // Ensure add RHK modal stays open
            setTimeout(function() {
                const modalRhk = bootstrap.Modal.getInstance(document.getElementById('rhkModal'));
                if (!modalRhk || !modalRhk._isShown) {
                    const newModalRhk = new bootstrap.Modal(document.getElementById('rhkModal'));
                    newModalRhk.show();
                }
            }, 100);
        }

        function confirmSubmitVerval() {
            Swal.fire({
                title: 'Ajukan Verval RHK?',
                text: 'RHK akan menjadi dasar pembuatan RKB setelah di verval oleh Pejabat Penilai.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Ajukan',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('vervalAction').value = '1';
                    document.getElementById('vervalForm').submit();
                }
            });
        }

        function confirmCancelVerval() {
            Swal.fire({
                title: 'Batalkan Pengajuan?',
                text: 'Anda dapat mengedit/menghapus/mengirim ulang RHK setelah membatalkan.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Batalkan',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('cancelVervalForm').submit();
                }
            });
        }

        function deleteRhk(id) {
            Swal.fire({
                title: 'Konfirmasi Hapus',
                text: 'Anda yakin ingin menghapus RHK ini?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteId').value = id;
                    document.getElementById('deleteForm').submit();
                }
            });
        }

        // Search functionality for previous RHK
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchPreviousRhk');
            const previousItems = document.querySelectorAll('.previous-rhk-item');
            const noDataMessage = document.getElementById('noDataPrevious');
            
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    let visibleCount = 0;
                    
                    previousItems.forEach(function(item) {
                        const uraian = item.getAttribute('data-uraian').toLowerCase();
                        const target = item.getAttribute('data-target').toLowerCase();
                        const satuan = item.getAttribute('data-satuan').toLowerCase();
                        
                        if (uraian.includes(searchTerm) || target.includes(searchTerm) || satuan.includes(searchTerm)) {
                            item.style.display = '';
                            visibleCount++;
                        } else {
                            item.style.display = 'none';
                        }
                    });
                    
                    if (noDataMessage) {
                        if (visibleCount === 0 && searchTerm.length > 0) {
                            noDataMessage.classList.remove('d-none');
                        } else {
                            noDataMessage.classList.add('d-none');
                        }
                    }
                });
            }

            // Event listener for previous RHK modal when closed
            const modalPreviousRhkElement = document.getElementById('previousRhkModal');
            if (modalPreviousRhkElement) {
                modalPreviousRhkElement.addEventListener('hidden.bs.modal', function() {
                    // Ensure add RHK modal stays open after previous RHK modal is closed
                    setTimeout(function() {
                        const modalRhk = bootstrap.Modal.getInstance(document.getElementById('rhkModal'));
                        if (!modalRhk || !modalRhk._isShown) {
                            const newModalRhk = new bootstrap.Modal(document.getElementById('rhkModal'));
                            newModalRhk.show();
                        }
                    }, 100);
                });
            }

            // Reset form when add RHK modal is closed
            const modalRhkElement = document.getElementById('rhkModal');
            if (modalRhkElement) {
                modalRhkElement.addEventListener('hidden.bs.modal', function(e) {
                    // Check if previous RHK modal is open
                    const modalPrevRhk = bootstrap.Modal.getInstance(document.getElementById('previousRhkModal'));
                    if (!modalPrevRhk || !modalPrevRhk._isShown) {
                        // Reset form only if previous RHK modal is not open
                        this.querySelector('form').reset();
                    }
                });
            }
        });
    </script>
</body>
</html>
