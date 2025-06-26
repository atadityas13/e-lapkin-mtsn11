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

$page_title = "Manajemen User";
$message = '';
$message_type = '';

// Handle approve/reject pendaftaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_registration']) && isset($_POST['nip_registration'])) {
    $nip_registration = $_POST['nip_registration'];
    $action = $_POST['action']; // 'approve' or 'reject'
    
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE pegawai SET status = 'approved' WHERE nip = ? AND status = 'pending'");
        $stmt->bind_param("s", $nip_registration);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = "Pendaftaran pegawai berhasil disetujui.";
            $message_type = "success";
        } else {
            $message = "Gagal menyetujui pendaftaran atau data tidak ditemukan.";
            $message_type = "danger";
        }
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE pegawai SET status = 'rejected' WHERE nip = ? AND status = 'pending'");
        $stmt->bind_param("s", $nip_registration);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = "Pendaftaran pegawai berhasil ditolak.";
            $message_type = "success";
        } else {
            $message = "Gagal menolak pendaftaran atau data tidak ditemukan.";
            $message_type = "danger";
        }
    }
    $stmt->close();
}

// Handle hapus pegawai
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_pegawai']) && isset($_POST['nip_hapus'])) {
    $nip_hapus = $_POST['nip_hapus'];
    if ($nip_hapus === $_SESSION['nip']) {
        $message = "Anda tidak bisa menghapus akun Anda sendiri!";
        $message_type = "danger";
    } else {
        $stmt = $conn->prepare("DELETE FROM pegawai WHERE nip = ?");
        $stmt->bind_param("s", $nip_hapus);
        if ($stmt->execute()) {
            $message = "Pegawai berhasil dihapus.";
            $message_type = "success";
        } else {
            $message = "Error: " . $stmt->error;
            $message_type = "danger";
        }
        $stmt->close();
    }
}

// Handle update pegawai
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_pegawai']) && isset($_POST['nip_edit'])) {
    $nip_edit = $_POST['nip_edit'];
    $nama_edit = trim($_POST['nama_edit']);
    $jabatan_edit = trim($_POST['jabatan_edit']);
    $unit_kerja_edit = trim($_POST['unit_kerja_edit']);
    $nip_penilai_edit = trim($_POST['nip_penilai_edit']);
    $nama_penilai_edit = trim($_POST['nama_penilai_edit']);
    $role_edit = $_POST['role_edit'];

    $stmt = $conn->prepare("UPDATE pegawai SET nama=?, jabatan=?, unit_kerja=?, nip_penilai=?, nama_penilai=?, role=? WHERE nip=?");
    $stmt->bind_param("sssssss", $nama_edit, $jabatan_edit, $unit_kerja_edit, $nip_penilai_edit, $nama_penilai_edit, $role_edit, $nip_edit);
    if ($stmt->execute()) {
        $message = "Data pegawai berhasil diperbarui.";
        $message_type = "success";
    } else {
        $message = "Error: " . $stmt->error;
        $message_type = "danger";
    }
    $stmt->close();
}

// Handle approve reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_reset']) && isset($_POST['nip_approve'])) {
    $nip_approve = $_POST['nip_approve'];
    $new_password = password_hash($nip_approve, PASSWORD_DEFAULT); // Password baru = NIP

    $stmt = $conn->prepare("UPDATE pegawai SET password=?, reset_request=0 WHERE nip=?");
    $stmt->bind_param("ss", $new_password, $nip_approve);
    if ($stmt->execute()) {
        $message = "Reset password telah di-approve dan password direset ke NIP pegawai.";
        $message_type = "success";
    } else {
        $message = "Error: " . $stmt->error;
        $message_type = "danger";
    }
    $stmt->close();
}

// Handle reset password pegawai (manual oleh admin, jika tidak ada permintaan)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password']) && isset($_POST['nip_reset'])) {
    $nip_reset = $_POST['nip_reset'];
    $new_password = password_hash($nip_reset, PASSWORD_DEFAULT); // Password baru = NIP

    $stmt = $conn->prepare("UPDATE pegawai SET password=? WHERE nip=?");
    $stmt->bind_param("ss", $new_password, $nip_reset);
    if ($stmt->execute()) {
        $message = "Password berhasil direset ke NIP pegawai.";
        $message_type = "success";
    } else {
        $message = "Error: " . $stmt->error;
        $message_type = "danger";
    }
    $stmt->close();
}

// Handle register pegawai
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_pegawai']) && isset($_POST['form_submitted'])) {
    // Create a more unique token that includes timestamp to avoid conflicts
    $form_token = 'register_form_' . md5($_POST['nip'] . time());
    
    // Check if this exact NIP was recently submitted (within last 30 seconds)
    $recent_submission = false;
    foreach ($_SESSION as $key => $value) {
        if (strpos($key, 'register_form_') === 0) {
            $token_parts = explode('_', $key);
            if (count($token_parts) >= 3 && isset($_POST['nip'])) {
                $stored_nip_hash = end($token_parts);
                $current_nip_hash = md5($_POST['nip']);
                if ($stored_nip_hash === $current_nip_hash && (time() - $value) < 30) {
                    $recent_submission = true;
                    break;
                }
            }
        }
    }
    
    if ($recent_submission) {
        $message = "Form sudah disubmit sebelumnya. Silakan tunggu beberapa saat.";
        $message_type = "warning";
    } else {
        // Set session flag to prevent double submission
        $_SESSION[$form_token] = time();
        
        $nip = trim($_POST['nip']);
        $password = trim($_POST['password']);
        $nama = trim($_POST['nama']);
        $jabatan = trim($_POST['jabatan']);
        $unit_kerja = trim($_POST['unit_kerja']);

        if (empty($nip) || empty($password) || empty($nama) || empty($jabatan) || empty($unit_kerja)) {
            $message = "NIP, Password, Nama, Jabatan, dan Unit Kerja harus diisi.";
            $message_type = "danger";
            unset($_SESSION[$form_token]); // Remove flag on error
        } else {
            // Load penilai settings from JSON
            $penilai_settings_file = __DIR__ . '/../config/penilai_settings.json';
            $nip_penilai = '';
            $nama_penilai = '';
            
            if (file_exists($penilai_settings_file)) {
                $penilai_settings = json_decode(file_get_contents($penilai_settings_file), true);
                
                // Tentukan penilai berdasarkan unit kerja
                if ($unit_kerja === 'Tata Usaha MTsN 11 Majalengka') {
                    // Untuk Tata Usaha, penilainya adalah Kepala Urusan Tata Usaha
                    if (!empty($penilai_settings['penilai_tata_usaha'])) {
                        $nip_penilai = $penilai_settings['penilai_tata_usaha']['nip'] ?? '';
                        $nama_penilai = $penilai_settings['penilai_tata_usaha']['nama'] ?? '';
                    }
                } else {
                    // Untuk unit kerja lainnya (MTsN 11 Majalengka), penilainya adalah Kepala Madrasah
                    if (!empty($penilai_settings['penilai_mtsn'])) {
                        $nip_penilai = $penilai_settings['penilai_mtsn']['nip'] ?? '';
                        $nama_penilai = $penilai_settings['penilai_mtsn']['nama'] ?? '';
                    }
                }
            }
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt_check_nip = $conn->prepare("SELECT nip FROM pegawai WHERE nip = ?");
            $stmt_check_nip->bind_param("s", $nip);
            $stmt_check_nip->execute();
            $stmt_check_nip->store_result();

            if ($stmt_check_nip->num_rows > 0) {
                $message = "NIP sudah terdaftar. Silakan gunakan NIP lain.";
                $message_type = "danger";
                unset($_SESSION[$form_token]); // Remove flag on error
            } else {
                $stmt = $conn->prepare("INSERT INTO pegawai (nip, password, nama, jabatan, unit_kerja, nip_penilai, nama_penilai, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $default_role = 'user';
                $stmt->bind_param("ssssssss", $nip, $hashed_password, $nama, $jabatan, $unit_kerja, $nip_penilai, $nama_penilai, $default_role);
                if ($stmt->execute()) {
                    $message = "Pegawai berhasil didaftarkan!";
                    $message_type = "success";
                    
                    // Clean up old session tokens (older than 10 minutes)
                    foreach ($_SESSION as $key => $value) {
                        if (strpos($key, 'register_form_') === 0 && (time() - $value) > 600) {
                            unset($_SESSION[$key]);
                        }
                    }
                    
                    // Clear current token after successful submission
                    unset($_SESSION[$form_token]);
                } else {
                    $message = "Gagal mendaftarkan pegawai: " . $stmt->error;
                    $message_type = "danger";
                    unset($_SESSION[$form_token]); // Remove flag on error
                }
                $stmt->close();
            }
            $stmt_check_nip->close();
        }
    }
}

// Cek apakah kolom reset_request dan status ada di tabel pegawai
$reset_request_exists = false;
$status_exists = false;
$check_col = $conn->query("SHOW COLUMNS FROM pegawai LIKE 'reset_request'");
if ($check_col && $check_col->num_rows > 0) {
    $reset_request_exists = true;
}
$check_col2 = $conn->query("SHOW COLUMNS FROM pegawai LIKE 'status'");
if ($check_col2 && $check_col2->num_rows > 0) {
    $status_exists = true;
}

// Query untuk menampilkan data pegawai
$query_fields = "nip, nama, jabatan, unit_kerja, nip_penilai, nama_penilai, role";
if ($reset_request_exists) {
    $query_fields .= ", IFNULL(reset_request,0) AS reset_request";
}
if ($status_exists) {
    $query_fields .= ", IFNULL(status,'approved') AS status";
}

$result = $conn->query("SELECT {$query_fields} FROM pegawai WHERE role != 'admin' ORDER BY 
    CASE 
        WHEN " . ($status_exists ? "status = 'pending'" : "0") . " THEN 1 
        WHEN " . ($status_exists ? "status = 'rejected'" : "0") . " THEN 2 
        ELSE 3 
    END, id_pegawai ASC");

// Query untuk pendaftaran yang pending (jika ada kolom status)
$pending_registrations = null;
if ($status_exists) {
    $pending_registrations = $conn->query("SELECT nip, nama, jabatan, unit_kerja, nip_penilai, nama_penilai FROM pegawai WHERE status = 'pending' ORDER BY id_pegawai DESC");
}

include __DIR__ . '/../template/header.php';
include __DIR__ . '/../template/menu_admin.php';
include __DIR__ . '/../template/topbar.php';
?>

<!-- Add Sweet Alert CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div id="layoutSidenav_content">
    <main>
        <div class="container-fluid px-4">
            <h1 class="mt-4 mb-3"><i class="fas fa-users-cog"></i> Manajemen Pegawai</h1>
            <p class="lead">Lihat dan kelola data pegawai di lingkungan MTsN 11 Majalengka.</p>

            <!-- Pending Registrations Section -->
            <?php if ($status_exists && $pending_registrations && $pending_registrations->num_rows > 0): ?>
            <div class="card shadow-sm mb-4 border-warning">
                <div class="card-header bg-warning text-dark">
                    <i class="fas fa-clock"></i> Pendaftaran Menunggu Persetujuan 
                    <span class="badge bg-dark"><?php echo $pending_registrations->num_rows; ?></span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>NIP</th>
                                    <th>Nama</th>
                                    <th>Jabatan</th>
                                    <th>Unit Kerja</th>
                                    <th>Penilai</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php while ($pending = $pending_registrations->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pending['nip']); ?></td>
                                    <td><?php echo htmlspecialchars($pending['nama']); ?></td>
                                    <td><?php echo htmlspecialchars($pending['jabatan']); ?></td>
                                    <td><?php echo htmlspecialchars($pending['unit_kerja']); ?></td>
                                    <td>
                                        <?php if (!empty($pending['nama_penilai'])): ?>
                                            <small><strong><?php echo htmlspecialchars($pending['nama_penilai']); ?></strong><br>
                                            NIP: <?php echo htmlspecialchars($pending['nip_penilai']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Belum diatur</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-success btn-sm me-1" onclick="approveRegistration('<?php echo $pending['nip']; ?>', '<?php echo addslashes($pending['nama']); ?>', 'approve')" title="Setujui Pendaftaran">
                                            <i class="fas fa-check"></i> Setujui
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="approveRegistration('<?php echo $pending['nip']; ?>', '<?php echo addslashes($pending['nama']); ?>', 'reject')" title="Tolak Pendaftaran">
                                            <i class="fas fa-times"></i> Tolak
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#modalRegisterPegawai">
                <i class="fas fa-user-plus"></i> Tambah Pegawai
            </button>

            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-list"></i> Daftar Pegawai
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>NIP</th>
                                    <th>Nama</th>
                                    <th>Jabatan</th>
                                    <th>Role</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $no = 1;
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()):
                                    // Skip pending registrations as they're shown above
                                    if ($status_exists && isset($row['status']) && $row['status'] === 'pending') {
                                        continue;
                                    }
                                ?>
                                    <tr class="<?php echo ($status_exists && isset($row['status']) && $row['status'] === 'rejected') ? 'table-danger' : ''; ?>">
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo htmlspecialchars($row['nip']); ?></td>
                                        <td><?php echo htmlspecialchars($row['nama']); ?></td>
                                        <td><?php echo htmlspecialchars($row['jabatan']); ?></td>
                                        <td><span class="badge bg-<?php echo ($row['role'] === 'admin' ? 'warning' : 'info'); ?>"><?php echo htmlspecialchars(ucfirst($row['role'])); ?></span></td>
                                        <td>
                                            <?php if (!$status_exists || (isset($row['status']) && $row['status'] !== 'rejected')): ?>
                                            <button class="btn btn-sm btn-warning mb-1" onclick="editPegawai('<?php echo $row['nip']; ?>', '<?php echo addslashes($row['nama']); ?>', '<?php echo addslashes($row['jabatan']); ?>', '<?php echo addslashes($row['unit_kerja']); ?>', '<?php echo addslashes($row['nip_penilai']); ?>', '<?php echo addslashes($row['nama_penilai']); ?>', '<?php echo $row['role']; ?>')" title="Edit Data">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>

                                            <?php if ($reset_request_exists && isset($row['reset_request']) && $row['reset_request'] == 1): ?>
                                                <button class="btn btn-sm btn-info mb-1" onclick="approveReset('<?php echo $row['nip']; ?>', '<?php echo addslashes($row['nama']); ?>')" title="Approve Reset Password">
                                                    <i class="fas fa-key"></i> Approve Reset
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary mb-1" onclick="resetPassword('<?php echo $row['nip']; ?>', '<?php echo addslashes($row['nama']); ?>')" title="Reset Password">
                                                    <i class="fas fa-unlock-alt"></i> Reset Password
                                                </button>
                                            <?php endif; ?>
                                            <?php endif; ?>

                                            <?php if ($row['nip'] !== $_SESSION['nip']): ?>
                                                <button class="btn btn-sm btn-danger mb-1" onclick="hapusPegawai('<?php echo $row['nip']; ?>', '<?php echo addslashes($row['nama']); ?>')" title="Hapus Pegawai">
                                                    <i class="fas fa-trash-alt"></i> Hapus
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php
                                endwhile;
                            } else {
                                echo "<tr><td colspan='6' class='text-center'>Tidak ada data pegawai.</td></tr>";
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Modal Register Pegawai -->
            <div class="modal fade" id="modalRegisterPegawai" tabindex="-1" aria-labelledby="modalRegisterPegawaiLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="modalRegisterPegawaiLabel">
                                <i class="fas fa-user-plus me-2"></i>Tambah Pegawai Baru
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form method="POST" id="registerForm">
                                <input type="hidden" name="register_pegawai" value="1">
                                
                                <div class="mb-3">
                                    <label for="nip" class="form-label">NIP <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nip" name="nip" required placeholder="Masukkan NIP pegawai">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password" required placeholder="Masukkan password" minlength="6">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('password', 'togglePasswordIcon')">
                                            <i class="fas fa-eye" id="togglePasswordIcon"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Gunakan password yang kuat (minimal 6 karakter).</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="nama" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nama" name="nama" required placeholder="Masukkan nama lengkap">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="jabatan" class="form-label">Jabatan <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="jabatan" name="jabatan" required placeholder="Masukkan jabatan">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="unit_kerja" class="form-label">Unit Kerja <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="unit_kerja" name="unit_kerja" required placeholder="Masukkan unit kerja" value="MTsN 11 Majalengka">
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Informasi:</strong> Data penilai akan diatur secara otomatis melalui pengaturan sistem.
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-success" id="submitBtn">
                                        <i class="fas fa-user-plus"></i> Daftarkan Pegawai
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <?php include __DIR__ . '/../template/footer.php'; ?>
</div>

<script>
// Show Sweet Alert if there's a message from PHP
<?php if ($message): ?>
Swal.fire({
    icon: '<?php echo $message_type === "success" ? "success" : "error"; ?>',
    title: '<?php echo $message_type === "success" ? "Berhasil!" : "Error!"; ?>',
    text: '<?php echo addslashes($message); ?>',
    showConfirmButton: true,
    timer: 3000
});
<?php endif; ?>

function approveRegistration(nip, nama, action) {
    const actionText = action === 'approve' ? 'menyetujui' : 'menolak';
    const actionColor = action === 'approve' ? '#28a745' : '#dc3545';
    const actionIcon = action === 'approve' ? 'success' : 'warning';
    
    Swal.fire({
        title: `${action === 'approve' ? 'Setujui' : 'Tolak'} Pendaftaran`,
        html: `
            <div class="text-start">
                <p>Anda akan ${actionText} pendaftaran untuk:</p>
                <div class="alert alert-info">
                    <strong>Nama:</strong> ${nama}<br>
                    <strong>NIP:</strong> ${nip}
                </div>
                ${action === 'approve' ? 
                    '<p class="text-success"><i class="fas fa-check-circle"></i> Pegawai akan dapat login setelah disetujui.</p>' : 
                    '<p class="text-danger"><i class="fas fa-exclamation-triangle"></i> Pegawai tidak akan dapat login jika ditolak.</p>'
                }
            </div>
        `,
        icon: actionIcon,
        showCancelButton: true,
        confirmButtonText: `<i class="fas fa-${action === 'approve' ? 'check' : 'times'}"></i> Ya, ${action === 'approve' ? 'Setujui' : 'Tolak'}`,
        cancelButtonText: '<i class="fas fa-times"></i> Batal',
        confirmButtonColor: actionColor,
        cancelButtonColor: '#6c757d'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Memproses...',
                text: `Sedang ${actionText} pendaftaran`,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const inputs = [
                { name: 'approve_registration', value: '1' },
                { name: 'nip_registration', value: nip },
                { name: 'action', value: action }
            ];
            
            inputs.forEach(input => {
                const element = document.createElement('input');
                element.type = 'hidden';
                element.name = input.name;
                element.value = input.value;
                form.appendChild(element);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function editPegawai(nip, nama, jabatan, unitKerja, nipPenilai, namaPenilai, role) {
    Swal.fire({
        title: 'Edit Data Pegawai',
        html: `
            <div class="text-start">
                <div class="mb-3">
                    <label class="form-label">NIP</label>
                    <input type="text" class="form-control" value="${nip}" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" id="swal-nama" class="form-control" value="${nama}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Jabatan <span class="text-danger">*</span></label>
                    <input type="text" id="swal-jabatan" class="form-control" value="${jabatan}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Unit Kerja <span class="text-danger">*</span></label>
                    <input type="text" id="swal-unit-kerja" class="form-control" value="${unitKerja}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">NIP Penilai</label>
                    <input type="text" id="swal-nip-penilai" class="form-control" value="${nipPenilai}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Nama Penilai</label>
                    <input type="text" id="swal-nama-penilai" class="form-control" value="${namaPenilai}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Role <span class="text-danger">*</span></label>
                    <select id="swal-role" class="form-select" required>
                        <option value="user" ${role === 'user' ? 'selected' : ''}>User</option>
                        <option value="admin" ${role === 'admin' ? 'selected' : ''}>Admin</option>
                    </select>
                </div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save"></i> Simpan Perubahan',
        cancelButtonText: '<i class="fas fa-times"></i> Batal',
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        width: '600px',
        focusConfirm: false,
        preConfirm: () => {
            const nama = document.getElementById('swal-nama').value.trim();
            const jabatan = document.getElementById('swal-jabatan').value.trim();
            const unitKerja = document.getElementById('swal-unit-kerja').value.trim();
            const nipPenilai = document.getElementById('swal-nip-penilai').value.trim();
            const namaPenilai = document.getElementById('swal-nama-penilai').value.trim();
            const role = document.getElementById('swal-role').value;
            
            if (!nama || !jabatan || !unitKerja || !role) {
                Swal.showValidationMessage('Semua field yang wajib harus diisi');
                return false;
            }
            
            return { nama, jabatan, unitKerja, nipPenilai, namaPenilai, role };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Memperbarui Data...',
                text: 'Sedang menyimpan perubahan data pegawai',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const inputs = [
                { name: 'edit_pegawai', value: '1' },
                { name: 'nip_edit', value: nip },
                { name: 'nama_edit', value: result.value.nama },
                { name: 'jabatan_edit', value: result.value.jabatan },
                { name: 'unit_kerja_edit', value: result.value.unitKerja },
                { name: 'nip_penilai_edit', value: result.value.nipPenilai },
                { name: 'nama_penilai_edit', value: result.value.namaPenilai },
                { name: 'role_edit', value: result.value.role }
            ];
            
            inputs.forEach(input => {
                const element = document.createElement('input');
                element.type = 'hidden';
                element.name = input.name;
                element.value = input.value;
                form.appendChild(element);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function approveReset(nip, nama) {
    Swal.fire({
        title: 'Approve Reset Password',
        html: `
            <div class="text-start">
                <p>Anda akan menyetujui permintaan reset password untuk:</p>
                <div class="alert alert-info">
                    <strong>Nama:</strong> ${nama}<br>
                    <strong>NIP:</strong> ${nip}
                </div>
                <p class="text-warning"><i class="fas fa-exclamation-triangle"></i> <strong>Password akan direset menjadi sama dengan NIP pegawai.</strong></p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-key"></i> Ya, Approve Reset',
        cancelButtonText: '<i class="fas fa-times"></i> Batal',
        confirmButtonColor: '#17a2b8',
        cancelButtonColor: '#6c757d'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Memproses...',
                text: 'Sedang memproses approve reset password',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const inputs = [
                { name: 'approve_reset', value: '1' },
                { name: 'nip_approve', value: nip }
            ];
            
            inputs.forEach(input => {
                const element = document.createElement('input');
                element.type = 'hidden';
                element.name = input.name;
                element.value = input.value;
                form.appendChild(element);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function resetPassword(nip, nama) {
    Swal.fire({
        title: 'Reset Password Manual',
        html: `
            <div class="text-start">
                <p>Anda akan mereset password untuk pegawai:</p>
                <div class="alert alert-warning">
                    <strong>Nama:</strong> ${nama}<br>
                    <strong>NIP:</strong> ${nip}
                </div>
                <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> <strong>Password akan direset menjadi sama dengan NIP pegawai.</strong></p>
                <p><strong>Tindakan ini tidak dapat dibatalkan!</strong></p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-unlock-alt"></i> Ya, Reset Password',
        cancelButtonText: '<i class="fas fa-times"></i> Batal',
        confirmButtonColor: '#6c757d',
        cancelButtonColor: '#dc3545'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Mereset Password...',
                text: 'Sedang memproses reset password',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const inputs = [
                { name: 'reset_password', value: '1' },
                { name: 'nip_reset', value: nip }
            ];
            
            inputs.forEach(input => {
                const element = document.createElement('input');
                element.type = 'hidden';
                element.name = input.name;
                element.value = input.value;
                form.appendChild(element);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function hapusPegawai(nip, nama) {
    Swal.fire({
        title: 'Hapus Pegawai',
        html: `
            <div class="text-start">
                <p>Anda akan menghapus pegawai:</p>
                <div class="alert alert-danger">
                    <strong>Nama:</strong> ${nama}<br>
                    <strong>NIP:</strong> ${nip}
                </div>
                <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> <strong>Semua data terkait pegawai ini akan ikut terhapus!</strong></p>
                <p><strong>Tindakan ini tidak dapat dibatalkan!</strong></p>
            </div>
        `,
        icon: 'error',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-trash-alt"></i> Ya, Hapus Pegawai',
        cancelButtonText: '<i class="fas fa-times"></i> Batal',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Menghapus Data...',
                text: 'Sedang menghapus data pegawai',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const inputs = [
                { name: 'hapus_pegawai', value: '1' },
                { name: 'nip_hapus', value: nip }
            ];
            
            inputs.forEach(input => {
                const element = document.createElement('input');
                element.type = 'hidden';
                element.name = input.name;
                element.value = input.value;
                form.appendChild(element);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Auto-close alerts
document.addEventListener('DOMContentLoaded', function() {
    const alertElement = document.querySelector('.alert');
    if (alertElement) {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alertElement);
            bsAlert.close();
        }, 5000);
    }
});

// Handle form submission with Sweet Alert
document.getElementById('registerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('submitBtn');
    
    // Check if form is already being submitted
    if (submitBtn.disabled) {
        return false;
    }
    
    // Show loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
    
    // Validate form
    const nip = document.getElementById('nip').value.trim();
    const password = document.getElementById('password').value.trim();
    const nama = document.getElementById('nama').value.trim();
    const jabatan = document.getElementById('jabatan').value.trim();
    const unit_kerja = document.getElementById('unit_kerja').value.trim();
    
    if (!nip || !password || !nama || !jabatan || !unit_kerja) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Semua field yang wajib harus diisi',
            showConfirmButton: true
        });
        
        // Reset button
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Daftarkan Pegawai';
        return;
    }
    
    if (password.length < 6) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Password minimal 6 karakter',
            showConfirmButton: true
        });
        
        // Reset button
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Daftarkan Pegawai';
        return;
    }
    
    // Add hidden flag to prevent double submission with unique timestamp
    const timestamp = Date.now();
    if (!document.querySelector('input[name="form_submitted"]')) {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'form_submitted';
        hiddenInput.value = timestamp;
        this.appendChild(hiddenInput);
    }
    
    // Submit form
    this.submit();
});

// Handle successful registration message
<?php if (isset($_POST['register_pegawai']) && isset($_POST['form_submitted']) && $message_type === "success"): ?>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: '<?php echo addslashes($message); ?>',
        showConfirmButton: true,
        confirmButtonText: 'OK'
    }).then(() => {
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('modalRegisterPegawai'));
        if (modal) {
            modal.hide();
        }
        // Redirect to prevent resubmission
        window.location.href = '<?php echo $_SERVER['PHP_SELF']; ?>?success=1';
    });
});
<?php endif; ?>

// Handle error messages (including warnings)
<?php if (isset($_POST['register_pegawai']) && isset($_POST['form_submitted']) && $message_type !== "success"): ?>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: '<?php echo $message_type === "warning" ? "warning" : "error"; ?>',
        title: '<?php echo $message_type === "warning" ? "Peringatan!" : "Error!"; ?>',
        text: '<?php echo addslashes($message); ?>',
        showConfirmButton: true
    }).then(() => {
        // Reset form state
        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Daftarkan Pegawai';
        }
        
        // Remove form submitted flag
        const form = document.getElementById('registerForm');
        const formSubmittedInput = form.querySelector('input[name="form_submitted"]');
        if (formSubmittedInput) {
            formSubmittedInput.remove();
        }
    });
});
<?php endif; ?>

// Password visibility toggle
function togglePasswordVisibility(inputId, iconId) {
    const passwordInput = document.getElementById(inputId);
    const toggleIcon = document.getElementById(iconId);
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

// Reset form when modal is closed
document.getElementById('modalRegisterPegawai').addEventListener('hidden.bs.modal', function () {
    const form = document.getElementById('registerForm');
    form.reset();
    
    // Remove form submitted flag
    const formSubmittedInput = form.querySelector('input[name="form_submitted"]');
    if (formSubmittedInput) {
        formSubmittedInput.remove();
    }
    
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = false;
    submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Daftarkan Pegawai';
    
    // Reset password visibility
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('togglePasswordIcon');
    if (passwordInput && toggleIcon) {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
});
</script>