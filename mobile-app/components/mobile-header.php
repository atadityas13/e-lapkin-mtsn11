<?php
/**
 * Mobile Header Component
 * Consistent header for all mobile pages
 */

function renderMobileHeader($pageTitle, $pageSubtitle, $backUrl = 'dashboard.php', $userData = null, $activePeriod = null) {
    // Get user data if not provided
    if (!$userData) {
        $userData = getMobileSessionData();
    }
    
    // Get active period if not provided
    if (!$activePeriod && isset($GLOBALS['conn'])) {
        $activePeriod = getMobileActivePeriod($GLOBALS['conn'], $userData['id_pegawai']);
    }
    
    echo '
    <nav class="navbar nav-header">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <a class="navbar-brand d-flex align-items-center text-white" href="' . htmlspecialchars($backUrl) . '">
                <i class="fas fa-arrow-left me-3"></i>
                <div>
                    <div class="fw-bold">' . htmlspecialchars($pageTitle) . '</div>
                    <small class="opacity-75">' . htmlspecialchars($pageSubtitle) . '</small>
                </div>
            </a>
            <div class="text-white text-end" style="background-color: rgba(255,255,255,0.1); padding: 8px 12px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.2);">
                <div class="fw-semibold" style="font-size: 0.85rem; line-height: 1.2;">' . htmlspecialchars($userData['nama']) . '</div>
                <div class="small opacity-75" style="font-size: 0.75rem; line-height: 1.2;">' . htmlspecialchars($userData['nip']) . '</div>';
    
    if ($activePeriod) {
        echo '<div class="small opacity-75" style="font-size: 0.75rem; line-height: 1.2;">' . htmlspecialchars($activePeriod) . '</div>';
    }
    
    echo '
            </div>
        </div>
    </nav>';
}

function getMobileHeaderCSS() {
    return '
    <style>
        .nav-header {
            background: var(--primary-gradient);
            color: white;
            padding: 20px 15px;
            border-radius: 0 0 25px 25px;
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
        }

        .nav-header .navbar-brand {
            font-size: 1.3rem;
            font-weight: 600;
        }

        .nav-header .navbar-brand:hover {
            color: white;
            text-decoration: none;
        }
        
        @media (max-width: 576px) {
            .nav-header {
                padding: 15px 10px;
            }
            
            .nav-header .navbar-brand {
                font-size: 1.1rem;
            }
            
            .nav-header .text-end > div {
                font-size: 0.8rem !important;
            }
            
            .nav-header .text-end .small {
                font-size: 0.7rem !important;
            }
        }
    </style>';
}
?>
