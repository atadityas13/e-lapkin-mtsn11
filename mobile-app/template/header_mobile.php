<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA - MOBILE HEADER
 * ========================================================
 * 
 * File: Mobile Header Template
 * Deskripsi: Template header khusus untuk aplikasi mobile
 * 
 * @package    E-Lapkin-MTSN11-Mobile
 * @version    1.0.0
 * ========================================================
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

// Set mobile headers
set_mobile_headers();

// Default page title jika tidak diset
if (!isset($page_title)) {
    $page_title = "E-LAPKIN Mobile - MTsN 11 Majalengka";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0, minimum-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    
    <!-- Mobile App Meta Tags -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="E-LAPKIN Mobile">
    <meta name="theme-color" content="#4e73df">
    <meta name="msapplication-navbutton-color" content="#4e73df">
    <meta name="msapplication-TileColor" content="#4e73df">
    
    <!-- Security Meta -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    
    <!-- Preload Critical Resources -->
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" as="style">
    <link rel="preload" href="/mobile-app/assets/css/mobile.css" as="style">
    
    <!-- Favicon -->
    <link rel="icon" href="/assets/img/favicon.png" type="image/png">
    <link rel="apple-touch-icon" href="/assets/img/favicon.png">
    <link rel="shortcut icon" href="/assets/img/favicon.png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" 
          integrity="sha384-9ndCyUa6J56LtAILV4KdLfS0F1ZdMSsKr2K6LJjzl6Cx0V4fJ7/JQj2QVALz0oR7" crossorigin="anonymous">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" 
          integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Mobile Custom CSS -->
    <link href="/mobile-app/assets/css/mobile.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Additional Page Styles -->
    <?php if (isset($additional_styles)): ?>
        <?= $additional_styles ?>
    <?php endif; ?>
    
    <!-- Performance Optimization -->
    <style>
        /* Critical CSS for faster rendering */
        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background: #f8f9fc;
        }
        
        .mobile-loading-skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body class="mobile-dashboard">
    
    <!-- Loading Screen -->
    <div id="mobileLoadingScreen" class="position-fixed w-100 h-100 d-flex align-items-center justify-content-center" 
         style="top: 0; left: 0; background: #4e73df; z-index: 9999; transition: opacity 0.5s ease;">
        <div class="text-center text-white">
            <div class="spinner-border text-white mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="fw-semibold">Memuat Aplikasi...</div>
            <small class="text-white-50">E-LAPKIN Mobile v<?= MOBILE_APP_VERSION ?></small>
        </div>
    </div>
    
    <!-- Connection Status -->
    <div id="connectionStatus" class="d-none position-fixed w-100 text-center py-2 text-white" 
         style="top: 0; z-index: 1050; background: #dc3545;">
        <small><i class="fas fa-wifi me-1"></i> Tidak ada koneksi internet</small>
    </div>
    
    <!-- SweetAlert Integration -->
    <?php if (isset($_SESSION['swal'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: '<?= $_SESSION['swal']['type'] ?>',
                title: '<?= addslashes($_SESSION['swal']['title']) ?>',
                text: '<?= addslashes($_SESSION['swal']['text']) ?>',
                confirmButtonColor: '#4e73df',
                showClass: {
                    popup: 'animate__animated animate__fadeInUp animate__faster'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutDown animate__faster'
                }
            });
        });
    </script>
    <?php unset($_SESSION['swal']); endif; ?>
    
    <!-- Mobile App Info for JavaScript -->
    <script>
        window.mobileAppConfig = {
            version: '<?= MOBILE_APP_VERSION ?>',
            name: '<?= MOBILE_APP_NAME ?>',
            baseUrl: '/mobile-app',
            apiUrl: '/mobile-app/api',
            isLoggedIn: <?= isset($_SESSION['mobile_loggedin']) && $_SESSION['mobile_loggedin'] ? 'true' : 'false' ?>,
            user: <?= isset($mobile_user_data) ? json_encode($mobile_user_data) : 'null' ?>,
            csrfToken: '<?= $_SESSION['csrf_token'] ?? '' ?>'
        };
        
        // Hide loading screen when page is loaded
        window.addEventListener('load', function() {
            const loadingScreen = document.getElementById('mobileLoadingScreen');
            if (loadingScreen) {
                loadingScreen.style.opacity = '0';
                setTimeout(() => {
                    loadingScreen.style.display = 'none';
                }, 500);
            }
        });
        
        // Check connection status
        function updateConnectionStatus() {
            const statusEl = document.getElementById('connectionStatus');
            if (navigator.onLine) {
                statusEl.classList.add('d-none');
            } else {
                statusEl.classList.remove('d-none');
            }
        }
        
        window.addEventListener('online', updateConnectionStatus);
        window.addEventListener('offline', updateConnectionStatus);
        document.addEventListener('DOMContentLoaded', updateConnectionStatus);
        
        // Prevent zoom on iOS
        document.addEventListener('gesturestart', function(e) {
            e.preventDefault();
        });
        
        // Disable pull-to-refresh on mobile browsers
        document.body.style.overscrollBehavior = 'none';
    </script>
