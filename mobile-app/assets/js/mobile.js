/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA - MOBILE JS
 * ========================================================
 * 
 * File: Mobile-Specific JavaScript
 * Deskripsi: JavaScript khusus untuk aplikasi mobile WebView
 * 
 * @package    E-Lapkin-MTSN11-Mobile
 * @version    1.0.0
 * ========================================================
 */

// Mobile App Utilities
class MobileApp {
    constructor() {
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.setupTouchHandlers();
        this.setupFormValidation();
        this.checkNetworkStatus();
        this.setupPullToRefresh();
    }
    
    // Setup event listeners
    setupEventListeners() {
        // Prevent zoom on input focus (iOS)
        document.addEventListener('touchstart', function(e) {
            if (e.touches.length > 1) {
                e.preventDefault();
            }
        });
        
        // Handle back button (Android WebView)
        window.addEventListener('popstate', (e) => {
            this.handleBackButton();
        });
        
        // Handle device orientation change
        window.addEventListener('orientationchange', () => {
            setTimeout(() => {
                this.handleOrientationChange();
            }, 100);
        });
        
        // Handle app visibility change
        document.addEventListener('visibilitychange', () => {
            this.handleVisibilityChange();
        });
    }
    
    // Setup touch handlers
    setupTouchHandlers() {
        // Add ripple effect to buttons
        document.addEventListener('touchstart', (e) => {
            if (e.target.classList.contains('btn')) {
                this.addRippleEffect(e.target, e);
            }
        });
        
        // Handle swipe gestures
        let startX, startY, startTime;
        
        document.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            startTime = Date.now();
        });
        
        document.addEventListener('touchend', (e) => {
            if (!startX || !startY) return;
            
            const endX = e.changedTouches[0].clientX;
            const endY = e.changedTouches[0].clientY;
            const endTime = Date.now();
            
            const diffX = startX - endX;
            const diffY = startY - endY;
            const diffTime = endTime - startTime;
            
            // Only consider fast swipes
            if (diffTime < 300) {
                if (Math.abs(diffX) > Math.abs(diffY)) {
                    if (Math.abs(diffX) > 50) {
                        if (diffX > 0) {
                            this.handleSwipeLeft();
                        } else {
                            this.handleSwipeRight();
                        }
                    }
                }
            }
            
            startX = startY = null;
        });
    }
    
    // Add ripple effect to buttons
    addRippleEffect(element, event) {
        const ripple = document.createElement('span');
        const rect = element.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = event.touches[0].clientX - rect.left - size / 2;
        const y = event.touches[0].clientY - rect.top - size / 2;
        
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = x + 'px';
        ripple.style.top = y + 'px';
        ripple.classList.add('ripple-effect');
        
        element.style.position = 'relative';
        element.style.overflow = 'hidden';
        element.appendChild(ripple);
        
        setTimeout(() => {
            if (ripple.parentNode) {
                ripple.parentNode.removeChild(ripple);
            }
        }, 600);
    }
    
    // Form validation
    setupFormValidation() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                    this.showToast('Mohon lengkapi semua field yang diperlukan', 'warning');
                }
            });
        });
    }
    
    validateForm(form) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        return isValid;
    }
    
    // Check network status
    checkNetworkStatus() {
        const updateOnlineStatus = () => {
            if (navigator.onLine) {
                this.hideNetworkWarning();
            } else {
                this.showNetworkWarning();
            }
        };
        
        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);
        updateOnlineStatus();
    }
    
    showNetworkWarning() {
        if (document.querySelector('.network-warning')) return;
        
        const warning = document.createElement('div');
        warning.className = 'alert alert-warning network-warning position-fixed';
        warning.style.cssText = `
            top: 0;
            left: 0;
            right: 0;
            z-index: 9999;
            margin: 0;
            border-radius: 0;
            text-align: center;
        `;
        warning.innerHTML = `
            <i class="fas fa-wifi me-2"></i>
            Tidak ada koneksi internet
        `;
        
        document.body.insertBefore(warning, document.body.firstChild);
    }
    
    hideNetworkWarning() {
        const warning = document.querySelector('.network-warning');
        if (warning) {
            warning.remove();
        }
    }
    
    // Pull to refresh
    setupPullToRefresh() {
        let startY = 0;
        let currentY = 0;
        let pullThreshold = 100;
        let isRefreshing = false;
        
        document.addEventListener('touchstart', (e) => {
            if (window.scrollY === 0) {
                startY = e.touches[0].clientY;
            }
        });
        
        document.addEventListener('touchmove', (e) => {
            if (isRefreshing || window.scrollY > 0) return;
            
            currentY = e.touches[0].clientY;
            const pullDistance = currentY - startY;
            
            if (pullDistance > 0 && pullDistance < pullThreshold * 2) {
                e.preventDefault();
                this.showPullIndicator(pullDistance, pullThreshold);
            }
        });
        
        document.addEventListener('touchend', () => {
            if (isRefreshing || window.scrollY > 0) return;
            
            const pullDistance = currentY - startY;
            
            if (pullDistance > pullThreshold) {
                this.triggerRefresh();
            } else {
                this.hidePullIndicator();
            }
            
            startY = currentY = 0;
        });
    }
    
    showPullIndicator(distance, threshold) {
        let indicator = document.querySelector('.pull-indicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.className = 'pull-indicator';
            indicator.style.cssText = `
                position: fixed;
                top: 0;
                left: 50%;
                transform: translateX(-50%);
                background: rgba(78, 115, 223, 0.9);
                color: white;
                padding: 10px 20px;
                border-radius: 0 0 20px 20px;
                font-size: 14px;
                z-index: 9998;
                transition: all 0.3s ease;
            `;
            document.body.appendChild(indicator);
        }
        
        const progress = Math.min(distance / threshold, 1);
        const opacity = Math.min(progress, 1);
        
        indicator.style.opacity = opacity;
        indicator.style.transform = `translateX(-50%) translateY(${Math.min(distance * 0.5, 50)}px)`;
        
        if (progress >= 1) {
            indicator.innerHTML = '<i class="fas fa-arrow-down me-2"></i>Lepas untuk refresh';
        } else {
            indicator.innerHTML = '<i class="fas fa-arrow-down me-2"></i>Tarik untuk refresh';
        }
    }
    
    hidePullIndicator() {
        const indicator = document.querySelector('.pull-indicator');
        if (indicator) {
            indicator.style.opacity = '0';
            indicator.style.transform = 'translateX(-50%) translateY(-50px)';
            setTimeout(() => {
                if (indicator.parentNode) {
                    indicator.parentNode.removeChild(indicator);
                }
            }, 300);
        }
    }
    
    triggerRefresh() {
        const indicator = document.querySelector('.pull-indicator');
        if (indicator) {
            indicator.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memuat...';
        }
        
        // Simulate refresh
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }
    
    // Handle back button
    handleBackButton() {
        // Implementasi custom back button handling jika diperlukan
        console.log('Back button pressed');
    }
    
    // Handle orientation change
    handleOrientationChange() {
        // Recalculate layouts if needed
        const viewport = document.querySelector('meta[name="viewport"]');
        if (viewport) {
            viewport.setAttribute('content', 
                'width=device-width, initial-scale=1.0, user-scalable=no');
        }
    }
    
    // Handle visibility change
    handleVisibilityChange() {
        if (document.hidden) {
            // App is in background
            console.log('App hidden');
        } else {
            // App is in foreground
            console.log('App visible');
            this.checkNetworkStatus();
        }
    }
    
    // Handle swipe gestures
    handleSwipeLeft() {
        // Implement swipe left action
        console.log('Swipe left detected');
    }
    
    handleSwipeRight() {
        // Implement swipe right action
        console.log('Swipe right detected');
    }
    
    // Utility: Show toast message
    showToast(message, type = 'info', duration = 3000) {
        // Remove existing toast
        const existingToast = document.querySelector('.mobile-toast');
        if (existingToast) {
            existingToast.remove();
        }
        
        const toast = document.createElement('div');
        toast.className = `mobile-toast alert alert-${type}`;
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            z-index: 9999;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
        `;
        toast.innerHTML = message;
        
        document.body.appendChild(toast);
        
        // Animate in
        setTimeout(() => {
            toast.style.transform = 'translateY(0)';
            toast.style.opacity = '1';
        }, 10);
        
        // Auto remove
        setTimeout(() => {
            toast.style.transform = 'translateY(100px)';
            toast.style.opacity = '0';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, duration);
    }
    
    // Utility: Show loading overlay
    showLoading(message = 'Memuat...') {
        this.hideLoading(); // Remove existing loading
        
        const loading = document.createElement('div');
        loading.className = 'mobile-loading';
        loading.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        `;
        
        loading.innerHTML = `
            <div style="
                background: white;
                padding: 30px;
                border-radius: 20px;
                text-align: center;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            ">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div style="color: #5a5c69; font-weight: 500;">${message}</div>
            </div>
        `;
        
        document.body.appendChild(loading);
    }
    
    hideLoading() {
        const loading = document.querySelector('.mobile-loading');
        if (loading) {
            loading.remove();
        }
    }
    
    // Utility: Confirm dialog
    showConfirm(message, onConfirm, onCancel) {
        const modal = document.createElement('div');
        modal.className = 'mobile-confirm';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 20px;
        `;
        
        modal.innerHTML = `
            <div style="
                background: white;
                padding: 30px;
                border-radius: 20px;
                text-align: center;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                max-width: 400px;
                width: 100%;
            ">
                <div style="color: #5a5c69; margin-bottom: 25px; font-size: 1.1rem;">
                    ${message}
                </div>
                <div style="display: flex; gap: 15px;">
                    <button class="btn btn-secondary flex-fill" onclick="mobileApp.hideConfirm(); ${onCancel || ''}">
                        Batal
                    </button>
                    <button class="btn btn-primary flex-fill" onclick="mobileApp.hideConfirm(); ${onConfirm}">
                        Ya
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }
    
    hideConfirm() {
        const modal = document.querySelector('.mobile-confirm');
        if (modal) {
            modal.remove();
        }
    }
}

// Initialize mobile app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.mobileApp = new MobileApp();
});

// Add ripple effect CSS if not exists
if (!document.querySelector('#ripple-styles')) {
    const style = document.createElement('style');
    style.id = 'ripple-styles';
    style.textContent = `
        .ripple-effect {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
        }
        
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}

// Prevent context menu on long press
document.addEventListener('contextmenu', (e) => {
    e.preventDefault();
});

// Prevent text selection on double tap
document.addEventListener('selectstart', (e) => {
    if (!e.target.matches('input, textarea')) {
        e.preventDefault();
    }
});

// Add mobile-specific meta tags if not exists
if (!document.querySelector('meta[name="mobile-web-app-capable"]')) {
    const meta = document.createElement('meta');
    meta.name = 'mobile-web-app-capable';
    meta.content = 'yes';
    document.head.appendChild(meta);
}

// Export for global access
window.MobileUtils = {
    showToast: (message, type, duration) => {
        if (window.mobileApp) {
            window.mobileApp.showToast(message, type, duration);
        }
    },
    showLoading: (message) => {
        if (window.mobileApp) {
            window.mobileApp.showLoading(message);
        }
    },
    hideLoading: () => {
        if (window.mobileApp) {
            window.mobileApp.hideLoading();
        }
    },
    showConfirm: (message, onConfirm, onCancel) => {
        if (window.mobileApp) {
            window.mobileApp.showConfirm(message, onConfirm, onCancel);
        }
    }
};
