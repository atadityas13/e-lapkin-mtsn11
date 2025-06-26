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

$page_title = "Indikator RHK";
$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tambah_indikator'])) {
        $id_pegawai = (int)$_POST['id_pegawai'];
        $nama_rhk = trim($_POST['nama_rhk']);
        $aspek = $_POST['aspek'];
        $target = trim($_POST['target']);
        $tahun = (int)$_POST['tahun'];
        $bulan = (int)$_POST['bulan'];
        
        $stmt = $conn->prepare("INSERT INTO rhk (id_pegawai, nama_rhk, aspek, target, tahun, bulan) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssii", $id_pegawai, $nama_rhk, $aspek, $target, $tahun, $bulan);
        
        if ($stmt->execute()) {
            $message = "Indikator RHK berhasil ditambahkan.";
            $message_type = "success";
        } else {
            $message = "Error: " . $stmt->error;
            $message_type = "danger";
        }
        $stmt->close();
    }
    
    if (isset($_POST['edit_indikator'])) {
        $id_rhk = (int)$_POST['id_rhk'];
        $nama_rhk = trim($_POST['nama_rhk']);
        $aspek = $_POST['aspek'];
        $target = trim($_POST['target']);
        
        $stmt = $conn->prepare("UPDATE rhk SET nama_rhk=?, aspek=?, target=? WHERE id_rhk=?");
        $stmt->bind_param("sssi", $nama_rhk, $aspek, $target, $id_rhk);
        
        if ($stmt->execute()) {
            $message = "Indikator RHK berhasil diperbarui.";
            $message_type = "success";
        } else {
            $message = "Error: " . $stmt->error;
            $message_type = "danger";
        }
        $stmt->close();
    }
    
    if (isset($_POST['hapus_indikator'])) {
        $id_rhk = (int)$_POST['id_rhk'];
        
        $stmt = $conn->prepare("DELETE FROM rhk WHERE id_rhk=?");
        $stmt->bind_param("i", $id_rhk);
        
        if ($stmt->execute()) {
            $message = "Indikator RHK berhasil dihapus.";
            $message_type = "success";
        } else {
            $message = "Error: " . $stmt->error;
            $message_type = "danger";
        }
        $stmt->close();
    }
    
    if (isset($_POST['update_periode'])) {
        $id_rhk = (int)$_POST['id_rhk'];
        $tahun = (int)$_POST['tahun'];
        $bulan = (int)$_POST['bulan'];
        
        $stmt = $conn->prepare("UPDATE rhk SET tahun=?, bulan=? WHERE id_rhk=?");
        $stmt->bind_param("iii", $tahun, $bulan, $id_rhk);
        
        if ($stmt->execute()) {
            $message = "Periode RHK berhasil diperbarui.";
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

// Get data RHK berdasarkan pegawai yang dipilih
$rhk_result = null;
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
        
        // Cek apakah kolom tahun dan bulan ada di tabel rhk
        $check_columns = $conn->query("SHOW COLUMNS FROM rhk LIKE 'tahun'");
        $has_tahun_column = $check_columns->num_rows > 0;
        
        $check_columns = $conn->query("SHOW COLUMNS FROM rhk LIKE 'bulan'");
        $has_bulan_column = $check_columns->num_rows > 0;
        
        // Jika kolom tahun dan bulan belum ada, tambahkan
        if (!$has_tahun_column) {
            $conn->query("ALTER TABLE rhk ADD COLUMN tahun INT DEFAULT NULL AFTER target");
        }
        if (!$has_bulan_column) {
            $conn->query("ALTER TABLE rhk ADD COLUMN bulan INT DEFAULT NULL AFTER tahun");
        }
        
        // Get data RHK untuk pegawai yang dipilih dengan filter periode
        if ($has_tahun_column && $has_bulan_column) {
            // Query dengan filter periode, termasuk data lama yang NULL
            $stmt = $conn->prepare("
                SELECT r.*, p.nama, p.jabatan, p.unit_kerja 
                FROM rhk r 
                JOIN pegawai p ON r.id_pegawai = p.id_pegawai 
                WHERE r.id_pegawai = ? AND (
                    (r.tahun = ? AND r.bulan = ?) OR 
                    (r.tahun IS NULL AND r.bulan IS NULL)
                )
                ORDER BY r.created_at DESC
            ");
            $stmt->bind_param("iii", $selected_id_pegawai, $filter_tahun, $filter_bulan);
        } else {
            // Fallback jika kolom belum ada
            $stmt = $conn->prepare("
                SELECT r.*, p.nama, p.jabatan, p.unit_kerja 
                FROM rhk r 
                JOIN pegawai p ON r.id_pegawai = p.id_pegawai 
                WHERE r.id_pegawai = ? 
                ORDER BY r.created_at DESC
            ");
            $stmt->bind_param("i", $selected_id_pegawai);
        }
        $stmt->execute();
        $rhk_result = $stmt->get_result();
        $stmt->close();
    }
}

// Get unique unit kerja untuk filter
$unit_kerja_list = $conn->query("SELECT DISTINCT unit_kerja FROM pegawai WHERE role = 'user' ORDER BY unit_kerja");

include __DIR__ . '/../template/header.php';
include __DIR__ . '/../template/menu_admin.php';
include __DIR__ . '/../template/topbar.php';
?>

<div id="layoutSidenav_content">
    <main>
        <div class="container-fluid px-4">
            <h1 class="mt-4 mb-3"><i class="fas fa-tasks"></i> Manajemen RHK Pegawai</h1>
            <p class="lead">Kelola indikator Rencana Hasil Kerja untuk setiap pegawai.</p>

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
                            <small class="opacity-75">Cari dan pilih pegawai untuk mengelola RHK</small>
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
                                    Pegawai
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
                                    <?php endwhile; ?>
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
                                        id="btnLihatRHK"
                                        <?php echo !$selected_pegawai ? 'disabled' : ''; ?>
                                        style="background: linear-gradient(45deg, #007bff, #0056b3);">
                                    <i class="fas fa-rocket me-2"></i>
                                    <span class="fw-bold">Lihat RHK</span>
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
                                Pengaturan Periode RHK
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

                <!-- Tombol Tambah RHK -->
                <div class="mb-4">
                    <button class="btn btn-success shadow" 
                            data-bs-toggle="modal" 
                            data-bs-target="#modalTambahIndikator">
                        <i class="fas fa-plus me-2"></i>
                        Tambah RHK
                    </button>
                    <small class="text-muted ms-2">
                        Periode: <?php echo $bulan_names[$filter_bulan] . ' ' . $filter_tahun; ?>
                    </small>
                </div>

                <!-- Daftar RHK -->
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i> Daftar RHK - <?php echo htmlspecialchars($selected_pegawai['nama']); ?>
                            <small class="ms-2">(<?php echo $bulan_names[$filter_bulan] . ' ' . $filter_tahun; ?>)</small>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Nama RHK</th>
                                        <th>Aspek</th>
                                        <th>Target</th>
                                        <th>Tanggal Dibuat</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    if ($rhk_result && $rhk_result->num_rows > 0):
                                        while ($row = $rhk_result->fetch_assoc()):
                                    ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo htmlspecialchars($row['nama_rhk']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $row['aspek'] === 'Kuantitas' ? 'primary' : 
                                                        ($row['aspek'] === 'Kualitas' ? 'success' : 
                                                        ($row['aspek'] === 'Waktu' ? 'warning' : 'info')); 
                                                ?>">
                                                    <?php echo htmlspecialchars($row['aspek']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['target']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                                            <td>
                                                <?php if (is_null($row['tahun']) || is_null($row['bulan'])): ?>
                                                    <button class="btn btn-sm btn-info mb-1" onclick="updatePeriode(<?php echo $row['id_rhk']; ?>, <?php echo $filter_tahun; ?>, <?php echo $filter_bulan; ?>)">
                                                        <i class="fas fa-calendar-plus"></i> Set Periode
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-warning mb-1" data-bs-toggle="modal" data-bs-target="#modalEditIndikator_<?php echo $row['id_rhk']; ?>">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger mb-1" onclick="hapusIndikator(<?php echo $row['id_rhk']; ?>)">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
                                            </td>
                                        </tr>

                                        <!-- Modal Edit -->
                                        <div class="modal fade" id="modalEditIndikator_<?php echo $row['id_rhk']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <form method="POST">
                                                        <div class="modal-header bg-warning text-white">
                                                            <h5 class="modal-title">Edit RHK</h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="edit_indikator" value="1">
                                                            <input type="hidden" name="id_rhk" value="<?php echo $row['id_rhk']; ?>">
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Nama RHK *</label>
                                                                <input type="text" name="nama_rhk" class="form-control" required value="<?php echo htmlspecialchars($row['nama_rhk']); ?>">
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Aspek *</label>
                                                                <select name="aspek" class="form-select" required>
                                                                    <option value="Kuantitas" <?php echo ($row['aspek'] === 'Kuantitas') ? 'selected' : ''; ?>>Kuantitas</option>
                                                                    <option value="Kualitas" <?php echo ($row['aspek'] === 'Kualitas') ? 'selected' : ''; ?>>Kualitas</option>
                                                                    <option value="Waktu" <?php echo ($row['aspek'] === 'Waktu') ? 'selected' : ''; ?>>Waktu</option>
                                                                    <option value="Biaya" <?php echo ($row['aspek'] === 'Biaya') ? 'selected' : ''; ?>>Biaya</option>
                                                                </select>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Target *</label>
                                                                <textarea name="target" class="form-control" rows="3" required><?php echo htmlspecialchars($row['target']); ?></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="submit" class="btn btn-warning" onclick="return confirmEdit()">
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
                                            <td colspan="6" class="text-center">Belum ada data RHK untuk pegawai ini</td>
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
                                    <h5 class="modal-title">Tambah RHK untuk <?php echo htmlspecialchars($selected_pegawai['nama']); ?></h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="tambah_indikator" value="1">
                                    <input type="hidden" name="id_pegawai" value="<?php echo $selected_pegawai['id_pegawai']; ?>">
                                    <input type="hidden" name="tahun" value="<?php echo $filter_tahun; ?>">
                                    <input type="hidden" name="bulan" value="<?php echo $filter_bulan; ?>">
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> 
                                        RHK ini akan dibuat untuk periode: <strong><?php echo $bulan_names[$filter_bulan] . ' ' . $filter_tahun; ?></strong>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Nama RHK *</label>
                                        <input type="text" name="nama_rhk" class="form-control" required 
                                               placeholder="Contoh: Melaksanakan kegiatan pembelajaran dengan efektif">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Aspek *</label>
                                        <select name="aspek" class="form-select" required>
                                            <option value="">Pilih Aspek</option>
                                            <option value="Kuantitas">Kuantitas</option>
                                            <option value="Kualitas">Kualitas</option>
                                            <option value="Waktu">Waktu</option>
                                            <option value="Biaya">Biaya</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Target *</label>
                                        <textarea name="target" class="form-control" rows="3" required 
                                                  placeholder="Contoh: Mencapai 100% kehadiran dalam mengajar, menyelesaikan tugas tepat waktu, dll."></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-success" onclick="return confirmTambah()">
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
                        <h5 class="text-muted">Silakan pilih pegawai untuk melihat dan mengelola data RHK</h5>
                        <p class="text-muted">Gunakan filter di atas untuk memilih pegawai yang akan dikelola data RHK-nya.</p>
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
document.getElementById('selectPegawai').addEventListener('change', function() {
    if (this.value) {
        const selectedOption = this.options[this.selectedIndex];
        const nama = selectedOption.getAttribute('data-nama');
        
        showLoadingAlert(
            '🔍 Memuat Data RHK', 
            `Sedang mengambil data RHK untuk ${nama}...`
        );
        
        setTimeout(() => {
            this.closest('form').submit();
        }, 1000);
    }
});

// Auto submit form ketika periode berubah
document.addEventListener('DOMContentLoaded', function() {
    const tahunSelect = document.getElementById('selectTahun');
    const bulanSelect = document.getElementById('selectBulan');
    
    if (tahunSelect) {
        tahunSelect.addEventListener('change', function() {
            const bulanNama = bulanSelect.options[bulanSelect.selectedIndex].text.replace('🗓️ ', '');
            const tahunNama = this.options[this.selectedIndex].text.replace('📅 ', '');
            
            Swal.fire({
                title: '📅 Mengubah Periode',
                text: `Memfilter RHK untuk periode ${bulanNama} ${tahunNama}`,
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
                text: `Memfilter RHK untuk periode ${bulanNama} ${tahunNama}`,
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
            title: 'Menampilkan RHK Pegawai',
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
});

// Function untuk confirm tambah RHK
function confirmTambah() {
    return true;
}

// Function untuk confirm edit RHK
function confirmEdit() {
    return true;
}

// Function untuk hapus indikator dengan SweetAlert
function hapusIndikator(idRhk) {
    Swal.fire({
        title: '🗑️ Hapus Indikator RHK?',
        html: `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                <p>Data yang dihapus <strong>tidak dapat dikembalikan!</strong></p>
                <p class="text-muted">Apakah Anda yakin ingin menghapus indikator ini?</p>
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
            showLoadingAlert('🗑️ Menghapus Data', 'Sedang menghapus indikator RHK...');
            
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
                inputId.name = 'id_rhk';
                inputId.value = idRhk;
                
                form.appendChild(inputAction);
                form.appendChild(inputId);
                
                document.body.appendChild(form);
                form.submit();
            }, 1000);
        }
    });
}

// Function untuk update periode RHK lama dengan SweetAlert
function updatePeriode(idRhk, tahun, bulan) {
    Swal.fire({
        title: '📅 Set Periode RHK',
        html: `
            <div class="text-center">
                <i class="fas fa-calendar-plus fa-3x text-info mb-3"></i>
                <p>Atur periode RHK ini ke:</p>
                <div class="badge bg-primary fs-6">${tahun} - Bulan ${bulan}</div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#17a2b8',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '📅 Ya, Set Periode!',
        cancelButtonText: '❌ Batal',
        reverseButtons: true,
        customClass: {
            popup: 'animate__animated animate__bounceIn'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            showLoadingAlert('📅 Mengatur Periode', 'Sedang mengupdate periode RHK...');
            
            setTimeout(() => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const inputAction = document.createElement('input');
                inputAction.type = 'hidden';
                inputAction.name = 'update_periode';
                inputAction.value = '1';
                
                const inputId = document.createElement('input');
                inputId.type = 'hidden';
                inputId.name = 'id_rhk';
                inputId.value = idRhk;
                
                const inputTahun = document.createElement('input');
                inputTahun.type = 'hidden';
                inputTahun.name = 'tahun';
                inputTahun.value = tahun;
                
                const inputBulan = document.createElement('input');
                inputBulan.type = 'hidden';
                inputBulan.name = 'bulan';
                inputBulan.value = bulan;
                
                form.appendChild(inputAction);
                form.appendChild(inputId);
                form.appendChild(inputTahun);
                form.appendChild(inputBulan);
                
                document.body.appendChild(form);
                form.submit();
            }, 1000);
        }
    });
}

// Search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchPegawai');
    const searchResults = document.getElementById('searchResults');
    const selectPegawai = document.getElementById('selectPegawai');
    const selectedPegawaiDiv = document.getElementById('selectedPegawai');
    const btnLihatRHK = document.getElementById('btnLihatRHK');
    
    let selectedPegawaiData = <?php echo $selected_pegawai ? json_encode($selected_pegawai) : 'null'; ?>;
    
    // Initialize search
    if (!selectedPegawaiData) {
        searchInput.classList.remove('d-none');
        selectedPegawaiDiv.classList.add('d-none');
    } else {
        searchInput.classList.add('d-none');
        selectedPegawaiDiv.classList.remove('d-none');
    }
    
    // Search input event
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
            if (searchText.includes(query)) {
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
    
    // Display search results
    function displaySearchResults(results, query) {
        let html = '';
        
        if (results.length === 0) {
            html = '<div class="no-results"><i class="fas fa-search text-muted mb-2"></i><br>Tidak ada pegawai yang ditemukan</div>';
        } else {
            results.forEach((result, index) => {
                const highlightedText = highlightSearchTerm(result.text, query);
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
            
            // Update selected pegawai display
            const pegawaiData = {
                nama: option.getAttribute('data-nama'),
                jabatan: option.getAttribute('data-jabatan'),
                unit: option.getAttribute('data-unit'),
                nip: option.getAttribute('data-nip')
            };
            
            displaySelectedPegawai(pegawaiData);
            
            // Hide search and show selected
            searchInput.classList.add('d-none');
            searchResults.classList.add('d-none');
            selectedPegawaiDiv.classList.remove('d-none');
            btnLihatRHK.disabled = false;
            
            // Show success message
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
            });
        }
    }
    
    // Display selected pegawai
    function displaySelectedPegawai(pegawaiData) {
        selectedPegawaiDiv.innerHTML = `
            <div class="card bg-success text-white border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">
                                <i class="fas fa-user-check me-2"></i>
                                ${pegawaiData.nama}
                            </h6>
                            <small class="opacity-75">
                                ${pegawaiData.jabatan} - ${pegawaiData.unit} - NIP: ${pegawaiData.nip}
                            </small>
                        </div>
                        <button type="button" class="btn btn-light btn-sm" onclick="clearSelection()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }
    
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
    
    // Hide results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.classList.add('d-none');
            currentResultIndex = -1;
        }
    });
});

// Clear selection function
function clearSelection() {
    const searchInput = document.getElementById('searchPegawai');
    const searchResults = document.getElementById('searchResults');
    const selectPegawai = document.getElementById('selectPegawai');
    const selectedPegawaiDiv = document.getElementById('selectedPegawai');
    const btnLihatRHK = document.getElementById('btnLihatRHK');
    
    // Reset form
    selectPegawai.value = '';
    searchInput.value = '';
    
    // Show search, hide selected
    searchInput.classList.remove('d-none');
    searchResults.classList.add('d-none');
    selectedPegawaiDiv.classList.add('d-none');
    btnLihatRHK.disabled = true;
    
    // Focus on search input
    searchInput.focus();
    
    // Navigate to clean URL
    window.location.href = window.location.pathname;
}
</script>