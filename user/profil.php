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
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../template/session_user.php';

// Define ABSPATH to fix topbar functionality
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

$page_title = "Profil Saya";
include __DIR__ . '/../template/header.php';
include __DIR__ . '/../template/menu_user.php';
include __DIR__ . '/../template/topbar.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Ambil data user dari session
$nip = $_SESSION['nip'] ?? '';
$nama = $_SESSION['nama'] ?? '';
$jabatan = $_SESSION['jabatan'] ?? '';
$unit_kerja = $_SESSION['unit_kerja'] ?? '';
$nip_penilai = $_SESSION['nip_penilai'] ?? '';
$nama_penilai = $_SESSION['nama_penilai'] ?? '';
$role = $_SESSION['role'] ?? '';

// Helper function for SweetAlert
function set_swal($type, $title, $text) {
    $_SESSION['swal'] = [
        'type' => $type,
        'title' => $title,
        'text' => $text
    ];
}

// Handle update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['update_profile'])) {
    // Ambil data lama jika field kosong (untuk upload foto saja)
    $nama_baru = isset($_POST['nama']) ? trim((string)$_POST['nama']) : $nama;
    $jabatan_baru = isset($_POST['jabatan']) ? trim((string)$_POST['jabatan']) : $jabatan;
    $unit_kerja_baru = isset($_POST['unit_kerja']) ? trim((string)$_POST['unit_kerja']) : $unit_kerja;
    $nip_penilai_baru = isset($_POST['nip_penilai']) ? trim((string)$_POST['nip_penilai']) : $nip_penilai;
    $nama_penilai_baru = isset($_POST['nama_penilai']) ? trim((string)$_POST['nama_penilai']) : $nama_penilai;

    // Handle upload foto profil
    $foto_path = $_SESSION['foto_profil'] ?? '';
    $foto_uploaded = false;
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
      $tmp_name = $_FILES['foto_profil']['tmp_name'];
      $ext = strtolower(pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION));
      $allowed = ['jpg', 'jpeg', 'png', 'gif'];
      if (in_array($ext, $allowed)) {
        $target_dir = __DIR__ . '/../uploads/foto_profil/';
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $filename = 'profil_' . $nip . '_' . time() . '.' . $ext;
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($tmp_name, $target_file)) {
          // Hapus foto lama jika ada dan berbeda
          if (!empty($foto_path) && file_exists(__DIR__ . '/../' . $foto_path) && $foto_path !== 'uploads/foto_profil/' . $filename) {
            @unlink(__DIR__ . '/../' . $foto_path);
          }
          $foto_path = 'uploads/foto_profil/' . $filename;
          $foto_uploaded = true;
        }
      }
    }

    // Validasi hanya jika bukan upload foto saja (yaitu jika form edit profil dibuka dari modal)
    $is_edit_form = isset($_POST['nama']) && isset($_POST['jabatan']) && isset($_POST['unit_kerja']);
    if ($is_edit_form && (!$nama_baru || !$jabatan_baru || !$unit_kerja_baru)) {
      set_swal('error', 'Gagal', 'Nama, Jabatan, dan Unit Kerja tidak boleh kosong.');
    } else {
      $stmt = $conn->prepare("UPDATE pegawai SET nama=?, jabatan=?, unit_kerja=?, nip_penilai=?, nama_penilai=?, foto_profil=? WHERE nip=?");
      $stmt->bind_param("sssssss", $nama_baru, $jabatan_baru, $unit_kerja_baru, $nip_penilai_baru, $nama_penilai_baru, $foto_path, $nip);
      if ($stmt->execute()) {
        $_SESSION['nama'] = $nama_baru;
        $_SESSION['jabatan'] = $jabatan_baru;
        $_SESSION['unit_kerja'] = $unit_kerja_baru;
        $_SESSION['nip_penilai'] = $nip_penilai_baru;
        $_SESSION['nama_penilai'] = $nama_penilai_baru;
        $_SESSION['foto_profil'] = $foto_path;
        
        if ($foto_uploaded) {
          set_swal('success', 'Berhasil', 'Foto profil berhasil diperbarui!');
        } else {
          set_swal('success', 'Berhasil', 'Profil berhasil diperbarui!');
        }
        
        echo '<script>window.location.href="profil.php";</script>';
        exit();
      } else {
        set_swal('error', 'Gagal', 'Gagal memperbarui profil: ' . $stmt->error);
      }
      $stmt->close();
    }
  }
  
  if (isset($_POST['change_password'])) {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
      set_swal('error', 'Gagal', 'Semua field password harus diisi.');
    } elseif ($new_password !== $confirm_password) {
      set_swal('error', 'Gagal', 'Konfirmasi password baru tidak cocok.');
    } elseif (strlen($new_password) < 6) {
      set_swal('error', 'Gagal', 'Password baru minimal 6 karakter.');
    } else {
      $stmt = $conn->prepare("SELECT password FROM pegawai WHERE nip=?");
      $stmt->bind_param("s", $nip);
      $stmt->execute();
      $stmt->bind_result($hash);
      $stmt->fetch();
      $stmt->close();
      if (!password_verify($old_password, $hash)) {
        set_swal('error', 'Gagal', 'Password lama salah.');
      } else {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE pegawai SET password=? WHERE nip=?");
        $stmt->bind_param("ss", $new_hash, $nip);
        if ($stmt->execute()) {
          set_swal('success', 'Berhasil', 'Password berhasil diubah!');
          echo '<script>window.location.href="profil.php";</script>';
          exit();
        } else {
          set_swal('error', 'Gagal', 'Gagal mengubah password: ' . $stmt->error);
        }
        $stmt->close();
      }
    }
  }
  
  // Handle remove photo
  if (isset($_POST['remove_foto'])) {
    if ($foto_profil && file_exists(__DIR__ . '/../' . $foto_profil)) {
      @unlink(__DIR__ . '/../' . $foto_profil);
    }
    $stmt = $conn->prepare("UPDATE pegawai SET foto_profil=NULL WHERE nip=?");
    $stmt->bind_param("s", $nip);
    if ($stmt->execute()) {
      $_SESSION['foto_profil'] = '';
      set_swal('success', 'Berhasil', 'Foto profil berhasil dihapus!');
    } else {
      set_swal('error', 'Gagal', 'Gagal menghapus foto profil.');
    }
    $stmt->close();
    echo '<script>window.location.href="profil.php";</script>';
    exit();
  }
}

// Ambil path foto profil dari session/database
$foto_profil = $_SESSION['foto_profil'] ?? '';
if (!$foto_profil) {
  // Cek di database jika belum ada di session
  // Cek apakah kolom foto_profil ada di tabel pegawai
  $result = $conn->query("SHOW COLUMNS FROM pegawai LIKE 'foto_profil'");
  if ($result && $result->num_rows > 0) {
    $stmt = $conn->prepare("SELECT foto_profil FROM pegawai WHERE nip=?");
    $stmt->bind_param("s", $nip);
    $stmt->execute();
    $stmt->bind_result($foto_db);
    $stmt->fetch();
    $stmt->close();
    $foto_profil = $foto_db ?: '';
    $_SESSION['foto_profil'] = $foto_profil;
  } else {
    // Kolom foto_profil tidak ada, jangan gunakan fitur foto
    $foto_profil = '';
    $_SESSION['foto_profil'] = '';
  }
}

// Ambil pesan sukses dari query string jika ada
if (isset($_GET['success'])) {
  $success_message = "Profil berhasil diperbarui.";
}
if (isset($_GET['success_pwd'])) {
  $success_message = "Password berhasil diubah.";
}
?>
<div id="layoutSidenav_content">
  <main>
    <div class="container-fluid px-4" style="max-width:100vw;">
      <h1 class="mt-4 mb-4">Profil Saya</h1>
      <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
      <?php endif; ?>
      <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
      <?php endif; ?>
      <div class="row justify-content-center">
        <div class="col-12"> <!-- Full width -->
          <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white d-flex align-items-center">
              <i class="fas fa-user-circle me-2"></i>
              <span>Data Pegawai</span>
            </div>
            <div class="card-body">
              <div class="d-flex flex-column align-items-center mb-4">
                <div class="position-relative" style="width: 110px; height: 110px;">
                  <?php if ($foto_profil && file_exists(__DIR__ . '/../' . $foto_profil)): ?>
                    <img src="/<?= htmlspecialchars($foto_profil) ?>" alt="Avatar" class="rounded-circle shadow" width="110" height="110" style="object-fit:cover;">
                  <?php else: ?>
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama) ?>&background=0d6efd&color=fff&size=110" alt="Avatar" class="rounded-circle shadow" width="110" height="110">
                  <?php endif; ?>
                  <button type="button" class="btn btn-sm btn-primary position-absolute bottom-0 end-0 rounded-circle"
                    style="width:38px;height:38px;padding:0;"
                    onclick="document.getElementById('fotoProfilInput').click();"
                    title="Ubah Foto Profil">
                    <i class="fas fa-camera"></i>
                  </button>
                  <form method="post" enctype="multipart/form-data" class="d-none" id="formFotoProfil">
                    <input type="hidden" name="update_profile" value="1">
                    <input type="file" name="foto_profil" id="fotoProfilInput" accept="image/*" onchange="document.getElementById('formFotoProfil').submit()">
                  </form>
                </div>
                <?php if ($foto_profil && file_exists(__DIR__ . '/../' . $foto_profil)): ?>
                  <form method="post" class="mt-2" onsubmit="return confirmDelete()">
                    <input type="hidden" name="remove_foto" value="1">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i> Hapus Foto</button>
                  </form>
                <?php endif; ?>
              </div>
              <div class="text-center mb-3">
                <h4 class="mb-1"><?= htmlspecialchars($nama) ?></h4>
                <span class="badge bg-<?= ($role == 'admin' ? 'warning' : 'info'); ?> text-dark text-capitalize"><?= ($role == 'admin' ? 'Admin' : 'Pegawai') ?></span>
              </div>
              <div class="table-responsive mb-3">
                <table class="table table-borderless table-sm mb-0">
                  <tbody>
                    <tr>
                      <th class="text-end" style="width: 40%"><i class="fas fa-id-badge me-2"></i> NIP</th>
                      <td><?= htmlspecialchars($nip) ?></td>
                    </tr>
                    <tr>
                      <th class="text-end"><i class="fas fa-briefcase me-2"></i> Jabatan</th>
                      <td><?= htmlspecialchars($jabatan) ?></td>
                    </tr>
                    <tr>
                      <th class="text-end"><i class="fas fa-building me-2"></i> Unit Kerja</th>
                      <td><?= htmlspecialchars($unit_kerja) ?></td>
                    </tr>
                    <tr>
                      <th class="text-end"><i class="fas fa-user-check me-2"></i> NIP Penilai</th>
                      <td><?= htmlspecialchars($nip_penilai ?: '-') ?></td>
                    </tr>
                    <tr>
                      <th class="text-end"><i class="fas fa-user-tie me-2"></i> Nama Penilai</th>
                      <td><?= htmlspecialchars($nama_penilai ?: '-') ?></td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalEditProfil">
                  <i class="fas fa-pencil-alt me-1"></i>Edit Profil
                </button>
                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalUbahPassword">
                  <i class="fas fa-key me-1"></i>Ubah Password
                </button>
              </div>
              <div class="text-end mt-3">
            <a href="../logout.php" class="btn btn-outline-danger logout-btn"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
          </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
  <?php include __DIR__ . '/../template/footer.php'; ?>
</div>

<!-- Modal Edit Profil -->
<div class="modal fade" id="modalEditProfil" tabindex="-1" aria-labelledby="modalEditProfilLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" enctype="multipart/form-data" autocomplete="off" class="modal-content" onsubmit="return validateEditForm()">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="modalEditProfilLabel"><i class="fas fa-pencil-alt me-2"></i>Edit Profil</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3 text-center">
          <?php if ($foto_profil && file_exists(__DIR__ . '/../' . $foto_profil)): ?>
            <img src="/<?= htmlspecialchars($foto_profil) ?>" alt="Avatar" class="rounded-circle shadow mb-2" width="80" height="80" style="object-fit:cover;">
          <?php else: ?>
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama) ?>&background=0d6efd&color=fff&size=80" alt="Avatar" class="rounded-circle shadow mb-2" width="80" height="80">
          <?php endif; ?>
        </div>
        <div class="mb-3">
          <label for="foto_profil_modal" class="form-label">Foto Profil (jpg/png, max 1MB)</label>
          <input type="file" class="form-control" id="foto_profil_modal" name="foto_profil" accept="image/*">
        </div>
        <div class="mb-3">
          <label for="edit_nama" class="form-label">Nama Lengkap</label>
          <input type="text" class="form-control" id="edit_nama" name="nama" value="<?= htmlspecialchars($nama) ?>" required>
        </div>
        <div class="mb-3">
          <label for="edit_jabatan" class="form-label">Jabatan</label>
          <input type="text" class="form-control" id="edit_jabatan" name="jabatan" value="<?= htmlspecialchars($jabatan) ?>" required>
        </div>
        <div class="mb-3">
          <label for="edit_unit_kerja" class="form-label">Unit Kerja</label>
          <input type="text" class="form-control" id="edit_unit_kerja" name="unit_kerja" value="<?= htmlspecialchars($unit_kerja) ?>" required>
        </div>
        <div class="mb-3">
          <label for="edit_nip_penilai" class="form-label">NIP Penilai</label>
          <input type="text" class="form-control" id="edit_nip_penilai" name="nip_penilai" value="<?= htmlspecialchars($nip_penilai) ?>">
        </div>
        <div class="mb-3">
          <label for="edit_nama_penilai" class="form-label">Nama Penilai</label>
          <input type="text" class="form-control" id="edit_nama_penilai" name="nama_penilai" value="<?= htmlspecialchars($nama_penilai) ?>">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" name="update_profile" class="btn btn-success">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Ubah Password -->
<div class="modal fade" id="modalUbahPassword" tabindex="-1" aria-labelledby="modalUbahPasswordLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" autocomplete="off" class="modal-content" onsubmit="return validatePasswordForm()">
      <div class="modal-header bg-secondary text-white">
        <h5 class="modal-title" id="modalUbahPasswordLabel"><i class="fas fa-key me-2"></i>Ubah Password</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="old_password" class="form-label">Password Lama</label>
          <input type="password" class="form-control" id="old_password" name="old_password" required>
        </div>
        <div class="mb-3">
          <label for="new_password" class="form-label">Password Baru</label>
          <input type="password" class="form-control" id="new_password" name="new_password" required>
        </div>
        <div class="mb-3">
          <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
          <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" name="change_password" class="btn btn-warning">Ubah Password</button>
      </div>
    </form>
  </div>
</div>

<!-- SweetAlert and Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Show SweetAlert if exists
  <?php if (isset($_SESSION['swal'])): ?>
    Swal.fire({
      icon: '<?php echo $_SESSION['swal']['type']; ?>',
      title: '<?php echo $_SESSION['swal']['title']; ?>',
      text: '<?php echo $_SESSION['swal']['text']; ?>',
      timer: 3000,
      showConfirmButton: false
    });
    <?php unset($_SESSION['swal']); ?>
  <?php endif; ?>
  
  // Initialize Bootstrap dropdowns
  setTimeout(function() {
    const dropdownElementList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'));
    
    dropdownElementList.forEach(function(dropdownToggleEl) {
      dropdownToggleEl.style.pointerEvents = 'auto';
      
      // Remove existing instance if any
      const existingDropdown = bootstrap.Dropdown.getInstance(dropdownToggleEl);
      if (existingDropdown) {
        existingDropdown.dispose();
      }
      
      // Create new dropdown instance
      new bootstrap.Dropdown(dropdownToggleEl, {
        autoClose: true,
        boundary: 'viewport'
      });
    });
    
    // Manual fallback for notification bell
    const notificationBell = document.querySelector('#notificationDropdown');
    if (notificationBell) {
      notificationBell.addEventListener('click', function(e) {
        e.stopPropagation();
        const dropdown = this.nextElementSibling;
        if (dropdown && dropdown.classList.contains('dropdown-menu')) {
          dropdown.classList.toggle('show');
        }
      });
    }
    
    // Manual fallback for profile dropdown
    const profileDropdown = document.querySelector('#navbarDropdown');
    if (profileDropdown) {
      profileDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
        const dropdown = this.nextElementSibling;
        if (dropdown && dropdown.classList.contains('dropdown-menu')) {
          dropdown.classList.toggle('show');
        }
      });
    }
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
          menu.classList.remove('show');
        });
      }
    });
  }, 100);
  
  // Handle notification link clicks
  setTimeout(function() {
    document.addEventListener('click', function(e) {
      const notificationDropdown = document.querySelector('[aria-labelledby="notificationDropdown"]');
      if (notificationDropdown && notificationDropdown.contains(e.target)) {
        const link = e.target.closest('a[href]');
        if (link && 
            link.href && 
            link.href !== '#' && 
            link.href !== '' &&
            !link.href.includes('profil.php') &&
            !link.href.includes('logout.php')) {
          e.preventDefault();
          e.stopPropagation();
          window.location.href = link.href;
        }
      }
    });
  }, 300);
  
  // Photo upload handler
  document.getElementById('fotoProfilInput').addEventListener('change', function() {
    if (this.files && this.files[0]) {
      const fileSize = this.files[0].size / 1024 / 1024;
      const fileType = this.files[0].type;
      
      if (!fileType.match(/image\/(jpeg|jpg|png|gif)/)) {
        Swal.fire({
          icon: 'error',
          title: 'Format Tidak Valid',
          text: 'Hanya file gambar (JPG, PNG, GIF) yang diizinkan.'
        });
        this.value = '';
        return;
      }
      
      if (fileSize > 1) {
        Swal.fire({
          icon: 'error',
          title: 'File Terlalu Besar',
          text: 'Ukuran file maksimal 1MB.'
        });
        this.value = '';
        return;
      }
      
      Swal.fire({
        title: 'Upload Foto',
        text: 'Apakah Anda yakin ingin mengubah foto profil?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Upload',
        cancelButtonText: 'Batal'
      }).then((result) => {
        if (result.isConfirmed) {
          document.getElementById('formFotoProfil').submit();
        } else {
          this.value = '';
        }
      });
    }
  });
  
  // Logout confirmation
  document.querySelectorAll('.logout-btn, a[href*="logout.php"]').forEach(function(logoutLink) {
    logoutLink.addEventListener('click', function(e) {
      e.preventDefault();
      const href = this.getAttribute('href');
      
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
    });
  });
});

// Form validation functions
function validateEditForm() {
  const nama = document.getElementById('edit_nama').value.trim();
  const jabatan = document.getElementById('edit_jabatan').value.trim();
  const unitKerja = document.getElementById('edit_unit_kerja').value.trim();
  
  if (!nama || !jabatan || !unitKerja) {
    Swal.fire({
      icon: 'error',
      title: 'Data Tidak Lengkap',
      text: 'Nama, Jabatan, dan Unit Kerja harus diisi.'
    });
    return false;
  }
  return true;
}

function validatePasswordForm() {
  const oldPassword = document.getElementById('old_password').value;
  const newPassword = document.getElementById('new_password').value;
  const confirmPassword = document.getElementById('confirm_password').value;
  
  if (!oldPassword || !newPassword || !confirmPassword) {
    Swal.fire({
      icon: 'error',
      title: 'Data Tidak Lengkap',
      text: 'Semua field password harus diisi.'
    });
    return false;
  }
  
  if (newPassword !== confirmPassword) {
    Swal.fire({
      icon: 'error',
      title: 'Password Tidak Cocok',
      text: 'Konfirmasi password baru tidak cocok.'
    });
    return false;
  }
  
  if (newPassword.length < 6) {
    Swal.fire({
      icon: 'error',
      title: 'Password Terlalu Pendek',
      text: 'Password baru minimal 6 karakter.'
    });
    return false;
  }
  
  return true;
}

function confirmDelete() {
  event.preventDefault();
  Swal.fire({
    title: 'Hapus Foto Profil',
    text: 'Apakah Anda yakin ingin menghapus foto profil?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      event.target.submit();
    }
  });
  return false;
}
</script>

<style>
/* Bootstrap dropdown fixes */
.dropdown-menu {
  z-index: 1050 !important;
  position: absolute !important;
}

.navbar-nav .dropdown-menu {
  position: absolute !important;
  top: 100% !important;
  right: 0 !important;
  left: auto !important;
}

.dropdown-toggle {
  pointer-events: auto !important;
  cursor: pointer !important;
}

.dropdown-toggle::after {
  pointer-events: none;
}

/* Notification dropdown positioning */
#notificationDropdown + .dropdown-menu {
  right: 0 !important;
  left: auto !important;
  min-width: 300px !important;
}

/* Profile dropdown positioning */
#navbarDropdown + .dropdown-menu {
  right: 0 !important;
  left: auto !important;
}
</style>