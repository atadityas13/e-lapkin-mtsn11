<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * Sistem Elektronik Laporan Kinerja Harian
 * MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat
 * 
 * File: Registration Page
 * Deskripsi: Halaman pendaftaran pegawai baru
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
 * ========================================================
 */

session_start();

// Redirect jika sudah login
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: dashboard.php");
    exit();
}

require_once 'config/database.php';

// Cek apakah sudah ada admin di sistem
$check_admin_stmt = $conn->prepare("SELECT COUNT(*) as admin_count FROM pegawai WHERE role = 'admin'");
$check_admin_stmt->execute();
$admin_result = $check_admin_stmt->get_result();
$admin_count = $admin_result->fetch_assoc()['admin_count'];

// Jika belum ada admin, redirect ke register_admin
if ($admin_count == 0) {
    header("Location: register_admin.php");
    exit();
}

$error_message = '';
$success_message = '';
$form_data = [
    'nip' => '',
    'nama' => '',
    'jabatan' => '',
    'unit_kerja' => ''
];

// Ambil data unit kerja - create static options since unit_kerja table might not exist yet
$unit_kerja_options = [
    ['nama_unit' => 'MTsN 11 Majalengka'],
    ['nama_unit' => 'Tata Usaha MTsN 11 Majalengka']
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil dan sanitasi data
    $form_data['nip'] = trim($_POST['nip']);
    $form_data['nama'] = trim($_POST['nama']);
    $form_data['jabatan'] = trim($_POST['jabatan']);
    $form_data['unit_kerja'] = trim($_POST['unit_kerja']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validasi input
    if (empty($form_data['nip']) || empty($form_data['nama']) || empty($form_data['jabatan']) || 
        empty($form_data['unit_kerja']) || empty($password) || empty($confirm_password)) {
        $error_message = "Semua field wajib diisi.";
    } elseif (strlen($form_data['nip']) < 8) {
        $error_message = "NIP minimal 8 karakter.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password minimal 6 karakter.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Konfirmasi password tidak cocok.";
    } else {
        // Cek apakah NIP sudah ada
        $check_stmt = $conn->prepare("SELECT nip FROM pegawai WHERE nip = ?");
        if ($check_stmt) {
            $check_stmt->bind_param("s", $form_data['nip']);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error_message = "NIP sudah terdaftar dalam sistem, silahkan Login!";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Load penilai settings from JSON berdasarkan unit kerja
                $penilai_settings_file = __DIR__ . '/config/penilai_settings.json';
                $nip_penilai = '';
                $nama_penilai = '';
                
                if (file_exists($penilai_settings_file)) {
                    $penilai_settings = json_decode(file_get_contents($penilai_settings_file), true);
                    
                    // Tentukan penilai berdasarkan unit kerja
                    if ($form_data['unit_kerja'] === 'Tata Usaha MTsN 11 Majalengka') {
                        // Untuk Tata Usaha, penilainya adalah Kepala Urusan Tata Usaha
                        if (!empty($penilai_settings['penilai_tata_usaha'])) {
                            $nip_penilai = $penilai_settings['penilai_tata_usaha']['nip'] ?? '';
                            $nama_penilai = $penilai_settings['penilai_tata_usaha']['nama'] ?? '';
                        }
                    } else {
                        // Untuk unit kerja lainnya (MTsN 11 Majalengka), penilainya adalah Kepala Madrasah
                        if (!empty($penilai_settings['penilai_mtsn'])) {
                            $nip_penilai = $penilai_settings['penilai_mtsn']['nip'] ?? '';
                            $nama_penilai = $penilai_settings['penilai_mtsn']['nama'] ?? '';
                        }
                    }
                }
                
                // Insert data pegawai baru dengan status pending (menunggu approval admin)
                $insert_stmt = $conn->prepare("INSERT INTO pegawai (nip, password, nama, jabatan, unit_kerja, nip_penilai, nama_penilai, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'user', 'pending')");
                if ($insert_stmt) {
                    $insert_stmt->bind_param("sssssss", 
                        $form_data['nip'], 
                        $hashed_password,
                        $form_data['nama'], 
                        $form_data['jabatan'], 
                        $form_data['unit_kerja'],
                        $nip_penilai,
                        $nama_penilai
                    );
                    
                    if ($insert_stmt->execute()) {
                        echo <<<HTML
                        <html>
                        <head>
                          <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                        </head>
                        <body>
                        <script>
                          Swal.fire({
                            icon: 'success',
                            title: 'Pendaftaran Berhasil!',
                            text: 'Akun Anda telah terdaftar dan menunggu persetujuan admin. Silakan hubungi administrator untuk aktivasi akun.',
                            timer: 4000,
                            showConfirmButton: true,
                            confirmButtonText: 'OK'
                          }).then(() => {
                            window.location.href = 'login.php';
                          });
                        </script>
                        </body>
                        </html>
                        HTML;
                        exit();
                    } else {
                        $error_message = "Terjadi kesalahan saat menyimpan data: " . $conn->error;
                    }
                    $insert_stmt->close();
                } else {
                    $error_message = "Terjadi kesalahan pada sistem: " . $conn->error;
                }
            }
            $check_stmt->close();
        } else {
            $error_message = "Terjadi kesalahan pada sistem: " . $conn->error;
        }
    }
}

$page_title = "Daftar - E-LAPKIN";
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title ?></title>
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

    .register-container {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 15px;
    }

    .register-box {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      border-radius: 16px;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
      padding: 0;
      width: 100%;
      max-width: 480px;
      border: 1px solid rgba(255, 255, 255, 0.2);
      overflow: hidden;
      position: relative;
      animation: floatingBox 6s ease-in-out infinite;
    }

    @keyframes floatingBox {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-10px); }
    }

    .register-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      text-align: center;
      padding: 20px;
      border-radius: 16px 16px 0 0;
      position: relative;
      overflow: hidden;
    }

    .register-header::before {
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
      width: 50px;
      height: 50px;
      background: rgba(255, 255, 255, 0.2);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 10px;
      font-size: 1.5rem;
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
      font-size: 1.2rem;
      font-weight: 700;
      margin-bottom: 3px;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .logo-subtitle {
      font-size: 0.8rem;
      font-weight: 400;
      opacity: 0.9;
    }

    .register-body {
      padding: 20px;
      max-height: 70vh;
      overflow-y: auto;
    }

    .welcome-text {
      text-align: center;
      color: #666;
      margin-bottom: 15px;
      font-weight: 500;
      font-size: 0.85rem;
    }

    .form-group {
      position: relative;
      margin-bottom: 15px;
    }

    .form-control, .form-select {
      width: 100%;
      padding: 10px 12px 10px 35px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 13px;
      background: #f8f9fa;
      transition: all 0.3s ease;
      font-family: 'Poppins', sans-serif;
    }

    .form-control:focus, .form-select:focus {
      outline: none;
      border-color: #667eea;
      background: white;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
      transform: translateY(-1px);
    }

    .input-icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #999;
      font-size: 0.9rem;
      transition: color 0.3s ease;
    }

    .form-control:focus + .input-icon,
    .form-select:focus + .input-icon {
      color: #667eea;
    }

    .password-toggle {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #999;
      cursor: pointer;
      font-size: 0.9rem;
      transition: color 0.3s ease;
    }

    .password-toggle:hover {
      color: #667eea;
    }

    .register-btn {
      width: 100%;
      padding: 10px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border: none;
      border-radius: 8px;
      color: white;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 5px;
      font-family: 'Poppins', sans-serif;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .register-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    }

    .register-btn:active {
      transform: translateY(0);
    }

    .login-link {
      text-align: center;
      margin-top: 15px;
    }

    .login-link a {
      color: #667eea;
      text-decoration: none;
      font-weight: 500;
      font-size: 0.85rem;
      transition: color 0.3s ease;
    }

    .login-link a:hover {
      color: #764ba2;
    }

    .register-footer {
      text-align: center;
      padding: 12px 20px;
      background: #f8f9fa;
      color: #666;
      font-size: 0.7rem;
      line-height: 1.4;
    }

    .register-footer a {
      color: #667eea;
      text-decoration: none;
      font-weight: 500;
    }

    .alert {
      border-radius: 8px;
      border: none;
      padding: 10px;
      margin-bottom: 15px;
      font-weight: 500;
      font-size: 0.8rem;
    }

    .alert-danger {
      background: linear-gradient(135deg, #ff6b6b, #ff5252);
      color: white;
    }

    .alert-success {
      background: linear-gradient(135deg, #4CAF50, #45a049);
      color: white;
    }

    /* Loading Animation */
    .loading {
      display: none;
      width: 14px;
      height: 14px;
      border: 2px solid rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      border-top-color: white;
      animation: spin 1s ease-in-out infinite;
      margin-right: 6px;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* Responsive */
    @media (max-width: 575.98px) {
      .register-container {
        padding: 10px;
      }
      
      .register-box {
        max-width: 100%;
        margin: 10px;
        border-radius: 12px;
      }
      
      .register-header {
        padding: 15px;
        border-radius: 12px 12px 0 0;
      }
      
      .register-body {
        padding: 15px;
        max-height: 60vh;
      }
      
      .logo-icon {
        width: 40px;
        height: 40px;
        font-size: 1.2rem;
      }
      
      .logo-text {
        font-size: 1rem;
      }
      
      .logo-subtitle {
        font-size: 0.75rem;
      }
      
      .form-control, .form-select {
        padding: 8px 10px 8px 30px;
        font-size: 12px;
      }
      
      .input-icon {
        left: 10px;
        font-size: 0.8rem;
      }
      
      .password-toggle {
        right: 10px;
        font-size: 0.8rem;
      }
      
      .register-btn {
        padding: 8px;
        font-size: 12px;
      }
      
      .welcome-text {
        font-size: 0.8rem;
      }
      
      .register-footer {
        font-size: 0.65rem;
        padding: 10px 15px;
      }
    }

    @media (orientation: landscape) and (max-height: 600px) {
      .register-container {
        padding: 5px;
      }
      
      .register-header {
        padding: 10px 20px;
      }
      
      .register-body {
        padding: 15px 20px;
        max-height: 50vh;
      }
      
      .logo-icon {
        width: 35px;
        height: 35px;
        font-size: 1.1rem;
        margin-bottom: 5px;
      }
      
      .logo-text {
        font-size: 0.95rem;
      }
      
      .welcome-text {
        margin-bottom: 10px;
      }
      
      .form-group {
        margin-bottom: 10px;
      }
    }

    /* Row layout for larger screens */
    @media (min-width: 768px) {
      .form-row {
        display: flex;
        gap: 10px;
      }
      
      .form-row .form-group {
        flex: 1;
      }
    }
  </style>
</head>
<body>
  <!-- Floating Graphics -->
  <div class="floating-graphics" id="floatingGraphics"></div>
  
  <!-- Floating Particles -->
  <div class="particles" id="particles"></div>

  <div class="register-container">
    <div class="register-box animate__animated animate__fadeInUp">
      <!-- Header -->
      <div class="register-header">
        <div class="logo-container">
          <div class="logo-icon">
            <i class="fas fa-user-plus"></i>
          </div>
          <div class="logo-text">Daftar E-LAPKIN</div>
          <div class="logo-subtitle">MTsN 11 Majalengka</div>
        </div>
      </div>

      <!-- Body -->
      <div class="register-body">
        <div class="welcome-text">
          <i class="fas fa-clipboard-list"></i>
          Daftar sebagai pengguna baru
        </div>

        <?php if (!empty($error_message)): ?>
          <div class="alert alert-danger animate__animated animate__shakeX" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $error_message; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
          <div class="alert alert-success animate__animated animate__bounceIn" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $success_message; ?>
          </div>
        <?php endif; ?>

        <form action="" method="POST" autocomplete="off" id="registerForm">
          <!-- NIP -->
          <div class="form-group">
            <input 
              id="nip" 
              name="nip" 
              type="text" 
              class="form-control" 
              placeholder="Nomor Induk Pegawai (NIP)" 
              required 
              value="<?php echo htmlspecialchars($form_data['nip']); ?>"
            >
            <i class="fas fa-id-badge input-icon"></i>
          </div>

          <!-- Nama -->
          <div class="form-group">
            <input 
              id="nama" 
              name="nama" 
              type="text" 
              class="form-control" 
              placeholder="Nama Lengkap" 
              required 
              value="<?php echo htmlspecialchars($form_data['nama']); ?>"
            >
            <i class="fas fa-user input-icon"></i>
          </div>

          <!-- Jabatan dan Unit Kerja -->
          <div class="form-row">
            <div class="form-group">
              <input 
                id="jabatan" 
                name="jabatan" 
                type="text" 
                class="form-control" 
                placeholder="Jabatan sesuai SK" 
                required 
                value="<?php echo htmlspecialchars($form_data['jabatan']); ?>"
              >
              <i class="fas fa-briefcase input-icon"></i>
            </div>

            <div class="form-group">
              <select id="unit_kerja" name="unit_kerja" class="form-select" required>
                <option value="">Pilih Unit Kerja</option>
                <?php foreach ($unit_kerja_options as $unit): ?>
                  <option value="<?php echo htmlspecialchars($unit['nama_unit']); ?>" 
                    <?php echo ($form_data['unit_kerja'] == $unit['nama_unit']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($unit['nama_unit']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <i class="fas fa-building input-icon"></i>
            </div>
          </div>

          <!-- Password -->
          <div class="form-group">
            <input 
              id="password" 
              name="password" 
              type="password" 
              class="form-control" 
              placeholder="Password (minimal 6 karakter)" 
              required
            >
            <i class="fas fa-lock input-icon"></i>
            <i class="fas fa-eye password-toggle" onclick="togglePassword('password', 'toggleEye1')" id="toggleEye1"></i>
          </div>

          <!-- Confirm Password -->
          <div class="form-group">
            <input 
              id="confirm_password" 
              name="confirm_password" 
              type="password" 
              class="form-control" 
              placeholder="Konfirmasi Password" 
              required
            >
            <i class="fas fa-lock input-icon"></i>
            <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password', 'toggleEye2')" id="toggleEye2"></i>
          </div>

          <!-- Info Penilai -->
          <div class="form-group">
            <div class="alert alert-info" style="margin-bottom: 15px; padding: 10px; font-size: 0.8rem;">
              <i class="fas fa-info-circle"></i>
              <strong>Informasi:</strong> Akun akan menunggu persetujuan admin sebelum dapat digunakan. Pejabat penilai akan otomatis terisi berdasarkan Unit Kerja yang dipilih.
            </div>
          </div>

          <button type="submit" class="register-btn" id="registerBtn">
            <span class="loading" id="loading"></span>
            <span id="btnText">
              <i class="fas fa-user-plus me-2"></i>
              Daftar Sekarang
            </span>
          </button>
        </form>

        <div class="login-link">
          <a href="login.php">
            <i class="fas fa-sign-in-alt me-1"></i>
            Sudah punya akun? Login di sini
          </a>
        </div>
      </div>

      <!-- Footer -->
      <div class="register-footer">
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
    // Reuse the same JavaScript functions from login.php with minor modifications
    function createFloatingGraphics() {
      const graphicsContainer = document.getElementById('floatingGraphics');
      const shapes = ['circle', 'triangle', 'square', 'hexagon'];
      const shapeCount = 8;

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

    function createParticles() {
      const particlesContainer = document.getElementById('particles');
      const particleCount = window.innerWidth < 768 ? 30 : 50;

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

    function togglePassword(fieldId, iconId) {
      const passwordField = document.getElementById(fieldId);
      const toggleIcon = document.getElementById(iconId);
      
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

    // Form validation
    document.getElementById('registerForm').addEventListener('submit', function(e) {
      const btn = document.getElementById('registerBtn');
      const loading = document.getElementById('loading');
      const btnText = document.getElementById('btnText');
      
      // Validate passwords match
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirm_password').value;
      
      if (password !== confirmPassword) {
        e.preventDefault();
        Swal.fire({
          icon: 'error',
          title: 'Password Tidak Cocok',
          text: 'Konfirmasi password harus sama dengan password.',
          timer: 2000
        });
        return;
      }
      
      btn.disabled = true;
      loading.style.display = 'inline-block';
      btnText.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';
    });

    // Input focus animations
    document.querySelectorAll('.form-control, .form-select').forEach(input => {
      input.addEventListener('focus', function() {
        this.parentElement.classList.add('animate__animated', 'animate__pulse');
      });
      
      input.addEventListener('blur', function() {
        this.parentElement.classList.remove('animate__animated', 'animate__pulse');
      });
    });

    function handleResize() {
      const particlesContainer = document.getElementById('particles');
      const graphicsContainer = document.getElementById('floatingGraphics');
      
      particlesContainer.innerHTML = '';
      graphicsContainer.innerHTML = '';
      
      createParticles();
      createFloatingGraphics();
    }

    document.addEventListener('DOMContentLoaded', function() {
      createParticles();
      createFloatingGraphics();
      document.getElementById('nip').focus();
    });

    window.addEventListener('resize', handleResize);

    // Phone number formatting - remove this since telepon field doesn't exist
    // document.getElementById('telepon').addEventListener('input', function(e) {
    //   let value = e.target.value.replace(/\D/g, '');
    //   if (value.length > 0) {
    //     if (value.startsWith('0')) {
    //       value = value;
    //     } else if (value.startsWith('62')) {
    //       value = '0' + value.substring(2);
    //     }
    //   }
    //   e.target.value = value;
    // });
  </script>
</body>
</html>
</html>
