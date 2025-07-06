<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA - MOBILE APP
 * ========================================================
 */

session_start();

// Include mobile session config for validation functions
require_once __DIR__ . '/config/mobile_session.php';

// For login page, only validate User Agent (not token/package since user hasn't logged in yet)
function validateLoginAccess() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if ($user_agent !== MOBILE_USER_AGENT) {
        http_response_code(403);
        die(json_encode([
            'error' => 'Access denied. This mobile interface is only accessible via the official E-LAPKIN Mobile App.',
            'required_user_agent' => MOBILE_USER_AGENT
        ]));
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
    <title>E-LAPKIN Mobile - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/mobile.css">
</head>
<body class="bg-primary">
    <div class="container-fluid vh-100 d-flex align-items-center">
        <div class="row w-100 justify-content-center">
            <div class="col-12 col-sm-8 col-md-6 col-lg-4">
                <div class="card shadow-lg border-0 rounded-4">
                    <div class="card-header bg-primary text-white text-center py-4 rounded-top-4">
                        <i class="fas fa-mobile-alt fa-3x mb-3"></i>
                        <h4 class="fw-bold mb-1">E-LAPKIN Mobile</h4>
                        <small>MTsN 11 Majalengka</small>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="nip" class="form-label">NIP</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-id-badge"></i></span>
                                    <input type="text" class="form-control" id="nip" name="nip" value="<?php echo htmlspecialchars($nip); ?>" required>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                                <i class="fas fa-sign-in-alt me-2"></i>Masuk
                            </button>
                        </form>
                    </div>
                    <div class="card-footer text-center text-muted py-3">
                        <small>&copy; <?= date('Y') ?> E-LAPKIN Mobile</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
