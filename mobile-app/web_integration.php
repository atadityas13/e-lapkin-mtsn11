<?php
/**
 * Web integration helper for mobile app features
 */

function checkMobileAppStatus() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $is_mobile_browser = preg_match('/Mobile|Android|iPhone|iPad/', $user_agent);
    
    return [
        'is_mobile_browser' => $is_mobile_browser,
        'user_can_access' => isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true,
        'mobile_app_available' => true
    ];
}

function generateMobileAppWidget() {
    $status = checkMobileAppStatus();
    
    if (!$status['user_can_access']) {
        return '';
    }
    
    $mobile_url = 'javascript:void(0)';
    $button_text = 'Install Mobile App';
    $button_class = 'btn-outline-primary';
    $description = 'Install aplikasi mobile untuk akses yang lebih mudah';
    
    if ($status['is_mobile_browser']) {
        require_once __DIR__ . '/config/shared_session.php';
        $mobile_url = createMobileBridgeUrl() ?: 'javascript:void(0)';
        $button_text = 'Buka Mobile App';
        $button_class = 'btn-primary';
        $description = 'Akses versi mobile yang dioptimalkan';
    }
    
    return <<<HTML
    <div class="card border-left-primary shadow h-100 py-2">
        <div class="card-body">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                        Mobile App
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        <i class="fas fa-mobile-alt text-primary mr-2"></i>
                        E-LAPKIN Mobile
                    </div>
                    <div class="text-sm text-gray-600 mt-2">
                        {$description}
                    </div>
                    <a href="{$mobile_url}" class="btn {$button_class} btn-sm mt-2" target="_blank">
                        <i class="fas fa-external-link-alt mr-1"></i>
                        {$button_text}
                    </a>
                </div>
                <div class="col-auto">
                    <i class="fas fa-mobile-alt fa-2x text-gray-300"></i>
                </div>
            </div>
        </div>
    </div>
    HTML;
}
?>
