/**
 * Mobile App JavaScript for E-LAPKIN
 */

// Global mobile app configuration
const MobileApp = {
    init: function() {
        this.setupPullToRefresh();
        this.setupTouchFeedback();
        this.setupFormValidation();
        this.setupOfflineDetection();
    },

    // Pull to refresh functionality
    setupPullToRefresh: function() {
        let startY = 0;
        let currentY = 0;
        let isRefreshing = false;

        document.addEventListener('touchstart', function(e) {
            startY = e.touches[0].pageY;
        }, { passive: true });

        document.addEventListener('touchmove', function(e) {
            currentY = e.touches[0].pageY;
            
            if (window.scrollY === 0 && currentY > startY + 50 && !isRefreshing) {
                showRefreshIndicator();
            }
        }, { passive: true });

        document.addEventListener('touchend', function(e) {
            if (window.scrollY === 0 && currentY > startY + 100 && !isRefreshing) {
                triggerRefresh();
            } else {
                hideRefreshIndicator();
            }
        }, { passive: true });

        function showRefreshIndicator() {
            const indicator = document.querySelector('.ptr-indicator') || createRefreshIndicator();
            indicator.style.display = 'block';
            indicator.textContent = 'Lepas untuk refresh';
        }

        function hideRefreshIndicator() {
            const indicator = document.querySelector('.ptr-indicator');
            if (indicator) {
                indicator.style.display = 'none';
            }
        }

        function createRefreshIndicator() {
            const indicator = document.createElement('div');
            indicator.className = 'ptr-indicator';
            document.body.appendChild(indicator);
            return indicator;
        }

        function triggerRefresh() {
            isRefreshing = true;
            const indicator = document.querySelector('.ptr-indicator');
            if (indicator) {
                indicator.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Refreshing...';
            }
            
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
    },

    // Touch feedback for better UX
    setupTouchFeedback: function() {
        document.addEventListener('touchstart', function(e) {
            if (e.target.matches('.btn, .card, .nav-link')) {
                e.target.style.transform = 'scale(0.98)';
                e.target.style.transition = 'transform 0.1s';
            }
        }, { passive: true });

        document.addEventListener('touchend', function(e) {
            if (e.target.matches('.btn, .card, .nav-link')) {
                setTimeout(() => {
                    e.target.style.transform = 'scale(1)';
                }, 100);
            }
        }, { passive: true });
    },

    // Enhanced form validation
    setupFormValidation: function() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';
                }
            });
        });
    },

    // Offline detection
    setupOfflineDetection: function() {
        window.addEventListener('online', function() {
            showToast('Koneksi internet tersedia', 'success');
        });

        window.addEventListener('offline', function() {
            showToast('Koneksi internet terputus', 'warning');
        });
    }
};

// Utility functions
function showToast(message, type = 'info') {
    Swal.fire({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        icon: type,
        title: message
    });
}

function showLoading(message = 'Memuat...') {
    Swal.fire({
        title: message,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
}

function hideLoading() {
    Swal.close();
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    MobileApp.init();
    
    // Add fade-in animation to cards
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        setTimeout(() => {
            card.classList.add('fade-in');
        }, index * 100);
    });
});

// Haptic feedback (if supported)
function vibrate(duration = 50) {
    if ('vibrate' in navigator) {
        navigator.vibrate(duration);
    }
}