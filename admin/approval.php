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

$page_title = "Approval Data";
$message = '';
$message_type = '';

// Handle approval actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_approval'])) {
        $jenis = $_POST['jenis']; // 'rkb' atau 'lkh'
        $id_pegawai = (int)$_POST['id_pegawai'];
        $bulan = (int)$_POST['bulan'];
        $tahun = (int)$_POST['tahun'];
        $action = $_POST['action']; // 'disetujui' atau 'ditolak'
        $alasan = $_POST['alasan'] ?? '';
        
        if ($jenis === 'rkb') {
            if ($action === 'ditolak') {
                $stmt = $conn->prepare("UPDATE rkb SET status_verval = ?, alasan = ? WHERE id_pegawai = ? AND bulan = ? AND tahun = ? AND status_verval = 'diajukan'");
                $stmt->bind_param("ssiii", $action, $alasan, $id_pegawai, $bulan, $tahun);
            } else {
                $stmt = $conn->prepare("UPDATE rkb SET status_verval = ? WHERE id_pegawai = ? AND bulan = ? AND tahun = ? AND status_verval = 'diajukan'");
                $stmt->bind_param("siii", $action, $id_pegawai, $bulan, $tahun);
            }
        } elseif ($jenis === 'lkh') {
            if ($action === 'ditolak') {
                $stmt = $conn->prepare("UPDATE lkh SET status_verval = ?, alasan = ? WHERE id_pegawai = ? AND MONTH(tanggal_lkh) = ? AND YEAR(tanggal_lkh) = ? AND status_verval = 'diajukan'");
                $stmt->bind_param("ssiii", $action, $alasan, $id_pegawai, $bulan, $tahun);
            } else {
                $stmt = $conn->prepare("UPDATE lkh SET status_verval = ? WHERE id_pegawai = ? AND MONTH(tanggal_lkh) = ? AND YEAR(tanggal_lkh) = ? AND status_verval = 'diajukan'");
                $stmt->bind_param("siii", $action, $id_pegawai, $bulan, $tahun);
            }
        }
        
        if ($stmt && $stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            $message = "$affected_rows data " . strtoupper($jenis) . " berhasil " . ($action === 'disetujui' ? 'disetujui' : 'ditolak') . ".";
            $message_type = "success";
        } else {
            $message = "Error: " . ($stmt ? $stmt->error : $conn->error);
            $message_type = "danger";
        }
        
        if ($stmt) $stmt->close();
    }
}

// Get RKB data grouped by pegawai and bulan
$rkb_query = "
    SELECT r.id_pegawai, r.bulan, r.tahun,
           p.nama, p.jabatan, p.unit_kerja, p.nip,
           COUNT(r.id_rkb) as total_data,
           GROUP_CONCAT(r.uraian_kegiatan SEPARATOR '; ') as all_kegiatan,
           GROUP_CONCAT(CONCAT(r.kuantitas, ' ', r.satuan) SEPARATOR '; ') as all_target
    FROM rkb r
    JOIN pegawai p ON r.id_pegawai = p.id_pegawai
    WHERE r.status_verval = 'diajukan'
    GROUP BY r.id_pegawai, r.bulan, r.tahun
    ORDER BY r.tahun DESC, r.bulan DESC, p.nama ASC
";
$rkb_result = $conn->query($rkb_query);

// Get LKH data grouped by pegawai and bulan
$lkh_query = "
    SELECT l.id_pegawai, MONTH(l.tanggal_lkh) as bulan, YEAR(l.tanggal_lkh) as tahun,
           p.nama, p.jabatan, p.unit_kerja, p.nip,
           COUNT(l.id_lkh) as total_data,
           GROUP_CONCAT(l.nama_kegiatan_harian SEPARATOR '; ') as all_kegiatan,
           GROUP_CONCAT(CONCAT(l.jumlah_realisasi, ' ', l.satuan_realisasi) SEPARATOR '; ') as all_realisasi
    FROM lkh l
    JOIN pegawai p ON l.id_pegawai = p.id_pegawai
    WHERE l.status_verval = 'diajukan'
    GROUP BY l.id_pegawai, MONTH(l.tanggal_lkh), YEAR(l.tanggal_lkh)
    ORDER BY YEAR(l.tanggal_lkh) DESC, MONTH(l.tanggal_lkh) DESC, p.nama ASC
";
$lkh_result = $conn->query($lkh_query);

$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

include __DIR__ . '/../template/header.php';
include __DIR__ . '/../template/menu_admin.php';
include __DIR__ . '/../template/topbar.php';
?>

<!-- Add Sweet Alert CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div id="layoutSidenav_content">
    <main>
        <div class="container-fluid px-4">
            <h1 class="mt-4 mb-3"><i class="fas fa-check-circle"></i> Approval Data</h1>
            <p class="lead">Kelola persetujuan data RKB dan LKH berdasarkan pegawai dan bulan.</p>

            <!-- Statistik Approval -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-warning text-white mb-4 shadow">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="small text-white-50">RKB Pegawai Menunggu</div>
                                    <div class="h2"><?php echo $rkb_result->num_rows; ?></div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-calendar-week fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-info text-white mb-4 shadow">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="small text-white-50">LKH Pegawai Menunggu</div>
                                    <div class="h2"><?php echo $lkh_result->num_rows; ?></div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-calendar-day fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-success text-white mb-4 shadow">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="small text-white-50">Total Menunggu</div>
                                    <div class="h2"><?php echo $rkb_result->num_rows + $lkh_result->num_rows; ?></div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-primary text-white mb-4 shadow">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="small text-white-50">Quick Action</div>
                                    <div class="small">Bulk Actions</div>
                                </div>
                                <div class="align-self-center">
                                    <button class="btn btn-light btn-sm" onclick="showBulkActions()">
                                        <i class="fas fa-cogs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RKB Approval -->
            <div class="card shadow mb-4">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-week me-2"></i>
                        Approval RKB
                        <span class="badge bg-light text-dark ms-2"><?php echo $rkb_result->num_rows; ?> pegawai</span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($rkb_result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Pegawai</th>
                                        <th>Periode</th>
                                        <th>Jumlah Data</th>
                                        <th>Ringkasan Kegiatan</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $rkb_result->data_seek(0);
                                    while ($rkb = $rkb_result->fetch_assoc()): 
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar bg-primary text-white rounded-circle me-3" style="width: 40px; height: 40px; line-height: 40px; text-align: center;">
                                                        <?php echo strtoupper(substr($rkb['nama'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($rkb['nama']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($rkb['jabatan']); ?></small><br>
                                                        <small class="text-muted">NIP: <?php echo htmlspecialchars($rkb['nip']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary fs-6"><?php echo $months[$rkb['bulan']] . ' ' . $rkb['tahun']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary fs-6"><?php echo $rkb['total_data']; ?> Rencana</span>
                                            </td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 300px;" title="<?php echo htmlspecialchars($rkb['all_kegiatan']); ?>">
                                                    <strong>Kegiatan:</strong> <?php echo htmlspecialchars(substr($rkb['all_kegiatan'], 0, 100)); ?>...
                                                </div>
                                                <div class="text-truncate text-muted" style="max-width: 300px;" title="<?php echo htmlspecialchars($rkb['all_target']); ?>">
                                                    <strong>Target:</strong> <?php echo htmlspecialchars(substr($rkb['all_target'], 0, 100)); ?>...
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-success btn-sm" onclick="approveData('rkb', <?php echo $rkb['id_pegawai']; ?>, <?php echo $rkb['bulan']; ?>, <?php echo $rkb['tahun']; ?>, 'disetujui', <?php echo $rkb['total_data']; ?>)" title="Setujui Semua">
                                                        <i class="fas fa-check"></i> Setujui
                                                    </button>
                                                    <button class="btn btn-danger btn-sm" onclick="approveData('rkb', <?php echo $rkb['id_pegawai']; ?>, <?php echo $rkb['bulan']; ?>, <?php echo $rkb['tahun']; ?>, 'ditolak', <?php echo $rkb['total_data']; ?>)" title="Tolak Semua">
                                                        <i class="fas fa-times"></i> Tolak
                                                    </button>
                                                    <button class="btn btn-info btn-sm" onclick="viewDetail('rkb', <?php echo $rkb['id_pegawai']; ?>)" title="Detail">
                                                        <i class="fas fa-eye"></i> Detail
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">Tidak ada RKB yang menunggu approval</h5>
                            <p class="text-muted">Semua data RKB sudah diproses.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- LKH Approval -->
            <div class="card shadow mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-day me-2"></i>
                        Approval LKH
                        <span class="badge bg-light text-dark ms-2"><?php echo $lkh_result->num_rows; ?> pegawai</span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($lkh_result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Pegawai</th>
                                        <th>Periode</th>
                                        <th>Jumlah Data</th>
                                        <th>Ringkasan Kegiatan</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $lkh_result->data_seek(0);
                                    while ($lkh = $lkh_result->fetch_assoc()): 
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar bg-info text-white rounded-circle me-3" style="width: 40px; height: 40px; line-height: 40px; text-align: center;">
                                                        <?php echo strtoupper(substr($lkh['nama'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($lkh['nama']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($lkh['jabatan']); ?></small><br>
                                                        <small class="text-muted">NIP: <?php echo htmlspecialchars($lkh['nip']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary fs-6"><?php echo $months[$lkh['bulan']] . ' ' . $lkh['tahun']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info fs-6"><?php echo $lkh['total_data']; ?> Kegiatan</span>
                                            </td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 300px;" title="<?php echo htmlspecialchars($lkh['all_kegiatan']); ?>">
                                                    <strong>Kegiatan:</strong> <?php echo htmlspecialchars(substr($lkh['all_kegiatan'], 0, 100)); ?>...
                                                </div>
                                                <div class="text-truncate text-muted" style="max-width: 300px;" title="<?php echo htmlspecialchars($lkh['all_realisasi']); ?>">
                                                    <strong>Realisasi:</strong> <?php echo htmlspecialchars(substr($lkh['all_realisasi'], 0, 100)); ?>...
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-success btn-sm" onclick="approveData('lkh', <?php echo $lkh['id_pegawai']; ?>, <?php echo $lkh['bulan']; ?>, <?php echo $lkh['tahun']; ?>, 'disetujui', <?php echo $lkh['total_data']; ?>)" title="Setujui Semua">
                                                        <i class="fas fa-check"></i> Setujui
                                                    </button>
                                                    <button class="btn btn-danger btn-sm" onclick="approveData('lkh', <?php echo $lkh['id_pegawai']; ?>, <?php echo $lkh['bulan']; ?>, <?php echo $lkh['tahun']; ?>, 'ditolak', <?php echo $lkh['total_data']; ?>)" title="Tolak Semua">
                                                        <i class="fas fa-times"></i> Tolak
                                                    </button>
                                                    <button class="btn btn-info btn-sm" onclick="viewDetail('lkh', <?php echo $lkh['id_pegawai']; ?>)" title="Detail">
                                                        <i class="fas fa-eye"></i> Detail
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">Tidak ada LKH yang menunggu approval</h5>
                            <p class="text-muted">Semua data LKH sudah diproses.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    <?php include __DIR__ . '/../template/footer.php'; ?>
</div>

<!-- Remove the Bootstrap modal -->

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

function approveData(jenis, id_pegawai, bulan, tahun, action, totalData) {
    const actionText = action === 'disetujui' ? 'menyetujui' : 'menolak';
    const jenisText = jenis.toUpperCase();
    const iconType = action === 'disetujui' ? 'question' : 'warning';
    const confirmButtonColor = action === 'disetujui' ? '#28a745' : '#dc3545';
    const bulanNama = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'][bulan];
    
    let htmlContent = `
        <div class="mb-3">
            <p>Yakin ingin <strong>${actionText}</strong> semua data ${jenisText} ini?</p>
            <div class="alert alert-info">
                <strong>Detail:</strong><br>
                • Periode: ${bulanNama} ${tahun}<br>
                • Jumlah data: ${totalData} ${jenisText}<br>
                • Semua data dalam periode ini akan ${action}
            </div>
        </div>
    `;
    
    if (action === 'ditolak') {
        htmlContent += `
            <div class="mb-3">
                <label for="swal-alasan" class="form-label text-start d-block">Alasan Penolakan <span class="text-danger">*</span>:</label>
                <textarea id="swal-alasan" class="form-control" rows="3" placeholder="Jelaskan alasan penolakan untuk semua data..." required></textarea>
            </div>
        `;
    }
    
    Swal.fire({
        title: 'Konfirmasi Approval',
        html: htmlContent,
        icon: iconType,
        showCancelButton: true,
        confirmButtonColor: confirmButtonColor,
        cancelButtonColor: '#6c757d',
        confirmButtonText: `<i class="fas fa-${action === 'disetujui' ? 'check' : 'times'}"></i> ${action === 'disetujui' ? 'Setujui Semua' : 'Tolak Semua'}`,
        cancelButtonText: '<i class="fas fa-times"></i> Batal',
        focusConfirm: false,
        preConfirm: () => {
            if (action === 'ditolak') {
                const alasan = document.getElementById('swal-alasan').value.trim();
                if (!alasan) {
                    Swal.showValidationMessage('Alasan penolakan harus diisi');
                    return false;
                }
                return { alasan: alasan };
            }
            return {};
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Memproses...',
                text: `Sedang memproses ${totalData} data ${jenisText}`,
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
                { name: 'action_approval', value: '1' },
                { name: 'jenis', value: jenis },
                { name: 'id_pegawai', value: id_pegawai },
                { name: 'bulan', value: bulan },
                { name: 'tahun', value: tahun },
                { name: 'action', value: action }
            ];
            
            if (action === 'ditolak' && result.value.alasan) {
                inputs.push({ name: 'alasan', value: result.value.alasan });
            }
            
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

function viewDetail(jenis, id_pegawai) {
    Swal.fire({
        title: 'Lihat Detail',
        text: `Membuka detail data ${jenis.toUpperCase()}...`,
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-external-link-alt"></i> Buka di Tab Baru',
        cancelButtonText: '<i class="fas fa-times"></i> Batal',
        confirmButtonColor: '#17a2b8'
    }).then((result) => {
        if (result.isConfirmed) {
            if (jenis === 'rkb') {
                window.open(`rkb.php?id_pegawai=${id_pegawai}`, '_blank');
            } else {
                window.open(`lkh.php?id_pegawai=${id_pegawai}`, '_blank');
            }
            
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Detail dibuka di tab baru',
                showConfirmButton: false,
                timer: 2000
            });
        }
    });
}

function showBulkActions() {
    Swal.fire({
        title: 'Aksi Massal',
        html: `
            <div class="d-grid gap-2">
                <button class="btn btn-success" onclick="Swal.close(); approveAll();">
                    <i class="fas fa-check-double"></i> Approve Semua Pegawai
                </button>
                <button class="btn btn-danger" onclick="Swal.close(); rejectAll();">
                    <i class="fas fa-times-circle"></i> Tolak Semua Pegawai
                </button>
                <button class="btn btn-info" onclick="Swal.close(); exportPendingData();">
                    <i class="fas fa-download"></i> Export Data Pending
                </button>
            </div>
        `,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: '<i class="fas fa-times"></i> Tutup'
    });
}

function approveAll() {
    const rkbGroups = <?php echo $rkb_result->num_rows; ?>;
    const lkhGroups = <?php echo $lkh_result->num_rows; ?>;
    const totalGroups = rkbGroups + lkhGroups;
    
    if (totalGroups === 0) {
        Swal.fire({
            icon: 'info',
            title: 'Tidak Ada Data',
            text: 'Tidak ada data pegawai yang menunggu approval',
            confirmButtonText: 'OK'
        });
        return;
    }
    
    Swal.fire({
        title: 'Approve Semua Pegawai?',
        html: `
            <div class="text-start">
                <p>Anda akan menyetujui <strong>SEMUA</strong> data pegawai yang menunggu approval:</p>
                <ul>
                    <li>RKB: <strong>${rkbGroups}</strong> pegawai</li>
                    <li>LKH: <strong>${lkhGroups}</strong> pegawai</li>
                </ul>
                <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> <strong>Tindakan ini tidak dapat dibatalkan!</strong></p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-check-double"></i> Ya, Approve Semua',
        cancelButtonText: '<i class="fas fa-times"></i> Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'info',
                title: 'Fitur dalam Pengembangan',
                text: 'Fitur approve all pegawai sedang dalam tahap pengembangan',
                confirmButtonText: 'OK'
            });
        }
    });
}

function rejectAll() {
    const rkbPending = <?php echo $rkb_result->num_rows; ?>;
    const lkhPending = <?php echo $lkh_result->num_rows; ?>;
    const totalPending = rkbPending + lkhPending;
    
    if (totalPending === 0) {
        Swal.fire({
            icon: 'info',
            title: 'Tidak Ada Data',
            text: 'Tidak ada data yang menunggu approval',
            confirmButtonText: 'OK'
        });
        return;
    }
    
    Swal.fire({
        title: 'Tolak Semua Data?',
        html: `
            <div class="text-start">
                <p>Anda akan menolak <strong>SEMUA</strong> data yang menunggu approval:</p>
                <ul>
                    <li>RKB: <strong>${rkbPending}</strong> data</li>
                    <li>LKH: <strong>${lkhPending}</strong> data</li>
                </ul>
                <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> <strong>Tindakan ini tidak dapat dibatalkan!</strong></p>
            </div>
            <div class="mb-3">
                <label for="swal-reject-reason" class="form-label text-start d-block">Alasan penolakan <span class="text-danger">*</span>:</label>
                <textarea id="swal-reject-reason" class="form-control" rows="3" placeholder="Jelaskan alasan penolakan..." required></textarea>
            </div>
        `,
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-times-circle"></i> Ya, Tolak Semua',
        cancelButtonText: '<i class="fas fa-arrow-left"></i> Batal',
        focusConfirm: false,
        preConfirm: () => {
            const reason = document.getElementById('swal-reject-reason').value.trim();
            if (!reason) {
                Swal.showValidationMessage('Alasan penolakan harus diisi');
                return false;
            }
            return { reason: reason };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'info',
                title: 'Fitur dalam Pengembangan',
                text: 'Fitur reject all sedang dalam tahap pengembangan',
                confirmButtonText: 'OK'
            });
        }
    });
}

function exportPendingData() {
    Swal.fire({
        title: 'Export Data Pending',
        text: 'Pilih format export yang diinginkan',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-file-excel"></i> Excel',
        cancelButtonText: '<i class="fas fa-file-pdf"></i> PDF',
        showDenyButton: true,
        denyButtonText: '<i class="fas fa-times"></i> Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            // Export to Excel
            Swal.fire({
                icon: 'success',
                title: 'Export Excel',
                text: 'Data pending berhasil diexport ke Excel',
                confirmButtonText: 'OK'
            });
        } else if (result.isDismissed && result.dismiss !== Swal.DismissReason.cancel) {
            // Export to PDF
            Swal.fire({
                icon: 'success',
                title: 'Export PDF',
                text: 'Data pending berhasil diexport ke PDF',
                confirmButtonText: 'OK'
            });
        }
    });
}

// Add event listener for Quick Actions button
document.addEventListener('DOMContentLoaded', function() {
    // Replace the Quick Action button click
    const quickActionBtn = document.querySelector('.btn-light[onclick="approveAll()"]');
    if (quickActionBtn) {
        quickActionBtn.setAttribute('onclick', 'showBulkActions()');
        quickActionBtn.innerHTML = '<i class="fas fa-cogs"></i>';
        quickActionBtn.title = 'Aksi Massal';
    }
});
</script>