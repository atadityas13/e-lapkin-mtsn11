<?php
ob_start();
session_start();

require_once __DIR__ . '/../config/mobile_session.php';
require_once __DIR__ . '/../config/talim_embed.php';
require_once __DIR__ . '/../../config/database.php';

checkMobileLogin();

$userData = getMobileSessionData();
$idPegawai = (int) $userData['id_pegawai'];
ensureTalimPeriod($conn, $idPegawai);

$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];

$stmt = $conn->prepare('SELECT tahun_aktif, bulan_aktif FROM pegawai WHERE id_pegawai = ?');
$stmt->bind_param('i', $idPegawai);
$stmt->execute();
$stmt->bind_result($tahunAktif, $bulanAktif);
$stmt->fetch();
$stmt->close();

$tahun = $tahunAktif ?: (int) date('Y');
$bulan = $bulanAktif ?: (int) date('m');
$periode = ($months[$bulan] ?? date('F')) . ' ' . $tahun;

$stmt = $conn->prepare('SELECT COUNT(*) FROM rkb WHERE id_pegawai = ? AND bulan = ? AND tahun = ?');
$stmt->bind_param('iii', $idPegawai, $bulan, $tahun);
$stmt->execute();
$stmt->bind_result($rkbCount);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare('SELECT COUNT(*) FROM lkh WHERE id_pegawai = ? AND MONTH(tanggal_lkh) = ? AND YEAR(tanggal_lkh) = ?');
$stmt->bind_param('iii', $idPegawai, $bulan, $tahun);
$stmt->execute();
$stmt->bind_result($lkhCount);
$stmt->fetch();
$stmt->close();

$today = date('Y-m-d');
$stmt = $conn->prepare('SELECT COUNT(*) FROM lkh WHERE id_pegawai = ? AND tanggal_lkh = ?');
$stmt->bind_param('is', $idPegawai, $today);
$stmt->execute();
$stmt->bind_result($todayLkh);
$stmt->fetch();
$stmt->close();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kinerja Guru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(180deg, #ecfdf5 0%, #f8fafc 100%);
            color: #1f2937;
            padding: 16px 16px 140px;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .hero-card, .stat-card, .action-card {
            background: rgba(255,255,255,0.96);
            border: 1px solid rgba(6,95,70,0.08);
            border-radius: 22px;
            box-shadow: 0 14px 32px rgba(6,95,70,0.08);
        }
        .hero-card {
            padding: 20px;
            background: linear-gradient(135deg, #065f46, #047857);
            color: white;
        }
        .period-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 11px;
            border-radius: 999px;
            background: rgba(255,255,255,0.16);
            border: 1px solid rgba(255,255,255,0.24);
            font-size: 12px;
        }
        .stat-card {
            padding: 16px;
            height: 100%;
        }
        a.stat-card {
            display: block;
            color: inherit;
            text-decoration: none;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        a.stat-card:active {
            transform: scale(0.97);
            box-shadow: 0 8px 20px rgba(6,95,70,0.12);
        }
        .stat-value {
            color: #065f46;
            font-size: 28px;
            font-weight: 800;
            line-height: 1;
        }
        .stat-label {
            margin-top: 6px;
            font-size: 12px;
            color: #64748b;
        }
        .action-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px;
            color: inherit;
            text-decoration: none;
        }
        .action-icon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #d1fae5;
            color: #047857;
            margin-right: 12px;
        }
    </style>
</head>
<body>
    <section class="hero-card mb-3">
        <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
                <div class="small opacity-75">Kinerja Guru</div>
                <h1 class="h4 fw-bold mb-0">RKB, LKH & Laporan</h1>
            </div>
            <div class="period-chip">
                <i class="fas fa-calendar"></i>
                <?= htmlspecialchars($periode) ?>
            </div>
        </div>
    </section>

    <div class="row g-2 mb-3">
        <div class="col-4">
            <a class="stat-card" href="talim://tab/rkb">
                <div class="stat-value"><?= (int) $rkbCount ?></div>
                <div class="stat-label">RKB bulan ini</div>
            </a>
        </div>
        <div class="col-4">
            <a class="stat-card" href="talim://tab/lkh">
                <div class="stat-value"><?= (int) $lkhCount ?></div>
                <div class="stat-label">LKH bulan ini</div>
            </a>
        </div>
        <div class="col-4">
            <a class="stat-card" href="talim://tab/lkh">
                <div class="stat-value"><?= ((int) $todayLkh) > 0 ? '✓' : '!' ?></div>
                <div class="stat-label">LKH hari ini</div>
            </a>
        </div>
    </div>

    <div class="d-grid gap-2">
        <a class="action-card" href="talim://tab/rkb">
            <div class="d-flex align-items-center">
                <span class="action-icon"><i class="fas fa-calendar-check"></i></span>
                <div>
                    <div class="fw-semibold">Rencana Kerja Bulanan</div>
                    <small class="text-muted">Buat dan kelola RKB periode aktif.</small>
                </div>
            </div>
            <i class="fas fa-chevron-right text-muted"></i>
        </a>
        <a class="action-card" href="talim://tab/lkh">
            <div class="d-flex align-items-center">
                <span class="action-icon"><i class="fas fa-list-check"></i></span>
                <div>
                    <div class="fw-semibold">Laporan Kinerja Harian</div>
                    <small class="text-muted">Catat aktivitas harian guru.</small>
                </div>
            </div>
            <i class="fas fa-chevron-right text-muted"></i>
        </a>
        <a class="action-card" href="talim://tab/laporan">
            <div class="d-flex align-items-center">
                <span class="action-icon"><i class="fas fa-file-pdf"></i></span>
                <div>
                    <div class="fw-semibold">Generate Laporan</div>
                    <small class="text-muted">Unduh LKB/LKH PDF periode ini.</small>
                </div>
            </div>
            <i class="fas fa-chevron-right text-muted"></i>
        </a>
    </div>
</body>
</html>
