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
$current_year = (int)date('Y');

// Helper function for SweetAlert-like notifications
function set_mobile_notification($type, $title, $text) {
    $_SESSION['mobile_notification'] = [
        'type' => $type,
        'title' => $title,
        'text' => $text
    ];
}

// Get active period from user settings
function get_periode_aktif($conn, $id_pegawai) {
    $stmt = $conn->prepare("SELECT tahun_aktif FROM pegawai WHERE id_pegawai = ?");
    $stmt->bind_param("i", $id_pegawai);
    $stmt->execute();
    $stmt->bind_result($tahun_aktif);
    $stmt->fetch();
    $stmt->close();
    return $tahun_aktif ?: (int)date('Y');
}

function set_periode_aktif($conn, $id_pegawai, $tahun) {
    $stmt = $conn->prepare("UPDATE pegawai SET tahun_aktif = ? WHERE id_pegawai = ?");
    $stmt->bind_param("ii", $tahun, $id_pegawai);
    $stmt->execute();
    $stmt->close();
}

// Ensure tahun_aktif column exists
function ensure_tahun_aktif_column($conn) {
    $result = $conn->query("SHOW COLUMNS FROM pegawai LIKE 'tahun_aktif'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE pegawai ADD COLUMN tahun_aktif INT DEFAULT NULL");
    }
}
ensure_tahun_aktif_column($conn);

$periode_aktif = get_periode_aktif($conn, $id_pegawai_login);

// Get active period for display
$activePeriod = getMobileActivePeriod($conn, $id_pegawai_login);

// Handle POST requests
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['set_periode_aktif'])) {
        $tahun_aktif_baru = (int)$_POST['tahun_aktif'];
        set_periode_aktif($conn, $id_pegawai_login, $tahun_aktif_baru);
        set_mobile_notification('success', 'Periode Diubah', 'Periode aktif berhasil diubah.');
        header('Location: rhk.php');
        exit();
    }

    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == 'add' || $action == 'edit') {
            $nama_rhk = trim($_POST['nama_rhk']);
            $aspek = trim($_POST['aspek']);
            $target = trim($_POST['target']);

            // Validation
            if (empty($nama_rhk) || empty($aspek) || empty($target)) {
                set_mobile_notification('error', 'Gagal', 'Semua field harus diisi.');
            } else {
                try {
                    if ($action == 'add') {
                        $stmt = $conn->prepare("INSERT INTO rhk (id_pegawai, nama_rhk, aspek, target) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("isss", $id_pegawai_login, $nama_rhk, $aspek, $target);
                    } else {
                        $id_rhk = (int)$_POST['id_rhk'];
                        $stmt = $conn->prepare("UPDATE rhk SET nama_rhk = ?, aspek = ?, target = ? WHERE id_rhk = ? AND id_pegawai = ?");
                        $stmt->bind_param("sssii", $nama_rhk, $aspek, $target, $id_rhk, $id_pegawai_login);
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
            
            // Check if RHK is used in RKB
            $cek_rkb = $conn->prepare("SELECT COUNT(*) FROM rkb WHERE id_rhk = ?");
            $cek_rkb->bind_param("i", $id_rhk_to_delete);
            $cek_rkb->execute();
            $cek_rkb->bind_result($jumlah_rkb);
            $cek_rkb->fetch();
            $cek_rkb->close();

            if ($jumlah_rkb > 0) {
                set_mobile_notification('error', 'Gagal', 'RHK tidak dapat dihapus karena sudah digunakan pada RKB.');
            } else {
                $stmt = $conn->prepare("DELETE FROM rhk WHERE id_rhk = ? AND id_pegawai = ?");
                $stmt->bind_param("ii", $id_rhk_to_delete, $id_pegawai_login);

                if ($stmt->execute()) {
                    set_mobile_notification('success', 'Berhasil', 'RHK berhasil dihapus!');
                } else {
                    set_mobile_notification('error', 'Gagal', "Gagal menghapus RHK: " . $stmt->error);
                }
                $stmt->close();
            }
            header('Location: rhk.php');
            exit();
        }
    }
}

// Check if period is not set
$stmt_check_periode = $conn->prepare("SELECT tahun_aktif FROM pegawai WHERE id_pegawai = ?");
$stmt_check_periode->bind_param("i", $id_pegawai_login);
$stmt_check_periode->execute();
$stmt_check_periode->bind_result($tahun_aktif_db);
$stmt_check_periode->fetch();
$stmt_check_periode->close();

$periode_belum_diatur = ($tahun_aktif_db === null);

// Get available years
$years = [];
$res = $conn->query("SELECT DISTINCT YEAR(created_at) as tahun FROM rhk WHERE id_pegawai = $id_pegawai_login ORDER BY tahun DESC");
while ($row = $res->fetch_assoc()) {
    $years[] = $row['tahun'];
}
if (empty($years)) $years[] = $current_year;
if (!in_array($current_year, $years)) {
    array_unshift($years, $current_year);
}

// Get RHK data for current year using created_at
$rhks = [];
$stmt = $conn->prepare("SELECT id_rhk, nama_rhk, aspek, target FROM rhk WHERE id_pegawai = ? AND YEAR(created_at) = ? ORDER BY created_at ASC");
$stmt->bind_param("ii", $id_pegawai_login, $periode_aktif);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $rhks[] = $row;
}
$stmt->close();

// Get previous RHK list for reference (from different years)
$previous_rhk_list = [];
$stmt_previous_rhk = $conn->prepare("
    SELECT 
        r1.nama_rhk,
        r1.aspek,
        r1.target
    FROM rhk r1
    INNER JOIN (
        SELECT 
            nama_rhk,
            MAX(id_rhk) as max_id_rhk
        FROM rhk 
        WHERE id_pegawai = ? AND YEAR(created_at) != ?
        GROUP BY nama_rhk
    ) r2 ON r1.nama_rhk = r2.nama_rhk
         AND r1.id_rhk = r2.max_id_rhk
    WHERE r1.id_pegawai = ? AND YEAR(r1.created_at) != ?
    ORDER BY r1.id_rhk DESC, r1.nama_rhk ASC
    LIMIT 20
");

$stmt_previous_rhk->bind_param("iiii", $id_pegawai_login, $periode_aktif, $id_pegawai_login, $periode_aktif);
$stmt_previous_rhk->execute();
$result_previous_rhk = $stmt_previous_rhk->get_result();

while ($row = $result_previous_rhk->fetch_assoc()) {
    $previous_rhk_list[] = [
        'nama_rhk' => $row['nama_rhk'],
        'aspek' => $row['aspek'],
        'target' => $row['target']
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
        :root {
            --primary-color: #4f46e5;
            --primary-dark: #3730a3;
            --secondary-color: #6b7280;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --light-gray: #f8fafc;
            --border-color: #e2e8f0;
            --text-dark: #1f2937;
            --text-light: #6b7280;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--light-gray);
            color: var(--text-dark);
            line-height: 1.5;
            padding-bottom: 80px;
        }

        /* Header Styles */
        .mobile-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .mobile-header .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 100%;
            gap: 1rem;
        }

        .mobile-header .header-left {
            display: flex;
            align-items: center;
            flex: 1;
            min-width: 0;
        }

        .mobile-header .back-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            margin-right: 0.75rem;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: background-color 0.2s;
            cursor: pointer;
        }

        .mobile-header .back-btn:hover {
            background-color: rgba(255,255,255,0.1);
        }

        .mobile-header .page-info {
            flex: 1;
            min-width: 0;
        }

        .mobile-header .page-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mobile-header .page-subtitle {
            font-size: 0.8rem;
            opacity: 0.9;
            margin-top: 0.1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mobile-header .user-info {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 0.75rem;
            padding: 0.5rem 0.75rem;
            font-size: 0.8rem;
            text-align: right;
            min-width: 120px;
            flex-shrink: 0;
        }

        .mobile-header .user-info .user-name {
            font-weight: 600;
            margin-bottom: 0.1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mobile-header .user-info .user-details {
            opacity: 0.9;
            font-size: 0.75rem;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Main Content */
        .main-content {
            padding: 1rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .mobile-header {
                padding: 0.75rem;
            }

            .mobile-header .page-title {
                font-size: 1rem;
            }

            .mobile-header .user-info {
                font-size: 0.75rem;
                padding: 0.5rem;
                min-width: 100px;
            }

            .main-content {
                padding: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .mobile-header .header-content {
                gap: 0.5rem;
            }

            .mobile-header .user-info {
                min-width: 80px;
                font-size: 0.7rem;
                padding: 0.4rem 0.6rem;
            }

            .mobile-header .user-info .user-details {
                font-size: 0.7rem;
            }
        }

        /* ...existing RHK-specific styles... */
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <header class="mobile-header">
        <div class="header-content">
            <div class="header-left">
                <button class="back-btn" onclick="window.location.href='dashboard.php'">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div class="page-info">
                    <h1 class="page-title">Rencana Hasil Kerja</h1>
                    <p class="page-subtitle">Kelola RHK Harian Anda</p>
                </div>
            </div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($userData['nama']) ?></div>
                <div class="user-details"><?= htmlspecialchars($userData['nip']) ?></div>
                <div class="user-details"><?= htmlspecialchars($activePeriod) ?></div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Stats Overview -->
        <!-- <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= count($rhks) ?></div>
                <div class="stat-label">Total RHK</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $periode_aktif ?></div>
                <div class="stat-label">Periode Aktif</div>
            </div>
        </div> -->

        <!-- Period Selection Card -->
        <div class="card period-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-calendar-alt text-primary me-2"></i>
                        Periode: Tahun <?= $periode_aktif ?>
                    </h6>
                    <button class="btn btn-outline-primary-custom btn-sm" onclick="showPeriodChangeModal()">
                        <i class="fas fa-edit me-1"></i>Ubah
                    </button>
                </div>
                
                <div class="period-selector">
                    <form id="periodeForm" method="POST" class="d-flex align-items-center gap-2">
                        <label class="form-label mb-0 me-2 fw-semibold flex-shrink-0">Pilih Periode:</label>
                        <select class="form-select form-select-custom flex-grow-1" name="tahun_aktif">
                            <?php foreach ($years as $year): ?>
                                <option value="<?= $year ?>" <?= ($periode_aktif == $year) ? 'selected' : '' ?>><?= $year ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="set_periode_aktif" value="1">
                    </form>
                </div>
            </div>
        </div>

        <!-- Period Not Set Modal -->
        <?php if ($periode_belum_diatur): ?>
        <div class="modal fade" id="modalPeriodeBelumDiatur" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle me-2"></i>Periode Tahun Belum Diatur
                        </h5>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Informasi:</strong> Periode tahun untuk RHK belum diatur. Silakan pilih periode tahun yang akan digunakan.
                        </div>
                        <form id="setPeriodeForm" method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Pilih Tahun Periode:</label>
                                <select class="form-select" name="tahun_aktif" required>
                                    <option value="">-- Pilih Tahun --</option>
                                    <?php 
                                    for ($i = $current_year - 2; $i <= $current_year + 2; $i++): ?>
                                        <option value="<?= $i ?>" <?= ($i == $current_year) ? 'selected' : '' ?>><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <input type="hidden" name="set_periode_aktif" value="1">
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" onclick="submitPeriodForm()">
                            <i class="fas fa-check me-1"></i>Atur Periode
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- RHK List -->
        <?php if (empty($rhks)): ?>
            <div class="card">
                <div class="card-body empty-state">
                    <i class="fas fa-tasks"></i>
                    <h5 class="text-muted mb-3">Belum ada RHK tahun ini</h5>
                    <p class="text-muted mb-4">Mulai buat rencana hasil kerja untuk mencapai target kinerja Anda</p>
                    <button class="btn btn-primary-large" onclick="showAddModal()">
                        <i class="fas fa-plus me-2"></i>Buat RHK Pertama
                    </button>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($rhks as $index => $rhk): ?>
                <div class="card rhk-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-primary me-2">#<?= $index + 1 ?></span>
                                    <small class="text-muted">RHK <?= $periode_aktif ?></small>
                                </div>
                                <h6 class="rhk-title"><?= htmlspecialchars($rhk['nama_rhk']) ?></h6>
                                <div class="rhk-meta">
                                    <span class="badge badge-custom badge-<?= strtolower($rhk['aspek']) ?>">
                                        <i class="fas fa-<?= $rhk['aspek'] == 'Kuantitas' ? 'chart-bar' : ($rhk['aspek'] == 'Kualitas' ? 'star' : ($rhk['aspek'] == 'Waktu' ? 'clock' : 'dollar-sign')) ?> me-1"></i>
                                        <?= htmlspecialchars($rhk['aspek']) ?>
                                    </span>
                                    <span class="badge badge-custom badge-target">
                                        <i class="fas fa-bullseye me-1"></i>
                                        <?= htmlspecialchars($rhk['target']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light rounded-circle" data-bs-toggle="dropdown" style="width: 35px; height: 35px;">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="editRhk(<?= $rhk['id_rhk'] ?>, '<?= htmlspecialchars($rhk['nama_rhk']) ?>', '<?= htmlspecialchars($rhk['aspek']) ?>', '<?= htmlspecialchars($rhk['target']) ?>')">
                                            <i class="fas fa-edit me-2 text-warning"></i>Edit RHK
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item text-danger" href="#" onclick="deleteRhk(<?= $rhk['id_rhk'] ?>)">
                                            <i class="fas fa-trash me-2"></i>Hapus RHK
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

    <!-- Floating Action Button -->
    <div class="floating-action">
        <button class="floating-btn" onclick="showAddModal()" title="Tambah RHK">
            <i class="fas fa-plus"></i>
        </button>
    </div>

    <!-- Period Change Confirmation Modal -->
    <div class="modal fade" id="modalKonfirmasiPeriode" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Ubah Periode
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin mengubah periode aktif?</p>
                    <p class="text-muted small">Data RHK yang tampil akan mengikuti periode yang dipilih.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-warning" onclick="submitPeriodChange()">
                        <i class="fas fa-check me-1"></i>Ya, Ubah Periode
                    </button>
                </div>
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
                                <label class="form-label mb-0">Nama RHK / Uraian Kegiatan</label>
                                <?php if (!empty($previous_rhk_list)): ?>
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="showPreviousRhk()">
                                        <i class="fas fa-history me-1"></i>RHK Terdahulu
                                    </button>
                                <?php endif; ?>
                            </div>
                            <input type="text" class="form-control" name="nama_rhk" id="namaRhk" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Aspek</label>
                            <select class="form-select" name="aspek" id="aspek" required>
                                <option value="">-- Pilih Aspek --</option>
                                <option value="Kuantitas">Kuantitas</option>
                                <option value="Kualitas">Kualitas</option>
                                <option value="Waktu">Waktu</option>
                                <option value="Biaya">Biaya</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Target</label>
                            <input type="text" class="form-control" name="target" id="target" placeholder="Contoh: 24 JP, 1 Laporan" required>
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
                                     data-nama="<?= htmlspecialchars($prev_rhk['nama_rhk']) ?>"
                                     data-aspek="<?= htmlspecialchars($prev_rhk['aspek']) ?>"
                                     data-target="<?= htmlspecialchars($prev_rhk['target']) ?>">
                                    <div class="card-body p-3">
                                        <h6 class="card-title mb-2"><?= htmlspecialchars($prev_rhk['nama_rhk']) ?></h6>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge bg-info me-1"><?= htmlspecialchars($prev_rhk['aspek']) ?></span>
                                                <span class="badge bg-success"><?= htmlspecialchars($prev_rhk['target']) ?></span>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-success" 
                                                    onclick="selectPreviousRhk('<?= htmlspecialchars($prev_rhk['nama_rhk']) ?>', '<?= htmlspecialchars($prev_rhk['aspek']) ?>', '<?= htmlspecialchars($prev_rhk['target']) ?>')">
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

    <!-- Bottom Navigation -->
    <?php include __DIR__ . '/components/bottom-nav.php'; ?>

    <!-- Hidden Forms for Actions -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id_rhk" id="deleteId">
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

        // Auto show period modal if not set
        <?php if ($periode_belum_diatur): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var modalPeriode = new bootstrap.Modal(document.getElementById('modalPeriodeBelumDiatur'));
            modalPeriode.show();
        });
        <?php endif; ?>

        function submitPeriodForm() {
            const tahunDipilih = document.querySelector('#setPeriodeForm select[name="tahun_aktif"]').value;
            if (!tahunDipilih) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Peringatan',
                    text: 'Silakan pilih tahun terlebih dahulu.',
                    timer: 2000
                });
                return;
            }
            document.getElementById('setPeriodeForm').submit();
        }

        function showPeriodChangeModal() {
            new bootstrap.Modal(document.getElementById('modalKonfirmasiPeriode')).show();
        }

        function submitPeriodChange() {
            document.getElementById('periodeForm').submit();
        }

        function confirmChangePeriod() {
            showPeriodChangeModal();
        }

        function showAddModal() {
            document.getElementById('rhkModalTitle').textContent = 'Tambah RHK';
            document.getElementById('rhkAction').value = 'add';
            document.getElementById('rhkId').value = '';
            document.getElementById('rhkForm').reset();
            document.getElementById('submitBtn').textContent = 'Simpan';
            new bootstrap.Modal(document.getElementById('rhkModal')).show();
        }

        function editRhk(id, nama, aspek, target) {
            document.getElementById('rhkModalTitle').textContent = 'Edit RHK';
            document.getElementById('rhkAction').value = 'edit';
            document.getElementById('rhkId').value = id;
            document.getElementById('namaRhk').value = nama;
            document.getElementById('aspek').value = aspek;
            document.getElementById('target').value = target;
            document.getElementById('submitBtn').textContent = 'Update';
            new bootstrap.Modal(document.getElementById('rhkModal')).show();
        }

        function showPreviousRhk() {
            new bootstrap.Modal(document.getElementById('previousRhkModal')).show();
        }

        function selectPreviousRhk(nama, aspek, target) {
            document.getElementById('namaRhk').value = nama;
            document.getElementById('aspek').value = aspek;
            document.getElementById('target').value = target;
            
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

        function deleteRhk(id) {
            Swal.fire({
                title: 'Konfirmasi Hapus',
                text: 'Anda yakin ingin menghapus RHK ini? RHK yang sudah digunakan pada RKB tidak dapat dihapus.',
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

        // Add smooth scroll animation for cards
        document.addEventListener('DOMContentLoaded', function() {
            // Animate cards on load
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

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
                        const nama = item.getAttribute('data-nama').toLowerCase();
                        const aspek = item.getAttribute('data-aspek').toLowerCase();
                        const target = item.getAttribute('data-target').toLowerCase();
                        
                        if (nama.includes(searchTerm) || aspek.includes(searchTerm) || target.includes(searchTerm)) {
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