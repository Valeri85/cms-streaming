<?php
/**
 * CMS Dashboard
 * 
 * UPDATED: Analytics icon now uses analytics_property_url for direct links
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Load data
$configContent = file_get_contents(WEBSITES_CONFIG_FILE);
$configData = json_decode($configContent, true);
$allWebsites = $configData['websites'] ?? [];

// Get current user
$user = getCurrentUser();
$currentUserOwner = getCurrentUserOwner();

// Separate websites into "mine" and "shared"
$myWebsites = [];
$sharedWebsites = [];

foreach ($allWebsites as $website) {
    $owner = $website['owner'] ?? 'shared';
    
    if ($owner === $currentUserOwner) {
        $myWebsites[] = $website;
    } elseif ($owner === 'shared') {
        $sharedWebsites[] = $website;
    }
}

// Count stats
$totalAccessible = count($myWebsites) + count($sharedWebsites);
$activeCount = count(array_filter(array_merge($myWebsites, $sharedWebsites), fn($w) => $w['status'] === 'active'));

// Check for flash messages
$successMessage = getFlashMessage('success');

// Get active tab from URL parameter (default to 'mine')
$activeTab = $_GET['tab'] ?? 'mine';
if (!in_array($activeTab, ['mine', 'shared'])) {
    $activeTab = 'mine';
}

// Page configuration for header
$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

include __DIR__ . '/includes/header.php';
?>

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
                <h3><?php echo $totalAccessible; ?></h3>
                <p>Total Websites</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👤</div>
            <div class="stat-info">
                <h3><?php echo count($myWebsites); ?></h3>
                <p>My Websites</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-info">
                <h3><?php echo count($sharedWebsites); ?></h3>
                <p>Shared Websites</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-info">
                <h3><?php echo $activeCount; ?></h3>
                <p>Active</p>
            </div>
        </div>
    </div>
    
    <!-- Websites Section with Tabs -->
    <div class="content-section">
        <div class="section-header">
            <div class="tabs-header">
                <a href="?tab=mine" class="tab-btn <?php echo $activeTab === 'mine' ? 'active' : ''; ?>">
                    👤 Your Websites <span class="tab-count"><?php echo count($myWebsites); ?></span>
                </a>
                <a href="?tab=shared" class="tab-btn <?php echo $activeTab === 'shared' ? 'active' : ''; ?>">
                    👥 Shared Websites <span class="tab-count"><?php echo count($sharedWebsites); ?></span>
                </a>
            </div>
            <a href="website-add.php" class="btn btn-primary">+ Add Website</a>
        </div>
        
        <?php 
        $displayWebsites = ($activeTab === 'shared') ? $sharedWebsites : $myWebsites;
        $emptyMessage = ($activeTab === 'shared') ? "No shared websites yet" : "You don't have any personal websites yet";
        $emptySubtext = ($activeTab === 'shared') ? "Shared websites are visible to all users" : "Click \"+ Add Website\" to create your first website";
        ?>
        
        <?php if (empty($displayWebsites)): ?>
            <div style="text-align: center; padding: 60px 20px; color: #999;">
                <div style="font-size: 60px; margin-bottom: 20px;"><?php echo $activeTab === 'shared' ? '👥' : '🌐'; ?></div>
                <h3><?php echo $emptyMessage; ?></h3>
                <p><?php echo $emptySubtext; ?></p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Tools</th>
                            <th>Domain</th>
                            <th>Site Name</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($displayWebsites as $website): 
                            $websiteDomain = $website['domain'];
                            $websiteGaId = $website['google_analytics_id'] ?? '';
                            $websiteAnalyticsUrl = $website['analytics_property_url'] ?? '';
                            $hasAnalytics = !empty($websiteGaId) && !empty($websiteAnalyticsUrl);
                            $hasPartialAnalytics = !empty($websiteGaId) && empty($websiteAnalyticsUrl);
                            $searchConsoleUrl = 'https://search.google.com/search-console?resource_id=sc-domain%3A' . urlencode($websiteDomain);
                        ?>
                            <tr>
                                <td>
                                    <div class="tools-icons">
                                        <?php if ($hasAnalytics): ?>
                                            <a href="<?php echo htmlspecialchars($websiteAnalyticsUrl); ?>" target="_blank" class="tool-icon analytics" title="Analytics: <?php echo htmlspecialchars($websiteDomain); ?>">📊</a>
                                        <?php elseif ($hasPartialAnalytics): ?>
                                            <a href="website-edit.php?id=<?php echo $website['id']; ?>" class="tool-icon analytics-partial" title="Add Property URL in Settings">📊</a>
                                        <?php else: ?>
                                            <span class="tool-icon disabled" title="Analytics not configured">📊</span>
                                        <?php endif; ?>
                                        <a href="<?php echo $searchConsoleUrl; ?>" target="_blank" class="tool-icon search-console" title="Search Console: <?php echo htmlspecialchars($websiteDomain); ?>">🔍</a>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($website['logo']) && preg_match('/\.(png|jpg|jpeg|webp|svg|avif)$/i', $website['logo'])): ?>
                                        <img src="/shared/logos/<?php echo htmlspecialchars($website['logo']); ?>" alt="Logo" style="width: 32px; height: 32px; object-fit: contain; vertical-align: middle; margin-right: 8px; border-radius: 4px;">
                                    <?php endif; ?>
                                    <a href="https://<?php echo htmlspecialchars($websiteDomain); ?>" target="_blank" style="color: #333; text-decoration: none; font-weight: 600;">
                                        <?php echo htmlspecialchars($websiteDomain); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($website['site_name']); ?></td>
                                <td>
                                    <span class="badge <?php echo $website['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
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

<style>
.tabs-header { display: flex; gap: 5px; }
.tab-btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #f8f9fa; border: 2px solid #e9ecef; border-radius: 8px 8px 0 0; text-decoration: none; color: #666; font-weight: 600; font-size: 14px; transition: all 0.2s; margin-bottom: -2px; }
.tab-btn:hover { background: #e9ecef; color: #333; }
.tab-btn.active { background: white; border-color: #667eea; border-bottom-color: white; color: #667eea; }
.tab-count { display: inline-flex; align-items: center; justify-content: center; min-width: 24px; height: 24px; padding: 0 8px; background: #e9ecef; border-radius: 12px; font-size: 12px; font-weight: 700; }
.tab-btn.active .tab-count { background: #667eea; color: white; }
.section-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px; padding-bottom: 0; border-bottom: 2px solid #667eea; }
.section-header .btn-primary { margin-bottom: 10px; }
.tools-icons { display: flex; gap: 6px; }
.tool-icon { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 6px; text-decoration: none; font-size: 16px; transition: all 0.2s; }
.tool-icon.analytics { background: linear-gradient(135deg, #fff3e0, #ffe0b2); }
.tool-icon.analytics:hover { background: linear-gradient(135deg, #ffe0b2, #ffcc80); transform: translateY(-2px); box-shadow: 0 4px 8px rgba(249, 171, 0, 0.3); }
.tool-icon.analytics-partial { background: linear-gradient(135deg, #fff8e1, #ffecb3); border: 2px dashed #ffc107; }
.tool-icon.analytics-partial:hover { background: #fff3e0; }
.tool-icon.search-console { background: linear-gradient(135deg, #e8f5e9, #c8e6c9); }
.tool-icon.search-console:hover { background: linear-gradient(135deg, #c8e6c9, #a5d6a7); transform: translateY(-2px); box-shadow: 0 4px 8px rgba(76, 175, 80, 0.3); }
.tool-icon.disabled { background: #f5f5f5; opacity: 0.4; cursor: not-allowed; }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>