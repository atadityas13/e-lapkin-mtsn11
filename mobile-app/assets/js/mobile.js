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

/**
 * E-LAPKIN Mobile Common JavaScript Functions
 */

// Show SweetAlert notifications
function showNotification(type, title, text, timer = 3000) {
    Swal.fire({
        icon: type,
        title: title,
        text: text,
        timer: timer,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}

// Enhanced download function for Android WebView compatibility
function downloadFile(url, filename) {
    console.log('Download initiated:', url, filename);
    
    try {
        // Method 1: Try Android interface first
        if (typeof Android !== 'undefined' && Android.downloadFile) {
            console.log('Using Android interface');
            Android.downloadFile(url, filename);
            showNotification('success', 'Download Dimulai', 'File sedang diunduh melalui aplikasi Android...');
            return;
        }
        
        // Method 2: Try window.location for WebView
        if (navigator.userAgent.includes('wv')) {
            console.log('Using WebView window.location method');
            window.location.href = url;
            showNotification('info', 'Membuka File', 'File akan dibuka/diunduh...');
            return;
        }
        
        // Method 3: Traditional download link
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        link.target = '_blank';
        
        // Add to DOM temporarily
        document.body.appendChild(link);
        
        // Trigger click
        link.click();
        
        // Clean up
        setTimeout(() => {
            document.body.removeChild(link);
        }, 100);
        
        console.log('Download link clicked');
        
        // Show appropriate message
        showNotification('info', 'Download Diproses', 'Silakan periksa folder Download Anda.');
        
    } catch (error) {
        console.error('Download error:', error);
        
        // Fallback: Try direct navigation
        Swal.fire({
            icon: 'question',
            title: 'Metode Download Alternatif',
            text: 'Klik "Buka File" untuk mengunduh atau melihat file.',
            showCancelButton: true,
            confirmButtonText: 'Buka File',
            cancelButtonText: 'Tutup'
        }).then((result) => {
            if (result.isConfirmed) {
                window.open(url, '_blank');
            }
        });
    }
}

// Alternative fetch-based download with proper headers
async function downloadFileWithFetch(url, filename) {
    try {
        console.log('Fetch download started:', url);
        
        Swal.fire({
            title: 'Mengunduh...',
            text: 'Sedang memproses unduhan file',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Add timestamp to prevent caching issues
        const downloadUrl = url + '?t=' + new Date().getTime();
        
        const response = await fetch(downloadUrl, {
            method: 'GET',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const blob = await response.blob();
        console.log('Blob created, size:', blob.size);
        
        // Check if we're in WebView
        if (navigator.userAgent.includes('wv')) {
            // For WebView, try to trigger native download
            const reader = new FileReader();
            reader.onload = function() {
                const base64data = reader.result.split(',')[1];
                
                // Try Android interface for base64 download
                if (typeof Android !== 'undefined' && Android.downloadBase64) {
                    Android.downloadBase64(base64data, filename, blob.type);
                } else {
                    // Fallback to blob URL
                    const downloadUrl = window.URL.createObjectURL(blob);
                    window.location.href = downloadUrl;
                }
            };
            reader.readAsDataURL(blob);
        } else {
            // Normal browser download
            const downloadUrl = window.URL.createObjectURL(blob);
            
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = filename;
            link.style.display = 'none';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Clean up
            setTimeout(() => {
                window.URL.revokeObjectURL(downloadUrl);
            }, 1000);
        }
        
        showNotification('success', 'Download Selesai', 'File berhasil diunduh! Periksa folder Download.');
        
    } catch (error) {
        console.error('Fetch download error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Download Gagal',
            text: 'Terjadi kesalahan: ' + error.message,
            confirmButtonText: 'OK'
        });
    }
}

// Main download function with multiple fallbacks
function enhancedDownload(url, filename) {
    console.log('Enhanced download called:', url, filename);
    
    // Detect environment
    const isAndroid = /Android/i.test(navigator.userAgent);
    const isWebView = navigator.userAgent.includes('wv');
    
    console.log('Environment:', { isAndroid, isWebView });
    
    // Strategy 1: Android WebView with native interface
    if (isAndroid && isWebView && typeof Android !== 'undefined') {
        if (Android.downloadFile) {
            console.log('Using Android.downloadFile');
            try {
                Android.downloadFile(url, filename);
                showNotification('success', 'Download Dimulai', 'File sedang diunduh ke perangkat Anda...');
                return;
            } catch (e) {
                console.error('Android download failed:', e);
            }
        }
    }
    
    // Strategy 2: Fetch API for WebView
    if (isWebView && window.fetch) {
        console.log('Using fetch download for WebView');
        downloadFileWithFetch(url, filename);
        return;
    }
    
    // Strategy 3: Direct URL navigation for WebView
    if (isWebView) {
        console.log('Using direct navigation for WebView');
        window.location.href = url;
        showNotification('info', 'Membuka File', 'File akan dibuka atau diunduh...');
        return;
    }
    
    // Strategy 4: Traditional download for regular browsers
    console.log('Using traditional download');
    downloadFile(url, filename);
}

// Confirm action with SweetAlert
function confirmAction(title, text, confirmText = 'Ya', cancelText = 'Batal') {
    return Swal.fire({
        title: title,
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: confirmText,
        cancelButtonText: cancelText
    });
}

// Loading overlay
function showLoading(text = 'Memuat...') {
    Swal.fire({
        title: text,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
}

function hideLoading() {
    Swal.close();
}

// Format date to Indonesian
function formatDateIndonesian(dateString) {
    const months = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    const date = new Date(dateString);
    const day = date.getDate();
    const month = months[date.getMonth()];
    const year = date.getFullYear();
    
    return `${day} ${month} ${year}`;
}

// Smooth animations for cards
function animateCards() {
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
}

// Back button handler
function setupBackButton() {
    const backBtn = document.querySelector('.back-btn');
    if (backBtn) {
        backBtn.addEventListener('click', function() {
            if (typeof Android !== 'undefined' && Android.goBack) {
                Android.goBack();
            } else {
                window.history.back();
            }
        });
    }
}

// Initialize common mobile functions
document.addEventListener('DOMContentLoaded', function() {
    // Setup back button
    setupBackButton();
    
    // Animate cards on load
    animateCards();
    
    // Set global download function
    window.downloadFile = enhancedDownload;
    
    // Handle form submissions with loading
    const forms = document.querySelectorAll('form[data-loading]');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            showLoading('Memproses...');
        });
    });
});

// Export functions for use in other scripts
window.MobileUtils = {
    showNotification,
    downloadFile: enhancedDownload,
    confirmAction,
    showLoading,
    hideLoading,
    formatDateIndonesian,
    animateCards
};
