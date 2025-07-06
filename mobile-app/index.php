<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA - MOBILE APP
 * ========================================================
 * * File: Mobile App Entry Point
 * Deskripsi: Halaman utama aplikasi mobile (WebView)
 * * @package     E-Lapkin-MTSN11-Mobile
 * @version     1.0.0
 * ========================================================
 */

// --- PERBAIKAN PENTING DI SINI ---
// Pastikan server menggunakan UTC untuk konsistensi token dengan Android
date_default_timezone_set('UTC'); 
// --- AKHIR PERBAIKAN PENTING ---

// Define ABSPATH
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Include mobile security (file ini yang berisi fungsi is_valid_mobile_app)
require_once __DIR__ . '/config/mobile_security.php';

// Blokir akses non-mobile
block_non_mobile_access();

// Log akses
log_mobile_access('index_access');

// Set mobile headers
set_mobile_headers();

session_start();

// Cek apakah sudah login
if (isset($_SESSION['mobile_loggedin']) && $_SESSION['mobile_loggedin'] === true) {
    header("Location: /mobile-app/user/dashboard.php");
    exit();
}

// Redirect ke login
header("Location: /mobile-app/auth/mobile_login.php");
exit();

// Tidak perlu ada penutup PHP ?> di sini jika langsung diikuti oleh HTML
// Jika ada kode PHP lagi di bawah, sebaiknya gabungkan dalam satu blok PHP utama
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>E-LAPKIN Mobile - MTsN 11 Majalengka</title>
    
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#4e73df">
    
    <link rel="icon" href="/assets/img/favicon.png" type="image/png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="/mobile-app/assets/css/mobile.css" rel="stylesheet">
</head>
<body class="mobile-body">
    <div class="mobile-container">
        <div class="mobile-header">
            <div class="text-center py-4">
                <img src="/assets/img/favicon.png" alt="Logo" class="mobile-logo mb-3">
                <h4 class="mobile-title text-white mb-1">E-LAPKIN Mobile</h4>
                <p class="mobile-subtitle text-white-50 mb-0">MTsN 11 Majalengka</p>
            </div>
        </div>
        
        <div class="mobile-content">
            <div class="login-card">
                <div class="card-header text-center">
                    <h5 class="mb-0">
                        <i class="fas fa-mobile-alt me-2"></i>
                        Login Mobile App
                    </h5>
                </div>
                
                <div class="card-body">
                    <?php 
                    // Pastikan $error_message didefinisikan sebelum digunakan
                    // Jika ini file utama, Anda mungkin perlu mengambilnya dari $_SESSION atau $_GET
                    $error_message = $error_message ?? ''; 
                    if (!empty($error_message)): 
                    ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($error_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="/mobile-app/auth/mobile_login.php" id="mobileLoginForm">
                        <div class="mb-3">
                            <label for="nip" class="form-label">
                                <i class="fas fa-id-card me-1"></i>
                                NIP / Username
                            </label>
                            <input type="text" 
                                    class="form-control form-control-lg" 
                                    id="nip" 
                                    name="nip" 
                                    placeholder="Masukkan NIP Anda"
                                    required 
                                    autocomplete="username">
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock me-1"></i>
                                Password
                            </label>
                            <div class="input-group">
                                <input type="password" 
                                        class="form-control form-control-lg" 
                                        id="password" 
                                        name="password" 
                                        placeholder="Masukkan Password"
                                        required 
                                        autocomplete="current-password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Masuk ke Aplikasi
                            </button>
                        </div>
                        
                        <input type="hidden" name="mobile_app" value="1">
                        <?php 
                        // Pastikan MOBILE_APP_VERSION didefinisikan di config/mobile_security.php atau tempat lain
                        // Jika tidak, akan ada warning: Use of undefined constant MOBILE_APP_VERSION
                        $mobile_app_version = defined('MOBILE_APP_VERSION') ? MOBILE_APP_VERSION : 'unknown';
                        ?>
                        <input type="hidden" name="app_version" value="<?= $mobile_app_version ?>">
                    </form>
                </div>
                
                <div class="card-footer text-center">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        Akses Khusus Aplikasi Mobile
                    </small>
                </div>
            </div>
        </div>
        
        <div class="mobile-footer">
            <div class="text-center py-3">
                <small class="text-white-50">
                    &copy; <?= date('Y') ?> MTsN 11 Majalengka<br>
                    Versi Mobile: <?= $mobile_app_version ?>
                </small>
            </div>
        </div>
    </div>
    
    <?php 
    // Pastikan $mobile_info didefinisikan sebelum digunakan
    // Ini biasanya diisi oleh fungsi block_non_mobile_access() atau set_mobile_headers()
    $mobile_info = $mobile_info ?? ['user_agent' => 'N/A', 'is_mobile_app' => false, 'ip_address' => 'N/A'];

    if (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false): 
    ?>
    <div class="dev-info">
        <small>
            <strong>Dev Info:</strong><br>
            User Agent: <?= htmlspecialchars($mobile_info['user_agent']) ?><br>
            Valid App: <?= $mobile_info['is_mobile_app'] ? 'Yes' : 'No' ?><br>
            IP: <?= htmlspecialchars($mobile_info['ip_address']) ?>
        </small>
    </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/mobile-app/assets/js/mobile.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        });
        
        // Form validation
        document.getElementById('mobileLoginForm').addEventListener('submit', function(e) {
            const nip = document.getElementById('nip').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!nip || !password) {
                e.preventDefault();
                alert('NIP dan Password harus diisi!');
                return false;
            }
            
            // Show loading
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';
            submitBtn.disabled = true;
            
            // Reset button after 10 seconds if no response
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 10000);
        });
    </script>
</body>
</html>