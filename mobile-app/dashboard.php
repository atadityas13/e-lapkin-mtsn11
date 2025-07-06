<?php
/**
 * E-LAPKIN Mobile Dashboard
 */

session_start();

// Include mobile session config
require_once __DIR__ . '/config/mobile_session.php';

// Check mobile login (only validate session, not headers for dashboard)
checkMobileLogin();

// Get user session data
$userData = getMobileSessionData();

// Handle logout
if (isset($_POST['logout'])) {
    mobileLogout();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-LAPKIN Mobile - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-stat {
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-light">
    <!-- Header -->
    <div class="gradient-bg text-white p-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-1">E-LAPKIN</h5>
                <small>Selamat Datang</small>
            </div>
            <div class="text-end">
                <div class="dropdown">
                    <button class="btn btn-link text-white p-0" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle fa-2x"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text"><strong><?= htmlspecialchars($userData['nama']) ?></strong></span></li>
                        <li><span class="dropdown-item-text"><?= htmlspecialchars($userData['jabatan']) ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" style="display: inline;">
                                <button type="submit" name="logout" class="dropdown-item text-danger">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <p class="mb-0 mt-2"><?= htmlspecialchars($userData['nama']) ?><br>
        <small>Operator Layanan Operasional</small></p>
    </div>

    <!-- Stats Cards -->
    <div class="container-fluid px-3 mt-3">
        <div class="row g-2">
            <div class="col-4">
                <div class="card card-stat text-center">
                    <div class="card-body py-3">
                        <h4 class="text-primary mb-1"><?= $rhk_count ?></h4>
                        <small class="text-muted">RHK</small>
                    </div>
                </div>
            </div>
            <div class="col-4">
                <div class="card card-stat text-center">
                    <div class="card-body py-3">
                        <h4 class="text-success mb-1"><?= $rkb_count ?></h4>
                        <small class="text-muted">RKB</small>
                    </div>
                </div>
            </div>
            <div class="col-4">
                <div class="card card-stat text-center">
                    <div class="card-body py-3">
                        <h4 class="text-info mb-1"><?= $lkh_count ?></h4>
                        <small class="text-muted">LKH</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="container-fluid px-3 mt-4">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title mb-3"><i class="fas fa-bolt text-warning me-2"></i>Aksi Cepat</h6>
                <div class="row g-2">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
