<?php
/**
 * E-LAPKIN Mobile RHK Management
 */

// Start output buffering to catch any unwanted output
ob_start();

session_start();

// Include mobile session config
require_once __DIR__ . '/config/mobile_session.php';
require_once __DIR__ . '/../config/database.php';

// Check mobile login (only validate session, not headers for dashboard)
checkMobileLogin();

// Get user session data
$userData = getMobileSessionData();
$id_pegawai_login = $userData['id_pegawai'];
$nama_pegawai_login = $userData['nama'];

$current_date = date('Y-m-d');
$current_year = (int)date('Y');

// Helper function for SweetAlert-like notifications
function set_mobile_notification($type, $title, $text) {
    $_SESSION['mobile_notification'] = [
        'type' => $type,
        'title' => $title,
        'text' => $text
    ];
}

// Get active period from user settings
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

// Ensure tahun_aktif column exists
function ensure_tahun_aktif_column($conn) {
    $result = $conn->query("SHOW COLUMNS FROM pegawai LIKE 'tahun_aktif'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE pegawai ADD COLUMN tahun_aktif INT DEFAULT NULL");
    }
}
ensure_tahun_aktif_column($conn);

$periode_aktif = get_periode_aktif($conn, $id_pegawai_login);

// Get active period for display
$activePeriod = getMobileActivePeriod($conn, $id_pegawai_login);

// Handle POST requests
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['set_periode_aktif'])) {
        $tahun_aktif_baru = (int)$_POST['tahun_aktif'];
        set_periode_aktif($conn, $id_pegawai_login, $tahun_aktif_baru);
        set_mobile_notification('success', 'Periode Diubah', 'Periode aktif berhasil diubah.');
        header('Location: rhk.php');
        exit();
    }

    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == 'add' || $action == 'edit') {
            $nama_rhk = trim($_POST['nama_rhk']);
            $aspek = trim($_POST['aspek']);
            $target = trim($_POST['target']);

            // Validation
            if (empty($nama_rhk) || empty($aspek) || empty($target)) {
                set_mobile_notification('error', 'Gagal', 'Semua field harus diisi.');
            } else {
                try {
                    if ($action == 'add') {
                        $stmt = $conn->prepare("INSERT INTO rhk (id_pegawai, nama_rhk, aspek, target) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("isss", $id_pegawai_login, $nama_rhk, $aspek, $target);
                    } else {
                        $id_rhk = (int)$_POST['id_rhk'];
                        $stmt = $conn->prepare("UPDATE rhk SET nama_rhk = ?, aspek = ?, target = ? WHERE id_rhk = ? AND id_pegawai = ?");
                        $stmt->bind_param("sssii", $nama_rhk, $aspek, $target, $id_rhk, $id_pegawai_login);
                    }

                    if ($stmt->execute()) {
                        set_mobile_notification('success', ($action == 'add') ? 'Berhasil' : 'Update Berhasil', ($action == 'add') ? "RHK berhasil ditambahkan!" : "RHK berhasil diperbarui!");
                    } else {
                        set_mobile_notification('error', 'Gagal', ($action == 'add') ? "Gagal menambahkan RHK: " . $stmt->error : "Gagal memperbarui RHK: " . $stmt->error);
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    set_mobile_notification('error', 'Gagal', 'Terjadi kesalahan database. Periksa data yang dimasukkan.');
                }
            }
            header('Location: rhk.php');
            exit();
            
        } elseif ($action == 'delete') {
            $id_rhk_to_delete = (int)$_POST['id_rhk'];
            
            // Check if RHK is used in RKB
            $cek_rkb = $conn->prepare("SELECT COUNT(*) FROM rkb WHERE id_rhk = ?");
            $cek_rkb->bind_param("i", $id_rhk_to_delete);
            $cek_rkb->execute();
            $cek_rkb->bind_result($jumlah_rkb);
            $cek_rkb->fetch();
            $cek_rkb->close();

            if ($jumlah_rkb > 0) {
                set_mobile_notification('error', 'Gagal', 'RHK tidak dapat dihapus karena sudah digunakan pada RKB.');
            } else {
                $stmt = $conn->prepare("DELETE FROM rhk WHERE id_rhk = ? AND id_pegawai = ?");
                $stmt->bind_param("ii", $id_rhk_to_delete, $id_pegawai_login);

                if ($stmt->execute()) {
                    set_mobile_notification('success', 'Berhasil', 'RHK berhasil dihapus!');
                } else {
                    set_mobile_notification('error', 'Gagal', "Gagal menghapus RHK: " . $stmt->error);
                }
                $stmt->close();
            }
            header('Location: rhk.php');
            exit();
        }
    }
}

// Check if period is not set
$stmt_check_periode = $conn->prepare("SELECT tahun_aktif FROM pegawai WHERE id_pegawai = ?");
$stmt_check_periode->bind_param("i", $id_pegawai_login);
$stmt_check_periode->execute();
$stmt_check_periode->bind_result($tahun_aktif_db);
$stmt_check_periode->fetch();
$stmt_check_periode->close();

$periode_belum_diatur = ($tahun_aktif_db === null);

// Get available years
$years = [];
$res = $conn->query("SELECT DISTINCT YEAR(created_at) as tahun FROM rhk WHERE id_pegawai = $id_pegawai_login ORDER BY tahun DESC");
while ($row = $res->fetch_assoc()) {
    $years[] = $row['tahun'];
}
if (empty($years)) $years[] = $current_year;
if (!in_array($current_year, $years)) {
    array_unshift($years, $current_year);
}

// Get RHK data for current year using created_at
$rhks = [];
$stmt = $conn->prepare("SELECT id_rhk, nama_rhk, aspek, target FROM rhk WHERE id_pegawai = ? AND YEAR(created_at) = ? ORDER BY created_at ASC");
$stmt->bind_param("ii", $id_pegawai_login, $periode_aktif);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $rhks[] = $row;
}
$stmt->close();

// Get previous RHK list for reference (from different years)
$previous_rhk_list = [];
$stmt_previous_rhk = $conn->prepare("
    SELECT 
        r1.nama_rhk,
        r1.aspek,
        r1.target
    FROM rhk r1
    INNER JOIN (
        SELECT 
            nama_rhk,
            MAX(id_rhk) as max_id_rhk
        FROM rhk 
        WHERE id_pegawai = ? AND YEAR(created_at) != ?
        GROUP BY nama_rhk
    ) r2 ON r1.nama_rhk = r2.nama_rhk
         AND r1.id_rhk = r2.max_id_rhk
    WHERE r1.id_pegawai = ? AND YEAR(r1.created_at) != ?
    ORDER BY r1.id_rhk DESC, r1.nama_rhk ASC
    LIMIT 20
");

$stmt_previous_rhk->bind_param("iiii", $id_pegawai_login, $periode_aktif, $id_pegawai_login, $periode_aktif);
$stmt_previous_rhk->execute();
$result_previous_rhk = $stmt_previous_rhk->get_result();

while ($row = $result_previous_rhk->fetch_assoc()) {
    $previous_rhk_list[] = [
        'nama_rhk' => $row['nama_rhk'],
        'aspek' => $row['aspek'],
        'target' => $row['target']
    ];
}

$stmt_previous_rhk->close();

// Clear any unwanted output before HTML
ob_clean();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RHK - E-Lapkin Mobile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/mobile.css">
</head>
<body>
    <!-- Mobile Header -->
    <header class="mobile-header">
        <div class="header-content">
            <div class="header-left">
                <button class="back-btn" onclick="window.location.href='dashboard.php'">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div class="page-info">
                    <h1 class="page-title">Rencana Hasil Kerja</h1>
                    <p class="page-subtitle">Kelola RHK Harian Anda</p>
                </div>
            </div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($userData['nama']) ?></div>
                <div class="user-details"><?= htmlspecialchars($userData['nip']) ?></div>
                <div class="user-details"><?= htmlspecialchars($activePeriod) ?></div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Replace existing content structure with consistent classes -->
        <!-- Example: Replace old alert with new structure -->
        <div class="alert alert-info">
            <div class="alert-icon">
                <i class="fas fa-info-circle"></i>
            </div>
            <div class="alert-content">
                <div class="alert-title">Informasi RHK</div>
                <div class="alert-text">
                    RHK adalah rencana kegiatan harian yang akan Anda lakukan...
                </div>
            </div>
        </div>

        <!-- Replace card structures -->
        <div class="card card-primary">
            <div class="card-header">
                <div class="card-info-section">
                    <div class="card-title">Tanggal RHK</div>
                    <span class="status-badge approved">Status</span>
                </div>
                <div class="card-actions">
                    <button class="btn btn-primary btn-sm">Action</button>
                </div>
            </div>
        </div>

        <!-- ...existing content adapted to new structure... -->
    </main>

    <!-- Bottom Navigation -->
    <?php include __DIR__ . '/components/bottom-nav.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/mobile.js"></script>
    
    <script>
        // Show notifications using MobileUtils
        <?php if (isset($_SESSION['mobile_notification'])): ?>
            MobileUtils.showNotification(
                '<?= $_SESSION['mobile_notification']['type'] ?>',
                '<?= $_SESSION['mobile_notification']['title'] ?>',
                '<?= $_SESSION['mobile_notification']['text'] ?>'
            );
            <?php unset($_SESSION['mobile_notification']); ?>
        <?php endif; ?>

        // Auto show period modal if not set
        <?php if ($periode_belum_diatur): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var modalPeriode = new bootstrap.Modal(document.getElementById('modalPeriodeBelumDiatur'));
            modalPeriode.show();
        });
        <?php endif; ?>

        function submitPeriodForm() {
            const tahunDipilih = document.querySelector('#setPeriodeForm select[name="tahun_aktif"]').value;
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
        }

        function showPeriodChangeModal() {
            new bootstrap.Modal(document.getElementById('modalKonfirmasiPeriode')).show();
        }

        function submitPeriodChange() {
            document.getElementById('periodeForm').submit();
        }

        function confirmChangePeriod() {
            showPeriodChangeModal();
        }

        function showAddModal() {
            document.getElementById('rhkModalTitle').textContent = 'Tambah RHK';
            document.getElementById('rhkAction').value = 'add';
            document.getElementById('rhkId').value = '';
            document.getElementById('rhkForm').reset();
            document.getElementById('submitBtn').textContent = 'Simpan';
            new bootstrap.Modal(document.getElementById('rhkModal')).show();
        }

        function editRhk(id, nama, aspek, target) {
            document.getElementById('rhkModalTitle').textContent = 'Edit RHK';
            document.getElementById('rhkAction').value = 'edit';
            document.getElementById('rhkId').value = id;
            document.getElementById('namaRhk').value = nama;
            document.getElementById('aspek').value = aspek;
            document.getElementById('target').value = target;
            document.getElementById('submitBtn').textContent = 'Update';
            new bootstrap.Modal(document.getElementById('rhkModal')).show();
        }

        function showPreviousRhk() {
            new bootstrap.Modal(document.getElementById('previousRhkModal')).show();
        }

        function selectPreviousRhk(nama, aspek, target) {
            document.getElementById('namaRhk').value = nama;
            document.getElementById('aspek').value = aspek;
            document.getElementById('target').value = target;
            
            // Close previous RHK modal
            bootstrap.Modal.getInstance(document.getElementById('previousRhkModal')).hide();
            
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'RHK Terpilih!',
                text: 'Data RHK terdahulu berhasil disalin ke form.',
                timer: 1500,
                showConfirmButton: false
            });
            
            // Ensure add RHK modal stays open
            setTimeout(function() {
                const modalRhk = bootstrap.Modal.getInstance(document.getElementById('rhkModal'));
                if (!modalRhk || !modalRhk._isShown) {
                    const newModalRhk = new bootstrap.Modal(document.getElementById('rhkModal'));
                    newModalRhk.show();
                }
            }, 100);
        }

        function deleteRhk(id) {
            Swal.fire({
                title: 'Konfirmasi Hapus',
                text: 'Anda yakin ingin menghapus RHK ini? RHK yang sudah digunakan pada RKB tidak dapat dihapus.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteId').value = id;
                    document.getElementById('deleteForm').submit();
                }
            });
        }

        // Add smooth scroll animation for cards
        document.addEventListener('DOMContentLoaded', function() {
            // Animate cards on load
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Search functionality for previous RHK
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchPreviousRhk');
            const previousItems = document.querySelectorAll('.previous-rhk-item');
            const noDataMessage = document.getElementById('noDataPrevious');
            
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    let visibleCount = 0;
                    
                    previousItems.forEach(function(item) {
                        const nama = item.getAttribute('data-nama').toLowerCase();
                        const aspek = item.getAttribute('data-aspek').toLowerCase();
                        const target = item.getAttribute('data-target').toLowerCase();
                        
                        if (nama.includes(searchTerm) || aspek.includes(searchTerm) || target.includes(searchTerm)) {
                            item.style.display = '';
                            visibleCount++;
                        } else {
                            item.style.display = 'none';
                        }
                    });
                    
                    if (noDataMessage) {
                        if (visibleCount === 0 && searchTerm.length > 0) {
                            noDataMessage.classList.remove('d-none');
                        } else {
                            noDataMessage.classList.add('d-none');
                        }
                    }
                });
            }

            // Event listener for previous RHK modal when closed
            const modalPreviousRhkElement = document.getElementById('previousRhkModal');
            if (modalPreviousRhkElement) {
                modalPreviousRhkElement.addEventListener('hidden.bs.modal', function() {
                    // Ensure add RHK modal stays open after previous RHK modal is closed
                    setTimeout(function() {
                        const modalRhk = bootstrap.Modal.getInstance(document.getElementById('rhkModal'));
                        if (!modalRhk || !modalRhk._isShown) {
                            const newModalRhk = new bootstrap.Modal(document.getElementById('rhkModal'));
                            newModalRhk.show();
                        }
                    }, 100);
                });
            }

            // Reset form when add RHK modal is closed
            const modalRhkElement = document.getElementById('rhkModal');
            if (modalRhkElement) {
                modalRhkElement.addEventListener('hidden.bs.modal', function(e) {
                    // Check if previous RHK modal is open
                    const modalPrevRhk = bootstrap.Modal.getInstance(document.getElementById('previousRhkModal'));
                    if (!modalPrevRhk || !modalPrevRhk._isShown) {
                        // Reset form only if previous RHK modal is not open
                        this.querySelector('form').reset();
                    }
                });
            }
        });
    </script>
</body>
</html>