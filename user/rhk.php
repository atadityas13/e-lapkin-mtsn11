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

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

$id_pegawai_login = $_SESSION['id_pegawai'];
$nama_pegawai_login = $_SESSION['nama'];
$role_pegawai_login = $_SESSION['role'];

$success_message = '';
$error_message = '';

// Tambahkan helper SweetAlert
function set_swal($type, $title, $text) {
    $_SESSION['swal'] = [
        'type' => $type,
        'title' => $title,
        'text' => $text
    ];
}

// Proses tambah/edit RHK
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add' || $action === 'edit') {
        $nama_rhk = trim($_POST['nama_rhk']);
        $aspek = trim($_POST['aspek']);
        $target = trim($_POST['target']);

        if (empty($nama_rhk) || empty($aspek) || empty($target)) {
            set_swal('error', 'Gagal', 'Semua field harus diisi.');
        } else {
            if ($action === 'add') {
                $stmt = $conn->prepare("INSERT INTO rhk (id_pegawai, nama_rhk, aspek, target) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $id_pegawai_login, $nama_rhk, $aspek, $target);
            } else {
                $id_rhk = (int)$_POST['id_rhk'];
                $stmt = $conn->prepare("UPDATE rhk SET nama_rhk = ?, aspek = ?, target = ? WHERE id_rhk = ? AND id_pegawai = ?");
                $stmt->bind_param("sssii", $nama_rhk, $aspek, $target, $id_rhk, $id_pegawai_login);
            }

            if ($stmt->execute()) {
                set_swal('success', ($action === 'add') ? 'Berhasil' : 'Update Berhasil', ($action === 'add') ? "RHK berhasil ditambahkan!" : "RHK berhasil diperbarui!");
            } else {
                set_swal('error', 'Gagal', "Gagal menyimpan RHK: " . $stmt->error);
            }
            $stmt->close();
        }
        echo '<script>window.location="rhk.php";</script>';
        exit();
    } elseif ($action === 'delete') {
        $id_rhk_to_delete = (int)$_POST['id_rhk'];
        // Cek apakah RHK sudah digunakan di RKB
        $cek_rkb = $conn->prepare("SELECT COUNT(*) FROM rkb WHERE id_rhk = ?");
        $cek_rkb->bind_param("i", $id_rhk_to_delete);
        $cek_rkb->execute();
        $cek_rkb->bind_result($jumlah_rkb);
        $cek_rkb->fetch();
        $cek_rkb->close();

        if ($jumlah_rkb > 0) {
            set_swal('error', 'Gagal', 'RHK tidak dapat dihapus karena sudah digunakan pada RKB.');
        } else {
            $stmt = $conn->prepare("DELETE FROM rhk WHERE id_rhk = ? AND id_pegawai = ?");
            $stmt->bind_param("ii", $id_rhk_to_delete, $id_pegawai_login);

            if ($stmt->execute()) {
                set_swal('success', 'Berhasil', 'RHK berhasil dihapus!');
            } else {
                set_swal('error', 'Gagal', "Gagal menghapus RHK: " . $stmt->error);
            }
            $stmt->close();
        }
        echo '<script>window.location="rhk.php";</script>';
        exit();
    }
}

// Pastikan kolom tahun_aktif ada di tabel pegawai
function ensure_tahun_aktif_column($conn) {
    $result = $conn->query("SHOW COLUMNS FROM pegawai LIKE 'tahun_aktif'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE pegawai ADD COLUMN tahun_aktif INT DEFAULT NULL");
    }
}
ensure_tahun_aktif_column($conn);

// Ambil/set periode aktif dari database
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

// Proses simpan periode aktif jika ada POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_periode_aktif'])) {
    $tahun_aktif_baru = (int)$_POST['tahun_aktif'];
    set_periode_aktif($conn, $id_pegawai_login, $tahun_aktif_baru);
    set_swal('success', 'Periode Diubah', 'Periode aktif berhasil diubah.');
    echo '<script>window.location="rhk.php";</script>';
    exit();
}

// Ambil tahun aktif dari database
$periode_aktif = get_periode_aktif($conn, $id_pegawai_login);

// Cek apakah periode tahun belum pernah diatur oleh user (NULL di database)
$stmt_check_periode = $conn->prepare("SELECT tahun_aktif FROM pegawai WHERE id_pegawai = ?");
$stmt_check_periode->bind_param("i", $id_pegawai_login);
$stmt_check_periode->execute();
$stmt_check_periode->bind_result($tahun_aktif_db);
$stmt_check_periode->fetch();
$stmt_check_periode->close();

// Periode belum diatur jika tahun_aktif masih NULL di database
$periode_belum_diatur = ($tahun_aktif_db === null);

// Ambil daftar tahun yang tersedia untuk pilihan
$years = [];
$res = $conn->query("SELECT DISTINCT YEAR(created_at) as tahun FROM rhk WHERE id_pegawai = $id_pegawai_login ORDER BY tahun DESC");
while ($row = $res->fetch_assoc()) {
    $years[] = $row['tahun'];
}
$current_year = (int)date('Y');
if (empty($years)) $years[] = $current_year;

// Pastikan tahun berjalan (misal 2026) selalu muncul di pilihan meskipun belum ada data RHK di tahun itu
if (!in_array($current_year, $years)) {
    array_unshift($years, $current_year);
}

// Ambil data RHK hanya untuk tahun aktif
$rhks = [];
$stmt = $conn->prepare("SELECT id_rhk, nama_rhk, aspek, target FROM rhk WHERE id_pegawai = ? AND YEAR(created_at) = ? ORDER BY created_at ASC");
$stmt->bind_param("ii", $id_pegawai_login, $periode_aktif);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $rhks[] = $row;
}
$stmt->close();

$edit_mode = false;
$edit_rhk = ['id_rhk' => '', 'nama_rhk' => '', 'aspek' => '', 'target' => ''];

if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
    $id_rhk_edit = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT id_rhk, nama_rhk, aspek, target FROM rhk WHERE id_rhk = ? AND id_pegawai = ?");
    $stmt->bind_param("ii", $id_rhk_edit, $id_pegawai_login);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $edit_rhk = $result->fetch_assoc();
        $edit_mode = true;
    } else {
        $error_message = "RHK tidak ditemukan atau Anda tidak memiliki akses.";
    }
    $stmt->close();
}

$page_title = "Manajemen RHK";
include __DIR__ . '/../template/header.php';
include __DIR__ . '/../template/menu_user.php';
include __DIR__ . '/../template/topbar.php';
?>
<div id="layoutSidenav_content">
  <main>
    <div class="container-fluid px-4">
      <h1 class="mt-4">Rencana Hasil Kerja (RHK)</h1>

      <!-- Modal Notifikasi Periode Belum Diatur -->
      <?php if ($periode_belum_diatur): ?>
      <div class="modal fade" id="modalPeriodeBelumDiatur" tabindex="-1" aria-labelledby="modalPeriodeBelumDiaturLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
              <h5 class="modal-title" id="modalPeriodeBelumDiaturLabel">
                <i class="fas fa-exclamation-triangle me-2"></i>Periode Tahun Belum Diatur
              </h5>
            </div>
            <div class="modal-body">
              <div class="alert alert-info mb-3">
                <i class="fas fa-info-circle me-1"></i>
                <strong>Informasi:</strong> Periode tahun untuk RHK belum diatur. Silakan pilih periode tahun yang akan digunakan.
              </div>
              <p>Silakan pilih periode tahun yang akan digunakan untuk Rencana Hasil Kerja (RHK) Anda:</p>
              <form id="setPeriodeForm" action="rhk.php" method="POST">
                <div class="mb-3">
                  <label for="tahun_aktif_modal" class="form-label fw-semibold">Pilih Tahun Periode:</label>
                  <select class="form-select" id="tahun_aktif_modal" name="tahun_aktif" required>
                    <option value="">-- Pilih Tahun --</option>
                    <?php 
                    $current_year = (int)date('Y');
                    for ($i = $current_year - 2; $i <= $current_year + 2; $i++): ?>
                      <option value="<?= $i ?>" <?= ($i == $current_year) ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                  </select>
                </div>
                <input type="hidden" name="set_periode_aktif" value="1">
              </form>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-primary" id="btnSetPeriode">
                <i class="fas fa-check me-1"></i>Atur Periode
              </button>
            </div>
          </div>
        </div>
      </div>

      <script>
        document.addEventListener('DOMContentLoaded', function() {
          // Auto show modal jika periode belum diatur
          <?php if ($periode_belum_diatur): ?>
          var modalPeriode = new bootstrap.Modal(document.getElementById('modalPeriodeBelumDiatur'));
          modalPeriode.show();
          <?php endif; ?>
          
          // Handle set periode
          document.getElementById('btnSetPeriode').addEventListener('click', function() {
            const tahunDipilih = document.getElementById('tahun_aktif_modal').value;
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
          });
        });
      </script>
      <?php endif; ?>

      <!-- Pilih Periode Aktif (Tahun) dengan Modal Konfirmasi -->
      <div class="mb-4">
        <form id="periodeForm" action="rhk.php" method="POST" class="d-flex align-items-center gap-2">
          <label for="tahun_aktif" class="form-label mb-0 me-2 fw-semibold">Pilih Periode Aktif :</label>
          <select class="form-select w-auto" id="tahun_aktif" name="tahun_aktif">
            <?php foreach ($years as $year): ?>
              <option value="<?= $year ?>" <?= ($periode_aktif == $year) ? 'selected' : '' ?>><?= $year ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn btn-primary ms-2" id="btnSimpanPeriode">Ubah Periode</button>
          <input type="hidden" name="set_periode_aktif" value="1">
        </form>
      </div>

      <!-- Modal Konfirmasi Ubah Periode -->
      <div class="modal fade" id="modalKonfirmasiPeriode" tabindex="-1" aria-labelledby="modalKonfirmasiPeriodeLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-warning">
              <h5 class="modal-title" id="modalKonfirmasiPeriodeLabel">Konfirmasi Ubah Periode</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
              Apakah anda yakin ingin mengubah periode aktif? Data RHK yang tampil akan mengikuti periode yang dipilih.
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="button" class="btn btn-primary" id="btnKonfirmasiPeriode">Ya, Ubah Periode</button>
            </div>
          </div>
        </div>
      </div>

      <script>
        document.getElementById('btnSimpanPeriode').addEventListener('click', function(e) {
          var modal = new bootstrap.Modal(document.getElementById('modalKonfirmasiPeriode'));
          modal.show();
        });
        document.getElementById('btnKonfirmasiPeriode').addEventListener('click', function() {
          document.getElementById('periodeForm').submit();
        });
      </script>

      <?php if ($success_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
      <?php endif; ?>

      <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
      <?php endif; ?>

      <!-- Tombol Tambah RHK Baru -->
      <div class="mb-3">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahRhk">
          <i class="fas fa-plus me-1"></i> Tambah RHK
        </button>
      </div>

      <!-- Modal Tambah RHK -->
      <div class="modal fade" id="modalTambahRhk" tabindex="-1" aria-labelledby="modalTambahRhkLabel" aria-hidden="true">
        <div class="modal-dialog">
          <form method="POST" action="rhk.php" class="modal-content">
            <div class="modal-header bg-primary text-white">
              <h5 class="modal-title" id="modalTambahRhkLabel"><i class="fas fa-plus me-2"></i>Tambah RHK Baru</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="action" value="add">
              <div class="mb-3">
                <label for="nama_rhk_modal" class="form-label">Nama RHK / Uraian Kegiatan</label>
                <input type="text" class="form-control" id="nama_rhk_modal" name="nama_rhk" required>
              </div>
              <div class="mb-3">
                <label for="aspek_modal" class="form-label">Aspek</label>
                <select class="form-select" id="aspek_modal" name="aspek" required>
                  <option value="">Pilih Aspek</option>
                  <option value="Kuantitas">Kuantitas</option>
                  <option value="Kualitas">Kualitas</option>
                  <option value="Waktu">Waktu</option>
                  <option value="Biaya">Biaya</option>
                </select>
              </div>
              <div class="mb-3">
                <label for="target_modal" class="form-label">Target</label>
                <input type="text" class="form-control" id="target_modal" name="target" placeholder="Contoh: 24 JP, 1 Laporan" required>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-primary">Tambah RHK</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Edit RHK Card (hanya tampil jika edit_mode) -->
      <?php if ($edit_mode): ?>
      <div class="card mb-4">
        <div class="card-header bg-warning text-dark">
          Edit RHK
        </div>
        <div class="card-body">
          <form method="POST" action="rhk.php">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id_rhk" value="<?= htmlspecialchars($edit_rhk['id_rhk']) ?>">
            <div class="mb-3">
              <label for="nama_rhk" class="form-label">Nama RHK / Uraian Kegiatan</label>
              <input type="text" class="form-control" id="nama_rhk" name="nama_rhk" value="<?= htmlspecialchars($edit_rhk['nama_rhk']) ?>" required>
            </div>
            <div class="mb-3">
              <label for="aspek" class="form-label">Aspek</label>
              <select class="form-select" name="aspek" required>
                <option value="">Pilih Aspek</option>
                <?php foreach (["Kuantitas", "Kualitas", "Waktu", "Biaya"] as $opt): ?>
                  <option value="<?= $opt ?>" <?= ($edit_rhk['aspek'] == $opt) ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label for="target" class="form-label">Target</label>
              <input type="text" class="form-control" id="target" name="target" value="<?= htmlspecialchars($edit_rhk['target']) ?>" placeholder="Contoh: 24 JP, 1 Laporan" required>
            </div>
            <button type="submit" class="btn btn-warning">Update RHK</button>
            <a href="rhk.php" class="btn btn-secondary">Batal</a>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-header bg-secondary text-white">Daftar Rencana Hasil Kerja Anda - Tahun <?= $periode_aktif ?></div>
        <div class="card-body">
          <?php if (empty($rhks)): ?>
            <div class="alert alert-info">Belum ada RHK yang ditambahkan untuk tahun ini.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Nama RHK</th>
                    <th>Aspek</th>
                    <th>Target</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $no = 1; foreach ($rhks as $rhk): ?>
                    <tr>
                      <td><?= $no++ ?></td>
                      <td><?= htmlspecialchars($rhk['nama_rhk']) ?></td>
                      <td><?= htmlspecialchars($rhk['aspek']) ?></td>
                      <td><?= htmlspecialchars($rhk['target']) ?></td>
                      <td>
                        <a href="rhk.php?action=edit&id=<?= $rhk['id_rhk'] ?>" class="btn btn-sm btn-warning mb-1">Edit</a>
                        <form method="POST" action="rhk.php" style="display:inline;" class="form-hapus-rhk">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id_rhk" value="<?= $rhk['id_rhk'] ?>">
                          <button class="btn btn-sm btn-danger mb-1" type="submit">Hapus</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
  <?php include __DIR__ . '/../template/footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // SweetAlert untuk pesan status
  <?php if (isset($_SESSION['swal'])): ?>
    Swal.fire({
      icon: '<?php echo $_SESSION['swal']['type']; ?>',
      title: '<?php echo $_SESSION['swal']['title']; ?>',
      text: '<?php echo $_SESSION['swal']['text']; ?>',
      timer: 2000,
      showConfirmButton: false
    });
    <?php unset($_SESSION['swal']); ?>
  <?php endif; ?>

  // SweetAlert konfirmasi hapus RHK
  document.querySelectorAll('.form-hapus-rhk').forEach(function(form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      Swal.fire({
        title: 'Konfirmasi Hapus',
        text: 'Anda yakin ingin menghapus RHK ini?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
      }).then((result) => {
        if (result.isConfirmed) {
          form.submit();
        }
      });
    });
  });
});
</script>