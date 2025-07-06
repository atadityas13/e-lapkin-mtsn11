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

// Periksa apakah pengguna sudah login, jika tidak, arahkan kembali ke halaman login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

$id_pegawai_login = $_SESSION['id_pegawai'];
$nama_pegawai_login = $_SESSION['nama'];
$role_pegawai_login = $_SESSION['role'];
$is_admin = ($role_pegawai_login === 'admin');


$success_message = '';
$error_message = '';

// Tanggal saat ini untuk default bulan/tahun
$current_month = (int)date('m');
$current_year = date('Y');

// Ambil/set periode aktif dari database (sama seperti di rhk.php)
function ensure_tahun_aktif_column($conn) {
    $result = $conn->query("SHOW COLUMNS FROM pegawai LIKE 'tahun_aktif'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE pegawai ADD COLUMN tahun_aktif INT DEFAULT NULL");
    }
}
ensure_tahun_aktif_column($conn);

// Ambil periode aktif tahun dari RHK (tabel pegawai)
function get_periode_aktif($conn, $id_pegawai) {
    $stmt = $conn->prepare("SELECT tahun_aktif FROM pegawai WHERE id_pegawai = ?");
    $stmt->bind_param("i", $id_pegawai);
    $stmt->execute();
    $stmt->bind_result($tahun_aktif);
    $stmt->fetch();
    $stmt->close();
    return $tahun_aktif ?: (int)date('Y');
}
$periode_aktif = get_periode_aktif($conn, $id_pegawai_login);

// Ambil daftar tahun yang tersedia untuk pilihan (tidak perlu modal periode di RKB)
$years = [];
$res = $conn->query("SELECT DISTINCT tahun FROM rkb WHERE id_pegawai = $id_pegawai_login ORDER BY tahun DESC");
while ($row = $res->fetch_assoc()) {
    if (!empty($row['tahun'])) { // Hindari array key kosong
        $years[] = $row['tahun'];
    }
}
$current_year = (int)date('Y');
if (empty($years)) $years[] = $current_year;
if (!in_array($current_year, $years)) {
    array_unshift($years, $current_year);
}

// Pastikan kolom bulan_aktif ada di tabel pegawai
function ensure_bulan_aktif_column($conn) {
    $result = $conn->query("SHOW COLUMNS FROM pegawai LIKE 'bulan_aktif'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE pegawai ADD COLUMN bulan_aktif TINYINT DEFAULT NULL");
    }
}
ensure_bulan_aktif_column($conn);

// Ambil/set bulan aktif dari database
function get_bulan_aktif($conn, $id_pegawai) {
    $stmt = $conn->prepare("SELECT bulan_aktif FROM pegawai WHERE id_pegawai = ?");
    $stmt->bind_param("i", $id_pegawai);
    $stmt->execute();
    $stmt->bind_result($bulan_aktif);
    $stmt->fetch();
    $stmt->close();
    return $bulan_aktif ?: (int)date('m');
}
function set_bulan_aktif($conn, $id_pegawai, $bulan) {
    $stmt = $conn->prepare("UPDATE pegawai SET bulan_aktif = ? WHERE id_pegawai = ?");
    $stmt->bind_param("ii", $bulan, $id_pegawai);
    $stmt->execute();
    $stmt->close();
}

// Helper untuk SweetAlert
function set_swal($type, $title, $text) {
    $_SESSION['swal'] = [
        'type' => $type,
        'title' => $title,
        'text' => $text
    ];
}

// Proses simpan bulan aktif jika ada POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_bulan_aktif'])) {
    $bulan_aktif_baru = (int)$_POST['bulan_aktif'];
    set_bulan_aktif($conn, $id_pegawai_login, $bulan_aktif_baru);
    set_swal('success', 'Periode Diubah', 'Periode bulan aktif berhasil diubah.');
    echo '<script>window.location="rkb.php";</script>';
    exit();
}

// --- LOGIKA TAMBAH/EDIT/HAPUS RKB ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == 'add' || $action == 'edit') {
            $id_rhk = (int)$_POST['id_rhk'];
            $uraian_kegiatan = trim($_POST['uraian_kegiatan']);
            $kuantitas = trim($_POST['kuantitas']);
            $satuan = trim($_POST['satuan']);
            $bulan = (int)$_POST['bulan'];
            $tahun = (int)$_POST['tahun'];

            if (empty($uraian_kegiatan) || empty($kuantitas) || empty($satuan) || empty($bulan) || empty($tahun)) {
                set_swal('error', 'Gagal', 'Semua field harus diisi.');
            } else {
                // Validate satuan against ENUM values
                $valid_satuan = ['Kegiatan','JP','Dokumen','Laporan','Hari','Jam','Menit','Unit'];
                if (!in_array($satuan, $valid_satuan)) {
                    set_swal('error', 'Gagal', 'Satuan tidak valid. Pilih salah satu: ' . implode(', ', $valid_satuan));
                    echo '<script>window.location="rkb.php?month=' . $bulan . '&year=' . $tahun . '";</script>';
                    exit();
                }
                
                try {
                    if ($action == 'add') {
                        $stmt = $conn->prepare("INSERT INTO rkb (id_pegawai, id_rhk, bulan, tahun, uraian_kegiatan, kuantitas, satuan) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("iiissss", $id_pegawai_login, $id_rhk, $bulan, $tahun, $uraian_kegiatan, $kuantitas, $satuan);
                    } else { // action == 'edit'
                        $id_rkb = (int)$_POST['id_rkb'];
                        $stmt = $conn->prepare("UPDATE rkb SET id_rhk = ?, bulan = ?, tahun = ?, uraian_kegiatan = ?, kuantitas = ?, satuan = ? WHERE id_rkb = ? AND id_pegawai = ?");
                        $stmt->bind_param("iissssii", $id_rhk, $bulan, $tahun, $uraian_kegiatan, $kuantitas, $satuan, $id_rkb, $id_pegawai_login);
                    }

                    if ($stmt->execute()) {
                        set_swal('success', ($action == 'add' ? 'Berhasil' : 'Update Berhasil'), ($action == 'add' ? 'RKB berhasil ditambahkan!' : 'RKB berhasil diperbarui!'));
                        echo '<script>window.location="rkb.php?month=' . $bulan . '&year=' . $tahun . '";</script>';
                        exit();
                    } else {
                        if (strpos($stmt->error, 'Data truncated') !== false) {
                            set_swal('error', 'Gagal', 'Data terlalu panjang untuk field satuan. Pilih salah satu opsi yang tersedia.');
                        } else {
                            set_swal('error', 'Gagal', ($action == 'add' ? "Gagal menambahkan RKB: " : "Gagal memperbarui RKB: ") . $stmt->error);
                        }
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'Data truncated') !== false) {
                        set_swal('error', 'Gagal', 'Terjadi kesalahan database: Data satuan tidak sesuai format yang diizinkan.');
                    } else {
                        set_swal('error', 'Gagal', 'Terjadi kesalahan database. Periksa data yang dimasukkan.');
                    }
                    echo '<script>window.location="rkb.php?month=' . $bulan . '&year=' . $tahun . '";</script>';
                    exit();
                }
            }
        } elseif ($action == 'delete') {
            $id_rkb_to_delete = (int)$_POST['id_rkb'];
            // Cek apakah RKB sudah digunakan di LKH
            $cek_lkh = $conn->prepare("SELECT COUNT(*) FROM lkh WHERE id_rkb = ?");
            $cek_lkh->bind_param("i", $id_rkb_to_delete);
            $cek_lkh->execute();
            $cek_lkh->bind_result($jumlah_lkh);
            $cek_lkh->fetch();
            $cek_lkh->close();

            if ($jumlah_lkh > 0) {
                set_swal('error', 'Gagal', 'RKB tidak dapat dihapus karena sudah digunakan pada LKH.');
                echo '<script>window.location="rkb.php?month=' . $_POST['month'] . '&year=' . $_POST['year'] . '";</script>';
                exit();
            }

            $stmt = $conn->prepare("DELETE FROM rkb WHERE id_rkb = ? AND id_pegawai = ?");
            $stmt->bind_param("ii", $id_rkb_to_delete, $id_pegawai_login);

            if ($stmt->execute()) {
                set_swal('success', 'Berhasil', 'RKB berhasil dihapus!');
                echo '<script>window.location="rkb.php?month=' . $_POST['month'] . '&year=' . $_POST['year'] . '";</script>';
                exit();
            } else {
                set_swal('error', 'Gagal', "Gagal menghapus RKB: " . $stmt->error);
            }
            $stmt->close();
        }
    }
}

// --- LOGIKA MENAMPILKAN DATA RKB ---
// Pindahkan inisialisasi filter bulan/tahun aktif ke sini (SEBELUM query data RKB)
$bulan_aktif = get_bulan_aktif($conn, $id_pegawai_login);
$filter_month = isset($_GET['month']) ? (int)$_GET['month'] : $bulan_aktif;
$filter_year = isset($_GET['year']) ? (int)$_GET['year'] : $periode_aktif;

// Cek apakah periode bulan belum pernah diatur oleh user (NULL di database)
$stmt_check_bulan = $conn->prepare("SELECT bulan_aktif FROM pegawai WHERE id_pegawai = ?");
$stmt_check_bulan->bind_param("i", $id_pegawai_login);
$stmt_check_bulan->execute();
$stmt_check_bulan->bind_result($bulan_aktif_db);
$stmt_check_bulan->fetch();
$stmt_check_bulan->close();

// Periode bulan belum diatur jika bulan_aktif masih NULL di database
$periode_bulan_belum_diatur = ($bulan_aktif_db === null);

// Gunakan $filter_year = $periode_aktif
$rkbs = [];
$stmt_rkb = $conn->prepare("SELECT rkb.id_rkb, rkb.id_rhk, rkb.bulan, rkb.tahun, rkb.uraian_kegiatan, rkb.kuantitas, rkb.satuan, rhk.nama_rhk
                            FROM rkb
                            JOIN rhk ON rkb.id_rhk = rhk.id_rhk
                            WHERE rkb.id_pegawai = ? AND rkb.bulan = ? AND rkb.tahun = ?
                            ORDER BY rkb.created_at ASC");
$stmt_rkb->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
$stmt_rkb->execute();
$result_rkb = $stmt_rkb->get_result();
while ($row = $result_rkb->fetch_assoc()) {
    $rkbs[] = $row;
}
$stmt_rkb->close();

// Data untuk mode edit (jika ada)
$edit_mode = false;
$edit_rkb = [
    'id_rkb' => '',
    'id_rhk' => '',
    'bulan' => $filter_month,
    'tahun' => $filter_year,
    'uraian_kegiatan' => '',
    'kuantitas' => '',
    'satuan' => ''
];
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $id_rkb_edit = (int)$_GET['id'];
    $stmt_get_edit = $conn->prepare("SELECT id_rkb, id_rhk, bulan, tahun, uraian_kegiatan, kuantitas, satuan FROM rkb WHERE id_rkb = ? AND id_pegawai = ?");
    $stmt_get_edit->bind_param("ii", $id_rkb_edit, $id_pegawai_login);
    $stmt_get_edit->execute();
    $result_get_edit = $stmt_get_edit->get_result();
    if ($result_get_edit->num_rows == 1) {
        $edit_rkb = $result_get_edit->fetch_assoc();
        $edit_mode = true;
    } else {
        $error_message = "RKB tidak ditemukan atau Anda tidak memiliki akses.";
    }
    $stmt_get_edit->close();
}

// Ambil daftar RHK untuk dropdown
$rhk_list = [];
$stmt_rhk_list = $conn->prepare("SELECT id_rhk, nama_rhk FROM rhk WHERE id_pegawai = ? ORDER BY nama_rhk ASC");
$stmt_rhk_list->bind_param("i", $id_pegawai_login);
$stmt_rhk_list->execute();
$result_rhk_list = $stmt_rhk_list->get_result();
while ($row = $result_rhk_list->fetch_assoc()) {
    $rhk_list[] = $row;
}
$stmt_rhk_list->close();

// Ambil daftar RKB terdahulu untuk referensi
$previous_rkb_list = [];

// Get unique uraian_kegiatan with latest kuantitas/satuan (compatible with ONLY_FULL_GROUP_BY)
$stmt_previous_rkb = $conn->prepare("
    SELECT 
        r1.uraian_kegiatan,
        r1.kuantitas,
        r1.satuan
    FROM rkb r1
    INNER JOIN (
        SELECT 
            uraian_kegiatan,
            MAX(created_at) as max_created_at
        FROM rkb 
        WHERE id_pegawai = ? AND NOT (bulan = ? AND tahun = ?)
        GROUP BY uraian_kegiatan
    ) r2 ON r1.uraian_kegiatan = r2.uraian_kegiatan 
         AND r1.created_at = r2.max_created_at
    WHERE r1.id_pegawai = ? AND NOT (r1.bulan = ? AND r1.tahun = ?)
    ORDER BY r1.created_at DESC, r1.uraian_kegiatan ASC
");

$stmt_previous_rkb->bind_param("iiiiii", $id_pegawai_login, $filter_month, $filter_year, $id_pegawai_login, $filter_month, $filter_year);
$stmt_previous_rkb->execute();
$result_previous_rkb = $stmt_previous_rkb->get_result();

while ($row = $result_previous_rkb->fetch_assoc()) {
    $previous_rkb_list[] = [
        'uraian_kegiatan' => $row['uraian_kegiatan'],
        'kuantitas' => $row['kuantitas'],
        'satuan' => $row['satuan']
    ];
}

$stmt_previous_rkb->close();

// Data untuk dropdown bulan
$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$page_title = "Manajemen RKB";
include __DIR__ . '/../template/header.php';
include __DIR__ . '/../template/menu_user.php';
include __DIR__ . '/../template/topbar.php';

          // Cek status verval periode aktif (PASTIKAN INI DIDEKLARASIKAN SEBELUM DIPAKAI DI TOMBOL TAMBAH RKB DAN HEADER TABEL)
          $status_verval = '';
          $stmt = $conn->prepare("SELECT status_verval FROM rkb WHERE id_pegawai = ? AND bulan = ? AND tahun = ? LIMIT 1");
          $stmt->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
          $stmt->execute();
          $stmt->bind_result($status_verval);
          $stmt->fetch();
          $stmt->close();
?>
<div id="layoutSidenav_content">
  <main>
    <div class="container-fluid px-4">
      <h1 class="mt-4">Rencana Kinerja Bulanan (RKB)</h1>
      <div class="mb-2">
        <span class="fw-semibold">Periode Aktif:</span>
        <span class="badge bg-info text-dark"><?= $months[$filter_month] . ' ' . $filter_year ?></span>
      </div>

      <!-- Modal Notifikasi Periode Bulan Belum Diatur -->
      <?php if ($periode_bulan_belum_diatur): ?>
      <div class="modal fade" id="modalPeriodeBulanBelumDiatur" tabindex="-1" aria-labelledby="modalPeriodeBulanBelumDiaturLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-info text-white">
              <h5 class="modal-title" id="modalPeriodeBulanBelumDiaturLabel">
                <i class="fas fa-calendar-alt me-2"></i>Periode Bulan Belum Diatur
              </h5>
            </div>
            <div class="modal-body">
              <div class="alert alert-warning mb-3">
                <i class="fas fa-info-circle me-1"></i>
                <strong>Informasi:</strong> Periode bulan untuk RKB belum diatur. Silakan pilih periode bulan yang akan digunakan.
              </div>
              <p>Silakan pilih periode bulan yang akan digunakan untuk Rencana Kinerja Bulanan (RKB) Anda:</p>
              <form id="setBulanForm" action="rkb.php" method="POST">
                <div class="mb-3">
                  <label for="bulan_aktif_modal" class="form-label fw-semibold">Pilih Bulan Periode:</label>
                  <select class="form-select" id="bulan_aktif_modal" name="bulan_aktif" required>
                    <option value="">-- Pilih Bulan --</option>
                    <?php foreach ($months as $num => $name): ?>
                      <option value="<?= $num ?>" <?= ($num == (int)date('m')) ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <input type="hidden" name="set_bulan_aktif" value="1">
              </form>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-info" id="btnSetBulan">
                <i class="fas fa-check me-1"></i>Atur Periode Bulan
              </button>
            </div>
          </div>
        </div>
      </div>

      <script>
        document.addEventListener('DOMContentLoaded', function() {
          // Auto show modal jika periode bulan belum diatur
          <?php if ($periode_bulan_belum_diatur): ?>
          var modalBulan = new bootstrap.Modal(document.getElementById('modalPeriodeBulanBelumDiatur'));
          modalBulan.show();
          <?php endif; ?>
          
          // Handle set periode bulan
          document.getElementById('btnSetBulan').addEventListener('click', function() {
            const bulanDipilih = document.getElementById('bulan_aktif_modal').value;
            if (!bulanDipilih) {
              Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Silakan pilih bulan terlebih dahulu.',
                timer: 2000
              });
              return;
            }
            document.getElementById('setBulanForm').submit();
          });
        });
      </script>
      <?php endif; ?>

      <!-- Pilih Periode Bulan Aktif dengan Modal Konfirmasi -->
      <div class="mb-4">
        <form id="bulanForm" action="rkb.php" method="POST" class="d-flex align-items-center gap-2">
          <label for="bulan_aktif" class="form-label mb-0 me-2 fw-semibold">Pilih Periode Bulan :</label>
          <select class="form-select w-auto" id="bulan_aktif" name="bulan_aktif">
            <?php foreach ($months as $num => $name): ?>
              <option value="<?= $num ?>" <?= ($bulan_aktif == $num) ? 'selected' : '' ?>><?= $name ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn btn-primary ms-2" id="btnSimpanBulan">Ubah Periode</button>
          <input type="hidden" name="set_bulan_aktif" value="1">
        </form>
      </div>
      <div class="modal fade" id="modalKonfirmasiBulan" tabindex="-1" aria-labelledby="modalKonfirmasiBulanLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-warning">
              <h5 class="modal-title" id="modalKonfirmasiBulanLabel">Konfirmasi Ubah Bulan</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
              Apakah anda yakin ingin mengubah bulan aktif? Data RKB yang tampil akan mengikuti bulan yang dipilih.
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="button" class="btn btn-primary" id="btnKonfirmasiBulan">Ya, Ubah Bulan</button>
            </div>
          </div>
        </div>
      </div>
      <script>
        document.getElementById('btnSimpanBulan').addEventListener('click', function(e) {
          var modal = new bootstrap.Modal(document.getElementById('modalKonfirmasiBulan'));
          modal.show();
        });
        document.getElementById('btnKonfirmasiBulan').addEventListener('click', function() {
          document.getElementById('bulanForm').submit();
        });
      </script>

      <!-- Modal Ajukan Verval RKB -->
      <div class="modal fade" id="modalAjukanVerval" tabindex="-1" aria-labelledby="modalAjukanVervalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <form method="post" class="modal-content">
            <div class="modal-header bg-success text-white">
              <h5 class="modal-title" id="modalAjukanVervalLabel"><i class="fas fa-paper-plane me-2"></i>Ajukan Verval RKB</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
              <div class="mb-2">
                <strong>Ajukan Verval RKB?</strong>
              </div>
              <div class="mb-3">
                RKB akan bisa digenerate setelah di verval oleh Pejabat Penilai.
              </div>
              <?php if (empty($rkbs)): ?>
              <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-1"></i>
                <strong>Peringatan:</strong> Belum ada data RKB untuk periode ini. Silakan tambah RKB terlebih dahulu.
              </div>
              <?php endif; ?>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" name="ajukan_verval" class="btn btn-success" <?php echo empty($rkbs) ? 'disabled' : ''; ?>>Ajukan</button>
            </div>
          </form>
        </div>
      </div>

      <?php
      // Proses pengajuan verval RKB
      if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajukan_verval'])) {
          // Cek apakah ada data RKB untuk periode ini
          $stmt_check = $conn->prepare("SELECT COUNT(*) FROM rkb WHERE id_pegawai = ? AND bulan = ? AND tahun = ?");
          $stmt_check->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
          $stmt_check->execute();
          $stmt_check->bind_result($count_rkb);
          $stmt_check->fetch();
          $stmt_check->close();
          
          if ($count_rkb == 0) {
              set_swal('error', 'Gagal', 'Tidak dapat mengajukan verval karena belum ada data RKB untuk periode ini. Silakan tambah RKB terlebih dahulu.');
              echo '<script>window.location="rkb.php?month=' . $filter_month . '&year=' . $filter_year . '";</script>';
              exit();
          }

          $stmt = $conn->prepare("UPDATE rkb SET status_verval = 'diajukan' WHERE id_pegawai = ? AND bulan = ? AND tahun = ?");
          $stmt->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
          if ($stmt->execute()) {
              set_swal('success', 'Berhasil', 'Pengajuan verval RKB berhasil dikirim. Menunggu verifikasi Pejabat Penilai.');
              echo '<script>window.location="rkb.php?month=' . $filter_month . '&year=' . $filter_year . '";</script>';
              exit();
          } else {
              set_swal('error', 'Gagal', 'Gagal mengajukan verval RKB: ' . htmlspecialchars($stmt->error));
              echo '<script>window.location="rkb.php?month=' . $filter_month . '&year=' . $filter_year . '";</script>';
              exit();
          }
          $stmt->close();
      }
      ?>

      <!-- Pesan sukses/error -->
      <?php if (!empty($success_message)): ?>
        <div class="alert alert-success" role="alert">
          <?php echo $success_message; ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger" role="alert">
          <?php echo $error_message; ?>
        </div>
      <?php endif; ?>

      <!-- SweetAlert -->
      <?php if (isset($_SESSION['swal'])): ?>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
          document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
              icon: '<?php echo $_SESSION['swal']['type']; ?>',
              title: '<?php echo $_SESSION['swal']['title']; ?>',
              text: '<?php echo $_SESSION['swal']['text']; ?>',
              timer: 2000,
              showConfirmButton: false
            });
          });
        </script>
        <?php unset($_SESSION['swal']); ?>
      <?php endif; ?>

      <!-- SweetAlert konfirmasi hapus -->
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <script>
      document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.form-hapus-rkb').forEach(function(form) {
          form.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
              title: 'Konfirmasi Hapus',
              text: 'Anda yakin ingin menghapus RKB ini?',
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

      <!-- Tombol Tambah RKB Baru -->
      <div class="mb-3">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahRkb" <?php echo ($status_verval == 'diajukan' || $status_verval == 'disetujui') ? 'disabled' : ''; ?>>
          <i class="fas fa-plus me-1"></i> Tambah RKB
        </button>
      </div>

      <!-- Modal Tambah RKB -->
      <div class="modal fade" id="modalTambahRkb" tabindex="-1" aria-labelledby="modalTambahRkbLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
          <form action="rkb.php" method="POST" class="modal-content">
            <div class="modal-header bg-primary text-white">
              <h5 class="modal-title" id="modalTambahRkbLabel"><i class="fas fa-plus me-2"></i>Tambah RKB Baru</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="action" value="add">
              <!-- Bulan dan Tahun otomatis, tidak perlu field input -->
              <input type="hidden" name="bulan" value="<?php echo $filter_month; ?>">
              <input type="hidden" name="tahun" value="<?php echo htmlspecialchars($filter_year); ?>">
              <div class="mb-3">
                <label class="form-label">Periode</label>
                <input type="text" class="form-control" value="<?php echo $months[$filter_month] . ' ' . $filter_year; ?>" readonly>
              </div>
              <div class="mb-3">
                <label for="id_rhk_modal" class="form-label">Pilih RHK Terkait</label>
                <select class="form-select" id="id_rhk_modal" name="id_rhk" required>
                  <option value="">-- Pilih RHK --</option>
                  <?php foreach ($rhk_list as $rhk): ?>
                    <option value="<?php echo htmlspecialchars($rhk['id_rhk']); ?>">
                      <?php echo htmlspecialchars($rhk['nama_rhk']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if (empty($rhk_list)): ?>
                  <div class="form-text text-danger">Anda belum memiliki RHK. Silakan <a href="rhk.php">tambah RHK terlebih dahulu</a>.</div>
                <?php endif; ?>
              </div>
              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <label for="uraian_kegiatan_modal" class="form-label mb-0">Uraian Kinerja Bulanan (RKB)</label>
                  <?php if (!empty($previous_rkb_list)): ?>
                    <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalRkbTerdahulu">
                      <i class="fas fa-history me-1"></i>Lihat RKB Terdahulu
                    </button>
                  <?php endif; ?>
                </div>
                <textarea class="form-control" id="uraian_kegiatan_modal" name="uraian_kegiatan" rows="3" required></textarea>
              </div>
              <div class="mb-3">
                <label for="kuantitas_modal" class="form-label">Kuantitas Target</label>
                <input type="text" class="form-control" id="kuantitas_modal" name="kuantitas" placeholder="Contoh: 12" required>
              </div>
              <div class="mb-3">
                <label for="satuan_modal" class="form-label">Satuan Target</label>
                <select class="form-select" id="satuan_modal" name="satuan" required>
                  <option value="">-- Pilih Satuan --</option>
                  <option value="Kegiatan">Kegiatan</option>
                  <option value="JP">JP</option>
                  <option value="Dokumen">Dokumen</option>
                  <option value="Laporan">Laporan</option>
                  <option value="Hari">Hari</option>
                  <option value="Jam">Jam</option>
                  <option value="Menit">Menit</option>
                  <option value="Unit">Unit</option>
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-primary" <?php echo empty($rhk_list) ? 'disabled' : ''; ?>>Tambah RKB</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Modal RKB Terdahulu -->
      <div class="modal fade" id="modalRkbTerdahulu" tabindex="-1" aria-labelledby="modalRkbTerdahuluLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header bg-info text-white">
              <h5 class="modal-title" id="modalRkbTerdahuluLabel">
                <i class="fas fa-history me-2"></i>RKB Terdahulu
              </h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="alert alert-info">
                <i class="fas fa-info-circle me-1"></i>
                Pilih salah satu RKB terdahulu untuk mengisi form otomatis. Data akan disalin ke form tambah RKB.
              </div>
              
              <?php if (empty($previous_rkb_list)): ?>
                <div class="text-center py-4">
                  <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                  <p class="text-muted">Belum ada data RKB terdahulu yang dapat dijadikan referensi.</p>
                </div>
              <?php else: ?>
                <div class="mb-3">
                  <input type="text" class="form-control" id="searchRkbTerdahulu" placeholder="🔍 Cari RKB terdahulu...">
                </div>
                
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                  <table class="table table-hover table-sm">
                    <thead class="table-light">
                      <tr>
                        <th width="60%">Uraian Kegiatan</th>
                        <th width="20%">Kuantitas Terakhir</th>
                        <th width="20%">Satuan Terakhir</th>
                        <th width="10%">Aksi</th>
                      </tr>
                    </thead>
                    <tbody id="rkbTerdahuluTableBody">
                      <?php foreach ($previous_rkb_list as $prev_rkb): ?>
                        <tr class="rkb-row" data-uraian="<?php echo htmlspecialchars($prev_rkb['uraian_kegiatan']); ?>"
                            data-kuantitas="<?php echo htmlspecialchars($prev_rkb['kuantitas']); ?>"
                            data-satuan="<?php echo htmlspecialchars($prev_rkb['satuan']); ?>">
                          <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($prev_rkb['uraian_kegiatan']); ?></div>
                          </td>
                          <td class="text-center">
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($prev_rkb['kuantitas']); ?></span>
                          </td>
                          <td class="text-center">
                            <span class="badge bg-primary"><?php echo htmlspecialchars($prev_rkb['satuan']); ?></span>
                          </td>
                          <td class="text-center">
                            <button type="button" class="btn btn-sm btn-success pilih-rkb-btn"
                                    data-uraian="<?php echo htmlspecialchars($prev_rkb['uraian_kegiatan']); ?>"
                                    data-kuantitas="<?php echo htmlspecialchars($prev_rkb['kuantitas']); ?>"
                                    data-satuan="<?php echo htmlspecialchars($prev_rkb['satuan']); ?>"
                                    title="Pilih RKB ini">
                              <i class="fas fa-check me-1"></i><small>Gunakan</small>
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                
                <div id="noDataMessage" class="text-center py-3 d-none">
                  <i class="fas fa-search fa-2x text-muted mb-2"></i>
                  <p class="text-muted">Tidak ada RKB yang sesuai dengan pencarian.</p>
                </div>
              <?php endif; ?>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Form Edit RKB (hanya tampil jika edit_mode) -->
      <?php if ($edit_mode): ?>
      <div class="card mb-4">
        <div class="card-header bg-warning text-dark">
          Edit RKB
        </div>
        <div class="card-body">
          <form action="rkb.php" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id_rkb" value="<?php echo htmlspecialchars($edit_rkb['id_rkb']); ?>">
            <div class="mb-3">
              <label for="bulan" class="form-label">Bulan</label>
              <select class="form-select" id="bulan" name="bulan" required>
                <?php foreach ($months as $num => $name): ?>
                  <option value="<?php echo $num; ?>" <?php echo ($edit_rkb['bulan'] == $num) ? 'selected' : ''; ?>>
                    <?php echo $name; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label for="tahun" class="form-label">Tahun</label>
              <input type="number" class="form-control" id="tahun" name="tahun" value="<?php echo htmlspecialchars($edit_rkb['tahun']); ?>" required min="2020" max="2099">
            </div>
            <div class="mb-3">
              <label for="id_rhk" class="form-label">Pilih RHK Terkait</label>
              <select class="form-select" id="id_rhk" name="id_rhk" required>
                <option value="">-- Pilih RHK --</option>
                <?php foreach ($rhk_list as $rhk): ?>
                  <option value="<?php echo htmlspecialchars($rhk['id_rhk']); ?>" <?php echo ($edit_rkb['id_rhk'] == $rhk['id_rhk']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($rhk['nama_rhk']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (empty($rhk_list)): ?>
                <div class="form-text text-danger">Anda belum memiliki RHK. Silakan <a href="rhk.php">tambah RHK terlebih dahulu</a>.</div>
              <?php endif; ?>
            </div>
            <div class="mb-3">
              <label for="uraian_kegiatan" class="form-label">Uraian Kinerja Bulanan (RKB)</label>
              <textarea class="form-control" id="uraian_kegiatan" name="uraian_kegiatan" rows="3" required><?php echo htmlspecialchars($edit_rkb['uraian_kegiatan']); ?></textarea>
            </div>
            <div class="mb-3">
              <label for="kuantitas" class="form-label">Kuantitas Target</label>
              <input type="text" class="form-control" id="kuantitas" name="kuantitas" value="<?php echo htmlspecialchars($edit_rkb['kuantitas']); ?>" placeholder="Contoh: 12" required>
            </div>
            <div class="mb-3">
              <label for="satuan" class="form-label">Satuan Target</label>
              <select class="form-select" id="satuan" name="satuan" required>
                <option value="">-- Pilih Satuan --</option>
                <option value="Kegiatan" <?php echo ($edit_rkb['satuan'] == 'Kegiatan') ? 'selected' : ''; ?>>Kegiatan</option>
                <option value="JP" <?php echo ($edit_rkb['satuan'] == 'JP') ? 'selected' : ''; ?>>JP</option>
                <option value="Dokumen" <?php echo ($edit_rkb['satuan'] == 'Dokumen') ? 'selected' : ''; ?>>Dokumen</option>
                <option value="Laporan" <?php echo ($edit_rkb['satuan'] == 'Laporan') ? 'selected' : ''; ?>>Laporan</option>
                <option value="Hari" <?php echo ($edit_rkb['satuan'] == 'Hari') ? 'selected' : ''; ?>>Hari</option>
                <option value="Jam" <?php echo ($edit_rkb['satuan'] == 'Jam') ? 'selected' : ''; ?>>Jam</option>
                <option value="Menit" <?php echo ($edit_rkb['satuan'] == 'Menit') ? 'selected' : ''; ?>>Menit</option>
                <option value="Unit" <?php echo ($edit_rkb['satuan'] == 'Unit') ? 'selected' : ''; ?>>Unit</option>
              </select>
            </div>
            <button type="submit" class="btn btn-warning">Update RKB</button>
            <a href="rkb.php?month=<?php echo $edit_rkb['bulan']; ?>&year=<?php echo $edit_rkb['tahun']; ?>" class="btn btn-secondary">Batal Edit</a>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <!-- Daftar RKB -->
      <div class="card">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
          <span>Daftar Rencana Kinerja Bulanan (RKB) Anda - Bulan <?php echo $months[$filter_month] . ' ' . $filter_year; ?></span>
          <div class="btn-group">
            <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalPreviewRkb" <?php echo empty($rkbs) ? 'disabled title="Tidak ada data RKB untuk di-preview"' : ''; ?>>
              <i class="fas fa-eye me-1"></i> Preview RKB
            </button>
            <?php if ($status_verval == 'diajukan'): ?>
              <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalBatalVerval">
                <i class="fas fa-times me-1"></i> Batal Ajukan Verval
              </button>
            <?php elseif ($status_verval == '' || $status_verval == null || $status_verval == "ditolak"): ?>
              <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalAjukanVerval" <?php echo empty($rkbs) ? 'disabled title="Tidak dapat mengajukan verval karena belum ada data RKB"' : ''; ?>>
                <i class="fas fa-paper-plane me-1"></i> Ajukan Verval RKB
              </button>
            <?php endif; ?>
          </div>
        </div>
        <div class="card-body">
          <?php
          // Proses pengajuan/batal verval RKB
          if ($_SERVER['REQUEST_METHOD'] === 'POST') {
              if (isset($_POST['ajukan_verval'])) {
                  // Cek apakah ada data RKB untuk periode ini
                  $stmt_check = $conn->prepare("SELECT COUNT(*) FROM rkb WHERE id_pegawai = ? AND bulan = ? AND tahun = ?");
                  $stmt_check->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
                  $stmt_check->execute();
                  $stmt_check->bind_result($count_rkb);
                  $stmt_check->fetch();
                  $stmt_check->close();
                  
                  if ($count_rkb == 0) {
                      set_swal('error', 'Gagal', 'Tidak dapat mengajukan verval karena belum ada data RKB untuk periode ini. Silakan tambah RKB terlebih dahulu.');
                      echo '<script>window.location="rkb.php?month=' . $filter_month . '&year=' . $filter_year . '";</script>';
                      exit();
                  }

                  $stmt = $conn->prepare("UPDATE rkb SET status_verval = 'diajukan' WHERE id_pegawai = ? AND bulan = ? AND tahun = ?");
                  $stmt->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
                  if ($stmt->execute()) {
                      set_swal('success', 'Berhasil', 'Pengajuan verval RKB berhasil dikirim. Menunggu verifikasi PejabatPenilai.');
                  } else {
                      set_swal('error', 'Gagal', 'Gagal mengajukan verval RKB: ' . htmlspecialchars($stmt->error));
                  }
                  $stmt->close();
                  echo '<script>window.location="rkb.php?month=' . $filter_month . '&year=' . $filter_year . '";</script>';
                  exit();
              } elseif (isset($_POST['batal_verval'])) {
                  $stmt = $conn->prepare("UPDATE rkb SET status_verval = NULL WHERE id_pegawai = ? AND bulan = ? AND tahun = ?");
                  $stmt->bind_param("iii", $id_pegawai_login, $filter_month, $filter_year);
                  if ($stmt->execute()) {
                      set_swal('success', 'Dibatalkan', 'Pengajuan verval RKB dibatalkan. Anda dapat mengedit/mengirim ulang.');
                  } else {
                      set_swal('error', 'Gagal', 'Gagal membatalkan verval RKB: ' . htmlspecialchars($stmt->error));
                  }
                  $stmt->close();
                  echo '<script>window.location="rkb.php?month=' . $filter_month . '&year=' . $filter_year . '";</script>';
                  exit();
              }
          }

          if ($status_verval == 'diajukan') {
            echo '<div class="alert alert-info mb-3">RKB periode ini sudah diajukan dan menunggu verifikasi Pejabat Penilai.</div>';
          } elseif ($status_verval == 'disetujui') {
            echo '<div class="alert alert-success mb-3">RKB periode ini sudah diverifikasi/validasi oleh Pejabat Penilai.</div>';
          } elseif ($status_verval == 'ditolak') {
            echo '<div class="alert alert-danger mb-3">RKB periode ini ditolak oleh Pejabat Penilai. Silakan perbaiki dan ajukan ulang.</div>';
          }
          ?>
          <?php if (empty($rkbs)): ?>
            <div class="alert alert-info">Belum ada Rencana Kinerja Bulanan untuk bulan ini.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>No.</th>
                    <th>RHK Terkait</th>
                    <th>Uraian Kinerja Bulanan</th>
                    <th>Kuantitas</th>
                    <th>Satuan</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $no = 1; foreach ($rkbs as $rkb): ?>
                    <tr>
                      <td><?php echo $no++; ?></td>
                      <td><?php echo htmlspecialchars($rkb['nama_rhk']); ?></td>
                      <td><?php echo htmlspecialchars($rkb['uraian_kegiatan']); ?></td>
                      <td><?php echo htmlspecialchars($rkb['kuantitas']); ?></td>
                      <td><?php echo htmlspecialchars($rkb['satuan']); ?></td>
                      <td>
                        <a href="rkb.php?action=edit&id=<?php echo $rkb['id_rkb']; ?>&month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>"
                           class="btn btn-sm btn-warning mb-1"
                           <?php echo ($status_verval == 'diajukan') ? 'disabled style="pointer-events:none;opacity:0.6;"' : ''; ?>>
                          Edit
                        </a>
                        <form action="rkb.php" method="POST" style="display:inline-block;" class="form-hapus-rkb">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id_rkb" value="<?php echo $rkb['id_rkb']; ?>">
                          <input type="hidden" name="month" value="<?php echo $filter_month; ?>">
                          <input type="hidden" name="year" value="<?php echo $filter_year; ?>">
                          <button type="submit" class="btn btn-sm btn-danger mb-1 btn-hapus-rkb" <?php echo ($status_verval == 'diajukan') ? 'disabled' : ''; ?>>Hapus</button>
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

      <!-- Modal Ajukan Verval RKB -->
      <div class="modal fade" id="modalAjukanVerval" tabindex="-1" aria-labelledby="modalAjukanVervalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <form method="post" class="modal-content">
            <div class="modal-header bg-success text-white">
              <h5 class="modal-title" id="modalAjukanVervalLabel"><i class="fas fa-paper-plane me-2"></i>Ajukan Verval RKB</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
              <div class="mb-2">
                <strong>Ajukan Verval RKB?</strong>
              </div>
              <div class="mb-3">
                RKB akan bisa digenerate setelah di verval oleh Penilai (Admin).
              </div>
              <?php if (empty($rkbs)): ?>
              <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-1"></i>
                <strong>Peringatan:</strong> Belum ada data RKB untuk periode ini. Silakan tambah RKB terlebih dahulu.
              </div>
              <?php endif; ?>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" name="ajukan_verval" class="btn btn-success" <?php echo empty($rkbs) ? 'disabled' : ''; ?>>Ajukan</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Modal Batal Verval RKB -->
      <div class="modal fade" id="modalBatalVerval" tabindex="-1" aria-labelledby="modalBatalVervalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <form method="post" class="modal-content">
            <div class="modal-header bg-warning text-dark">
              <h5 class="modal-title" id="modalBatalVervalLabel"><i class="fas fa-times me-2"></i>Batal Ajukan Verval RKB</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
              <div class="mb-2">
                <strong>Batalkan pengajuan verval RKB?</strong>
              </div>
              <div>
                Anda dapat mengedit/menghapus/mengirim ulang RKB setelah membatalkan pengajuan verval.
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" name="batal_verval" class="btn btn-warning">Ya, Batalkan</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Modal Preview RKB -->
      <div class="modal fade" id="modalPreviewRkb" tabindex="-1" aria-labelledby="modalPreviewRkbLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header bg-info text-white">
              <h5 class="modal-title" id="modalPreviewRkbLabel">
                <i class="fas fa-eye me-2"></i>Preview Rencana Kinerja Bulanan (RKB)
              </h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <h6 class="fw-bold">Periode: <?php echo $months[$filter_month] . ' ' . $filter_year; ?></h6>
                <h6 class="fw-bold">Nama Pegawai: <?php echo htmlspecialchars($nama_pegawai_login); ?></h6>
              </div>
              
              <?php if (empty($rkbs)): ?>
                <div class="alert alert-info text-center">
                  <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                  <p class="text-muted mb-0">Belum ada data RKB untuk periode ini.</p>
                </div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-bordered table-striped">
                    <thead class="table-primary">
                      <tr class="text-center">
                        <th width="5%">No</th>
                        <th width="65%">Uraian Kegiatan</th>
                        <th width="15%">Jumlah</th>
                        <th width="15%">Satuan</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php $no = 1; foreach ($rkbs as $rkb): ?>
                        <tr>
                          <td class="text-center"><?php echo $no++; ?></td>
                          <td><?php echo htmlspecialchars($rkb['uraian_kegiatan']); ?></td>
                          <td class="text-center"><?php echo htmlspecialchars($rkb['kuantitas']); ?></td>
                          <td class="text-center"><?php echo htmlspecialchars($rkb['satuan']); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                
                <div class="mt-3">
                  <div class="row">
                    <div class="col-md-6">
                      <small class="text-muted">
                        <strong>Total RKB:</strong> <?php echo count($rkbs); ?> rencana kegiatan
                      </small>
                    </div>
                    <div class="col-md-6 text-end">
                      <small class="text-muted">
                        <strong>Status:</strong> 
                        <?php 
                        if ($status_verval == 'diajukan') {
                          echo '<span class="badge bg-warning">Menunggu Verifikasi</span>';
                        } elseif ($status_verval == 'disetujui') {
                          echo '<span class="badge bg-success">Disetujui</span>';
                        } elseif ($status_verval == 'ditolak') {
                          echo '<span class="badge bg-danger">Ditolak</span>';
                        } else {
                          echo '<span class="badge bg-secondary">Belum Diajukan</span>';
                        }
                        ?>
                      </small>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
  <?php include __DIR__ . '/../template/footer.php'; ?>
</div>

<script>
  // JavaScript untuk fitur RKB terdahulu
  document.addEventListener('DOMContentLoaded', function() {
    // Event listener untuk tombol pilih RKB
    document.querySelectorAll('.pilih-rkb-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        const uraian = this.getAttribute('data-uraian');
        const kuantitas = this.getAttribute('data-kuantitas');
        const satuan = this.getAttribute('data-satuan');
        
        // Isi form di modal tambah RKB
        document.getElementById('uraian_kegiatan_modal').value = uraian;
        document.getElementById('kuantitas_modal').value = kuantitas;
        document.getElementById('satuan_modal').value = satuan;
        
        // Tutup hanya modal RKB terdahulu, bukan modal tambah RKB
        const modalRkbTerdahulu = bootstrap.Modal.getInstance(document.getElementById('modalRkbTerdahulu'));
        if (modalRkbTerdahulu) {
          modalRkbTerdahulu.hide();
        }
        
        // Show success message
        Swal.fire({
          icon: 'success',
          title: 'RKB Terpilih!',
          text: 'Data RKB terdahulu berhasil disalin ke form.',
          timer: 1500,
          showConfirmButton: false
        });
        
        // Pastikan modal tambah RKB tetap terbuka
        setTimeout(function() {
          const modalTambahRkb = bootstrap.Modal.getInstance(document.getElementById('modalTambahRkb'));
          if (!modalTambahRkb || !modalTambahRkb._isShown) {
            const newModalTambahRkb = new bootstrap.Modal(document.getElementById('modalTambahRkb'));
            newModalTambahRkb.show();
          }
        }, 100);
      });
    });
    
    // Event listener untuk modal RKB terdahulu ketika ditutup
    document.getElementById('modalRkbTerdahulu').addEventListener('hidden.bs.modal', function() {
      // Pastikan modal tambah RKB tetap terbuka setelah modal RKB terdahulu ditutup
      setTimeout(function() {
        const modalTambahRkb = bootstrap.Modal.getInstance(document.getElementById('modalTambahRkb'));
        if (!modalTambahRkb || !modalTambahRkb._isShown) {
          const newModalTambahRkb = new bootstrap.Modal(document.getElementById('modalTambahRkb'));
          newModalTambahRkb.show();
        }
      }, 100);
    });
    
    // Search functionality untuk RKB terdahulu
    const searchInput = document.getElementById('searchRkbTerdahulu');
    const tableBody = document.getElementById('rkbTerdahuluTableBody');
    const noDataMessage = document.getElementById('noDataMessage');
    
    if (searchInput && tableBody) {
      searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = tableBody.querySelectorAll('.rkb-row');
        let visibleCount = 0;
        
        rows.forEach(function(row) {
          const uraian = row.getAttribute('data-uraian').toLowerCase();
          const kuantitas = row.getAttribute('data-kuantitas').toLowerCase();
          const satuan = row.getAttribute('data-satuan').toLowerCase();
          
          if (uraian.includes(searchTerm) || kuantitas.includes(searchTerm) || satuan.includes(searchTerm)) {
            row.style.display = '';
            visibleCount++;
          } else {
            row.style.display = 'none';
          }
        });
        
        // Show/hide no data message
        if (noDataMessage) {
          if (visibleCount === 0 && searchTerm.length > 0) {
            noDataMessage.classList.remove('d-none');
          } else {
            noDataMessage.classList.add('d-none');
          }
        }
      });
    }
    
    // Reset form ketika modal tambah RKB ditutup (hanya reset jika benar-benar ditutup oleh user)
    document.getElementById('modalTambahRkb').addEventListener('hidden.bs.modal', function(e) {
      // Cek apakah modal RKB terdahulu sedang terbuka
      const modalRkbTerdahulu = bootstrap.Modal.getInstance(document.getElementById('modalRkbTerdahulu'));
      if (!modalRkbTerdahulu || !modalRkbTerdahulu._isShown) {
        // Reset form hanya jika modal RKB terdahulu tidak terbuka
        this.querySelector('form').reset();
        // Reset periode display
        const periodeInput = this.querySelector('input[readonly]');
        if (periodeInput) {
          periodeInput.value = '<?php echo $months[$filter_month] . ' ' . $filter_year; ?>';
        }
      }
    });
  });
</script>

<!-- SweetAlert CSS/JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
