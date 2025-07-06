<?php
/**
 * ========================================================
 * E-LAPKIN MTSN 11 MAJALENGKA - INTEGRATION TEST
 * ========================================================
 * 
 * File: Integration Test
 * Deskripsi: Test integrasi antara web utama dan mobile app
 * 
 * @package    E-Lapkin-MTSN11
 * @version    1.0.0
 * ========================================================
 */

session_start();

// Define ABSPATH
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Include dependencies
require_once __DIR__ . '/config/mobile_security.php';
require_once __DIR__ . '/config/mobile_database.php';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-LAPKIN Mobile Integration Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .test-card { margin-bottom: 1rem; }
        .test-result { margin-top: 10px; }
        .status-ok { color: #28a745; }
        .status-error { color: #dc3545; }
        .status-warning { color: #ffc107; }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-wrench me-2"></i>
                            E-LAPKIN Mobile Integration Test
                        </h4>
                    </div>
                    <div class="card-body">
                        
                        <!-- Database Connection Test -->
                        <div class="test-card card">
                            <div class="card-header">
                                <h5><i class="fas fa-database me-2"></i>Database Connection Test</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $db_test = getMobileDBConnection();
                                if ($db_test) {
                                    echo '<div class="status-ok"><i class="fas fa-check-circle me-2"></i>Database connection successful</div>';
                                    
                                    // Test pegawai table
                                    try {
                                        $stmt = $db_test->prepare("SELECT COUNT(*) as total FROM pegawai");
                                        $stmt->execute();
                                        $result = $stmt->fetch();
                                        echo '<div class="test-result status-ok">Pegawai table: ' . $result['total'] . ' records found</div>';
                                    } catch (Exception $e) {
                                        echo '<div class="test-result status-error">Pegawai table error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                                    }
                                    
                                    // Test LKH table
                                    try {
                                        $stmt = $db_test->prepare("SELECT COUNT(*) as total FROM lkh");
                                        $stmt->execute();
                                        $result = $stmt->fetch();
                                        echo '<div class="test-result status-ok">LKH table: ' . $result['total'] . ' records found</div>';
                                    } catch (Exception $e) {
                                        echo '<div class="test-result status-error">LKH table error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                                    }
                                    
                                    // Test RKB table
                                    try {
                                        $stmt = $db_test->prepare("SELECT COUNT(*) as total FROM rkb");
                                        $stmt->execute();
                                        $result = $stmt->fetch();
                                        echo '<div class="test-result status-ok">RKB table: ' . $result['total'] . ' records found</div>';
                                    } catch (Exception $e) {
                                        echo '<div class="test-result status-error">RKB table error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                                    }
                                } else {
                                    echo '<div class="status-error"><i class="fas fa-times-circle me-2"></i>Database connection failed</div>';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <!-- Mobile Security Test -->
                        <div class="test-card card">
                            <div class="card-header">
                                <h5><i class="fas fa-shield-alt me-2"></i>Mobile Security Test</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                                $is_valid = is_valid_mobile_app();
                                
                                echo '<div>Current User Agent: <code>' . htmlspecialchars($user_agent) . '</code></div>';
                                echo '<div>Expected User Agent: <code>' . MOBILE_APP_USER_AGENT . '</code></div>';
                                
                                if ($is_valid) {
                                    echo '<div class="test-result status-ok"><i class="fas fa-check-circle me-2"></i>Mobile app validation: PASSED</div>';
                                } else {
                                    if (is_development_mode()) {
                                        echo '<div class="test-result status-warning"><i class="fas fa-exclamation-triangle me-2"></i>Mobile app validation: DEVELOPMENT MODE (validation relaxed)</div>';
                                    } else {
                                        echo '<div class="test-result status-error"><i class="fas fa-times-circle me-2"></i>Mobile app validation: FAILED</div>';
                                    }
                                }
                                
                                echo '<div class="test-result">Development mode: ' . (is_development_mode() ? 'YES' : 'NO') . '</div>';
                                ?>
                            </div>
                        </div>
                        
                        <!-- File Structure Test -->
                        <div class="test-card card">
                            <div class="card-header">
                                <h5><i class="fas fa-folder me-2"></i>File Structure Test</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $required_files = [
                                    'config/mobile_security.php' => 'Mobile Security Config',
                                    'config/mobile_database.php' => 'Mobile Database Config',
                                    'config/shared_session.php' => 'Shared Session Config',
                                    'auth/mobile_login.php' => 'Mobile Login Handler',
                                    'auth/mobile_logout.php' => 'Mobile Logout Handler',
                                    'user/dashboard.php' => 'Mobile Dashboard',
                                    'template/header_mobile.php' => 'Mobile Header Template',
                                    'template/navigation_mobile.php' => 'Mobile Navigation',
                                    'assets/css/mobile.css' => 'Mobile CSS',
                                    'assets/js/mobile.js' => 'Mobile JavaScript',
                                    'bridge.php' => 'Web-Mobile Bridge'
                                ];
                                
                                foreach ($required_files as $file => $description) {
                                    $path = __DIR__ . '/' . $file;
                                    if (file_exists($path)) {
                                        echo '<div class="test-result status-ok"><i class="fas fa-check me-2"></i>' . $description . ': Found</div>';
                                    } else {
                                        echo '<div class="test-result status-error"><i class="fas fa-times me-2"></i>' . $description . ': Missing</div>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        
                        <!-- Functions Test -->
                        <div class="test-card card">
                            <div class="card-header">
                                <h5><i class="fas fa-code me-2"></i>Functions Test</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $functions = [
                                    'getMobileDBConnection' => 'Database Connection Function',
                                    'getMobilePegawaiData' => 'Get Pegawai Data Function',
                                    'getMobileLKHSummary' => 'Get LKH Summary Function',
                                    'getMobileRKBData' => 'Get RKB Data Function',
                                    'getMobileRecentActivities' => 'Get Recent Activities Function',
                                    'is_valid_mobile_app' => 'Mobile App Validation Function',
                                    'block_non_mobile_access' => 'Block Non-Mobile Access Function',
                                    'log_mobile_access' => 'Mobile Access Logging Function',
                                    'sendMobileResponse' => 'Mobile Response Function'
                                ];
                                
                                foreach ($functions as $func => $description) {
                                    if (function_exists($func)) {
                                        echo '<div class="test-result status-ok"><i class="fas fa-check me-2"></i>' . $description . ': Available</div>';
                                    } else {
                                        echo '<div class="test-result status-error"><i class="fas fa-times me-2"></i>' . $description . ': Missing</div>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        
                        <!-- API Test -->
                        <div class="test-card card">
                            <div class="card-header">
                                <h5><i class="fas fa-globe me-2"></i>API Endpoints Test</h5>
                            </div>
                            <div class="card-body">
                                <div class="test-result">
                                    <strong>Mobile App URLs:</strong><br>
                                    <ul class="mt-2">
                                        <li><a href="/mobile-app/" target="_blank">Mobile App Entry Point</a></li>
                                        <li><a href="/mobile-app/index.php" target="_blank">Mobile Login Page</a></li>
                                        <li><a href="/mobile-app/bridge.php" target="_blank">Web-Mobile Bridge</a> (requires token)</li>
                                        <li><a href="/mobile-app/test.html" target="_blank">Mobile Security Test</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Integration Status -->
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h5><i class="fas fa-info-circle me-2"></i>Integration Status</h5>
                                <p class="mb-0">
                                    Mobile app terintegrasi dengan database yang sama dengan aplikasi web utama.<br>
                                    User dengan role 'user' dapat mengakses mobile app, admin tidak dapat mengakses.<br>
                                    Session mobile terpisah namun data user tetap sinkron dengan aplikasi web.
                                </p>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
