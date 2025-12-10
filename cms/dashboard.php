<?php
/**
 * CMS Dashboard
 * 
 * UPDATED: Filters websites by ownership (user's own + shared)
 * UPDATED: Uses 'user_id' session instead of 'admin_id'
 * UPDATED: Uses 'users' array instead of 'admins'
 * 
 * Location: /var/www/u1852176/data/www/watchlivesport.online/dashboard.php
 */

session_start();

// ==========================================
// LOAD CENTRALIZED CONFIG AND FUNCTIONS
// ==========================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Check if user is logged in (NEW: user_id instead of admin_id)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Load websites from JSON - using constant from config.php
if (!file_exists(WEBSITES_CONFIG_FILE)) {
    die("Configuration file not found at: " . WEBSITES_CONFIG_FILE);
}

$configContent = file_get_contents(WEBSITES_CONFIG_FILE);
$configData = json_decode($configContent, true);
$allWebsites = $configData['websites'] ?? [];

// Get users (NEW: 'users' instead of 'admins')
$users = $configData['users'] ?? $configData['admins'] ?? [];

// Find current user (NEW: using getCurrentUser function)
$user = getCurrentUser();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// ==========================================
// FILTER WEBSITES FOR CURRENT USER
// Only show: user's own websites + shared websites
// ==========================================
$websites = filterWebsitesForUser($allWebsites);
$websiteCounts = getWebsiteCountsForUser($allWebsites);

// Check for success messages
$successMessage = getFlashMessage('success');

// ==========================================
// DASHBOARD-SPECIFIC FUNCTIONS
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
 * Get Google Analytics URL for a website
 * Uses the stored analytics_url if available
 */
function getAnalyticsUrl($website) {
    // If website has a specific analytics URL stored, use it
    if (!empty($website['analytics_url'])) {
        return $website['analytics_url'];
    }
    // Fallback to generic analytics page
    return 'https://analytics.google.com/analytics/web/';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CMS</title>
    <link rel="stylesheet" href="cms-style.css">
    <style>
        /* Owner badge styles */
        .owner-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .owner-badge.own {
            background: #e3f2fd;
            color: #1565c0;
        }
        .owner-badge.shared {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        /* External link icon styles */
        .external-link-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.2s;
            position: relative;
        }
        .external-link-icon:hover {
            transform: translateY(-2px);
        }
        .external-link-icon.analytics {
            background: #fff3e0;
            color: #e65100;
        }
        .external-link-icon.analytics:hover {
            background: #ffe0b2;
        }
        .external-link-icon.search-console {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .external-link-icon.search-console:hover {
            background: #c8e6c9;
        }
        .external-link-icon.visit {
            background: #e3f2fd;
            color: #1565c0;
        }
        .external-link-icon.visit:hover {
            background: #bbdefb;
        }
        
        /* Tooltip */
        .external-link-icon::after {
            content: attr(title);
            position: absolute;
            bottom: calc(100% + 5px);
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s;
        }
        .external-link-icon:hover::after {
            opacity: 1;
            visibility: visible;
            bottom: calc(100% + 5px);
        }
        
        /* Stats card for ownership */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
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
                    <span>🖼️</span> Icons
                </a>
                <a href="users.php" class="nav-item">
                    <span>👥</span> Users
                </a>
            </nav>
            
            <div class="cms-user">
                <p><strong><?php echo htmlspecialchars($user['username']); ?></strong></p>
                <a href="profile.php" class="btn btn-sm btn-outline" style="margin-bottom: 5px;">My Profile</a>
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
                            <h3><?php echo $websiteCounts['total']; ?></h3>
                            <p>Total Websites</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">👤</div>
                        <div class="stat-info">
                            <h3><?php echo $websiteCounts['own']; ?></h3>
                            <p>My Websites</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-info">
                            <h3><?php echo $websiteCounts['shared']; ?></h3>
                            <p>Shared Websites</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">✅</div>
                        <div class="stat-info">
                            <h3><?php echo count(array_filter($websites, fn($w) => $w['status'] === 'active')); ?></h3>
                            <p>Active</p>
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
                                        <th style="width: 80px;">Tools</th>
                                        <th>Domain</th>
                                        <th>Site Name</th>
                                        <th>Owner</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($websites as $website): ?>
                                        <?php 
                                        $domain = $website['domain'];
                                        $gaId = $website['google_analytics_id'] ?? '';
                                        $owner = $website['owner'] ?? 'shared';
                                        $isOwn = isWebsiteOwner($website);
                                        ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; gap: 4px;">
                                                    <!-- Google Analytics (only if has analytics ID) -->
                                                    <?php if ($gaId): ?>
                                                        <a href="<?php echo htmlspecialchars(getAnalyticsUrl($website)); ?>" 
                                                           target="_blank" 
                                                           class="external-link-icon analytics"
                                                           title="Google Analytics">
                                                            📊
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Search Console -->
                                                    <a href="<?php echo getSearchConsoleUrl($domain); ?>" 
                                                       target="_blank" 
                                                       class="external-link-icon search-console"
                                                       title="Search Console">
                                                        🔍
                                                    </a>
                                                </div>
                                            </td>
                                            <td>
                                                <?php echo renderLogoPreview($website['logo'] ?? ''); ?>
                                                <a href="https://<?php echo htmlspecialchars($domain); ?>" 
                                                   target="_blank" 
                                                   style="color: #333; text-decoration: none; font-weight: 600;">
                                                    <?php echo htmlspecialchars($domain); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($website['site_name']); ?></td>
                                            <td>
                                                <span class="owner-badge <?php echo $owner === 'shared' ? 'shared' : 'own'; ?>">
                                                    <?php echo $owner === 'shared' ? '👥 Shared' : '👤 ' . getOwnerDisplayName($owner); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo $website['status']; ?>">
                                                    <?php echo ucfirst($website['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="website-edit.php?id=<?php echo $website['id']; ?>" class="btn btn-sm">Settings</a>
                                                    <a href="website-pages.php?id=<?php echo $website['id']; ?>" class="btn btn-sm">Pages</a>
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