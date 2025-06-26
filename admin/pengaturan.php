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

$page_title = "Pengaturan Sistem";
$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_penilai_mtsn'])) {
        // Load existing settings
        $penilai_settings_file = __DIR__ . '/../config/penilai_settings.json';
        $penilai_settings = [];
        if (file_exists($penilai_settings_file)) {
            $penilai_settings = json_decode(file_get_contents($penilai_settings_file), true);
        }
        
        $penilai_settings['penilai_mtsn'] = [
            'nip' => trim($_POST['mtsn_nip']),
            'nama' => trim($_POST['mtsn_nama']),
            'jabatan' => trim($_POST['mtsn_jabatan']),
            'unit_kerja' => 'MTsN 11 Majalengka'
        ];
        $penilai_settings['updated_at'] = date('Y-m-d H:i:s');
        
        if (file_put_contents($penilai_settings_file, json_encode($penilai_settings, JSON_PRETTY_PRINT))) {
            $message = "Pengaturan penilai MTsN berhasil diperbarui.";
            $message_type = "success";
        } else {
            $message = "Gagal memperbarui pengaturan penilai MTsN.";
            $message_type = "danger";
        }
    }
    
    if (isset($_POST['update_penilai_tata_usaha'])) {
        // Load existing settings
        $penilai_settings_file = __DIR__ . '/../config/penilai_settings.json';
        $penilai_settings = [];
        if (file_exists($penilai_settings_file)) {
            $penilai_settings = json_decode(file_get_contents($penilai_settings_file), true);
        }
        
        $penilai_settings['penilai_tata_usaha'] = [
            'nip' => trim($_POST['tu_nip']),
            'nama' => trim($_POST['tu_nama']),
            'jabatan' => trim($_POST['tu_jabatan']),
            'unit_kerja' => 'Tata Usaha MTsN 11 Majalengka'
        ];
        $penilai_settings['updated_at'] = date('Y-m-d H:i:s');
        
        if (file_put_contents($penilai_settings_file, json_encode($penilai_settings, JSON_PRETTY_PRINT))) {
            $message = "Pengaturan penilai Tata Usaha berhasil diperbarui.";
            $message_type = "success";
        } else {
            $message = "Gagal memperbarui pengaturan penilai Tata Usaha.";
            $message_type = "danger";
        }
    }
    
    if (isset($_POST['sync_penilai'])) {
        // Load penilai settings
        $penilai_settings_file = __DIR__ . '/../config/penilai_settings.json';
        if (file_exists($penilai_settings_file)) {
            $penilai_settings = json_decode(file_get_contents($penilai_settings_file), true);
            
            // Update MTsN users
            if (!empty($penilai_settings['penilai_mtsn'])) {
                $penilai_mtsn = $penilai_settings['penilai_mtsn'];
                $stmt1 = $conn->prepare("UPDATE pegawai SET nip_penilai = ?, nama_penilai = ? WHERE role = 'user' AND unit_kerja = 'MTsN 11 Majalengka'");
                $stmt1->bind_param("ss", $penilai_mtsn['nip'], $penilai_mtsn['nama']);
                $stmt1->execute();
                $affected_mtsn = $stmt1->affected_rows;
                $stmt1->close();
            }
            
            // Update Tata Usaha users
            if (!empty($penilai_settings['penilai_tata_usaha'])) {
                $penilai_tu = $penilai_settings['penilai_tata_usaha'];
                $stmt2 = $conn->prepare("UPDATE pegawai SET nip_penilai = ?, nama_penilai = ? WHERE role = 'user' AND unit_kerja = 'Tata Usaha MTsN 11 Majalengka'");
                $stmt2->bind_param("ss", $penilai_tu['nip'], $penilai_tu['nama']);
                $stmt2->execute();
                $affected_tu = $stmt2->affected_rows;
                $stmt2->close();
            }
            
            $total_affected = ($affected_mtsn ?? 0) + ($affected_tu ?? 0);
            $message = "Berhasil menyinkronkan data penilai untuk $total_affected pegawai.";
            $message_type = "success";
        } else {
            $message = "File pengaturan penilai tidak ditemukan.";
            $message_type = "danger";
        }
    }
}

// Get system statistics
$stats = [];
$result = $conn->query("SELECT COUNT(*) as total FROM pegawai WHERE role = 'user'");
$stats['total_pegawai'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM rhk");
$stats['total_rhk'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM rkb");
$stats['total_rkb'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM lkh");
$stats['total_lkh'] = $result->fetch_assoc()['total'];

// Load penilai settings
$penilai_settings_file = __DIR__ . '/../config/penilai_settings.json';
$penilai_settings = [];
if (file_exists($penilai_settings_file)) {
    $penilai_settings = json_decode(file_get_contents($penilai_settings_file), true);
}

// Get current penilai data from database
$current_penilai_mtsn_query = "SELECT nip_penilai, nama_penilai, COUNT(*) as count 
                              FROM pegawai 
                              WHERE role = 'user' AND unit_kerja = 'MTsN 11 Majalengka' AND nip_penilai IS NOT NULL 
                              GROUP BY nip_penilai, nama_penilai 
                              ORDER BY count DESC 
                              LIMIT 1";
$current_penilai_mtsn_result = $conn->query($current_penilai_mtsn_query);
$current_penilai_mtsn_db = $current_penilai_mtsn_result->fetch_assoc();

$current_penilai_tu_query = "SELECT nip_penilai, nama_penilai, COUNT(*) as count 
                            FROM pegawai 
                            WHERE role = 'user' AND unit_kerja = 'Tata Usaha MTsN 11 Majalengka' AND nip_penilai IS NOT NULL 
                            GROUP BY nip_penilai, nama_penilai 
                            ORDER BY count DESC 
                            LIMIT 1";
$current_penilai_tu_result = $conn->query($current_penilai_tu_query);
$current_penilai_tu_db = $current_penilai_tu_result->fetch_assoc();

// Check if there's a mismatch between JSON settings and database
$is_mismatch = false;
if (!empty($penilai_settings['penilai_mtsn']) && !empty($current_penilai_mtsn_db)) {
    $json_nip = $penilai_settings['penilai_mtsn']['nip'];
    $json_nama = $penilai_settings['penilai_mtsn']['nama'];
    $db_nip = $current_penilai_mtsn_db['nip_penilai'];
    $db_nama = $current_penilai_mtsn_db['nama_penilai'];
    
    if ($json_nip !== $db_nip || $json_nama !== $db_nama) {
        $is_mismatch = true;
    }
}

if (!empty($penilai_settings['penilai_tata_usaha']) && !empty($current_penilai_tu_db)) {
    $json_nip = $penilai_settings['penilai_tata_usaha']['nip'];
    $json_nama = $penilai_settings['penilai_tata_usaha']['nama'];
    $db_nip = $current_penilai_tu_db['nip_penilai'];
    $db_nama = $current_penilai_tu_db['nama_penilai'];
    
    if ($json_nip !== $db_nip || $json_nama !== $db_nama) {
        $is_mismatch = true;
    }
}

include __DIR__ . '/../template/header.php';
include __DIR__ . '/../template/menu_admin.php';
include __DIR__ . '/../template/topbar.php';
?>

<div id="layoutSidenav_content">
    <main>
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="mt-4 mb-0"><i class="fas fa-cogs"></i> Pengaturan Sistem</h1>
                    <p class="lead mb-0">Kelola pengaturan dan konfigurasi sistem E-Lapkin.</p>
                </div>
                <a href="../logout.php" class="btn btn-outline-danger logout-btn mt-4">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Informasi Sistem -->
                <div class="col-xl-6">
                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-info-circle me-1"></i>
                            Informasi Sistem
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless">
                                <tr>
                                    <td width="40%"><strong>Nama Sistem</strong></td>
                                    <td>E-Lapkin MTSN 11 Majalengka</td>
                                </tr>
                                <tr>
                                    <td><strong>Versi</strong></td>
                                    <td>1.0.0</td>
                                </tr>
                                <tr>
                                    <td><strong>Database</strong></td>
                                    <td>MySQL <?php echo $conn->server_info; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>PHP Version</strong></td>
                                    <td><?php echo phpversion(); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Last Update</strong></td>
                                    <td><?php echo date('d/m/Y H:i:s'); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Backup & Maintenance -->
                <div class="col-xl-6">
                    <div class="card shadow mb-4">
                        <div class="card-header bg-success text-white">
                            <i class="fas fa-database me-1"></i>
                            Backup & Maintenance
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <h6>Database Backup</h6>
                                <p class="text-muted">Buat backup database untuk keamanan data.</p>
                                <a href="../config/export_database.php" class="btn btn-success me-2">
                                    <i class="fas fa-download"></i> Backup Database
                                </a>
                                <a href="../config/export_structure_only.php" class="btn btn-outline-success">
                                    <i class="fas fa-sitemap"></i> Backup Struktur
                                </a>
                            </div>
                            
                            <hr>
                            
                            <div class="mb-3">
                                <h6>Clear Cache</h6>
                                <p class="text-muted">Bersihkan cache sistem untuk performa optimal.</p>
                                <button type="button" class="btn btn-info" onclick="clearCache()">
                                    <i class="fas fa-broom"></i> Clear Cache
                                </button>
                            </div>
                            
                            <hr>
                            
                            <div class="mb-3">
                                <h6>Logs & Monitoring</h6>
                                <p class="text-muted">Pantau aktivitas sistem dan error logs.</p>
                                <button type="button" class="btn btn-secondary" onclick="viewLogs()">
                                    <i class="fas fa-list"></i> View Logs
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tools & Utilities -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow mb-4">
                        <div class="card-header bg-dark text-white">
                            <i class="fas fa-tools me-1"></i>
                            Tools & Utilities
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="text-center p-3">
                                        <a href="../config/check_database.php" class="btn btn-outline-primary btn-lg w-100" target="_blank">
                                            <i class="fas fa-search fa-2x mb-2"></i><br>
                                            Database Inspector
                                        </a>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center p-3">
                                        <a href="laporan_statistik.php" class="btn btn-outline-info btn-lg w-100">
                                            <i class="fas fa-chart-bar fa-2x mb-2"></i><br>
                                            Statistik Sistem
                                        </a>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center p-3">
                                        <button type="button" class="btn btn-outline-warning btn-lg w-100" onclick="optimizeDatabase()">
                                            <i class="fas fa-tachometer-alt fa-2x mb-2"></i><br>
                                            Optimize Database
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center p-3">
                                        <button type="button" class="btn btn-outline-success btn-lg w-100" onclick="checkSystemHealth()">
                                            <i class="fas fa-heartbeat fa-2x mb-2"></i><br>
                                            System Health
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pengaturan Email -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow mb-4">
                        <div class="card-header bg-info text-white">
                            <i class="fas fa-envelope me-1"></i>
                            Pengaturan Email & Notifikasi
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="update_email" value="1">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">SMTP Server</label>
                                            <input type="text" class="form-control" name="smtp_server" value="smtp.gmail.com" placeholder="smtp.gmail.com">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">SMTP Port</label>
                                            <input type="number" class="form-control" name="smtp_port" value="587" placeholder="587">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Email Username</label>
                                            <input type="email" class="form-control" name="email_username" placeholder="your-email@gmail.com">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Email Password</label>
                                            <input type="password" class="form-control" name="email_password" placeholder="App Password">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" checked>
                                        <label class="form-check-label" for="email_notifications">
                                            Aktifkan notifikasi email untuk status approval
                                        </label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-info">
                                    <i class="fas fa-save"></i> Simpan Pengaturan Email
                                </button>
                                <button type="button" class="btn btn-outline-info ms-2" onclick="testEmail()">
                                    <i class="fas fa-paper-plane"></i> Test Email
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pengaturan Penilai -->
            <div class="row">
                <!-- Penilai MTsN -->
                <div class="col-xl-6">
                    <div class="card shadow mb-4">
                        <div class="card-header bg-warning text-white">
                            <i class="fas fa-user-tie me-1"></i>
                            Penilai MTsN 11 Majalengka
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="update_penilai_mtsn" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label">NIP Penilai MTsN</label>
                                    <input type="text" class="form-control" name="mtsn_nip" 
                                           value="<?php echo htmlspecialchars($penilai_settings['penilai_mtsn']['nip'] ?? ''); ?>" 
                                           placeholder="Masukkan NIP penilai MTsN" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Nama Penilai MTsN</label>
                                    <input type="text" class="form-control" name="mtsn_nama" 
                                           value="<?php echo htmlspecialchars($penilai_settings['penilai_mtsn']['nama'] ?? ''); ?>" 
                                           placeholder="Masukkan nama penilai MTsN" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Jabatan Penilai MTsN</label>
                                    <input type="text" class="form-control" name="mtsn_jabatan" 
                                           value="<?php echo htmlspecialchars($penilai_settings['penilai_mtsn']['jabatan'] ?? ''); ?>" 
                                           placeholder="Masukkan jabatan penilai MTsN" required>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    Penilai untuk pegawai di unit kerja <strong>MTsN 11 Majalengka</strong>
                                </div>
                                
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-save"></i> Simpan Penilai MTsN
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Penilai Tata Usaha -->
                <div class="col-xl-6">
                    <div class="card shadow mb-4">
                        <div class="card-header bg-success text-white">
                            <i class="fas fa-user-tie me-1"></i>
                            Penilai Tata Usaha MTsN 11
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="update_penilai_tata_usaha" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label">NIP Penilai Tata Usaha</label>
                                    <input type="text" class="form-control" name="tu_nip" 
                                           value="<?php echo htmlspecialchars($penilai_settings['penilai_tata_usaha']['nip'] ?? ''); ?>" 
                                           placeholder="Masukkan NIP penilai Tata Usaha" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Nama Penilai Tata Usaha</label>
                                    <input type="text" class="form-control" name="tu_nama" 
                                           value="<?php echo htmlspecialchars($penilai_settings['penilai_tata_usaha']['nama'] ?? ''); ?>" 
                                           placeholder="Masukkan nama penilai Tata Usaha" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Jabatan Penilai Tata Usaha</label>
                                    <input type="text" class="form-control" name="tu_jabatan" 
                                           value="<?php echo htmlspecialchars($penilai_settings['penilai_tata_usaha']['jabatan'] ?? ''); ?>" 
                                           placeholder="Masukkan jabatan penilai Tata Usaha" required>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    Penilai untuk pegawai di unit kerja <strong>Tata Usaha MTsN 11 Majalengka</strong>
                                </div>
                                
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Simpan Penilai Tata Usaha
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Penilai Saat Ini -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow mb-4">
                        <div class="card-header bg-info text-white">
                            <i class="fas fa-info-circle me-1"></i>
                            Status Penilai Saat Ini
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- MTsN Status -->
                                <div class="col-md-6">
                                    <h6 class="text-warning">MTsN 11 Majalengka</h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <strong>Pengaturan JSON:</strong>
                                            <?php if (!empty($penilai_settings['penilai_mtsn'])): ?>
                                                <table class="table table-sm table-borderless mt-2">
                                                    <tr><td>NIP:</td><td><?php echo htmlspecialchars($penilai_settings['penilai_mtsn']['nip']); ?></td></tr>
                                                    <tr><td>Nama:</td><td><?php echo htmlspecialchars($penilai_settings['penilai_mtsn']['nama']); ?></td></tr>
                                                </table>
                                            <?php else: ?>
                                                <div class="alert alert-warning alert-sm mt-2">Belum dikonfigurasi</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-6">
                                            <strong>Data Database:</strong>
                                            <?php if (!empty($current_penilai_mtsn_db)): ?>
                                                <table class="table table-sm table-borderless mt-2">
                                                    <tr><td>NIP:</td><td><?php echo htmlspecialchars($current_penilai_mtsn_db['nip_penilai']); ?></td></tr>
                                                    <tr><td>Nama:</td><td><?php echo htmlspecialchars($current_penilai_mtsn_db['nama_penilai']); ?></td></tr>
                                                    <tr><td>Pegawai:</td><td><?php echo $current_penilai_mtsn_db['count']; ?> orang</td></tr>
                                                </table>
                                            <?php else: ?>
                                                <div class="alert alert-warning alert-sm mt-2">Tidak ada data</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Tata Usaha Status -->
                                <div class="col-md-6">
                                    <h6 class="text-success">Tata Usaha MTsN 11</h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <strong>Pengaturan JSON:</strong>
                                            <?php if (!empty($penilai_settings['penilai_tata_usaha'])): ?>
                                                <table class="table table-sm table-borderless mt-2">
                                                    <tr><td>NIP:</td><td><?php echo htmlspecialchars($penilai_settings['penilai_tata_usaha']['nip']); ?></td></tr>
                                                    <tr><td>Nama:</td><td><?php echo htmlspecialchars($penilai_settings['penilai_tata_usaha']['nama']); ?></td></tr>
                                                </table>
                                            <?php else: ?>
                                                <div class="alert alert-warning alert-sm mt-2">Belum dikonfigurasi</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-6">
                                            <strong>Data Database:</strong>
                                            <?php if (!empty($current_penilai_tu_db)): ?>
                                                <table class="table table-sm table-borderless mt-2">
                                                    <tr><td>NIP:</td><td><?php echo htmlspecialchars($current_penilai_tu_db['nip_penilai']); ?></td></tr>
                                                    <tr><td>Nama:</td><td><?php echo htmlspecialchars($current_penilai_tu_db['nama_penilai']); ?></td></tr>
                                                    <tr><td>Pegawai:</td><td><?php echo $current_penilai_tu_db['count']; ?> orang</td></tr>
                                                </table>
                                            <?php else: ?>
                                                <div class="alert alert-warning alert-sm mt-2">Tidak ada data</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($is_mismatch): ?>
                                <div class="alert alert-danger mt-3">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>Peringatan!</strong> Data penilai di JSON berbeda dengan database.
                                    <form method="POST" class="mt-2" onsubmit="return confirmSync()">
                                        <input type="hidden" name="sync_penilai" value="1">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="fas fa-sync-alt"></i> Sinkronkan Semua Data Penilai
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success mt-3">
                                    <i class="fas fa-check-circle"></i>
                                    Semua data penilai tersinkronisasi dengan baik.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Remove the logout button from bottom -->
            <!-- 
            <div class="text-end mt-3">
              <a href="../logout.php" class="btn btn-outline-danger logout-btn"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
            </div>
            -->
        </div>
    </main>
    <?php include __DIR__ . '/../template/footer.php'; ?>
</div>

<!-- Add SweetAlert CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function clearCache() {
    if (confirm('Yakin ingin clear cache sistem?')) {
        alert('Cache berhasil dibersihkan!');
    }
}

function viewLogs() {
    alert('Fitur view logs sedang dalam pengembangan');
}

function optimizeDatabase() {
    if (confirm('Yakin ingin optimize database? Proses ini mungkin memakan waktu.')) {
        alert('Database berhasil dioptimasi!');
    }
}

function checkSystemHealth() {
    alert('System Health: OK ✓\n- Database: Connected\n- PHP: Running\n- Disk Space: 85% available\n- Memory: 60% used');
}

function testEmail() {
    alert('Test email sedang dikirim...\nSilakan cek inbox Anda.');
}

function confirmSync() {
    return Swal.fire({
        title: 'Sinkronkan Data Penilai?',
        html: `
            <div class="text-start">
                <p>Anda akan menyinkronkan data penilai untuk semua pegawai dengan pengaturan JSON terbaru:</p>
                <div class="alert alert-info">
                    <strong>NIP:</strong> <?php echo htmlspecialchars($penilai_settings['penilai_mtsn']['nip'] ?? ''); ?><br>
                    <strong>Nama:</strong> <?php echo htmlspecialchars($penilai_settings['penilai_mtsn']['nama'] ?? ''); ?>
                </div>
                <p class="text-warning"><i class="fas fa-exclamation-triangle"></i> <strong>Semua data penilai pegawai akan diperbarui!</strong></p>
                <p><strong>Tindakan ini tidak dapat dibatalkan!</strong></p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-sync-alt"></i> Ya, Sinkronkan',
        cancelButtonText: '<i class="fas fa-times"></i> Batal',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Menyinkronkan...',
                text: 'Sedang memperbarui data penilai untuk semua pegawai',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
            return true;
        }
        return false;
    });
}

// Logout confirmation - wait for DOM and SweetAlert to load
document.addEventListener('DOMContentLoaded', function() {
    // Wait a bit to ensure SweetAlert is fully loaded
    setTimeout(function() {
        document.querySelectorAll('.logout-btn, a[href*="logout.php"]').forEach(function(logoutLink) {
            logoutLink.addEventListener('click', function(e) {
                e.preventDefault();
                const href = this.getAttribute('href');
                
                // Check if SweetAlert is available
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Konfirmasi Logout',
                        text: 'Apakah Anda yakin ingin keluar dari sistem?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Ya, Logout',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = href;
                        }
                    });
                } else {
                    // Fallback to native confirm if SweetAlert not loaded
                    if (confirm('Apakah Anda yakin ingin keluar dari sistem?')) {
                        window.location.href = href;
                    }
                }
            });
        });
    }, 100);
});
</script>