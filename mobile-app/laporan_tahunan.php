<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA - MOBILE APP VERSION
 * ========================================================
 * 
 * Mobile App Annual Report Page
 * 
 * @package    E-Lapkin-MTSN11
 * @version    1.0.0
 * ========================================================
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../template/session_user.php';

// Check if user is logged in
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

.download-section {
    background: white;
    border-radius: 20px;
    padding: 30px;
    margin: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.download-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 30px;
    padding: 18px 35px;
    color: white;
    font-weight: 600;
    font-size: 16px;
    width: 100%;
    transition: all 0.3s ease;
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}

.download-btn:hover:not(:disabled) {
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(102, 126, 234, 0.6);
}

.download-btn:disabled {
    background: linear-gradient(135deg, #6c757d, #5a6268);
    transform: none;
    box-shadow: 0 4px 10px rgba(108, 117, 125, 0.3);
    cursor: not-allowed;
}

.mobile-header {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 0 0 20px 20px;
    padding: 20px;
    margin-bottom: 20px;
    color: white;
}

.progress-container {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 20px;
    margin: 20px 0;
    display: none;
}

.progress {
    height: 12px;
    border-radius: 6px;
    overflow: hidden;
    background: #e9ecef;
}

.progress-bar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    height: 100%;
    border-radius: 6px;
    transition: width 0.4s ease;
    position: relative;
    overflow: hidden;
}

.progress-bar::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    right: 0;
    background: linear-gradient(
        45deg,
        rgba(255, 255, 255, 0.2) 25%,
        transparent 25%,
        transparent 50%,
        rgba(255, 255, 255, 0.2) 50%,
        rgba(255, 255, 255, 0.2) 75%,
        transparent 75%,
        transparent
    );
    background-size: 15px 15px;
    animation: progress-animation 1s linear infinite;
}

@keyframes progress-animation {
    0% { transform: translateX(-15px); }
    100% { transform: translateX(15px); }
}

.loading-spinner {
    display: none;
    text-align: center;
    padding: 25px;
}

.feature-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin: 20px 0;
}

.feature-item {
    background: rgba(102, 126, 234, 0.1);
    border-radius: 12px;
    padding: 15px;
    text-align: center;
    border: 1px solid rgba(102, 126, 234, 0.2);
}

/* Add enhanced back navigation for better UX */
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
    
    .report-card, .download-section {
        margin: 10px;
        padding: 20px;
    }
    
    .feature-grid {
        grid-template-columns: 1fr;
        gap: 10px;
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
    <!-- Enhanced Mobile Header with better navigation -->
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
                <div class="col-6">
                    <div class="h3 mb-1"><?php echo $data_count; ?></div>
                    <small>Total RKB</small>
                </div>
                <div class="col-6">
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
            </div>
            
            <div class="text-center mt-3">
                <small class="opacity-75">
                    <i class="fas fa-calendar-alt me-1"></i>
                    Periode: Januari - Desember <?php echo $periode_tahun; ?>
                </small>
            </div>
        </div>

        <!-- Feature Grid -->
        <div class="feature-grid">
            <div class="feature-item">
                <i class="fas fa-file-pdf text-danger fa-2x mb-2"></i>
                <h6>Format PDF</h6>
                <small class="text-muted">Siap cetak</small>
            </div>
            <div class="feature-item">
                <i class="fas fa-table text-primary fa-2x mb-2"></i>
                <h6>Tabel Lengkap</h6>
                <small class="text-muted">RKB & LKH</small>
            </div>
            <div class="feature-item">
                <i class="fas fa-signature text-success fa-2x mb-2"></i>
                <h6>Tanda Tangan</h6>
                <small class="text-muted">Digital</small>
            </div>
            <div class="feature-item">
                <i class="fas fa-mobile-alt text-warning fa-2x mb-2"></i>
                <h6>Mobile App</h6>
                <small class="text-muted">Optimized</small>
            </div>
        </div>
    </div>

    <!-- Download Section -->
    <div class="download-section">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width: 80px; height: 80px;">
                <i class="fas fa-download fa-2x text-primary"></i>
            </div>
            <h5>Download Laporan PDF</h5>
            <p class="text-muted">Unduh laporan tahunan dalam format PDF yang dapat dicetak dan dibagikan</p>
        </div>

        <!-- Progress Container -->
        <div class="progress-container" id="progressContainer">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="fw-bold text-primary">
                    <i class="fas fa-cog fa-spin me-2"></i>
                    Generating PDF...
                </span>
                <span class="badge bg-primary" id="progressText">0%</span>
            </div>
            <div class="progress">
                <div class="progress-bar" id="progressBar" style="width: 0%"></div>
            </div>
            <small class="text-muted mt-2 d-block">
                <i class="fas fa-info-circle me-1"></i>
                Mohon tunggu, sedang menyiapkan laporan PDF Anda...
            </small>
        </div>

        <!-- Loading Spinner -->
        <div class="loading-spinner" id="loadingSpinner">
            <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h6 class="text-primary">Menyiapkan Laporan PDF</h6>
            <p class="text-muted mb-0">Mohon tunggu beberapa saat...</p>
        </div>

        <?php if ($data_count > 0): ?>
            <button class="download-btn" id="downloadBtn" onclick="downloadPDF()">
                <i class="fas fa-download me-2"></i>
                Download Laporan Tahunan PDF
            </button>
        <?php else: ?>
            <div class="alert alert-warning text-center border-0" style="background: linear-gradient(135deg, #ffeaa7, #fdcb6e);">
                <i class="fas fa-exclamation-triangle fa-2x mb-2 text-warning"></i>
                <h6 class="text-dark">Belum Ada Data</h6>
                <p class="mb-0 text-dark">Belum ada RKB atau LKH untuk tahun <?php echo $periode_tahun; ?></p>
            </div>
            <button class="download-btn" disabled>
                <i class="fas fa-ban me-2"></i>
                Tidak Ada Data untuk Diunduh
            </button>
        <?php endif; ?>

        <div class="mt-4 p-3 bg-light rounded-3">
            <small class="text-muted d-block">
                <i class="fas fa-shield-alt text-success me-1"></i>
                <strong>Keamanan:</strong> File PDF akan dihapus otomatis setelah 3 menit untuk melindungi data pribadi Anda.
            </small>
            <small class="text-muted d-block mt-1">
                <i class="fas fa-mobile-alt text-primary me-1"></i>
                <strong>Mobile App:</strong> Optimized untuk pengalaman mobile yang lebih baik.
            </small>
        </div>
    </div>

    <!-- Add integration notice -->
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

function downloadPDF() {
    const downloadBtn = document.getElementById('downloadBtn');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    
    // Disable button and show loading
    downloadBtn.disabled = true;
    downloadBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Menyiapkan PDF...';
    loadingSpinner.style.display = 'block';
    progressContainer.style.display = 'block';
    
    // Enhanced progress simulation
    let progress = 0;
    const progressSteps = [
        { value: 15, message: 'Mengumpulkan data...' },
        { value: 35, message: 'Memproses RKB...' },
        { value: 55, message: 'Memproses LKH...' },
        { value: 75, message: 'Generating PDF...' },
        { value: 90, message: 'Finalizing...' }
    ];
    
    let stepIndex = 0;
    const progressInterval = setInterval(() => {
        if (stepIndex < progressSteps.length) {
            const step = progressSteps[stepIndex];
            progress = step.value;
            progressBar.style.width = progress + '%';
            progressText.textContent = Math.round(progress) + '%';
            
            // Update loading message
            const loadingElement = document.querySelector('#loadingSpinner p');
            if (loadingElement) {
                loadingElement.textContent = step.message;
            }
            
            stepIndex++;
        } else {
            clearInterval(progressInterval);
        }
    }, 500);
    
    // Create form for download
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
