<?php
/**
 * CMS Dashboard
 * 
 * REFACTORED: Uses centralized config and functions
 */

session_start();

// ==========================================
// LOAD CENTRALIZED CONFIG AND FUNCTIONS
// ==========================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Load websites from JSON - using constant from config.php
if (!file_exists(WEBSITES_CONFIG_FILE)) {
    die("Configuration file not found at: " . WEBSITES_CONFIG_FILE);
}

$configContent = file_get_contents(WEBSITES_CONFIG_FILE);
$configData = json_decode($configContent, true);
$websites = $configData['websites'] ?? [];
$admins = $configData['admins'] ?? [];

// Find current admin
$admin = null;
foreach ($admins as $a) {
    if ($a['id'] == $_SESSION['admin_id']) {
        $admin = $a;
        break;
    }
}

if (!$admin) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Check for success messages
$successMessage = '';
if (isset($_SESSION['delete_success'])) {
    $successMessage = $_SESSION['delete_success'];
    unset($_SESSION['delete_success']);
}

// ==========================================
// DASHBOARD-SPECIFIC FUNCTIONS
// (These are specific to dashboard and don't need to be in shared functions.php)
// ==========================================

/**
 * Render logo preview with relative path
 */
function renderLogoPreview($logo) {
    if (preg_match('/\.(png|jpg|jpeg|webp|svg|avif)$/i', $logo)) {
        $logoFile = htmlspecialchars($logo);
        $logoUrl = '/images/logos/' . $logoFile;
        return '<img src="' . $logoUrl . '?v=' . time() . '" alt="Logo" class="logo-preview-img" style="width: 32px; height: 32px; object-fit: contain; vertical-align: middle; margin-right: 8px; border-radius: 4px;" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'inline\';">';
    } else {
        return '<span class="site-logo">' . htmlspecialchars($logo) . '</span>';
    }
}

/**
 * Generate Search Console URL for domain
 */
function getSearchConsoleUrl($domain) {
    return 'https://search.google.com/search-console?resource_id=sc-domain%3A' . urlencode($domain);
}

/**
 * Generate Analytics URL
 */
function getAnalyticsUrl() {
    return 'https://analytics.google.com/analytics/web/';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS Dashboard</title>
    <link rel="stylesheet" href="cms-style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        /* External Links Icons */
        .external-links {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .external-link-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s ease;
            position: relative;
        }
        
        .external-link-icon:hover {
            transform: scale(1.1);
        }
        
        .external-link-icon.gsc {
            background: linear-gradient(135deg, #4285f4, #34a853);
            color: white;
        }
        
        .external-link-icon.ga {
            background: linear-gradient(135deg, #f9ab00, #e37400);
            color: white;
        }
        
        .external-link-icon.ga.disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.5;
        }
        
        .external-link-icon.ga.disabled:hover {
            transform: none;
        }
        
        /* Tooltip */
        .external-link-icon::after {
            content: attr(title);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 4px 8px;
            background: #333;
            color: white;
            font-size: 11px;
            border-radius: 4px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s;
            pointer-events: none;
        }
        
        .external-link-icon:hover::after {
            opacity: 1;
            visibility: visible;
            bottom: calc(100% + 5px);
        }
        
        /* Update table layout */
        .data-table th:first-child,
        .data-table td:first-child {
            width: 80px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="cms-layout">
        <!-- Sidebar -->
        <aside class="cms-sidebar">
            <div class="cms-logo">
                <h2>🎯 CMS</h2>
            </div>
            
            <nav class="cms-nav">
                <a href="dashboard.php" class="nav-item active">
                    <span>🏠</span> Dashboard
                </a>
                <a href="website-add.php" class="nav-item">
                    <span>➕</span> Add Website
                </a>
                <a href="languages.php" class="nav-item">
                    <span>🌐</span> Languages
                </a>
                <a href="icons.php" class="nav-item">
                    <span>🖼️</span> Sport Icons
                </a>
            </nav>
            
            <div class="cms-user">
                <p><strong><?php echo htmlspecialchars($admin['username']); ?></strong></p>
                <a href="logout.php" class="btn btn-sm btn-outline">Logout</a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="cms-main">
            <header class="cms-header">
                <h1>Dashboard</h1>
            </header>
            
            <div class="cms-content">
                <?php if ($successMessage): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
                <?php endif; ?>
                
                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">🌐</div>
                        <div class="stat-info">
                            <h3><?php echo count($websites); ?></h3>
                            <p>Total Websites</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">✅</div>
                        <div class="stat-info">
                            <h3><?php echo count(array_filter($websites, fn($w) => $w['status'] === 'active')); ?></h3>
                            <p>Active Websites</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">👤</div>
                        <div class="stat-info">
                            <h3><?php echo htmlspecialchars($admin['username']); ?></h3>
                            <p>Logged in as</p>
                        </div>
                    </div>
                </div>
                
                <!-- Websites List -->
                <div class="content-section">
                    <div class="section-header">
                        <h2>Your Websites</h2>
                        <a href="website-add.php" class="btn btn-primary">+ Add Website</a>
                    </div>
                    
                    <?php if (empty($websites)): ?>
                        <div style="text-align: center; padding: 60px 20px; color: #999;">
                            <div style="font-size: 60px; margin-bottom: 20px;">🌐</div>
                            <h3>No websites yet</h3>
                            <p>Click "Add Website" to create your first website</p>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Tools</th>
                                        <th>Domain</th>
                                        <th>Site Name</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($websites as $website): ?>
                                        <tr>
                                            <!-- External Links Column -->
                                            <td>
                                                <div class="external-links">
                                                    <a href="<?php echo getSearchConsoleUrl($website['domain']); ?>" 
                                                       target="_blank" 
                                                       class="external-link-icon gsc" 
                                                       title="Search Console">
                                                        🔍
                                                    </a>
                                                    <?php if (!empty($website['google_analytics_id'])): ?>
                                                        <a href="<?php echo getAnalyticsUrl(); ?>" 
                                                           target="_blank" 
                                                           class="external-link-icon ga" 
                                                           title="Analytics: <?php echo htmlspecialchars($website['google_analytics_id']); ?>">
                                                            📊
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="external-link-icon ga disabled" 
                                                              title="No Analytics ID configured">
                                                            📊
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <a href="https://<?php echo htmlspecialchars($website['domain']); ?>" target="_blank" class="domain-link">
                                                    <?php echo htmlspecialchars($website['domain']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <div class="logo-with-name">
                                                    <?php echo renderLogoPreview($website['logo']); ?>
                                                    <span><?php echo htmlspecialchars($website['site_name']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $website['status']; ?>">
                                                    <?php echo ucfirst($website['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="website-edit.php?id=<?php echo $website['id']; ?>" class="btn btn-sm">Settings</a>
                                                    <a href="website-pages.php?id=<?php echo $website['id']; ?>" class="btn btn-sm" style="background: #9b59b6; color: white;">Pages</a>
                                                    <a href="website-delete.php?id=<?php echo $website['id']; ?>" class="btn btn-sm btn-danger">Delete</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>