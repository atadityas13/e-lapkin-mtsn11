<?php
// Start output buffering to prevent any unwanted output
ob_start();

session_start();
require_once 'config/mobile_session.php';

// Only check login session, not headers for internal pages
checkMobileLogin();

require_once __DIR__ . '/../config/database.php';

$user = getMobileSessionData();
$id_pegawai = $user['id_pegawai'];
$page_title = 'LKH - E-LAPKIN Mobile';

// Get current month/year
$current_month = (int)date('m');
$current_year = (int)date('Y');

// Get LKH data for current month
$lkhs = [];
$stmt_lkh = $conn->prepare("
    SELECT lkh.*, rkb.uraian_kegiatan as rkb_uraian 
    FROM lkh 
    JOIN rkb ON lkh.id_rkb = rkb.id_rkb 
    WHERE lkh.id_pegawai = ? AND MONTH(lkh.tanggal_lkh) = ? AND YEAR(lkh.tanggal_lkh) = ?
    ORDER BY lkh.tanggal_lkh DESC
");
$stmt_lkh->bind_param("iii", $id_pegawai, $current_month, $current_year);
$stmt_lkh->execute();
$result_lkh = $stmt_lkh->get_result();
while ($row = $result_lkh->fetch_assoc()) {
    $lkhs[] = $row;
}

// Clear any unwanted output before HTML
ob_clean();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <!-- Mobile Navigation -->
    <nav class="navbar navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
                <i class="fas fa-arrow-left me-2"></i>
                <span>LKH</span>
            </a>
            <div class="d-flex align-items-center text-white">
                <small><?= htmlspecialchars($user['nama']) ?></small>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-3">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="fas fa-book me-2"></i>Laporan Kinerja Harian</h5>
            <a href="lkh_add.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i>
            </a>
        </div>
        
        <!-- Month info -->
        <div class="alert alert-info">
            <i class="fas fa-calendar-alt me-2"></i>
            <?= date('F Y') ?> - <?= count($lkhs) ?> kegiatan
        </div>

        <!-- LKH List -->
        <?php if (empty($lkhs)): ?>
            <div class="card text-center">
                <div class="card-body py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h6 class="text-muted">Belum ada LKH bulan ini</h6>
                    <p class="text-muted">Mulai tambahkan kegiatan harian Anda</p>
                    <a href="lkh_add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Tambah LKH
                    </a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($lkhs as $lkh): ?>
                <div class="card mb-3 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-primary me-2">
                                        <?= date('d M', strtotime($lkh['tanggal_lkh'])) ?>
                                    </span>
                                    <small class="text-muted">
                                        <?= date('l', strtotime($lkh['tanggal_lkh'])) ?>
                                    </small>
                                </div>
                                <h6 class="card-title mb-2"><?= htmlspecialchars($lkh['nama_kegiatan_harian']) ?></h6>
                                <p class="card-text small text-muted mb-2">
                                    <?= htmlspecialchars($lkh['uraian_kegiatan_lkh']) ?>
                                </p>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-success me-2">
                                        <?= htmlspecialchars($lkh['jumlah_realisasi'] . ' ' . $lkh['satuan_realisasi']) ?>
                                    </span>
                                    <?php if ($lkh['lampiran']): ?>
                                        <i class="fas fa-paperclip text-muted"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-link text-muted" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="lkh_edit.php?id=<?= $lkh['id_lkh'] ?>">
                                        <i class="fas fa-edit me-2"></i>Edit
                                    </a></li>
                                    <?php if ($lkh['lampiran']): ?>
                                    <li><a class="dropdown-item" href="../uploads/lkh/<?= $lkh['lampiran'] ?>" target="_blank">
                                        <i class="fas fa-eye me-2"></i>Lihat Lampiran
                                    </a></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
