<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA - MOBILE NAVIGATION
 * ========================================================
 * 
 * File: Mobile Navigation Template
 * Deskripsi: Template navigasi khusus untuk aplikasi mobile
 * 
 * @package    E-Lapkin-MTSN11-Mobile
 * @version    1.0.0
 * ========================================================
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

// Get current page
$current_page = basename($_SERVER['PHP_SELF']);

// Get user data
$user_data = getMobileUserData();
?>

<!-- Mobile Navigation Bar -->
<nav class="navbar navbar-expand-lg mobile-nav sticky-top">
    <div class="container-fluid">
        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center" href="/mobile-app/user/dashboard.php">
            <img src="/assets/img/favicon.png" alt="Logo" width="32" height="32" class="me-2 rounded">
            <span class="fw-bold">E-LAPKIN</span>
        </a>
        
        <!-- User Info -->
        <div class="d-flex align-items-center">
            <div class="dropdown">
                <button class="btn btn-link text-white text-decoration-none dropdown-toggle" 
                        type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user-circle me-1"></i>
                    <span class="d-none d-sm-inline"><?= htmlspecialchars(substr($user_data['nama'], 0, 15)) ?><?= strlen($user_data['nama']) > 15 ? '...' : '' ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li>
                        <div class="dropdown-header">
                            <div class="fw-bold"><?= htmlspecialchars($user_data['nama']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($user_data['nip']) ?></small>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="/mobile-app/user/profil.php">
                            <i class="fas fa-user me-2"></i>Profil Saya
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="/mobile-app/user/pengaturan.php">
                            <i class="fas fa-cog me-2"></i>Pengaturan
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="#" onclick="confirmLogout()">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- Bottom Navigation (Mobile) -->
<div class="mobile-bottom-nav d-lg-none">
    <div class="container-fluid">
        <div class="row g-0">
            <div class="col">
                <a href="/mobile-app/user/dashboard.php" class="nav-item<?= $current_page === 'dashboard.php' ? ' active' : '' ?>">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="col">
                <a href="/mobile-app/user/rkb.php" class="nav-item<?= $current_page === 'rkb.php' ? ' active' : '' ?>">
                    <i class="fas fa-tasks"></i>
                    <span>RKB</span>
                </a>
            </div>
            <div class="col">
                <a href="/mobile-app/user/lkh.php" class="nav-item<?= $current_page === 'lkh.php' ? ' active' : '' ?>">
                    <i class="fas fa-calendar-check"></i>
                    <span>LKH</span>
                </a>
            </div>
            <div class="col">
                <a href="/mobile-app/user/laporan.php" class="nav-item<?= $current_page === 'laporan.php' ? ' active' : '' ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Laporan</span>
                </a>
            </div>
            <div class="col">
                <a href="/mobile-app/user/profil.php" class="nav-item<?= $current_page === 'profil.php' ? ' active' : '' ?>">
                    <i class="fas fa-user"></i>
                    <span>Profil</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Sidebar Navigation (Desktop/Tablet) -->
<div class="mobile-sidebar d-none d-lg-block">
    <div class="sidebar-content">
        <div class="sidebar-header">
            <h6 class="text-muted mb-3">MENU UTAMA</h6>
        </div>
        
        <div class="sidebar-menu">
            <a href="/mobile-app/user/dashboard.php" class="sidebar-item<?= $current_page === 'dashboard.php' ? ' active' : '' ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <a href="/mobile-app/user/rkb.php" class="sidebar-item<?= $current_page === 'rkb.php' ? ' active' : '' ?>">
                <i class="fas fa-tasks"></i>
                <span>Rencana Kerja Bulanan</span>
            </a>
            
            <a href="/mobile-app/user/rhk.php" class="sidebar-item<?= $current_page === 'rhk.php' ? ' active' : '' ?>">
                <i class="fas fa-clipboard-list"></i>
                <span>Rencana Harian Kegiatan</span>
            </a>
            
            <a href="/mobile-app/user/lkh.php" class="sidebar-item<?= $current_page === 'lkh.php' ? ' active' : '' ?>">
                <i class="fas fa-calendar-check"></i>
                <span>Laporan Kinerja Harian</span>
            </a>
            
            <div class="sidebar-divider"></div>
            
            <h6 class="text-muted mb-3 mt-4">LAPORAN</h6>
            
            <a href="/mobile-app/user/laporan.php" class="sidebar-item<?= $current_page === 'laporan.php' ? ' active' : '' ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Laporan Kinerja</span>
            </a>
            
            <a href="/mobile-app/user/generate_lkb.php" class="sidebar-item<?= $current_page === 'generate_lkb.php' ? ' active' : '' ?>">
                <i class="fas fa-file-pdf"></i>
                <span>Generate LKB</span>
            </a>
            
            <a href="/mobile-app/user/generate_lkh.php" class="sidebar-item<?= $current_page === 'generate_lkh.php' ? ' active' : '' ?>">
                <i class="fas fa-file-export"></i>
                <span>Generate LKH</span>
            </a>
        </div>
    </div>
</div>

<!-- Logout Confirmation Script -->
<script>
function confirmLogout() {
    Swal.fire({
        title: 'Konfirmasi Logout',
        text: 'Apakah Anda yakin ingin keluar dari aplikasi?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Logout',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Logging out...',
                text: 'Mohon tunggu sebentar',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Perform logout
            fetch('/mobile-app/auth/mobile_logout.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        title: 'Logout Berhasil',
                        text: 'Anda telah keluar dari aplikasi',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = data.data.redirect_url || '/mobile-app/index.php';
                    });
                } else {
                    throw new Error(data.message || 'Logout failed');
                }
            })
            .catch(error => {
                console.error('Logout error:', error);
                Swal.fire({
                    title: 'Error',
                    text: 'Terjadi kesalahan saat logout: ' + error.message,
                    icon: 'error',
                    confirmButtonColor: '#4e73df'
                });
            });
        }
    });
}

// Active page indicator
document.addEventListener('DOMContentLoaded', function() {
    // Add active class to current page
    const currentPath = window.location.pathname;
    const navItems = document.querySelectorAll('.nav-item, .sidebar-item');
    
    navItems.forEach(item => {
        if (item.getAttribute('href') === currentPath) {
            item.classList.add('active');
        }
    });
});
</script>

<!-- Mobile Navigation Styles -->
<style>
.mobile-bottom-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: white;
    border-top: 1px solid #e3e6f0;
    z-index: 1000;
    box-shadow: 0 -2px 20px rgba(0, 0, 0, 0.1);
    padding: 8px 0;
}

.mobile-bottom-nav .nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 8px 4px;
    text-decoration: none;
    color: #858796;
    transition: all 0.3s ease;
    border-radius: 12px;
    margin: 0 4px;
}

.mobile-bottom-nav .nav-item:hover,
.mobile-bottom-nav .nav-item.active {
    color: #4e73df;
    background: rgba(78, 115, 223, 0.1);
    transform: translateY(-2px);
}

.mobile-bottom-nav .nav-item i {
    font-size: 1.2rem;
    margin-bottom: 4px;
}

.mobile-bottom-nav .nav-item span {
    font-size: 0.7rem;
    font-weight: 500;
}

.mobile-sidebar {
    position: fixed;
    left: 0;
    top: 76px;
    width: 280px;
    height: calc(100vh - 76px);
    background: white;
    border-right: 1px solid #e3e6f0;
    overflow-y: auto;
    z-index: 999;
}

.sidebar-content {
    padding: 20px;
}

.sidebar-item {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    text-decoration: none;
    color: #5a5c69;
    border-radius: 12px;
    margin-bottom: 4px;
    transition: all 0.3s ease;
    font-weight: 500;
}

.sidebar-item:hover,
.sidebar-item.active {
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    color: white;
    transform: translateX(5px);
}

.sidebar-item i {
    width: 20px;
    margin-right: 12px;
    text-align: center;
}

.sidebar-divider {
    height: 1px;
    background: #e3e6f0;
    margin: 20px 0;
}

/* Content margin for desktop */
@media (min-width: 992px) {
    .mobile-content {
        margin-left: 280px;
        padding-top: 76px;
    }
}

/* Content margin for mobile */
@media (max-width: 991.98px) {
    .mobile-content {
        padding-bottom: 80px; /* Space for bottom nav */
        padding-top: 76px;
    }
}

/* Dropdown menu improvements */
.dropdown-menu {
    border: none;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    border-radius: 16px;
    overflow: hidden;
    margin-top: 8px;
}

.dropdown-item {
    padding: 12px 20px;
    transition: all 0.3s ease;
}

.dropdown-item:hover {
    background: #f8f9fc;
    transform: translateX(4px);
}

.dropdown-header {
    padding: 16px 20px 8px;
    border-bottom: 1px solid #e3e6f0;
}
</style>
