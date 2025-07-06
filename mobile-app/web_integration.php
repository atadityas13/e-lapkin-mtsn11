<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA - WEB INTEGRATION
 * ========================================================
 * 
 * File: Web Integration Helper
 * Deskripsi: Helper untuk integrasi antara web utama dan mobile app
 * 
 * @package    E-Lapkin-MTSN11
 * @version    1.0.0
 * ========================================================
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    die('Direct access denied');
}

require_once __DIR__ . '/config/shared_session.php';

/**
 * Generate mobile access button for web interface
 */
function generateMobileAccessButton($class = 'btn btn-primary', $text = 'Akses Mobile App') {
    if (!canAccessMobileFromWeb()) {
        return '';
    }
    
    $bridge_url = createMobileBridgeUrl();
    if (!$bridge_url) {
        return '';
    }
    
    return sprintf(
        '<a href="%s" class="%s" target="_mobile_app" title="Akses versi mobile aplikasi">
            <i class="fas fa-mobile-alt me-2"></i>%s
        </a>',
        htmlspecialchars($bridge_url),
        htmlspecialchars($class),
        htmlspecialchars($text)
    );
}

/**
 * Generate mobile app info widget for web dashboard
 */
function generateMobileAppWidget() {
    if (!canAccessMobileFromWeb()) {
        return '';
    }
    
    $bridge_url = createMobileBridgeUrl();
    if (!$bridge_url) {
        return '';
    }
    
    return '
    <div class="card border-left-info shadow h-100 py-2">
        <div class="card-body">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                        Mobile App
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        Akses dari ponsel Anda
                    </div>
                    <div class="mt-2">
                        <a href="' . htmlspecialchars($bridge_url) . '" 
                           class="btn btn-info btn-sm" 
                           target="_mobile_app">
                            <i class="fas fa-mobile-alt me-1"></i>
                            Buka Mobile App
                        </a>
                    </div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-mobile-alt fa-2x text-gray-300"></i>
                </div>
            </div>
        </div>
    </div>';
}

/**
 * Generate QR Code for mobile access (optional)
 */
function generateMobileQRCode() {
    if (!canAccessMobileFromWeb()) {
        return '';
    }
    
    $bridge_url = createMobileBridgeUrl();
    if (!$bridge_url) {
        return '';
    }
    
    $full_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $bridge_url;
    
    return '
    <div class="text-center">
        <p class="small text-muted mb-2">Scan QR Code untuk akses mobile:</p>
        <div class="qr-code-container">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . 
                 urlencode($full_url) . '" 
                 alt="QR Code Mobile Access" 
                 class="img-fluid border" 
                 style="max-width: 150px;">
        </div>
        <p class="small text-muted mt-1">Atau klik: <a href="' . htmlspecialchars($bridge_url) . '" target="_mobile_app">Buka Mobile App</a></p>
    </div>';
}

/**
 * Check mobile app availability status
 */
function checkMobileAppStatus() {
    $status = [
        'available' => true,
        'user_can_access' => canAccessMobileFromWeb(),
        'features' => [
            'dashboard' => true,
            'lkh_input' => true,
            'rkb_view' => true,
            'laporan' => true,
            'offline_mode' => false // Future feature
        ],
        'requirements' => [
            'role' => 'user',
            'status' => 'approved',
            'session' => 'active'
        ]
    ];
    
    return $status;
}

/**
 * Generate mobile app download links (future feature)
 */
function generateMobileAppDownload() {
    return '
    <div class="mobile-download-links text-center">
        <h6 class="mb-3">Download Aplikasi Mobile</h6>
        <div class="row">
            <div class="col-6">
                <a href="#" class="btn btn-outline-dark btn-sm disabled">
                    <i class="fab fa-android me-1"></i>
                    Android App
                    <br><small>(Coming Soon)</small>
                </a>
            </div>
            <div class="col-6">
                <a href="#" class="btn btn-outline-dark btn-sm disabled">
                    <i class="fab fa-apple me-1"></i>
                    iOS App
                    <br><small>(Coming Soon)</small>
                </a>
            </div>
        </div>
        <div class="mt-2">
            <small class="text-muted">
                Sementara ini, gunakan browser mobile atau web app
            </small>
        </div>
    </div>';
}
?>
