<?php
session_start();
require_once 'config/mobile_session.php';
checkMobileLogin();
require_once __DIR__ . '/../config/database.php';

$user = getMobileSessionData();
$id_pegawai = $user['id_pegawai'];

// Get statistics
$stmt_rhk = $conn->prepare("SELECT COUNT(*) as count FROM rhk WHERE id_pegawai = ?");
$stmt_rhk->bind_param("i", $id_pegawai);
$stmt_rhk->execute();
$rhk_count = $stmt_rhk->get_result()->fetch_assoc()['count'];

$stmt_rkb = $conn->prepare("SELECT COUNT(*) as count FROM rkb WHERE id_pegawai = ?");
$stmt_rkb->bind_param("i", $id_pegawai);
$stmt_rkb->execute();
$rkb_count = $stmt_rkb->get_result()->fetch_assoc()['count'];

$stmt_lkh = $conn->prepare("SELECT COUNT(*) as count FROM lkh WHERE id_pegawai = ?");
$stmt_lkh->bind_param("i", $id_pegawai);
$stmt_lkh->execute();
$lkh_count = $stmt_lkh->get_result()->fetch_assoc()['count'];

include 'includes/mobile_header.php';
?>

<div class="container-fluid py-3">
    <!-- Welcome Card -->
    <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="card-body text-white">
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <i class="fas fa-user-circle fa-3x"></i>
                </div>
                <div>
                    <h5 class="card-title mb-1">Selamat Datang</h5>
                    <h6 class="card-subtitle mb-1 text-white-50"><?= htmlspecialchars($user['nama']) ?></h6>
                    <small class="text-white-50"><?= htmlspecialchars($user['jabatan']) ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-4">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <i class="fas fa-tasks text-primary fa-2x mb-2"></i>
                    <h4 class="mb-1"><?= $rhk_count ?></h4>
                    <small class="text-muted">RHK</small>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <i class="fas fa-calendar-alt text-success fa-2x mb-2"></i>
                    <h4 class="mb-1"><?= $rkb_count ?></h4>
                    <small class="text-muted">RKB</small>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <i class="fas fa-book text-info fa-2x mb-2"></i>
                    <h4 class="mb-1"><?= $lkh_count ?></h4>
                    <small class="text-muted">LKH</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0">
            <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Aksi Cepat</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-6">
                    <a href="lkh_add.php" class="btn btn-primary w-100 py-3">
                        <i class="fas fa-plus-circle fa-lg mb-2 d-block"></i>
                        <small>Tambah LKH</small>
                    </a>
                </div>
                <div class="col-6">
                    <a href="lkh.php" class="btn btn-outline-primary w-100 py-3">
                        <i class="fas fa-list fa-lg mb-2 d-block"></i>
                        <small>Lihat LKH</small>
                    </a>
                </div>
                <div class="col-6">
                    <a href="rkb.php" class="btn btn-outline-success w-100 py-3">
                        <i class="fas fa-calendar fa-lg mb-2 d-block"></i>
                        <small>RKB Saya</small>
                    </a>
                </div>
                <div class="col-6">
                    <a href="rhk.php" class="btn btn-outline-info w-100 py-3">
                        <i class="fas fa-tasks fa-lg mb-2 d-block"></i>
                        <small>RHK Saya</small>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/mobile_footer.php'; ?>
