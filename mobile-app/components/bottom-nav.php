<?php
/**
 * Bottom Navigation Component for E-Lapkin Mobile
 * Provides consistent navigation across all mobile pages
 */

// Get current page name from the URL
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>

<style>
.bottom-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(255,255,255,0.98);
    backdrop-filter: blur(20px);
    border-top: 1px solid rgba(0,0,0,0.08);
    z-index: 1000;
    box-shadow: 0 -8px 32px rgba(0,0,0,0.12);
    padding: 8px 0;
}

.bottom-nav .nav-item {
    flex: 1;
    text-align: center;
}

.bottom-nav .nav-link {
    padding: 10px 8px;
    color: #6c757d;
    text-decoration: none;
    display: block;
    font-size: 11px;
    font-weight: 500;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 16px;
    margin: 4px;
    position: relative;
    overflow: hidden;
}

.bottom-nav .nav-link::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    opacity: 0;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 16px;
    transform: scale(0.8);
}

.bottom-nav .nav-link.active::before {
    opacity: 0.15;
    transform: scale(1);
}

.bottom-nav .nav-link:hover::before {
    opacity: 0.08;
    transform: scale(1);
}

.bottom-nav .nav-link.active {
    color: #667eea;
    transform: translateY(-4px) scale(1.05);
    font-weight: 600;
}

.bottom-nav .nav-link:hover {
    color: #667eea;
    transform: translateY(-2px) scale(1.02);
}

.bottom-nav .nav-link i {
    font-size: 20px;
    margin-bottom: 4px;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    z-index: 1;
}

.bottom-nav .nav-link.active i {
    transform: scale(1.1);
    text-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
}

.bottom-nav .nav-link:hover i {
    transform: scale(1.05);
}

.bottom-nav .nav-link small {
    position: relative;
    z-index: 1;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.bottom-nav .nav-link.active small {
    font-weight: 600;
    letter-spacing: 0.3px;
}

/* Ripple effect */
.bottom-nav .nav-link::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: radial-gradient(circle, rgba(102, 126, 234, 0.3) 0%, transparent 70%);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: width 0.6s ease, height 0.6s ease;
}

.bottom-nav .nav-link:active::after {
    width: 60px;
    height: 60px;
}

/* Smooth page transition effect */
.page-transition {
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.page-transition.loaded {
    opacity: 1;
    transform: translateY(0);
}

@media (max-width: 576px) {
    .bottom-nav .nav-link {
        padding: 8px 4px;
        margin: 2px;
        border-radius: 12px;
    }
    
    .bottom-nav .nav-link i {
        font-size: 18px;
    }
    
    .bottom-nav .nav-link small {
        font-size: 10px;
    }
}
</style>

<!-- Bottom Navigation -->
<div class="bottom-nav">
    <div class="d-flex">
        <div class="nav-item">
            <a href="rhk.php" class="nav-link <?= $current_page === 'rhk' ? 'active' : '' ?>">
                <i class="fas fa-tasks d-block"></i>
                <small>RHK</small>
            </a>
        </div>
        <div class="nav-item">
            <a href="rkb.php" class="nav-link <?= $current_page === 'rkb' ? 'active' : '' ?>">
                <i class="fas fa-calendar d-block"></i>
                <small>RKB</small>
            </a>
        </div>
        <div class="nav-item">
            <a href="dashboard.php" class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-home d-block"></i>
                <small>Beranda</small>
            </a>
        </div>
        <div class="nav-item">
            <a href="lkh.php" class="nav-link <?= $current_page === 'lkh' ? 'active' : '' ?>">
                <i class="fas fa-list d-block"></i>
                <small>LKH</small>
            </a>
        </div>
        <div class="nav-item">
            <a href="laporan.php" class="nav-link <?= $current_page === 'laporan' ? 'active' : '' ?>">
                <i class="fas fa-file-alt d-block"></i>
                <small>Laporan</small>
            </a>
        </div>
    </div>
</div>

<script>
// Smooth page transitions
document.addEventListener('DOMContentLoaded', function() {
    // Add page transition class to main content
    const mainContent = document.querySelector('.main-content, .container-fluid, body > div:first-child');
    if (mainContent) {
        mainContent.classList.add('page-transition');
        setTimeout(() => {
            mainContent.classList.add('loaded');
        }, 100);
    }
    
    // Add smooth navigation transitions
    const navLinks = document.querySelectorAll('.bottom-nav .nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (this.href && !this.classList.contains('active')) {
                e.preventDefault();
                
                // Add exit animation
                if (mainContent) {
                    mainContent.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
                    mainContent.style.opacity = '0';
                    mainContent.style.transform = 'translateY(-20px)';
                }
                
                // Navigate after animation
                setTimeout(() => {
                    window.location.href = this.href;
                }, 300);
            }
        });
    });
    
    // Add touch feedback for mobile
    navLinks.forEach(link => {
        link.addEventListener('touchstart', function() {
            this.style.transform = 'translateY(-2px) scale(0.98)';
        });
        
        link.addEventListener('touchend', function() {
            setTimeout(() => {
                if (!this.classList.contains('active')) {
                    this.style.transform = '';
                }
            }, 150);
        });
    });
});

// Preload pages for faster navigation
window.addEventListener('load', function() {
    const pages = ['rhk.php', 'rkb.php', 'dashboard.php', 'lkh.php', 'laporan.php'];
    pages.forEach(page => {
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = page;
        document.head.appendChild(link);
    });
});
</script>
