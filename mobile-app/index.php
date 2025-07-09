<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA - MOBILE APP
 * ========================================================
 */

session_start();
require_once '../config/database.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['id_pegawai']);
$userInfo = null;
$error = '';

if ($isLoggedIn) {
    $userId = $_SESSION['id_pegawai'];
    $stmt = $conn->prepare("SELECT id_pegawai, nama, nip, jabatan, unit_kerja FROM pegawai WHERE id_pegawai = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userInfo = $result->fetch_assoc();
    $stmt->close();
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLoggedIn) {
    $nip = trim($_POST['nip']);
    $password = trim($_POST['password']);
    
    if (!empty($nip) && !empty($password)) {
        $stmt = $conn->prepare("SELECT id_pegawai, nama, nip, jabatan, unit_kerja, password FROM pegawai WHERE nip = ?");
        $stmt->bind_param("s", $nip);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Simple password check (you should use password_hash/password_verify in production)
            if ($password === $user['password'] || password_verify($password, $user['password'])) {
                $_SESSION['id_pegawai'] = $user['id_pegawai'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['nip'] = $user['nip'];
                $_SESSION['jabatan'] = $user['jabatan'];
                $_SESSION['unit_kerja'] = $user['unit_kerja'];
                
                $isLoggedIn = true;
                $userInfo = $user;
            } else {
                $error = 'Password salah';
            }
        } else {
            $error = 'NIP tidak ditemukan';
        }
        $stmt->close();
    } else {
        $error = 'NIP dan password harus diisi';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-LAPKIN MTSN 11 Majalengka</title>
    
    <?php if ($isLoggedIn): ?>
    <meta name="user-session" content="<?= $userInfo['id_pegawai'] ?>">
    <meta name="user-name" content="<?= htmlspecialchars($userInfo['nama']) ?>">
    <meta name="user-nip" content="<?= htmlspecialchars($userInfo['nip']) ?>">
    <meta name="user-jabatan" content="<?= htmlspecialchars($userInfo['jabatan']) ?>">
    <?php endif; ?>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-card { 
            background: rgba(255,255,255,0.95); 
            border-radius: 15px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.1); 
        }
        .btn-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: white;
        }
        .user-info {
            background: rgba(102, 126, 234, 0.1);
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid d-flex align-items-center justify-content-center min-vh-100">
        <div class="col-md-6">
            <div class="card main-card">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h2 class="fw-bold text-primary">E-LAPKIN</h2>
                        <p class="text-muted">Sistem Elektronik Laporan Kegiatan Harian</p>
                        <p class="text-muted">MTSN 11 Majalengka</p>
                    </div>
                    
                    <?php if ($isLoggedIn): ?>
                        <div class="user-info">
                            <h5 class="text-primary mb-2">
                                <i class="fas fa-user-circle me-2"></i>Selamat Datang
                            </h5>
                            <p class="mb-1"><strong><?= htmlspecialchars($userInfo['nama']) ?></strong></p>
                            <small class="text-muted">
                                NIP: <?= htmlspecialchars($userInfo['nip']) ?> | 
                                <?= htmlspecialchars($userInfo['jabatan']) ?>
                            </small>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <a href="dashboard.php" class="btn btn-custom w-100 mb-3">
                                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="rkb.php" class="btn btn-custom w-100 mb-3">
                                    <i class="fas fa-calendar-alt me-2"></i>RKB
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="lkh.php" class="btn btn-custom w-100 mb-3">
                                    <i class="fas fa-clipboard-list me-2"></i>LKH
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="laporan.php" class="btn btn-custom w-100 mb-3">
                                    <i class="fas fa-chart-bar me-2"></i>Laporan
                                </a>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="logout.php" class="btn btn-outline-danger">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </div>
                    <?php else: ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="nip" class="form-label">
                                    <i class="fas fa-id-card me-2"></i>NIP
                                </label>
                                <input type="text" class="form-control" id="nip" name="nip" required 
                                       placeholder="Masukkan NIP Anda" value="<?= htmlspecialchars($_POST['nip'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Password
                                </label>
                                <input type="password" class="form-control" id="password" name="password" required 
                                       placeholder="Masukkan Password">
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-custom">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Global user data for Android app
        window.ELAPKIN_USER_DATA = {
            isLoggedIn: <?= $isLoggedIn ? 'true' : 'false' ?>,
            userId: <?= $isLoggedIn ? $userInfo['id_pegawai'] : 'null' ?>,
            userName: <?= $isLoggedIn ? '"' . addslashes($userInfo['nama']) . '"' : 'null' ?>,
            userNip: <?= $isLoggedIn ? '"' . addslashes($userInfo['nip']) . '"' : 'null' ?>,
            userJabatan: <?= $isLoggedIn ? '"' . addslashes($userInfo['jabatan']) . '"' : 'null' ?>
        };

        // Function to get user ID (called by Android app)
        window.getUserId = function() {
            console.log('getUserId called, returning:', window.ELAPKIN_USER_DATA.userId);
            return window.ELAPKIN_USER_DATA.userId ? window.ELAPKIN_USER_DATA.userId.toString() : null;
        };

        // Function to get user info (called by Android app)
        window.getUserInfo = function() {
            console.log('getUserInfo called, returning:', window.ELAPKIN_USER_DATA);
            return window.ELAPKIN_USER_DATA;
        };

        // Function to receive FCM token from Android app
        window.setFCMToken = function(token) {
            console.log('FCM Token received from Android:', token);
            localStorage.setItem('fcm_token', token);
            
            // If user is logged in, immediately try to send token to server
            if (window.ELAPKIN_USER_DATA.isLoggedIn && typeof Android !== 'undefined') {
                console.log('User is logged in, notifying Android about user ID');
                if (Android.setUserId) {
                    Android.setUserId(window.ELAPKIN_USER_DATA.userId.toString());
                }
            }
        };

        // Notify Android app when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, user data:', window.ELAPKIN_USER_DATA);
            
            if (window.ELAPKIN_USER_DATA.isLoggedIn && typeof Android !== 'undefined') {
                console.log('Notifying Android about logged in user');
                
                // Notify Android app about logged in user
                if (Android.onUserLogin) {
                    Android.onUserLogin(
                        window.ELAPKIN_USER_DATA.userId.toString(), 
                        window.ELAPKIN_USER_DATA.userName
                    );
                }
                
                // Also call setUserId directly
                if (Android.setUserId) {
                    Android.setUserId(window.ELAPKIN_USER_DATA.userId.toString());
                }
            }
        });

        // Also try after a short delay to ensure Android interface is ready
        setTimeout(function() {
            if (window.ELAPKIN_USER_DATA.isLoggedIn && typeof Android !== 'undefined') {
                console.log('Delayed notification to Android about user ID');
                if (Android.setUserId) {
                    Android.setUserId(window.ELAPKIN_USER_DATA.userId.toString());
                }
            }
        }, 1000);
    </script>
</body>
</html>
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            position: relative;
            animation: floatingBox 6s ease-in-out infinite;
        }

        @keyframes floatingBox {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 30px 20px;
            border-radius: 20px 20px 0 0;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: ripple 3s ease-in-out infinite;
        }

        @keyframes ripple {
            0% { transform: scale(0.8); opacity: 1; }
            100% { transform: scale(1.2); opacity: 0; }
        }

        .logo-container {
            position: relative;
            z-index: 2;
        }

        .logo-icon {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2rem;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .logo-subtitle {
            font-size: 0.9rem;
            font-weight: 400;
            opacity: 0.9;
        }

        .login-body {
            padding: 30px 25px;
        }

        .welcome-text {
            text-align: center;
            color: #666;
            margin-bottom: 25px;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .form-group {
            position: relative;
            margin-bottom: 20px;
        }

        .form-control {
            width: 100%;
            padding: 15px 20px 15px 50px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            background: #f8f9fa;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }

        .form-control:focus + .input-icon {
            color: #667eea;
        }

        .password-toggle {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            cursor: pointer;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #667eea;
        }

        .login-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            font-family: 'Poppins', sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .login-footer {
            text-align: center;
            padding: 20px 25px;
            background: #f8f9fa;
            color: #666;
            font-size: 0.8rem;
            line-height: 1.4;
            border-radius: 0 0 20px 20px;
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px;
            margin-bottom: 20px;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b, #ff5252);
            color: white;
        }

        /* Loading Animation */
        .loading {
            display: none;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Mobile Responsive */
        @media (max-width: 480px) {
            .login-container {
                padding: 15px;
            }
            
            .login-box {
                max-width: 100%;
                border-radius: 16px;
            }
            
            .login-header {
                padding: 25px 20px;
                border-radius: 16px 16px 0 0;
            }
            
            .login-body {
                padding: 25px 20px;
            }
            
            .logo-icon {
                width: 60px;
                height: 60px;
                font-size: 1.8rem;
            }
            
            .logo-text {
                font-size: 1.3rem;
            }
            
            .form-control {
                padding: 12px 15px 12px 45px;
                font-size: 14px;
            }
            
            .input-icon {
                left: 15px;
                font-size: 1rem;
            }
            
            .password-toggle {
                right: 15px;
                font-size: 1rem;
            }
            
            .login-btn {
                padding: 12px;
                font-size: 14px;
            }
            
            .login-footer {
                padding: 15px 20px;
                border-radius: 0 0 16px 16px;
            }
        }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.8s ease-out;
        }

        .slide-up {
            animation: slideUp 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(30px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <!-- Floating Graphics -->
    <div class="floating-graphics" id="floatingGraphics"></div>
    
    <!-- Floating Particles -->
    <div class="particles" id="particles"></div>

    <div class="login-container">
        <div class="login-box animate__animated animate__fadeInUp">
            <!-- Header -->
            <div class="login-header">
                <div class="logo-container">
                    <div class="logo-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <div class="logo-text">E-Lapkin Mobile</div>
                    <div class="logo-subtitle">MTsN 11 Majalengka</div>
                </div>
            </div>

            <!-- Body -->
            <div class="login-body">
                <div class="welcome-text">
                    <i class="fas fa-shield-alt"></i>
                    Silakan login untuk mengakses aplikasi mobile
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger animate__animated animate__shakeX" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" autocomplete="off" id="loginForm">
                    <div class="form-group">
                        <input 
                            id="nip" 
                            name="nip" 
                            type="text" 
                            class="form-control" 
                            placeholder="Masukkan NIP Anda" 
                            required 
                            value="<?php echo htmlspecialchars($nip); ?>"
                        >
                        <i class="fas fa-id-badge input-icon"></i>
                    </div>

                    <div class="form-group">
                        <input 
                            id="password" 
                            name="password" 
                            type="password" 
                            class="form-control" 
                            placeholder="Masukkan Password Anda" 
                            required
                        >
                        <i class="fas fa-lock input-icon"></i>
                        <i class="fas fa-eye password-toggle" onclick="togglePassword()" id="toggleEye"></i>
                    </div>

                    <!-- Remember Me Checkbox -->
                    <div class="form-group d-flex align-items-center justify-content-between mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="remember_me" name="remember_me">
                            <label class="form-check-label" for="remember_me" style="font-size: 0.9rem; color: #666;">
                                <i class="fas fa-heart me-1" style="color: #ff6b6b;"></i>
                                Ingat saya
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="login-btn" id="loginBtn">
                        <span class="loading" id="loading"></span>
                        <span id="btnText">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            Masuk ke Aplikasi
                        </span>
                    </button>
                </form>
            </div>

            <!-- Footer -->
            <div class="login-footer">
                <div>&copy; <?= date('Y') ?> E-Lapkin Mobile MTsN 11 Majalengka v.1.0</div>
                <div class="mt-1">
                    <small>Developed by A.T. Aditya</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Simple encryption for password storage (for demo purposes)
        function simpleEncrypt(text) {
            let result = '';
            const key = 'E-LAPKIN-2025';
            for (let i = 0; i < text.length; i++) {
                result += String.fromCharCode(text.charCodeAt(i) ^ key.charCodeAt(i % key.length));
            }
            return btoa(result);
        }

        function simpleDecrypt(encodedText) {
            try {
                const text = atob(encodedText);
                let result = '';
                const key = 'E-LAPKIN-2025';
                for (let i = 0; i < text.length; i++) {
                    result += String.fromCharCode(text.charCodeAt(i) ^ key.charCodeAt(i % key.length));
                }
                return result;
            } catch (e) {
                return '';
            }
        }

        // Load saved login information
        function loadSavedLogin() {
            const rememberLogin = localStorage.getItem('rememberLogin');
            const savedNip = localStorage.getItem('savedNip');
            const savedPassword = localStorage.getItem('savedPassword');
            
            if (rememberLogin === 'true' && savedNip) {
                document.getElementById('nip').value = savedNip;
                document.getElementById('remember_me').checked = true;
                
                // Decrypt and set password if available
                if (savedPassword) {
                    const decryptedPassword = simpleDecrypt(savedPassword);
                    if (decryptedPassword) {
                        document.getElementById('password').value = decryptedPassword;
                    }
                }
                
                // Show a subtle indication that credentials are loaded
                const nipField = document.getElementById('nip');
                const passwordField = document.getElementById('password');
                nipField.style.borderColor = '#28a745';
                if (passwordField.value) {
                    passwordField.style.borderColor = '#28a745';
                }
            }
        }

        // Save login information
        function saveLoginInfo() {
            const rememberMe = document.getElementById('remember_me').checked;
            const nip = document.getElementById('nip').value;
            const password = document.getElementById('password').value;
            
            if (rememberMe && nip) {
                localStorage.setItem('rememberLogin', 'true');
                localStorage.setItem('savedNip', nip);
                
                // Encrypt and save password
                if (password) {
                    const encryptedPassword = simpleEncrypt(password);
                    localStorage.setItem('savedPassword', encryptedPassword);
                }
            } else {
                // Clear all saved data if remember me is unchecked
                localStorage.removeItem('rememberLogin');
                localStorage.removeItem('savedNip');
                localStorage.removeItem('savedPassword');
            }
        }

        // Clear saved credentials
        function clearSavedLogin() {
            localStorage.removeItem('rememberLogin');
            localStorage.removeItem('savedNip');
            localStorage.removeItem('savedPassword');
            
            // Reset form
            document.getElementById('nip').value = '';
            document.getElementById('password').value = '';
            document.getElementById('remember_me').checked = false;
            
            // Reset border colors
            document.getElementById('nip').style.borderColor = '#e0e0e0';
            document.getElementById('password').style.borderColor = '#e0e0e0';
        }

        // Toggle password visibility
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleEye');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Form submission with loading animation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            // Save login info before submission
            saveLoginInfo();
            
            const btn = document.getElementById('loginBtn');
            const loading = document.getElementById('loading');
            const btnText = document.getElementById('btnText');
            
            btn.disabled = true;
            loading.style.display = 'inline-block';
            btnText.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            createParticles();
            createFloatingGraphics();
            
            // Load saved login information
            loadSavedLogin();
            
            // Auto focus on appropriate field
            const nipField = document.getElementById('nip');
            const passwordField = document.getElementById('password');
            
            if (nipField.value) {
                passwordField.focus();
            } else {
                nipField.focus();
            }
        });

        // Remember me checkbox animation
        document.getElementById('remember_me').addEventListener('change', function() {
            const label = this.nextElementSibling;
            if (this.checked) {
                label.classList.add('animate__animated', 'animate__heartBeat');
                setTimeout(() => {
                    label.classList.remove('animate__animated', 'animate__heartBeat');
                }, 1000);
                
                // Show tooltip
                if (!document.getElementById('nip').value || !document.getElementById('password').value) {
                    // Create temporary tooltip
                    const tooltip = document.createElement('div');
                    tooltip.innerHTML = '<small><i class="fas fa-info-circle me-1"></i>NIP dan Password akan disimpan untuk login berikutnya</small>';
                    tooltip.style.cssText = `
                        position: absolute;
                        background: #667eea;
                        color: white;
                        padding: 8px 12px;
                        border-radius: 6px;
                        font-size: 12px;
                        top: -40px;
                        left: 50%;
                        transform: translateX(-50%);
                        z-index: 1000;
                        animation: fadeIn 0.3s ease;
                    `;
                    
                    this.parentElement.style.position = 'relative';
                    this.parentElement.appendChild(tooltip);
                    
                    setTimeout(() => {
                        if (tooltip.parentElement) {
                            tooltip.remove();
                        }
                    }, 3000);
                }
            } else {
                // Clear saved data when unchecked
                clearSavedLogin();
            }
        });

        // Add event listener for NIP field to save on change if remember me is checked
        document.getElementById('nip').addEventListener('input', function() {
            if (document.getElementById('remember_me').checked) {
                localStorage.setItem('savedNip', this.value);
            }
        });

        // Add warning about password storage
        document.getElementById('password').addEventListener('focus', function() {
            if (document.getElementById('remember_me').checked && !localStorage.getItem('savedPassword')) {
                // Show subtle hint
                this.setAttribute('title', 'Password akan disimpan secara aman untuk kemudahan login');
            }
        });

        // Create floating graphics
        function createFloatingGraphics() {
            const graphicsContainer = document.getElementById('floatingGraphics');
            const shapes = ['circle', 'square', 'hexagon'];
            const shapeCount = 6;

            for (let i = 0; i < shapeCount; i++) {
                const shape = document.createElement('div');
                const shapeType = shapes[Math.floor(Math.random() * shapes.length)];
                shape.className = `floating-shape ${shapeType}`;
                
                const size = Math.random() * 40 + 20;
                const startY = Math.random() * window.innerHeight;
                const delay = Math.random() * 20;
                const duration = Math.random() * 15 + 10;

                shape.style.width = size + 'px';
                shape.style.height = size + 'px';
                shape.style.top = startY + 'px';
                shape.style.left = '-100px';
                shape.style.animationDelay = delay + 's';
                shape.style.animationDuration = duration + 's';

                graphicsContainer.appendChild(shape);
            }
        }

        // Create floating particles
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 25;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                const size = Math.random() * 4 + 2;
                const left = Math.random() * 100;
                const delay = Math.random() * 6;
                const duration = Math.random() * 3 + 3;

                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                particle.style.left = left + '%';
                particle.style.animationDelay = delay + 's';
                particle.style.animationDuration = duration + 's';
                particle.style.top = Math.random() * 100 + '%';

                particlesContainer.appendChild(particle);
            }
        }

        // Add interactive effects
        document.querySelector('.login-btn').addEventListener('mouseenter', function() {
            this.classList.add('animate__animated', 'animate__pulse');
        });

        document.querySelector('.login-btn').addEventListener('mouseleave', function() {
            this.classList.remove('animate__animated', 'animate__pulse');
        });

        // Global user data for Android app
        window.ELAPKIN_USER_DATA = {
            isLoggedIn: <?= $isLoggedIn ? 'true' : 'false' ?>,
            userId: <?= $isLoggedIn ? $userInfo['id_pegawai'] : 'null' ?>,
            userName: <?= $isLoggedIn ? '"' . addslashes($userInfo['nama']) . '"' : 'null' ?>,
            userNip: <?= $isLoggedIn ? '"' . addslashes($userInfo['nip']) . '"' : 'null' ?>,
            userJabatan: <?= $isLoggedIn ? '"' . addslashes($userInfo['jabatan']) . '"' : 'null' ?>
        };

        // Function to get user ID (called by Android app)
        window.getUserId = function() {
            console.log('getUserId called, returning:', window.ELAPKIN_USER_DATA.userId);
            return window.ELAPKIN_USER_DATA.userId ? window.ELAPKIN_USER_DATA.userId.toString() : null;
        };

        // Function to get user info (called by Android app)
        window.getUserInfo = function() {
            console.log('getUserInfo called, returning:', window.ELAPKIN_USER_DATA);
            return window.ELAPKIN_USER_DATA;
        };

        // Function to receive FCM token from Android app
        window.setFCMToken = function(token) {
            console.log('FCM Token received from Android:', token);
            localStorage.setItem('fcm_token', token);
            
            // If user is logged in, immediately try to send token to server
            if (window.ELAPKIN_USER_DATA.isLoggedIn && typeof Android !== 'undefined') {
                console.log('User is logged in, notifying Android about user ID');
                if (Android.setUserId) {
                    Android.setUserId(window.ELAPKIN_USER_DATA.userId.toString());
                }
            }
        };

        // Notify Android app when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, user data:', window.ELAPKIN_USER_DATA);
            
            if (window.ELAPKIN_USER_DATA.isLoggedIn && typeof Android !== 'undefined') {
                console.log('Notifying Android about logged in user');
                
                // Notify Android app about logged in user
                if (Android.onUserLogin) {
                    Android.onUserLogin(
                        window.ELAPKIN_USER_DATA.userId.toString(), 
                        window.ELAPKIN_USER_DATA.userName
                    );
                }
                
                // Also call setUserId directly
                if (Android.setUserId) {
                    Android.setUserId(window.ELAPKIN_USER_DATA.userId.toString());
                }
            }
        });

        // Also try after a short delay to ensure Android interface is ready
        setTimeout(function() {
            if (window.ELAPKIN_USER_DATA.isLoggedIn && typeof Android !== 'undefined') {
                console.log('Delayed notification to Android about user ID');
                if (Android.setUserId) {
                    Android.setUserId(window.ELAPKIN_USER_DATA.userId.toString());
                }
            }
        }, 1000);
    </script>
</body>
</html>
