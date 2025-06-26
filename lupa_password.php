<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * Sistem Elektronik Laporan Kinerja Harian
 * MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat
 * 
 * File: Main Entry Point
 * Deskripsi: Halaman utama aplikasi - redirect ke login atau dashboard
 * 
 * @package    E-Lapkin-MTSN11
 * @author     MTsN 11 Majalengka Development Team
 * @copyright  2025 MTsN 11 Majalengka. All rights reserved.
 * @license    Proprietary License
 * @version    1.0.0
 * @since      2025-01-01
 * @created    2025-06-25
 * @modified   2025-06-25
 * 
 * DISCLAIMER:
 * Software ini dikembangkan khusus untuk MTsN 11 Majalengka.
 * Dilarang keras menyalin, memodifikasi, atau mendistribusikan
 * tanpa izin tertulis dari MTsN 11 Majalengka.
 * 
 * CONTACT:
 * Website: https://mtsn11majalengka.sch.id
 * Email: mtsn11majalengka@gmail.com
 * Phone: (0233) 8319182
 * Address: Kp. Sindanghurip Desa Maniis Kec. Cingambul, Majalengka, Jawa Barat
 * 
 * ========================================================
 */

// Include konfigurasi aplikasi
require_once __DIR__ . '/config/init.php';

// Meta tags configuration
$meta_title = 'Lupa Password - E-Lapkin MTSN 11 Majalengka';
$meta_description = 'Reset password akun E-Lapkin MTSN 11 Majalengka jika lupa password';
$meta_keywords = 'lupa password, reset password, e-lapkin, mtsn 11 majalengka';

require_once 'config/database.php';

$success_message = '';
$error_message = '';
$status_message = '';
$nip = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nip = trim($_POST['nip']);
    if (empty($nip)) {
        $error_message = "NIP harus diisi.";
    } else {
        // Cek apakah NIP ada
        $stmt = $conn->prepare("SELECT reset_request, password FROM pegawai WHERE nip = ?");
        $stmt->bind_param("s", $nip);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            $reset_request = $user['reset_request'];
            $password_hash = $user['password'];
            
            // Cek status permintaan
            if ($reset_request == 1) {
                $status_message = '<div class="alert alert-warning" role="alert">Menunggu Persetujuan Admin, silahkan cek secara berkala!</div>';
            } else {
                // Cek apakah password sudah sama dengan NIP (berarti sudah direset)
                if (password_verify($nip, $password_hash)) {
                    $status_message = '<div class="alert alert-success" role="alert">Password berhasil direset, silahkan login!</div>';
                } else {
                    // Tutup statement sebelum membuat yang baru
                    $stmt->close();
                    
                    // Set kolom reset_request = 1
                    $stmt2 = $conn->prepare("UPDATE pegawai SET reset_request=1 WHERE nip=?");
                    $stmt2->bind_param("s", $nip);
                    if ($stmt2->execute()) {
                        $success_message = "Permintaan reset password berhasil dikirim ke admin.<br>Setelah disetujui Admin, password akan disetel ke default (sama dengan NIP anda).";
                    } else {
                        $error_message = "Gagal mengirim permintaan reset password.";
                    }
                    $stmt2->close();
                }
            }
        } else {
            $error_message = "NIP tidak ditemukan.";
        }
        
        // Hanya tutup statement jika belum ditutup sebelumnya
        if ($stmt && !isset($stmt2)) {
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
    <title>Lupa Password - E-LAPKIN MTsN 11 Majalengka</title>
    <!-- Favicon -->
    <link rel="icon" href="/assets/img/favicon.png" type="image/png">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Animate.css -->
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

        .floating-shape.triangle {
            width: 0;
            height: 0;
            background: none;
            border-style: solid;
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

        .reset-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
        }

        .reset-box {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 0;
            width: 100%;
            max-width: 420px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            position: relative;
            animation: floatingBox 6s ease-in-out infinite;
        }

        @keyframes floatingBox {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .reset-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 25px 20px;
            border-radius: 16px 16px 0 0;
            position: relative;
            overflow: hidden;
        }

        .reset-header::before {
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
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.8rem;
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
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 3px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .logo-subtitle {
            font-size: 0.85rem;
            font-weight: 400;
            opacity: 0.9;
        }

        .reset-body {
            padding: 25px 20px;
        }

        .welcome-text {
            text-align: center;
            color: #666;
            margin-bottom: 20px;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-group {
            position: relative;
            margin-bottom: 18px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            background: #f8f9fa;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 1rem;
            transition: color 0.3s ease;
        }

        .form-control:focus + .input-icon {
            color: #667eea;
        }

        .reset-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 8px;
            font-family: 'Poppins', sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .reset-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .reset-btn:active {
            transform: translateY(0);
        }

        .back-to-login {
            width: 100%;
            padding: 12px;
            background: transparent;
            border: 2px solid #667eea;
            border-radius: 10px;
            color: #667eea;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 8px;
            font-family: 'Poppins', sans-serif;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .back-to-login:hover {
            background: #667eea;
            color: white;
            transform: translateY(-1px);
        }

        .reset-footer {
            text-align: center;
            padding: 15px 20px;
            background: #f8f9fa;
            color: #666;
            font-size: 0.75rem;
            line-height: 1.4;
        }

        .reset-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .alert {
            border-radius: 10px;
            border: none;
            padding: 12px;
            margin-bottom: 18px;
            font-weight: 500;
            font-size: 0.85rem;
        }

        .alert-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b, #ff5252);
            color: white;
        }

        .alert-warning {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: white;
        }

        .alert-info {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }

        /* Loading Animation */
        .loading {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Extra Small Devices (phones, 576px and down) */
        @media (max-width: 575.98px) {
            .reset-container {
                padding: 10px;
            }
            
            .reset-box {
                max-width: 100%;
                margin: 10px;
                border-radius: 12px;
            }
            
            .reset-header {
                padding: 20px 15px;
                border-radius: 12px 12px 0 0;
            }
            
            .reset-body {
                padding: 20px 15px;
            }
            
            .logo-icon {
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
            }
            
            .logo-text {
                font-size: 1.2rem;
            }
            
            .logo-subtitle {
                font-size: 0.8rem;
            }
            
            .form-control {
                padding: 10px 12px 10px 35px;
                font-size: 13px;
            }
            
            .input-icon {
                left: 12px;
                font-size: 0.9rem;
            }
            
            .reset-btn, .back-to-login {
                padding: 10px;
                font-size: 13px;
            }
            
            .welcome-text {
                font-size: 0.85rem;
            }
            
            .reset-footer {
                font-size: 0.7rem;
                padding: 12px 15px;
            }
        }

        /* Small Devices (landscape phones, 576px and up) */
        @media (min-width: 576px) and (max-width: 767.98px) {
            .reset-box {
                max-width: 400px;
            }
        }

        /* Medium Devices (tablets, 768px and up) */
        @media (min-width: 768px) and (max-width: 991.98px) {
            .reset-box {
                max-width: 420px;
            }
        }

        /* Landscape Orientation */
        @media (orientation: landscape) and (max-height: 600px) {
            .reset-container {
                padding: 10px;
            }
            
            .reset-header {
                padding: 15px 20px;
            }
            
            .reset-body {
                padding: 20px;
            }
            
            .logo-icon {
                width: 45px;
                height: 45px;
                font-size: 1.4rem;
                margin-bottom: 10px;
            }
            
            .logo-text {
                font-size: 1.1rem;
            }
            
            .welcome-text {
                margin-bottom: 15px;
            }
            
            .form-group {
                margin-bottom: 15px;
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

    <div class="reset-container">
        <div class="reset-box animate__animated animate__fadeInUp">
            <!-- Header -->
            <div class="reset-header">
                <div class="logo-container">
                    <div class="logo-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <div class="logo-text">Reset Password</div>
                    <div class="logo-subtitle">E-LAPKIN MTsN 11 Majalengka</div>
                </div>
            </div>

            <!-- Body -->
            <div class="reset-body">
                <div class="welcome-text">
                    <i class="fas fa-lock-open"></i>
                    Masukkan NIP untuk reset password
                </div>

                <div class="alert alert-info animate__animated animate__fadeIn" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    Setelah disetujui Admin, password akan disetel ke default (sama dengan NIP anda).
                </div>

                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success animate__animated animate__bounceIn" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger animate__animated animate__shakeX" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($status_message)): ?>
                    <div class="animate__animated animate__fadeIn">
                        <?php echo str_replace('class="alert', 'class="alert animate__animated animate__pulse', $status_message); ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" autocomplete="off" id="resetForm">
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

                    <button type="submit" class="reset-btn" id="resetBtn">
                        <span class="loading" id="loading"></span>
                        <span id="btnText">
                            <i class="fas fa-paper-plane me-2"></i>
                            Kirim Permintaan Reset
                        </span>
                    </button>

                    <a href="login.php" class="back-to-login">
                        <i class="fas fa-arrow-left me-2"></i>
                        Kembali ke Login
                    </a>
                </form>
            </div>

            <!-- Footer -->
            <div class="reset-footer">
                <div>&copy; <?= date('Y') ?> E-LAPKIN MTsN 11 Majalengka</div>
                <div class="mt-1">
                    Dikembangkan oleh <a href="https://www.instagram.com/atadityas_13/" target="_blank">A.T. Aditya</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Create floating graphics
        function createFloatingGraphics() {
            const graphicsContainer = document.getElementById('floatingGraphics');
            const shapes = ['circle', 'triangle', 'square', 'hexagon'];
            const shapeCount = 6;

            for (let i = 0; i < shapeCount; i++) {
                const shape = document.createElement('div');
                const shapeType = shapes[Math.floor(Math.random() * shapes.length)];
                shape.className = `floating-shape ${shapeType}`;
                
                const size = Math.random() * 60 + 20;
                const startY = Math.random() * window.innerHeight;
                const delay = Math.random() * 20;
                const duration = Math.random() * 15 + 10;

                shape.style.width = size + 'px';
                shape.style.height = size + 'px';
                shape.style.top = startY + 'px';
                shape.style.left = '-100px';
                shape.style.animationDelay = delay + 's';
                shape.style.animationDuration = duration + 's';

                // Special styling for triangle
                if (shapeType === 'triangle') {
                    shape.style.borderLeft = `${size/2}px solid transparent`;
                    shape.style.borderRight = `${size/2}px solid transparent`;
                    shape.style.borderBottom = `${size}px solid rgba(255, 255, 255, 0.2)`;
                    shape.style.width = '0';
                    shape.style.height = '0';
                }

                graphicsContainer.appendChild(shape);
            }
        }

        // Create floating particles
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = window.innerWidth < 768 ? 25 : 40;

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

        // Form submission with loading animation
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('resetBtn');
            const loading = document.getElementById('loading');
            const btnText = document.getElementById('btnText');
            
            btn.disabled = true;
            loading.style.display = 'inline-block';
            btnText.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';
        });

        // Input focus animations
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('animate__animated', 'animate__pulse');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('animate__animated', 'animate__pulse');
            });
        });

        // Responsive particles recreation
        function handleResize() {
            const particlesContainer = document.getElementById('particles');
            const graphicsContainer = document.getElementById('floatingGraphics');
            
            // Clear existing particles and graphics
            particlesContainer.innerHTML = '';
            graphicsContainer.innerHTML = '';
            
            // Recreate with appropriate count for screen size
            createParticles();
            createFloatingGraphics();
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            createParticles();
            createFloatingGraphics();
            
            // Auto focus on NIP field
            document.getElementById('nip').focus();
        });

        // Handle window resize
        window.addEventListener('resize', handleResize);

        // Add some interactive effects
        document.querySelector('.reset-btn').addEventListener('mouseenter', function() {
            this.classList.add('animate__animated', 'animate__pulse');
        });

        document.querySelector('.reset-btn').addEventListener('mouseleave', function() {
            this.classList.remove('animate__animated', 'animate__pulse');
        });

        document.querySelector('.back-to-login').addEventListener('mouseenter', function() {
            this.classList.add('animate__animated', 'animate__pulse');
        });

        document.querySelector('.back-to-login').addEventListener('mouseleave', function() {
            this.classList.remove('animate__animated', 'animate__pulse');
        });
    </script>
</body>
</html>
