<?php
/**
 * CMS Dashboard
 * 
 * REFACTORED Phase 3: Uses header.php, sidebar.php, footer.php components
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Load data
$configContent = file_get_contents(WEBSITES_CONFIG_FILE);
$configData = json_decode($configContent, true);
$allWebsites = $configData['websites'] ?? [];

// Filter websites for current user
$websites = filterWebsitesForUser($allWebsites);
$websiteCounts = getWebsiteCountsForUser($allWebsites);

// Get current user
$user = getCurrentUser();

// Check for flash messages
$successMessage = getFlashMessage('success');

// Page configuration for header
$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

// Include header (opens HTML, head, body, layout, sidebar, main)
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
                        <?php foreach ($websites as $website): 
                            $domain = $website['domain'];
                            $gaId = $website['google_analytics_id'] ?? '';
                            $owner = $website['owner'] ?? 'shared';
                            $analyticsUrl = !empty($website['analytics_url']) ? $website['analytics_url'] : 'https://analytics.google.com/analytics/web/';
                            $searchConsoleUrl = 'https://search.google.com/search-console?resource_id=sc-domain%3A' . urlencode($domain);
                        ?>
                            <tr>
                                <td>
                                    <div style="display: flex; gap: 4px;">
                                        <?php if ($gaId): ?>
                                            <a href="<?php echo htmlspecialchars($analyticsUrl); ?>" target="_blank" class="external-link-icon analytics" title="Google Analytics">📊</a>
                                        <?php endif; ?>
                                        <a href="<?php echo $searchConsoleUrl; ?>" target="_blank" class="external-link-icon search-console" title="Search Console">🔍</a>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($website['logo']) && preg_match('/\.(png|jpg|jpeg|webp|svg|avif)$/i', $website['logo'])): ?>
                                        <img src="/shared/logos/<?php echo htmlspecialchars($website['logo']); ?>" alt="Logo" style="width: 32px; height: 32px; object-fit: contain; vertical-align: middle; margin-right: 8px; border-radius: 4px;">
                                    <?php endif; ?>
                                    <a href="https://<?php echo htmlspecialchars($domain); ?>" target="_blank" style="color: #333; text-decoration: none; font-weight: 600;">
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
    .owner-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
    .owner-badge.own { background: #e3f2fd; color: #1565c0; }
    .owner-badge.shared { background: #f3e5f5; color: #7b1fa2; }
    .external-link-icon { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; text-decoration: none; transition: all 0.2s; }
    .external-link-icon:hover { transform: translateY(-2px); }
    .external-link-icon.analytics { background: #fff3e0; }
    .external-link-icon.search-console { background: #e8f5e9; }
</style>

<?php
// Include footer (closes main, layout, body, html)
include __DIR__ . '/includes/footer.php';
?>