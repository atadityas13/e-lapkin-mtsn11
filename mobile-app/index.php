<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA - MOBILE APP
 * ========================================================
 */

session_start();

// Mobile app configuration (define here to avoid including full mobile_session.php)
define('MOBILE_SECRET_KEY', 'MTSN11-MOBILE-KEY-2025');
define('MOBILE_PACKAGE_NAME', 'id.sch.mtsn11majalengka.elapkin');
define('MOBILE_USER_AGENT', 'E-LAPKIN-MTSN11-Mobile-App/1.0');

// Generate mobile token for validation
function generateLoginToken() {
    $originalTimezone = date_default_timezone_get();
    date_default_timezone_set('Asia/Jakarta');
    
    $currentDate = date('Y-m-d');
    $input = MOBILE_SECRET_KEY . $currentDate;
    $token = md5($input);
    
    date_default_timezone_set($originalTimezone);
    return $token;
}

// For login page, validate User Agent and optionally validate token/package if provided
function validateLoginAccess() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Always validate User Agent
    if ($user_agent !== MOBILE_USER_AGENT) {
        http_response_code(403);
        die(json_encode([
            'error' => 'Access denied. This mobile interface is only accessible via the official E-LAPKIN Mobile App.',
            'required_user_agent' => MOBILE_USER_AGENT
        ]));
    }
    
    // If token and package headers are provided, validate them too
    $headers = getallheaders();
    $receivedToken = '';
    $receivedPackage = '';
    
    // Handle case-insensitive headers
    if (isset($headers['X-Mobile-Token'])) {
        $receivedToken = $headers['X-Mobile-Token'];
    } elseif (isset($headers['x-mobile-token'])) {
        $receivedToken = $headers['x-mobile-token'];
    }
    
    if (isset($headers['X-App-Package'])) {
        $receivedPackage = $headers['X-App-Package'];
    } elseif (isset($headers['x-app-package'])) {
        $receivedPackage = $headers['x-app-package'];
    }
    
    if (!empty($receivedToken) && !empty($receivedPackage)) {
        // Validate token
        $expectedToken = generateLoginToken();
        if ($receivedToken !== $expectedToken) {
            http_response_code(403);
            die(json_encode([
                'error' => 'Invalid mobile token.',
                'code' => 'INVALID_TOKEN'
            ]));
        }
        
        // Validate package
        if ($receivedPackage !== MOBILE_PACKAGE_NAME) {
            http_response_code(403);
            die(json_encode([
                'error' => 'Invalid app package.',
                'code' => 'INVALID_PACKAGE'
            ]));
        }
    }
}

// Validate access for login page
validateLoginAccess();

// Check if user is already logged in
if (isset($_SESSION['mobile_loggedin']) && $_SESSION['mobile_loggedin'] === true) {
    header("Location: dashboard.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';

$error_message = '';
$nip = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nip = trim($_POST['nip']);
    $password = trim($_POST['password']);

    if (empty($nip) || empty($password)) {
        $error_message = "NIP dan Password harus diisi.";
    } else {
        $stmt = $conn->prepare("SELECT id_pegawai, nip, password, nama, jabatan, unit_kerja, role, status FROM pegawai WHERE nip = ? AND role != 'admin'");
        if ($stmt) {
            $stmt->bind_param("s", $nip);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                $pegawai = $result->fetch_assoc();

                if (password_verify($password, $pegawai['password'])) {
                    $status = $pegawai['status'] ?? 'approved';
                    
                    if ($status === 'approved' || empty($status)) {
                        // Mobile login session
                        $_SESSION['mobile_loggedin'] = true;
                        $_SESSION['mobile_id_pegawai'] = $pegawai['id_pegawai'];
                        $_SESSION['mobile_nip'] = $pegawai['nip'];
                        $_SESSION['mobile_nama'] = $pegawai['nama'];
                        $_SESSION['mobile_jabatan'] = $pegawai['jabatan'];
                        $_SESSION['mobile_unit_kerja'] = $pegawai['unit_kerja'];
                        $_SESSION['mobile_role'] = $pegawai['role'];

                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $error_message = "Akun Anda belum disetujui atau ditolak.";
                    }
                } else {
                    $error_message = "NIP atau password salah.";
                }
            } else {
                $error_message = "NIP atau password salah.";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Lapkin Mobile - MTsN 11 Majalengka</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, #667eea, #764ba2, #f093fb, #f5576c);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            z-index: -3;
        }

        /* Floating Graphics */
        .floating-graphics {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            pointer-events: none;
        }

        .floating-shape {
            position: absolute;
            opacity: 0.1;
            animation: floatShape 20s linear infinite;
        }

        .floating-shape.circle {
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
        }

        .floating-shape.square {
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(45deg);
        }

        .floating-shape.hexagon {
            background: rgba(255, 255, 255, 0.25);
            clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);
        }

        /* Floating Particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        @keyframes floatShape {
            0% {
                transform: translateX(-100px) translateY(100vh) rotate(0deg);
            }
            100% {
                transform: translateX(calc(100vw + 100px)) translateY(-100px) rotate(360deg);
            }
        }

        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-box {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            padding: 0;
            width: 100%;
            max-width: 400px;
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
            
            // Auto focus on NIP field
            document.getElementById('nip').focus();
        });

        // Add interactive effects
        document.querySelector('.login-btn').addEventListener('mouseenter', function() {
            this.classList.add('animate__animated', 'animate__pulse');
        });

        document.querySelector('.login-btn').addEventListener('mouseleave', function() {
            this.classList.remove('animate__animated', 'animate__pulse');
        });
    </script>
</body>
</html>
