<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Load websites from JSON - using absolute path
$configFile = '/var/www/u1852176/data/www/streaming/config/websites.json';

if (!file_exists($configFile)) {
    die("Configuration file not found at: " . $configFile);
}

$configContent = file_get_contents($configFile);
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

// Function to render logo (image or emoji)
function renderLogoPreview($logo, $domain) {
    // Check if logo contains file extension (is a file)
    if (preg_match('/\.(png|jpg|jpeg|webp|svg|avif)$/i', $logo)) {
        // It's an image file
        $logoFile = htmlspecialchars($logo);
        $logoUrl = 'https://' . htmlspecialchars($domain) . '/images/logos/' . $logoFile;
        return '<img src="' . $logoUrl . '?v=' . time() . '" alt="Logo" class="logo-preview-img" style="width: 32px; height: 32px; object-fit: contain; vertical-align: middle; margin-right: 8px; border-radius: 4px;" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'inline\';">';
    } else {
        // It's an emoji or text
        return '<span class="site-logo">' . htmlspecialchars($logo) . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS Dashboard</title>
    <link rel="stylesheet" href="cms-style.css">
    <style>
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .logo-with-name {
            display: flex;
            align-items: center;
            gap: 8px;
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
                                        <th>Domain</th>
                                        <th>Site Name</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($websites as $website): ?>
                                        <tr>
                                            <td>
                                                <a href="https://<?php echo htmlspecialchars($website['domain']); ?>" target="_blank" class="domain-link">
                                                    <?php echo htmlspecialchars($website['domain']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <div class="logo-with-name">
                                                    <?php echo renderLogoPreview($website['logo'], $website['domain']); ?>
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
                                                    <a href="website-edit.php?id=<?php echo $website['id']; ?>" class="btn btn-sm">Edit</a>
                                                    <a href="website-sports.php?id=<?php echo $website['id']; ?>" class="btn btn-sm" style="background: #27ae60; color: white;">Sports</a>
                                                    <a href="website-seo.php?id=<?php echo $website['id']; ?>" class="btn btn-sm" style="background: #3498db; color: white;">SEO</a>
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