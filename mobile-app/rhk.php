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
require_once __DIR__ . '/components/mobile-header.php';

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
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --card-shadow: 0 8px 25px rgba(0,0,0,0.1);
            --card-hover-shadow: 0 12px 35px rgba(0,0,0,0.15);
            --accent-blue: #667eea;
            --accent-purple: #764ba2;
        }

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
            color: var(--accent-blue);
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.05));
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .nav-header {
            background: var(--primary-gradient);
            color: white;
            padding: 20px 15px;
            border-radius: 0 0 25px 25px;
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
        }

        .nav-header .navbar-brand {
            font-size: 1.3rem;
            font-weight: 600;
        }

        .card {
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            border: none;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-hover-shadow);
        }

        .card-header-custom {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.05));
            border-bottom: 1px solid rgba(102, 126, 234, 0.2);
            padding: 20px;
            border-radius: 20px 20px 0 0;
        }

        .period-card {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.02));
        }

        .rhk-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9ff 100%);
            border-left: 4px solid var(--accent-blue);
        }

        .rhk-card:hover {
            border-left-color: var(--accent-purple);
        }

        .btn {
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary-large {
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 15px;
            background: var(--primary-gradient);
            border: none;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }

        .btn-primary-large:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-outline-primary-custom {
            border: 2px solid var(--accent-blue);
            color: var(--accent-blue);
            background: transparent;
        }

        .btn-outline-primary-custom:hover {
            background: var(--primary-gradient);
            border-color: transparent;
            color: white;
        }

        .badge-custom {
            border-radius: 20px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-kuantitas { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .badge-kualitas { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .badge-waktu { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .badge-biaya { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }

        .badge-target {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9ff 100%);
        }

        .empty-state i {
            font-size: 4rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
        }

        .floating-action {
            position: fixed;
            bottom: 100px;
            right: 20px;
            z-index: 999;
        }

        .floating-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-gradient);
            color: white;
            border: none;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            transition: all 0.3s ease;
        }

        .floating-btn:hover {
            transform: scale(1.1) translateY(-2px);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.5);
            color: white;
        }

        .period-selector {
            background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(248,249,255,0.8));
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 15px;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .form-select-custom {
            border: 2px solid rgba(102, 126, 234, 0.3);
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .form-select-custom:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .rhk-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .rhk-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .dropdown-menu {
            border-radius: 12px;
            border: none;
            box-shadow: var(--card-shadow);
        }

        .dropdown-item {
            padding: 10px 15px;
            border-radius: 8px;
            margin: 2px;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.05));
        }

        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: var(--card-hover-shadow);
        }

        .modal-header {
            border-radius: 20px 20px 0 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid rgba(102, 126, 234, 0.2);
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .alert {
            border-radius: 15px;
            border: none;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9ff 100%);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(102, 126, 234, 0.1);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-shadow);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }

        @media (max-width: 576px) {
            .floating-action {
                bottom: 90px;
                right: 15px;
            }
            
            .floating-btn {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
            
            .card-body {
                padding: 15px;
            }
            
            .rhk-meta {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
    <?= getMobileHeaderCSS() ?>
</head>
<body>
    <!-- Header -->
    <?php renderMobileHeader('RHK', 'Rencana Hasil Kerja', 'dashboard.php', $userData, $activePeriod); ?>

    <div class="container-fluid px-3">
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