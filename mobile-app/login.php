<?php
session_start();
require_once '../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nip = trim($_POST['nip']);
    $password = trim($_POST['password']);
    
    if (!empty($nip) && !empty($password)) {
        $stmt = $conn->prepare("SELECT id_pegawai, nama, nip, jabatan, unit_kerja, password FROM pegawai WHERE nip = ?");
        $stmt->bind_param("s", $nip);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Check password (assuming plain text for now, use password_verify for hashed passwords)
            if ($password === $user['password'] || password_verify($password, $user['password'])) {
                $_SESSION['id_pegawai'] = $user['id_pegawai'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['nip'] = $user['nip'];
                $_SESSION['jabatan'] = $user['jabatan'];
                $_SESSION['unit_kerja'] = $user['unit_kerja'];
                
                // Redirect to dashboard or main page
                header('Location: index.php');
                exit;
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

// If there's an error, redirect back to index with error
if ($error) {
    $_SESSION['login_error'] = $error;
    header('Location: index.php?error=' . urlencode($error));
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - E-LAPKIN MTSN 11 Majalengka</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .login-card { background: rgba(255,255,255,0.95); border-radius: 15px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="container-fluid d-flex align-items-center justify-content-center min-vh-100">
        <div class="col-md-4">
            <div class="card login-card">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold text-primary">E-LAPKIN</h3>
                        <p class="text-muted">MTSN 11 Majalengka</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="nip" class="form-label">NIP</label>
                            <input type="text" class="form-control" id="nip" name="nip" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Login</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Notify Android app about user login
        function notifyAndroidLogin(userId, userName) {
            if (typeof Android !== 'undefined' && Android.onUserLogin) {
                Android.onUserLogin(userId, userName);
            }
        }

        // Function to get user ID (called by Android app)
        window.getUserId = function() {
            <?php if (isset($_SESSION['user_id'])): ?>
                return '<?= $_SESSION['user_id'] ?>';
            <?php else: ?>
                return null;
            <?php endif; ?>
        };

        // If user is logged in, notify Android
        <?php if (isset($_SESSION['user_id'])): ?>
            document.addEventListener('DOMContentLoaded', function() {
                notifyAndroidLogin('<?= $_SESSION['user_id'] ?>', '<?= addslashes($_SESSION['user_name']) ?>');
            });
        <?php endif; ?>
    </script>
</body>
</html>
