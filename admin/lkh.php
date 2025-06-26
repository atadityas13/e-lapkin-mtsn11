<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * Sistem Elektronik Laporan Kinerja Harian
 * MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat
 * 
 * File: Admin LKH Management
 * Deskripsi: Halaman manajemen LKH untuk admin
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

$page_title = "Indikator LKH";
$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tambah_indikator'])) {
        $id_pegawai = (int)$_POST['id_pegawai'];
        $id_rkb = (int)$_POST['id_rkb'];
        $tanggal_lkh = $_POST['tanggal_lkh'];
        $nama_kegiatan_harian = trim($_POST['nama_kegiatan_harian']);
        $uraian_kegiatan_lkh = trim($_POST['uraian_kegiatan_lkh']);
        $jumlah_realisasi = (int)$_POST['jumlah_realisasi'];
        $satuan_realisasi = trim($_POST['satuan_realisasi']);
        
        $stmt = $conn->prepare("INSERT INTO lkh (id_pegawai, id_rkb, tanggal_lkh, nama_kegiatan_harian, uraian_kegiatan_lkh, jumlah_realisasi, satuan_realisasi) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissssi", $id_pegawai, $id_rkb, $tanggal_lkh, $nama_kegiatan_harian, $uraian_kegiatan_lkh, $jumlah_realisasi, $satuan_realisasi);
        
        if ($stmt->execute()) {
            $message = "Indikator LKH berhasil ditambahkan.";
            $message_type = "success";
        } else {
            $message = "Error: " . $stmt->error;
            $message_type = "danger";
        }
        $stmt->close();
    }
    
    if (isset($_POST['edit_indikator'])) {
        $id_lkh = (int)$_POST['id_lkh'];
        $id_rkb = (int)$_POST['id_rkb'];
        $tanggal_lkh = $_POST['tanggal_lkh'];
        $nama_kegiatan_harian = trim($_POST['nama_kegiatan_harian']);
        $uraian_kegiatan_lkh = trim($_POST['uraian_kegiatan_lkh']);
        $jumlah_realisasi = (int)$_POST['jumlah_realisasi'];
        $satuan_realisasi = trim($_POST['satuan_realisasi']);
        
        $stmt = $conn->prepare("UPDATE lkh SET id_rkb=?, tanggal_lkh=?, nama_kegiatan_harian=?, uraian_kegiatan_lkh=?, jumlah_realisasi=?, satuan_realisasi=? WHERE id_lkh=?");
        $stmt->bind_param("issssii", $id_rkb, $tanggal_lkh, $nama_kegiatan_harian, $uraian_kegiatan_lkh, $jumlah_realisasi, $satuan_realisasi, $id_lkh);
        
        if ($stmt->execute()) {
            $message = "Indikator LKH berhasil diperbarui.";
            $message_type = "success";
        } else {
            $message = "Error: " . $stmt->error;
            $message_type = "danger";
        }
        $stmt->close();
    }
    
    if (isset($_POST['hapus_indikator'])) {
        $id_lkh = (int)$_POST['id_lkh'];
        
        $stmt = $conn->prepare("DELETE FROM lkh WHERE id_lkh=?");
        $stmt->bind_param("i", $id_lkh);
        
        if ($stmt->execute()) {
            $message = "Indikator LKH berhasil dihapus.";
            $message_type = "success";
        } else {
            $message = "Error: " . $stmt->error;
            $message_type = "danger";
        }
        $stmt->close();
    }
    
    if (isset($_POST['update_status_periode'])) {
        $id_pegawai = (int)$_POST['id_pegawai'];
        $tahun = (int)$_POST['tahun'];
        $bulan = (int)$_POST['bulan'];
        $status_verval = $_POST['status_verval'];
        
        // Jika status adalah draft, set ke NULL di database
        if ($status_verval === 'draft') {
            $stmt = $conn->prepare("UPDATE lkh SET status_verval=NULL WHERE id_pegawai=? AND YEAR(tanggal_lkh)=? AND MONTH(tanggal_lkh)=?");
            $stmt->bind_param("iii", $id_pegawai, $tahun, $bulan);
        } else {
            // Update status untuk semua LKH pada periode tertentu
            $stmt = $conn->prepare("UPDATE lkh SET status_verval=? WHERE id_pegawai=? AND YEAR(tanggal_lkh)=? AND MONTH(tanggal_lkh)=?");
            $stmt->bind_param("siii", $status_verval, $id_pegawai, $tahun, $bulan);
        }
        
        if ($stmt->execute()) {
            $message = "Status LKH periode " . $bulan . "/" . $tahun . " berhasil diperbarui.";
            $message_type = "success";
        } else {
            $message = "Error: " . $stmt->error;
            $message_type = "danger";
        }
        $stmt->close();
    }
}

// Filter pegawai
$selected_id_pegawai = $_GET['id_pegawai'] ?? '';
$filter_tahun = $_GET['tahun'] ?? '';
$filter_bulan = $_GET['bulan'] ?? '';

// Get data pegawai untuk filter
$pegawai_query = "SELECT id_pegawai, nip, nama, jabatan, unit_kerja FROM pegawai WHERE role = 'user' ORDER BY nama";
$pegawai_result = $conn->query($pegawai_query);

// Debug: Check if query executed successfully
if (!$pegawai_result) {
    die('Error in pegawai query: ' . $conn->error);
}

// Get data LKH berdasarkan pegawai yang dipilih
$lkh_result = null;
$selected_pegawai = null;
if ($selected_id_pegawai) {
    // Get data pegawai yang dipilih
    $stmt_pegawai = $conn->prepare("SELECT * FROM pegawai WHERE id_pegawai = ?");
    $stmt_pegawai->bind_param("i", $selected_id_pegawai);
    $stmt_pegawai->execute();
    $selected_pegawai = $stmt_pegawai->get_result()->fetch_assoc();
    $stmt_pegawai->close();
    
    if ($selected_pegawai) {
        // Set default tahun dan bulan jika belum dipilih
        if (!$filter_tahun) {
            $filter_tahun = $selected_pegawai['tahun_aktif'] ?? date('Y');
        }
        if (!$filter_bulan) {
            $filter_bulan = $selected_pegawai['bulan_aktif'] ?? date('n');
        }
        
        // Get data RKB untuk pegawai ini (untuk dropdown)
        $rkb_query = $conn->prepare("SELECT id_rkb, uraian_kegiatan FROM rkb WHERE id_pegawai = ? AND tahun = ? AND bulan = ? ORDER BY uraian_kegiatan");
        $rkb_query->bind_param("iii", $selected_id_pegawai, $filter_tahun, $filter_bulan);
        $rkb_query->execute();
        $rkb_options = $rkb_query->get_result();
        $rkb_query->close();
        
        // Get data LKH untuk pegawai yang dipilih dengan filter periode
        $stmt = $conn->prepare("
            SELECT l.*, p.nama, p.jabatan, p.unit_kerja, r.uraian_kegiatan as rkb_kegiatan 
            FROM lkh l 
            JOIN pegawai p ON l.id_pegawai = p.id_pegawai 
            LEFT JOIN rkb r ON l.id_rkb = r.id_rkb
            WHERE l.id_pegawai = ? AND YEAR(l.tanggal_lkh) = ? AND MONTH(l.tanggal_lkh) = ?
            ORDER BY l.tanggal_lkh DESC, l.created_at DESC
        ");
        $stmt->bind_param("iii", $selected_id_pegawai, $filter_tahun, $filter_bulan);
        $stmt->execute();
        $lkh_result = $stmt->get_result();
        $stmt->close();
    }
}

// Get status LKH untuk periode ini
$current_status = 'draft';
if ($selected_id_pegawai) {
    $status_query = $conn->prepare("SELECT DISTINCT status_verval FROM lkh WHERE id_pegawai = ? AND YEAR(tanggal_lkh) = ? AND MONTH(tanggal_lkh) = ? LIMIT 1");
    $status_query->bind_param("iii", $selected_id_pegawai, $filter_tahun, $filter_bulan);
    $status_query->execute();
    $status_result = $status_query->get_result();
    $current_status = $status_result->num_rows > 0 ? $status_result->fetch_assoc()['status_verval'] : null;
    
    // Jika status NULL atau kosong, anggap sebagai draft
    if (empty($current_status)) {
        $current_status = 'draft';
    }
    $status_query->close();
}

include __DIR__ . '/../template/header.php';
include __DIR__ . '/../template/menu_admin.php';
include __DIR__ . '/../template/topbar.php';
?>

<div id="layoutSidenav_content">
    <main>
        <div class="container-fluid px-4">
            <h1 class="mt-4 mb-3"><i class="fas fa-calendar-day"></i> Manajemen LKH Pegawai</h1>
            <p class="lead">Kelola indikator Laporan Kegiatan Harian untuk setiap pegawai.</p>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filter Pegawai -->
            <div class="card shadow-lg mb-4 border-0">
                <div class="card-header bg-gradient-info text-white border-0">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="mb-0">
                                <i class="fas fa-user-search me-2"></i>
                                Pilih Pegawai
                            </h5>
                            <small class="opacity-75">Cari dan pilih pegawai untuk mengelola LKH</small>
                        </div>
                        <div class="col-auto">
                            <div class="avatar-group">
                                <div class="avatar avatar-sm bg-white text-info rounded-circle">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body bg-light">
                    <form method="GET" id="formPilihPegawai">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-9">
                                <label class="form-label fw-bold text-primary">
                                    <i class="fas fa-user-tie me-1"></i>
                                    Pegawai (Total: <?php echo $pegawai_result->num_rows; ?> pegawai)
                                </label>
                                
                                <!-- Search Input -->
                                <div class="position-relative mb-2">
                                    <input type="text" 
                                           id="searchPegawai" 
                                           class="form-control form-control-lg border-primary" 
                                           placeholder="🔍 Ketik untuk mencari nama, NIP, jabatan, atau unit kerja..."
                                           style="box-shadow: 0 0.125rem 0.25rem rgba(0, 123, 255, 0.075);">
                                    <div class="position-absolute top-50 end-0 translate-middle-y me-3">
                                        <i class="fas fa-search text-muted"></i>
                                    </div>
                                </div>
                                
                                <!-- Select Dropdown -->
                                <select name="id_pegawai" 
                                        class="form-select form-select-lg border-primary d-none" 
                                        id="selectPegawai" 
                                        required 
                                        style="box-shadow: 0 0.125rem 0.25rem rgba(0, 123, 255, 0.075);">
                                    <option value="">-- Pilih Pegawai --</option>
                                    <?php 
                                    // Reset pointer to beginning
                                    if ($pegawai_result->num_rows > 0) {
                                        $pegawai_result->data_seek(0);
                                        while ($pegawai = $pegawai_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $pegawai['id_pegawai']; ?>" 
                                                <?php echo ($selected_id_pegawai == $pegawai['id_pegawai']) ? 'selected' : ''; ?>
                                                data-nama="<?php echo htmlspecialchars($pegawai['nama']); ?>"
                                                data-jabatan="<?php echo htmlspecialchars($pegawai['jabatan']); ?>"
                                                data-unit="<?php echo htmlspecialchars($pegawai['unit_kerja']); ?>"
                                                data-nip="<?php echo htmlspecialchars($pegawai['nip']); ?>"
                                                data-search="<?php echo strtolower(htmlspecialchars($pegawai['nama'] . ' ' . $pegawai['nip'] . ' ' . $pegawai['jabatan'] . ' ' . $pegawai['unit_kerja'])); ?>">
                                            👤 <?php echo htmlspecialchars($pegawai['nama']); ?> 
                                            - <?php echo htmlspecialchars($pegawai['jabatan']); ?> 
                                            (<?php echo htmlspecialchars($pegawai['unit_kerja']); ?>) 
                                            - NIP: <?php echo htmlspecialchars($pegawai['nip']); ?>
                                        </option>
                                    <?php 
                                        endwhile;
                                    } else {
                                        echo '<option value="">Tidak ada pegawai ditemukan</option>';
                                    }
                                    ?>
                                </select>
                                
                                <!-- Search Results -->
                                <div id="searchResults" class="search-results d-none">
                                    <!-- Results will be populated by JavaScript -->
                                </div>
                                
                                <!-- Selected Pegawai Display -->
                                <div id="selectedPegawai" class="selected-pegawai <?php echo $selected_pegawai ? '' : 'd-none'; ?>">
                                    <?php if ($selected_pegawai): ?>
                                        <div class="card bg-success text-white border-0 shadow-sm">
                                            <div class="card-body p-3">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1">
                                                            <i class="fas fa-user-check me-2"></i>
                                                            <?php echo htmlspecialchars($selected_pegawai['nama']); ?>
                                                        </h6>
                                                        <small class="opacity-75">
                                                            <?php echo htmlspecialchars($selected_pegawai['jabatan']); ?> - 
                                                            <?php echo htmlspecialchars($selected_pegawai['unit_kerja']); ?> - 
                                                            NIP: <?php echo htmlspecialchars($selected_pegawai['nip']); ?>
                                                        </small>
                                                    </div>
                                                    <button type="button" class="btn btn-light btn-sm" onclick="clearSelection()">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" 
                                        class="btn btn-primary btn-lg w-100 shadow" 
                                        id="btnLihatLKH"
                                        <?php echo !$selected_pegawai ? 'disabled' : ''; ?>
                                        style="background: linear-gradient(45deg, #007bff, #0056b3);">
                                    <i class="fas fa-rocket me-2"></i>
                                    <span class="fw-bold">Lihat LKH</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($selected_pegawai): ?>
                <!-- Info Pegawai Terpilih -->
                <div class="card shadow-lg mb-4 border-0" id="cardInfoPegawai" style="animation: slideInUp 0.6s ease;">
                    <div class="card-header bg-gradient-success text-white border-0">
                        <div class="row align-items-center">
                            <div class="col">
                                <h5 class="mb-0">
                                    <i class="fas fa-id-card me-2"></i>
                                    Informasi Pegawai Terpilih
                                </h5>
                                <small class="opacity-75">Data lengkap pegawai yang dipilih</small>
                            </div>
                            <div class="col-auto">
                                <span class="badge bg-white text-success px-3 py-2">
                                    <i class="fas fa-check-circle me-1"></i>
                                    Aktif
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body bg-light">
                        <div class="row g-4">
                            <div class="col-md-3">
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-hashtag text-primary"></i>
                                    </div>
                                    <div class="info-content">
                                        <strong class="text-primary">NIP</strong><br>
                                        <span class="fs-6"><?php echo htmlspecialchars($selected_pegawai['nip']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-user text-success"></i>
                                    </div>
                                    <div class="info-content">
                                        <strong class="text-success">Nama</strong><br>
                                        <span class="fs-6 fw-bold"><?php echo htmlspecialchars($selected_pegawai['nama']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-briefcase text-warning"></i>
                                    </div>
                                    <div class="info-content">
                                        <strong class="text-warning">Jabatan</strong><br>
                                        <span class="fs-6"><?php echo htmlspecialchars($selected_pegawai['jabatan']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-building text-info"></i>
                                    </div>
                                    <div class="info-content">
                                        <strong class="text-info">Unit Kerja</strong><br>
                                        <span class="fs-6"><?php echo htmlspecialchars($selected_pegawai['unit_kerja']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Filter Periode -->
                        <hr class="my-4" style="border-top: 2px dashed #dee2e6;">
                        <div class="periode-section">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Pengaturan Periode LKH
                            </h6>
                            <form method="GET" id="formFilterPeriode" class="row g-3 align-items-end">
                                <input type="hidden" name="id_pegawai" value="<?php echo $selected_id_pegawai; ?>">
                                
                                <div class="col-md-2">
                                    <label class="form-label fw-bold text-primary">
                                        <i class="fas fa-calendar-year me-1"></i>
                                        Tahun
                                    </label>
                                    <select name="tahun" class="form-select border-primary" id="selectTahun">
                                        <?php for ($year = date('Y'); $year >= 2020; $year--): ?>
                                            <option value="<?php echo $year; ?>" 
                                                    <?php echo ($filter_tahun == $year) ? 'selected' : ''; ?>>
                                                📅 <?php echo $year; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <label class="form-label fw-bold text-primary">
                                        <i class="fas fa-calendar-month me-1"></i>
                                        Bulan
                                    </label>
                                    <select name="bulan" class="form-select border-primary" id="selectBulan">
                                        <?php 
                                        $bulan_names = [
                                            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                                        ];
                                        foreach ($bulan_names as $num => $name): 
                                        ?>
                                            <option value="<?php echo $num; ?>" 
                                                    <?php echo ($filter_bulan == $num) ? 'selected' : ''; ?>>
                                                🗓️ <?php echo $name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-warning w-100 shadow">
                                        <i class="fas fa-filter me-2"></i>
                                        Filter
                                    </button>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="periode-info p-3 bg-white rounded border">
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-star text-warning me-1"></i>
                                                    <strong>Periode Aktif:</strong>
                                                </small>
                                                <span class="badge bg-success">
                                                    <?php echo $bulan_names[$selected_pegawai['bulan_aktif'] ?? date('n')] . ' ' . ($selected_pegawai['tahun_aktif'] ?? date('Y')); ?>
                                                </span>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-eye text-primary me-1"></i>
                                                    <strong>Sedang Dilihat:</strong>
                                                </small>
                                                <span class="badge bg-primary">
                                                    <?php echo $bulan_names[$filter_bulan] . ' ' . $filter_tahun; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Tombol Tambah LKH -->
                <div class="mb-4">
                    <button class="btn btn-success shadow" 
                            data-bs-toggle="modal" 
                            data-bs-target="#modalTambahIndikator">
                        <i class="fas fa-plus me-2"></i>
                        Tambah LKH
                    </button>
                    <small class="text-muted ms-2">
                        Periode: <?php echo $bulan_names[$filter_bulan] . ' ' . $filter_tahun; ?>
                    </small>
                </div>

                <!-- Daftar LKH -->
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <div class="row align-items-center">
                            <div class="col">
                                <h5 class="mb-0">
                                    <i class="fas fa-calendar-day"></i> Daftar LKH - <?php echo htmlspecialchars($selected_pegawai['nama']); ?>
                                    <small class="ms-2">(<?php echo $bulan_names[$filter_bulan] . ' ' . $filter_tahun; ?>)</small>
                                </h5>
                            </div>
                            <div class="col-auto">
                                <!-- Status Periode dengan Dropdown -->
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-<?php 
                                        echo $current_status === 'disetujui' ? 'success' : 
                                            ($current_status === 'ditolak' ? 'danger' : 
                                            ($current_status === 'diajukan' ? 'warning' : 'secondary')); 
                                    ?> me-2 fs-6">
                                        <?php 
                                        $status_text = [
                                            'draft' => 'Draft',
                                            'diajukan' => 'Diajukan',
                                            'disetujui' => 'Disetujui',
                                            'ditolak' => 'Ditolak'
                                        ];
                                        echo $status_text[$current_status] ?? 'Draft';
                                        ?>
                                    </span>
                                    
                                    <?php if ($lkh_result && $lkh_result->num_rows > 0): ?>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-cog"></i> Ubah Status
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="#" onclick="updateStatusPeriode('draft')">
                                                <i class="fas fa-file-alt text-secondary me-2"></i>Draft
                                            </a></li>
                                            <li><a class="dropdown-item" href="#" onclick="updateStatusPeriode('diajukan')">
                                                <i class="fas fa-paper-plane text-warning me-2"></i>Diajukan
                                            </a></li>
                                            <li><a class="dropdown-item" href="#" onclick="updateStatusPeriode('disetujui')">
                                                <i class="fas fa-check text-success me-2"></i>Disetujui
                                            </a></li>
                                            <li><a class="dropdown-item" href="#" onclick="updateStatusPeriode('ditolak')">
                                                <i class="fas fa-times text-danger me-2"></i>Ditolak
                                            </a></li>
                                        </ul>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th width="4%">No.</th>
                                        <th width="8%">Hari</th>
                                        <th width="20%">RKB Terkait</th>
                                        <th width="18%">Nama Kegiatan</th>
                                        <th width="18%">Uraian Kegiatan</th>
                                        <th width="6%">Jumlah</th>
                                        <th width="6%">Satuan</th>
                                        <th width="10%">Lampiran</th>
                                        <th width="10%">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    if ($lkh_result && $lkh_result->num_rows > 0):
                                        while ($row = $lkh_result->fetch_assoc()):
                                    ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td class="text-center">
                                                <?php 
                                                $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                                                $tanggal = date('w', strtotime($row['tanggal_lkh']));
                                                $tgl = date('d', strtotime($row['tanggal_lkh']));
                                                echo $hari[$tanggal] . ', ' . $tgl;
                                                ?>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?php echo htmlspecialchars($row['rkb_kegiatan'] ?? 'RKB tidak ditemukan'); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['nama_kegiatan_harian']); ?></td>
                                            <td><?php echo htmlspecialchars($row['uraian_kegiatan_lkh']); ?></td>
                                            <td class="text-center"><?php echo htmlspecialchars($row['jumlah_realisasi']); ?></td>
                                            <td><?php echo htmlspecialchars($row['satuan_realisasi']); ?></td>
                                            <td class="text-center">
                                                <?php if (!empty($row['lampiran'])): ?>
                                                    <div class="btn-group-vertical" role="group">
                                                        <button type="button" class="btn btn-sm btn-info mb-1" 
                                                                onclick="viewLampiran('<?php echo htmlspecialchars($row['lampiran']); ?>', '<?php echo htmlspecialchars($row['nama_kegiatan_harian']); ?>')" 
                                                                title="Lihat Lampiran">
                                                            <i class="fas fa-eye"></i> Lihat
                                                        </button>
                                                        <a href="../uploads/lkh/<?php echo htmlspecialchars($row['lampiran']); ?>" 
                                                           class="btn btn-sm btn-success" 
                                                           download 
                                                           title="Download Lampiran">
                                                            <i class="fas fa-download"></i> Download
                                                        </a>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="fas fa-minus"></i> Tidak ada
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <!-- Action Buttons -->
                                                <div class="btn-group-vertical" role="group">
                                                    <button class="btn btn-sm btn-warning mb-1" data-bs-toggle="modal" data-bs-target="#modalEditIndikator_<?php echo $row['id_lkh']; ?>" title="Edit">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="hapusIndikator(<?php echo $row['id_lkh']; ?>)" title="Hapus">
                                                        <i class="fas fa-trash"></i> Hapus
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Modal Edit -->
                                        <div class="modal fade" id="modalEditIndikator_<?php echo $row['id_lkh']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <form method="POST">
                                                        <div class="modal-header bg-warning text-white">
                                                            <h5 class="modal-title">Edit LKH</h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="edit_indikator" value="1">
                                                            <input type="hidden" name="id_lkh" value="<?php echo $row['id_lkh']; ?>">
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">RKB Terkait *</label>
                                                                <select name="id_rkb" class="form-select" required>
                                                                    <option value="">Pilih RKB</option>
                                                                    <?php 
                                                                    $rkb_options->data_seek(0);
                                                                    while ($rkb = $rkb_options->fetch_assoc()): 
                                                                    ?>
                                                                        <option value="<?php echo $rkb['id_rkb']; ?>" 
                                                                                <?php echo ($row['id_rkb'] == $rkb['id_rkb']) ? 'selected' : ''; ?>>
                                                                            <?php echo htmlspecialchars($rkb['uraian_kegiatan']); ?>
                                                                        </option>
                                                                    <?php endwhile; ?>
                                                                </select>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Tanggal LKH *</label>
                                                                <input type="date" name="tanggal_lkh" class="form-control" required value="<?php echo $row['tanggal_lkh']; ?>">
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Nama Kegiatan Harian *</label>
                                                                <input type="text" name="nama_kegiatan_harian" class="form-control" required value="<?php echo htmlspecialchars($row['nama_kegiatan_harian']); ?>" placeholder="Contoh: Mengajar Matematika">
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Uraian Kegiatan LKH *</label>
                                                                <textarea name="uraian_kegiatan_lkh" class="form-control" rows="3" required><?php echo htmlspecialchars($row['uraian_kegiatan_lkh']); ?></textarea>
                                                            </div>
                                                            
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Jumlah Realisasi *</label>
                                                                        <input type="number" name="jumlah_realisasi" class="form-control" required value="<?php echo htmlspecialchars($row['jumlah_realisasi']); ?>">
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Satuan Realisasi *</label>
                                                                        <input type="text" name="satuan_realisasi" class="form-control" required value="<?php echo htmlspecialchars($row['satuan_realisasi']); ?>" placeholder="Contoh: Kegiatan, JP, Dokumen">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="submit" class="btn btn-warning">
                                                                <i class="fas fa-save"></i> Simpan Perubahan
                                                            </button>
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php 
                                        endwhile;
                                    else:
                                    ?>
                                        <tr>
                                            <td colspan="9" class="text-center">
                                                Belum ada data LKH untuk pegawai ini pada periode <?php echo $bulan_names[$filter_bulan] . ' ' . $filter_tahun; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Modal Tambah -->
                <div class="modal fade" id="modalTambahIndikator" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <form method="POST">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title">Tambah LKH untuk <?php echo htmlspecialchars($selected_pegawai['nama']); ?></h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="tambah_indikator" value="1">
                                    <input type="hidden" name="id_pegawai" value="<?php echo $selected_pegawai['id_pegawai']; ?>">
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> 
                                        LKH ini akan dibuat untuk periode: <strong><?php echo $bulan_names[$filter_bulan] . ' ' . $filter_tahun; ?></strong>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">RKB Terkait *</label>
                                        <select name="id_rkb" class="form-select" required>
                                            <option value="">Pilih RKB yang akan dijabarkan</option>
                                            <?php 
                                            if ($rkb_options->num_rows > 0) {
                                                $rkb_options->data_seek(0);
                                                while ($rkb = $rkb_options->fetch_assoc()): 
                                            ?>
                                                <option value="<?php echo $rkb['id_rkb']; ?>">
                                                    <?php echo htmlspecialchars($rkb['uraian_kegiatan']); ?>
                                                </option>
                                            <?php 
                                                endwhile;
                                            } else {
                                                echo '<option value="">Tidak ada RKB untuk periode ini</option>';
                                            }
                                            ?>
                                        </select>
                                        <small class="text-muted">Pilih RKB yang akan dijabarkan menjadi kegiatan harian</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Tanggal LKH *</label>
                                        <input type="date" name="tanggal_lkh" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                                        <small class="text-muted">Pilih tanggal pelaksanaan kegiatan</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Nama Kegiatan Harian *</label>
                                        <input type="text" name="nama_kegiatan_harian" class="form-control" required 
                                               placeholder="Contoh: Mengajar Matematika">
                                        <small class="text-muted">Nama singkat kegiatan yang dilakukan</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Uraian Kegiatan LKH *</label>
                                        <textarea name="uraian_kegiatan_lkh" class="form-control" rows="3" required 
                                                  placeholder="Contoh: Mengajar matematika kelas VII A jam pelajaran 1-2 dengan materi aljabar"></textarea>
                                        <small class="text-muted">Jelaskan kegiatan spesifik yang dilakukan pada hari ini</small>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Jumlah Realisasi *</label>
                                                <input type="number" name="jumlah_realisasi" class="form-control" required 
                                                       placeholder="Contoh: 2" min="1">
                                                <small class="text-muted">Jumlah/volume kegiatan yang terealisasi</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Satuan Realisasi *</label>
                                                <input type="text" name="satuan_realisasi" class="form-control" required 
                                                       placeholder="Contoh: Kegiatan">
                                                <small class="text-muted">Satuan pengukuran (Kegiatan, JP, Dokumen, dll)</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save"></i> Simpan
                                    </button>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Pesan ketika belum memilih pegawai -->
                <div class="card shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-user-friends fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">Silakan pilih pegawai untuk melihat dan mengelola data LKH</h5>
                        <p class="text-muted">Gunakan filter di atas untuk memilih pegawai yang akan dikelola data LKH-nya.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <?php include __DIR__ . '/../template/footer.php'; ?>
</div>

<!-- Custom CSS -->
<style>
@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInScale {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.bg-gradient-info {
    background: linear-gradient(45deg, #17a2b8, #138496) !important;
}

.bg-gradient-success {
    background: linear-gradient(45deg, #28a745, #20c997) !important;
}

.info-item {
    display: flex;
    align-items: center;
    padding: 15px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.info-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.info-icon {
    margin-right: 15px;
    font-size: 1.5rem;
}

.periode-section {
    animation: fadeInScale 0.8s ease 0.3s both;
}

.form-select:focus, .form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.btn:hover {
    transform: translateY(-2px);
}

.card {
    transition: all 0.3s ease;
}

.periode-info {
    transition: all 0.3s ease;
}

.periode-info:hover {
    box-shadow: 0 3px 15px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.search-results {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    background: white;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    z-index: 1000;
}

.search-result-item {
    padding: 12px 16px;
    border-bottom: 1px solid #f8f9fa;
    cursor: pointer;
    transition: all 0.2s ease;
}

.search-result-item:last-child {
    border-bottom: none;
}

.search-result-item:hover {
    background-color: #f8f9fa;
    border-left: 4px solid #007bff;
}

.search-result-item.active {
    background-color: #e3f2fd;
    border-left: 4px solid #007bff;
}

.search-highlight {
    background-color: #fff3cd;
    font-weight: bold;
    padding: 1px 3px;
    border-radius: 2px;
}

.selected-pegawai {
    animation: fadeInScale 0.5s ease;
}

.no-results {
    padding: 20px;
    text-align: center;
    color: #6c757d;
}

#searchPegawai:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}
</style>

<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.27/dist/sweetalert2.min.css">

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.27/dist/sweetalert2.all.min.js"></script>

<script>
// Loading animation untuk form submission
function showLoadingAlert(title, text) {
    Swal.fire({
        title: title,
        text: text,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        background: '#fff',
        customClass: {
            container: 'animate__animated animate__fadeIn'
        },
        didOpen: () => {
            Swal.showLoading();
        }
    });
}

// Auto submit form ketika pegawai dipilih
document.addEventListener('DOMContentLoaded', function() {
    const selectPegawai = document.getElementById('selectPegawai');
    
    if (selectPegawai) {
        selectPegawai.addEventListener('change', function() {
            if (this.value) {
                const selectedOption = this.options[this.selectedIndex];
                const nama = selectedOption.getAttribute('data-nama');
                
                showLoadingAlert(
                    '🔍 Memuat Data LKH', 
                    `Sedang mengambil data LKH untuk ${nama}...`
                );
                
                setTimeout(() => {
                    this.closest('form').submit();
                }, 1000);
            }
        });
    }

    const tahunSelect = document.getElementById('selectTahun');
    const bulanSelect = document.getElementById('selectBulan');
    
    if (tahunSelect) {
        tahunSelect.addEventListener('change', function() {
            const bulanNama = bulanSelect.options[bulanSelect.selectedIndex].text.replace('🗓️ ', '');
            const tahunNama = this.options[this.selectedIndex].text.replace('📅 ', '');
            
            Swal.fire({
                title: '📅 Mengubah Periode',
                text: `Memfilter LKH untuk periode ${bulanNama} ${tahunNama}`,
                icon: 'info',
                timer: 1500,
                showConfirmButton: false,
                background: '#fff',
                customClass: {
                    popup: 'animate__animated animate__bounceIn'
                }
            }).then(() => {
                this.closest('form').submit();
            });
        });
    }
    
    if (bulanSelect) {
        bulanSelect.addEventListener('change', function() {
            const bulanNama = this.options[this.selectedIndex].text.replace('🗓️ ', '');
            const tahunNama = tahunSelect.options[tahunSelect.selectedIndex].text.replace('📅 ', '');
            
            Swal.fire({
                title: '📅 Mengubah Periode',
                text: `Memfilter LKH untuk periode ${bulanNama} ${tahunNama}`,
                icon: 'info',
                timer: 1500,
                showConfirmButton: false,
                background: '#fff',
                customClass: {
                    popup: 'animate__animated animate__bounceIn'
                }
            }).then(() => {
                this.closest('form').submit();
            });
        });
    }
    
    // Show success/error message if exists
    <?php if ($message): ?>
        Swal.fire({
            icon: '<?php echo $message_type === "success" ? "success" : "error"; ?>',
            title: '<?php echo $message_type === "success" ? "🎉 Berhasil!" : "❌ Error!"; ?>',
            text: '<?php echo addslashes($message); ?>',
            timer: 3000,
            showConfirmButton: false,
            background: '#fff',
            customClass: {
                popup: 'animate__animated animate__bounceIn'
            }
        });
    <?php endif; ?>
    
    // Show welcome message when pegawai selected
    <?php if ($selected_pegawai && !$message): ?>
        Swal.fire({
            title: 'Menampilkan LKH Pegawai',
            html: `
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-user-circle fa-4x text-success"></i>
                    </div>
                    <h5 class="text-primary"><?php echo htmlspecialchars($selected_pegawai['nama']); ?></h5>
                    <p class="text-muted"><?php echo htmlspecialchars($selected_pegawai['jabatan']); ?></p>
                    <div class="badge bg-primary">
                        Periode: <?php echo $bulan_names[$filter_bulan] . ' ' . $filter_tahun; ?>
                    </div>
                </div>
            `,
            icon: 'success',
            timer: 2500,
            showConfirmButton: false,
            background: '#fff',
            customClass: {
                popup: 'animate__animated animate__bounceIn'
            }
        });
    <?php endif; ?>
    
    // Initialize search functionality
    initializeSearch();
});

// Search functionality
function initializeSearch() {
    const searchInput = document.getElementById('searchPegawai');
    const searchResults = document.getElementById('searchResults');
    const selectPegawai = document.getElementById('selectPegawai');
    const selectedPegawaiDiv = document.getElementById('selectedPegawai');
    const btnLihatLKH = document.getElementById('btnLihatLKH');
    
    let selectedPegawaiData = <?php echo $selected_pegawai ? json_encode($selected_pegawai) : 'null'; ?>;
    
    // Initialize search display
    if (!selectedPegawaiData) {
        searchInput.classList.remove('d-none');
        selectPegawai.classList.add('d-none');
        selectedPegawaiDiv.classList.add('d-none');
    } else {
        searchInput.classList.add('d-none');
        selectPegawai.classList.remove('d-none');
        selectedPegawaiDiv.classList.remove('d-none');
    }
    
    // Search input event
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            
            if (query.length < 2) {
                searchResults.classList.add('d-none');
                return;
            }
            
            const options = selectPegawai.querySelectorAll('option[data-search]');
            const results = [];
            
            options.forEach(option => {
                const searchText = option.getAttribute('data-search');
                if (searchText && searchText.includes(query)) {
                    results.push({
                        id: option.value,
                        nama: option.getAttribute('data-nama'),
                        jabatan: option.getAttribute('data-jabatan'),
                        unit: option.getAttribute('data-unit'),
                        nip: option.getAttribute('data-nip'),
                        text: option.textContent,
                        searchText: searchText
                    });
                }
            });
            
            displaySearchResults(results, query);
        });
        
        // Keyboard navigation for search results
        let currentResultIndex = -1;
        
        searchInput.addEventListener('keydown', function(e) {
            const items = searchResults.querySelectorAll('.search-result-item');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                currentResultIndex = Math.min(currentResultIndex + 1, items.length - 1);
                updateActiveResult(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                currentResultIndex = Math.max(currentResultIndex - 1, -1);
                updateActiveResult(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (currentResultIndex >= 0 && items[currentResultIndex]) {
                    selectPegawaiFromSearch(items[currentResultIndex].getAttribute('data-id'));
                }
            } else if (e.key === 'Escape') {
                searchResults.classList.add('d-none');
                currentResultIndex = -1;
            }
        });
        
        // Update active result highlighting
        function updateActiveResult(items) {
            items.forEach((item, index) => {
                if (index === currentResultIndex) {
                    item.classList.add('active');
                    item.scrollIntoView({ block: 'nearest' });
                } else {
                    item.classList.remove('active');
                }
            });
        }
    }
    
    // Display search results
    function displaySearchResults(results, query) {
        let html = '';
        
        if (results.length === 0) {
            html = '<div class="no-results"><i class="fas fa-search text-muted mb-2"></i><br>Tidak ada pegawai yang ditemukan</div>';
        } else {
            results.forEach((result, index) => {
                html += `
                    <div class="search-result-item" data-id="${result.id}" data-index="${index}">
                        <div class="fw-bold text-primary">${highlightSearchTerm(result.nama, query)}</div>
                        <small class="text-muted">
                            ${highlightSearchTerm(result.jabatan, query)} - 
                            ${highlightSearchTerm(result.unit, query)} - 
                            NIP: ${highlightSearchTerm(result.nip, query)}
                        </small>
                    </div>
                `;
            });
        }
        
        searchResults.innerHTML = html;
        searchResults.classList.remove('d-none');
        
        // Add click events to result items
        searchResults.querySelectorAll('.search-result-item').forEach(item => {
            item.addEventListener('click', function() {
                selectPegawaiFromSearch(this.getAttribute('data-id'));
            });
        });
    }
    
    // Highlight search terms
    function highlightSearchTerm(text, term) {
        if (!term) return text;
        const regex = new RegExp(`(${escapeRegExp(term)})`, 'gi');
        return text.replace(regex, '<span class="search-highlight">$1</span>');
    }
    
    // Escape regex special characters
    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    // Select pegawai from search
    function selectPegawaiFromSearch(id) {
        const option = selectPegawai.querySelector(`option[value="${id}"]`);
        if (option) {
            selectPegawai.value = id;
            
            // Show success message and submit
            const pegawaiData = {
                nama: option.getAttribute('data-nama'),
                jabatan: option.getAttribute('data-jabatan'),
                unit: option.getAttribute('data-unit'),
                nip: option.getAttribute('data-nip')
            };
            
            Swal.fire({
                title: '✅ Pegawai Dipilih!',
                html: `
                    <div class="text-center">
                        <i class="fas fa-user-check fa-3x text-success mb-3"></i>
                        <h5>${pegawaiData.nama}</h5>
                        <p class="text-muted">${pegawaiData.jabatan}</p>
                    </div>
                `,
                timer: 1500,
                showConfirmButton: false,
                customClass: {
                    popup: 'animate__animated animate__bounceIn'
                }
            }).then(() => {
                // Submit the form
                selectPegawai.closest('form').submit();
            });
        }
    }
    
    // Hide results when clicking outside
    document.addEventListener('click', function(e) {
        if (searchInput && searchResults && !searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.classList.add('d-none');
        }
    });
}

// Clear selection function
function clearSelection() {
    // Navigate to clean URL
    window.location.href = window.location.pathname;
}

// Function untuk approve LKH dengan SweetAlert
function approveLKH(idLkh) {
    Swal.fire({
        title: '✅ Setujui LKH?',
        html: `
            <div class="text-center">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <p>LKH akan <strong>disetujui</strong> dan tidak dapat diubah lagi.</p>
                <p class="text-muted">Apakah Anda yakin ingin menyetujui LKH ini?</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '✅ Ya, Setujui!',
        cancelButtonText: '❌ Batal',
        reverseButtons: true,
        customClass: {
            popup: 'animate__animated animate__bounceIn'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            showLoadingAlert('✅ Menyetujui LKH', 'Sedang memproses persetujuan...');
            
            setTimeout(() => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const inputAction = document.createElement('input');
                inputAction.type = 'hidden';
                inputAction.name = 'approve_lkh';
                inputAction.value = '1';
                
                const inputId = document.createElement('input');
                inputId.type = 'hidden';
                inputId.name = 'id_lkh';
                inputId.value = idLkh;
                
                form.appendChild(inputAction);
                form.appendChild(inputId);
                
                document.body.appendChild(form);
                form.submit();
            }, 1000);
        }
    });
}

// Function untuk reject LKH dengan SweetAlert
function rejectLKH(idLkh) {
    Swal.fire({
        title: '❌ Tolak LKH?',
        html: `
            <div class="text-center">
                <i class="fas fa-times-circle fa-3x text-danger mb-3"></i>
                <p>LKH akan <strong>ditolak</strong> dan perlu diperbaiki.</p>
                <p class="text-muted">Berikan alasan penolakan:</p>
            </div>
        `,
        input: 'textarea',
        inputPlaceholder: 'Masukkan alasan penolakan...',
        inputAttributes: {
            'aria-label': 'Alasan penolakan',
            'style': 'height: 100px;'
        },
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '❌ Ya, Tolak!',
        cancelButtonText: '🔙 Batal',
        reverseButtons: true,
        customClass: {
            popup: 'animate__animated animate__shakeX'
        },
        preConfirm: (reason) => {
            if (!reason || reason.trim() === '') {
                Swal.showValidationMessage('Alasan penolakan wajib diisi!');
                return false;
            }
            return reason;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            showLoadingAlert('❌ Menolak LKH', 'Sedang memproses penolakan...');
            
            setTimeout(() => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const inputAction = document.createElement('input');
                inputAction.type = 'hidden';
                inputAction.name = 'reject_lkh';
                inputAction.value = '1';
                
                const inputId = document.createElement('input');
                inputId.type = 'hidden';
                inputId.name = 'id_lkh';
                inputId.value = idLkh;
                
                const inputAlasan = document.createElement('input');
                inputAlasan.type = 'hidden';
                inputAlasan.name = 'alasan_tolak';
                inputAlasan.value = result.value;
                
                form.appendChild(inputAction);
                form.appendChild(inputId);
                form.appendChild(inputAlasan);
                
                document.body.appendChild(form);
                form.submit();
            }, 1000);
        }
    });
}

// Function untuk hapus LKH dengan SweetAlert
function hapusIndikator(idLkh) {
    Swal.fire({
        title: '🗑️ Hapus LKH?',
        html: `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                <p>Data yang dihapus <strong>tidak dapat dikembalikan!</strong></p>
                <p class="text-muted">Apakah Anda yakin ingin menghapus LKH ini?</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '🗑️ Ya, Hapus!',
        cancelButtonText: '❌ Batal',
        reverseButtons: true,
        customClass: {
            popup: 'animate__animated animate__shakeX'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            showLoadingAlert('🗑️ Menghapus Data', 'Sedang menghapus LKH...');
            
            setTimeout(() => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const inputAction = document.createElement('input');
                inputAction.type = 'hidden';
                inputAction.name = 'hapus_indikator';
                inputAction.value = '1';
                
                const inputId = document.createElement('input');
                inputId.type = 'hidden';
                inputId.name = 'id_lkh';
                inputId.value = idLkh;
                
                form.appendChild(inputAction);
                form.appendChild(inputId);
                
                document.body.appendChild(form);
                form.submit();
            }, 1000);
        }
    });
}

// Function untuk melihat lampiran
function viewLampiran(filename, namaKegiatan) {
    const fileExtension = filename.toLowerCase().split('.').pop();
    const imageMimeTypes = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
    const pdfMimeTypes = ['pdf'];
    
    let content = '';
    
    if (imageMimeTypes.includes(fileExtension)) {
        content = `
            <div class="text-center">
                <img src="../uploads/lkh/${filename}" 
                     class="img-fluid rounded shadow" 
                     style="max-height: 500px; max-width: 100%;" 
                     alt="Lampiran ${namaKegiatan}">
                <p class="mt-3 text-muted">
                    <i class="fas fa-file-image me-2"></i>
                    ${filename}
                </p>
            </div>
        `;
    } else if (pdfMimeTypes.includes(fileExtension)) {
        content = `
            <div class="text-center">
                <iframe src="../uploads/lkh/${filename}" 
                        width="100%" 
                        height="500" 
                        style="border: none; border-radius: 8px;">
                </iframe>
                <p class="mt-3 text-muted">
                    <i class="fas fa-file-pdf me-2"></i>
                    ${filename}
                </p>
            </div>
        `;
    } else {
        content = `
            <div class="text-center">
                <i class="fas fa-file fa-5x text-muted mb-3"></i>
                <h5>Preview tidak tersedia</h5>
                <p class="text-muted">File: ${filename}</p>
                <a href="../uploads/lkh/${filename}" 
                   class="btn btn-primary" 
                   download>
                    <i class="fas fa-download me-2"></i>Download File
                </a>
            </div>
        `;
    }
    
    Swal.fire({
        title: `📎 Lampiran: ${namaKegiatan}`,
        html: content,
        width: '80%',
        showCloseButton: true,
        showConfirmButton: false,
        customClass: {
            popup: 'animate__animated animate__zoomIn'
        },
        footer: `
            <div class="d-flex justify-content-between align-items-center w-100">
                <small class="text-muted">Klik di luar untuk menutup</small>
                <a href="../uploads/lkh/${filename}" 
                   class="btn btn-sm btn-success" 
                   download>
                    <i class="fas fa-download me-1"></i>Download
                </a>
            </div>
        `
    });
}

// Function untuk update status periode
function updateStatusPeriode(status) {
    const statusLabels = {
        'draft': 'Draft',
        'diajukan': 'Diajukan',
        'disetujui': 'Disetujui',
        'ditolak': 'Ditolak'
    };
    
    const statusColors = {
        'draft': 'secondary',
        'diajukan': 'warning',
        'disetujui': 'success',
        'ditolak': 'danger'
    };
    
    const statusIcons = {
        'draft': 'file-alt',
        'diajukan': 'paper-plane',
        'disetujui': 'check-circle',
        'ditolak': 'times-circle'
    };
    
    Swal.fire({
        title: `📋 Ubah Status LKH`,
        html: `
            <div class="text-center">
                <i class="fas fa-${statusIcons[status]} fa-3x text-${statusColors[status]} mb-3"></i>
                <p>Status LKH periode ini akan diubah menjadi:</p>
                <span class="badge bg-${statusColors[status]} fs-5 px-3 py-2">
                    ${statusLabels[status]}
                </span>
                <p class="text-muted mt-3">
                    Periode: <?php echo $bulan_names[$filter_bulan] . ' ' . $filter_tahun; ?>
                </p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: status === 'ditolak' ? '#dc3545' : '#007bff',
        cancelButtonColor: '#6c757d',
        confirmButtonText: `✅ Ya, Ubah ke ${statusLabels[status]}!`,
        cancelButtonText: '❌ Batal',
        reverseButtons: true,
        customClass: {
            popup: 'animate__animated animate__bounceIn'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            showLoadingAlert('🔄 Mengubah Status', `Sedang mengubah status ke ${statusLabels[status]}...`);
            
            setTimeout(() => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const inputAction = document.createElement('input');
                inputAction.type = 'hidden';
                inputAction.name = 'update_status_periode';
                inputAction.value = '1';
                
                const inputIdPegawai = document.createElement('input');
                inputIdPegawai.type = 'hidden';
                inputIdPegawai.name = 'id_pegawai';
                inputIdPegawai.value = '<?php echo $selected_id_pegawai; ?>';
                
                const inputTahun = document.createElement('input');
                inputTahun.type = 'hidden';
                inputTahun.name = 'tahun';
                inputTahun.value = '<?php echo $filter_tahun; ?>';
                
                const inputBulan = document.createElement('input');
                inputBulan.type = 'hidden';
                inputBulan.name = 'bulan';
                inputBulan.value = '<?php echo $filter_bulan; ?>';
                
                const inputStatus = document.createElement('input');
                inputStatus.type = 'hidden';
                inputStatus.name = 'status_verval';
                inputStatus.value = status;
                
                form.appendChild(inputAction);
                form.appendChild(inputIdPegawai);
                form.appendChild(inputTahun);
                form.appendChild(inputBulan);
                form.appendChild(inputStatus);
                
                document.body.appendChild(form);
                form.submit();
            }, 1000);
        }
    });
}
</script>
