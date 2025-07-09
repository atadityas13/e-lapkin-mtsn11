<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA - MOBILE APP VERSION
 * ========================================================
 * 
 * Annual Report List - Mobile Optimized View
 * 
 * @package    E-Lapkin-MTSN11
 * @version    1.0.0
 * ========================================================
 */

// Redirect to main laporan page with annual tab active
header("Location: laporan.php?tab=tahunan#tahunan");
exit();
?>
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

$id_pegawai_login = $_SESSION['id_pegawai'];
$nama_pegawai_login = $_SESSION['nama'];
$role_pegawai_login = $_SESSION['role'];

// Get active period
function get_periode_aktif($conn, $id_pegawai) {
    $stmt = $conn->prepare("SELECT tahun_aktif FROM pegawai WHERE id_pegawai = ?");
    $stmt->bind_param("i", $id_pegawai);
    $stmt->execute();
    $stmt->bind_result($tahun_aktif);
    $stmt->fetch();
    $stmt->close();
    return ['tahun' => $tahun_aktif ?: (int)date('Y')];
}

$periode_aktif = get_periode_aktif($conn, $id_pegawai_login);
$periode_tahun = $periode_aktif['tahun'];

// Check if there's any data for this year
$stmt_check = $conn->prepare("SELECT COUNT(*) as total FROM rhk r 
    JOIN rkb ON r.id_rhk = rkb.id_rhk 
    WHERE r.id_pegawai = ? AND rkb.tahun = ?");
$stmt_check->bind_param("ii", $id_pegawai_login, $periode_tahun);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$data_count = $result_check->fetch_assoc()['total'];
$stmt_check->close();

// Get monthly data for the year
function get_monthly_data($conn, $id_pegawai, $tahun) {
    $monthly_data = [];
    $bulan_names = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $stmt = $conn->prepare("
        SELECT 
            MONTH(l.tanggal) as bulan,
            COUNT(DISTINCT l.id_lkh) as total_lkh,
            COUNT(DISTINCT rkb.id_rkb) as total_rkb
        FROM lkh l
        JOIN rkb ON l.id_rkb = rkb.id_rkb  
        JOIN rhk ON rkb.id_rhk = rhk.id_rhk
        WHERE rhk.id_pegawai = ? AND rkb.tahun = ?
        GROUP BY MONTH(l.tanggal)
        ORDER BY MONTH(l.tanggal)
    ");
    
    $stmt->bind_param("ii", $id_pegawai, $tahun);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $monthly_data[$row['bulan']] = [
            'nama_bulan' => $bulan_names[$row['bulan']],
            'total_lkh' => $row['total_lkh'],
            'total_rkb' => $row['total_rkb'],
            'bulan_number' => $row['bulan']
        ];
    }
    
    $stmt->close();
    return $monthly_data;
}

$monthly_data = get_monthly_data($conn, $id_pegawai_login, $periode_tahun);

$page_title = "Laporan Tahunan - Mobile App";
include __DIR__ . '/template/header.php';
?>

<!-- Add breadcrumb navigation -->
<nav aria-label="breadcrumb" class="d-none d-md-block" style="padding: 20px;">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="laporan.php" class="text-white text-decoration-none">
                <i class="fas fa-arrow-left me-1"></i>Laporan
            </a>
        </li>
        <li class="breadcrumb-item active text-white" aria-current="page">Tahunan</li>
    </ol>
</nav>

<!-- Mobile App Specific Styles -->
<style>
.mobile-app-container {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 0;
}

.report-card {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    padding: 25px;
    margin: 15px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    backdrop-filter: blur(10px);
}

.stats-container {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    border-radius: 15px;
    padding: 20px;
    margin: 15px 0;
    color: white;
}

.mobile-header {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 0 0 20px 20px;
    padding: 20px;
    margin-bottom: 20px;
    color: white;
}

.month-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    border-left: 4px solid #667eea;
    transition: all 0.3s ease;
}

.month-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.month-header {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 15px;
}

.month-stats {
    display: flex;
    justify-content: space-around;
    text-align: center;
}

.stat-item {
    flex: 1;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: #667eea;
}

.stat-label {
    font-size: 0.8rem;
    color: #6c757d;
    margin-top: 5px;
}

.development-notice {
    background: linear-gradient(135deg, #ffeaa7, #fdcb6e);
    border-radius: 15px;
    padding: 20px;
    margin: 15px;
    text-align: center;
    border: none;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

.enhanced-back-btn {
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 1000;
    background: rgba(255, 255, 255, 0.9);
    border: none;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.enhanced-back-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
}

@media (max-width: 768px) {
    .mobile-app-container {
        padding: 0;
    }
    
    .report-card {
        margin: 10px;
        padding: 20px;
    }
    
    .month-card {
        margin: 10px;
        padding: 15px;
    }
    
    .enhanced-back-btn {
        top: 10px;
        left: 10px;
        width: 45px;
        height: 45px;
    }
}
</style>

<div class="mobile-app-container">
    <!-- Enhanced Mobile Header -->
    <div class="mobile-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <button class="btn btn-light btn-sm me-3" onclick="goBackToReports()" id="backToReportsBtn">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div>
                    <h4 class="mb-1">📊 Laporan Tahunan</h4>
                    <small class="opacity-75">Mobile App - Tahun <?php echo $periode_tahun; ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Statistics Card -->
    <div class="report-card">
        <div class="stats-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">📈 Statistik Laporan</h5>
                <i class="fas fa-chart-line fa-2x opacity-75"></i>
            </div>
            
            <div class="row text-center">
                <div class="col-4">
                    <div class="h3 mb-1"><?php echo $data_count; ?></div>
                    <small>Total RKB</small>
                </div>
                <div class="col-4">
                    <?php
                    $stmt_lkh = $conn->prepare("SELECT COUNT(*) as total FROM lkh l 
                        JOIN rkb ON l.id_rkb = rkb.id_rkb 
                        JOIN rhk ON rkb.id_rhk = rhk.id_rhk 
                        WHERE rhk.id_pegawai = ? AND rkb.tahun = ?");
                    $stmt_lkh->bind_param("ii", $id_pegawai_login, $periode_tahun);
                    $stmt_lkh->execute();
                    $result_lkh = $stmt_lkh->get_result();
                    $lkh_count = $result_lkh->fetch_assoc()['total'];
                    $stmt_lkh->close();
                    ?>
                    <div class="h3 mb-1"><?php echo $lkh_count; ?></div>
                    <small>Total LKH</small>
                </div>
                <div class="col-4">
                    <div class="h3 mb-1"><?php echo count($monthly_data); ?></div>
                    <small>Bulan Aktif</small>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <small class="opacity-75">
                    <i class="fas fa-calendar-alt me-1"></i>
                    Periode: Januari - Desember <?php echo $periode_tahun; ?>
                </small>
            </div>
        </div>
    </div>

    <!-- Development Notice -->
    <div class="development-notice">
        <i class="fas fa-tools fa-2x mb-3 text-warning"></i>
        <h5 class="text-dark mb-2">Fitur Download Sedang Dikembangkan</h5>
        <p class="mb-0 text-dark">
            Saat ini Anda dapat melihat daftar laporan bulanan. 
            Fitur download PDF akan segera tersedia.
        </p>
    </div>

    <!-- Monthly Reports List -->
    <div class="report-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">📅 Laporan Bulanan</h5>
            <small class="text-muted"><?php echo count($monthly_data); ?> bulan</small>
        </div>

        <?php if (!empty($monthly_data)): ?>
            <?php foreach ($monthly_data as $bulan => $data): ?>
                <div class="month-card">
                    <div class="month-header">
                        <h6 class="mb-0 fw-bold text-primary">
                            <i class="fas fa-calendar me-2"></i>
                            <?php echo $data['nama_bulan']; ?> <?php echo $periode_tahun; ?>
                        </h6>
                        <span class="badge bg-success">
                            <i class="fas fa-check me-1"></i>Terisi
                        </span>
                    </div>
                    
                    <div class="month-stats">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $data['total_rkb']; ?></div>
                            <div class="stat-label">RKB</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $data['total_lkh']; ?></div>
                            <div class="stat-label">LKH</div>
                        </div>
                        <div class="stat-item">
                            <button class="btn btn-outline-primary btn-sm" onclick="viewMonthDetail(<?php echo $bulan; ?>)">
                                <i class="fas fa-eye me-1"></i>Detail
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times fa-3x mb-3 text-muted"></i>
                <h5 class="text-muted">Belum Ada Data</h5>
                <p class="text-muted">
                    Belum ada laporan yang tersedia untuk tahun <?php echo $periode_tahun; ?>
                </p>
                <a href="laporan.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Buat Laporan
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Integration Notice -->
    <div class="report-card">
        <div class="d-flex align-items-center p-3 bg-light rounded-3">
            <i class="fas fa-link text-primary fa-2x me-3"></i>
            <div>
                <h6 class="mb-1">Terintegrasi dengan Sistem Laporan</h6>
                <small class="text-muted">
                    Halaman ini terintegrasi dengan tab laporan utama untuk pengalaman yang lebih baik
                </small>
            </div>
        </div>
    </div>
</div>

<script>
// Enhanced navigation functions
function goBackToReports() {
    // Check if came from reports page
    if (document.referrer.includes('laporan.php')) {
        window.history.back();
    } else {
        // Direct navigation to reports with annual tab active
        window.location.href = 'laporan.php#annual';
    }
}

function viewMonthDetail(bulan) {
    // Navigate to monthly report detail
    window.location.href = `laporan.php?tab=bulanan&bulan=${bulan}&tahun=<?php echo $periode_tahun; ?>`;
}

// Add mobile app specific enhancements
document.addEventListener('DOMContentLoaded', function() {
    // Add touch feedback for mobile
    const monthCards = document.querySelectorAll('.month-card');
    monthCards.forEach(card => {
        card.addEventListener('touchstart', function() {
            this.style.transform = 'scale(0.98)';
        });
        
        card.addEventListener('touchend', function() {
            this.style.transform = 'scale(1)';
        });
    });
    
    // Enhanced back button behavior
    const backBtn = document.getElementById('backToReportsBtn');
    if (backBtn) {
        backBtn.addEventListener('touchstart', function() {
            this.style.transform = 'scale(0.95)';
        });
        
        backBtn.addEventListener('touchend', function() {
            this.style.transform = 'scale(1)';
        });
    }
    
    // Add swipe gesture for going back (mobile enhancement)
    let startX = 0;
    let startY = 0;
    
    document.addEventListener('touchstart', function(e) {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
    });
    
    document.addEventListener('touchend', function(e) {
        if (!startX || !startY) return;
        
        const endX = e.changedTouches[0].clientX;
        const endY = e.changedTouches[0].clientY;
        
        const diffX = startX - endX;
        const diffY = startY - endY;
        
        // Detect right swipe (back gesture)
        if (Math.abs(diffX) > Math.abs(diffY) && diffX < -100 && startX < 50) {
            goBackToReports();
        }
        
        startX = 0;
        startY = 0;
    });
    
    // Animate cards on load
    const cards = document.querySelectorAll('.month-card');
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
</script>

<?php include __DIR__ . '/template/footer.php'; ?>
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'generate_pdf_tahunan.php';
    form.style.display = 'none';
    
    // Add mobile app identifier
    const mobileInput = document.createElement('input');
    mobileInput.type = 'hidden';
    mobileInput.name = 'mobile_app';
    mobileInput.value = '1';
    form.appendChild(mobileInput);
    
    // Add CSRF token if available
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (csrfToken) {
        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = 'csrf_token';
        tokenInput.value = csrfToken.getAttribute('content');
        form.appendChild(tokenInput);
    }
    
    document.body.appendChild(form);
    form.submit();
    
    // Complete progress and cleanup
    setTimeout(() => {
        clearInterval(progressInterval);
        progressBar.style.width = '100%';
        progressText.textContent = '100%';
        
        // Update final message
        const loadingElement = document.querySelector('#loadingSpinner p');
        if (loadingElement) {
            loadingElement.textContent = 'Download dimulai...';
        }
        
        setTimeout(() => {
            // Reset UI
            downloadBtn.disabled = false;
            downloadBtn.innerHTML = '<i class="fas fa-download me-2"></i>Download Laporan Tahunan PDF';
            loadingSpinner.style.display = 'none';
            progressContainer.style.display = 'none';
            progressBar.style.width = '0%';
            progressText.textContent = '0%';
            
            // Remove form
            if (document.body.contains(form)) {
                document.body.removeChild(form);
            }
            
            // Show success message
            showSuccessMessage();
        }, 1500);
    }, 3000);
}

function showSuccessMessage() {
    const successAlert = document.createElement('div');
    successAlert.className = 'alert alert-success alert-dismissible fade show position-fixed';
    successAlert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 300px;';
    successAlert.innerHTML = `
        <i class="fas fa-check-circle me-2"></i>
        <strong>Berhasil!</strong> Download akan dimulai otomatis.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(successAlert);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (document.body.contains(successAlert)) {
            successAlert.remove();
        }
    }, 5000);
}

// Handle page visibility change to reset UI if needed
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') {
        const downloadBtn = document.getElementById('downloadBtn');
        const loadingSpinner = document.getElementById('loadingSpinner');
        const progressContainer = document.getElementById('progressContainer');
        
        if (downloadBtn && downloadBtn.disabled) {
            downloadBtn.disabled = false;
            downloadBtn.innerHTML = '<i class="fas fa-download me-2"></i>Download Laporan Tahunan PDF';
            loadingSpinner.style.display = 'none';
            progressContainer.style.display = 'none';
        }
    }
});

// Add mobile app specific enhancements
document.addEventListener('DOMContentLoaded', function() {
    // Add touch feedback for mobile
    const downloadBtn = document.getElementById('downloadBtn');
    if (downloadBtn) {
        downloadBtn.addEventListener('touchstart', function() {
            this.style.transform = 'scale(0.98)';
        });
        
        downloadBtn.addEventListener('touchend', function() {
            this.style.transform = 'scale(1)';
        });
    }
    
    // Add mobile-specific meta tags dynamically
    const viewport = document.querySelector('meta[name="viewport"]');
    if (viewport) {
        viewport.setAttribute('content', 'width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no');
    }
    
    // Enhanced back button behavior
    const backBtn = document.getElementById('backToReportsBtn');
    if (backBtn) {
        backBtn.addEventListener('touchstart', function() {
            this.style.transform = 'scale(0.95)';
        });
        
        backBtn.addEventListener('touchend', function() {
            this.style.transform = 'scale(1)';
        });
    }
    
    // Add swipe gesture for going back (mobile enhancement)
    let startX = 0;
    let startY = 0;
    
    document.addEventListener('touchstart', function(e) {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
    });
    
    document.addEventListener('touchend', function(e) {
        if (!startX || !startY) return;
        
        const endX = e.changedTouches[0].clientX;
        const endY = e.changedTouches[0].clientY;
        
        const diffX = startX - endX;
        const diffY = startY - endY;
        
        // Detect right swipe (back gesture)
        if (Math.abs(diffX) > Math.abs(diffY) && diffX < -100 && startX < 50) {
            goBackToReports();
        }
        
        startX = 0;
        startY = 0;
    });
});
</script>

<?php include __DIR__ . '/template/footer.php'; ?>
