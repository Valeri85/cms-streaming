<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$websiteId = $_GET['id'] ?? null;

if (!$websiteId) {
    header('Location: dashboard.php');
    exit;
}

// Using absolute path
$configFile = '/var/www/u1852176/data/www/streaming/config/websites.json';

if (!file_exists($configFile)) {
    die("Configuration file not found at: " . $configFile);
}

$configContent = file_get_contents($configFile);
$configData = json_decode($configContent, true);
$websites = $configData['websites'] ?? [];

// Find website to delete
$websiteToDelete = null;
foreach ($websites as $website) {
    if ($website['id'] == $websiteId) {
        $websiteToDelete = $website;
        break;
    }
}

if (!$websiteToDelete) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Handle deletion confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    // Remove website from array
    $newWebsites = [];
    foreach ($websites as $website) {
        if ($website['id'] != $websiteId) {
            $newWebsites[] = $website;
        }
    }
    
    $configData['websites'] = $newWebsites;
    
    // Save to JSON with pretty print
    $jsonContent = json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if (file_put_contents($configFile, $jsonContent)) {
        $_SESSION['delete_success'] = 'Website deleted successfully!';
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Failed to delete. Check file permissions: chmod 644 ' . $configFile;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Website - CMS</title>
    <link rel="stylesheet" href="cms-style.css">
</head>
<body>
    <div class="cms-layout">
        <aside class="cms-sidebar">
            <div class="cms-logo">
                <h2>🎯 CMS</h2>
            </div>
            
            <nav class="cms-nav">
                <a href="dashboard.php" class="nav-item">
                    <span>🏠</span> Dashboard
                </a>
                <a href="website-add.php" class="nav-item">
                    <span>➕</span> Add Website
                </a>
                <a href="languages.php" class="nav-item">
                    <span>🌐</span> Languages
                </a>
            </nav>
            
            <div class="cms-user">
                <a href="logout.php" class="btn btn-sm btn-outline">Logout</a>
            </div>
        </aside>
        
        <main class="cms-main">
            <header class="cms-header">
                <h1>Delete Website</h1>
                <a href="dashboard.php" class="btn">← Back to Dashboard</a>
            </header>
            
            <div class="cms-content">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <div class="content-section" style="max-width: 600px;">
                    <div style="text-align: center; padding: 30px;">
                        <div style="font-size: 60px; margin-bottom: 20px;">⚠️</div>
                        <h2 style="color: #e74c3c; margin-bottom: 20px;">Delete Website?</h2>
                        
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                            <p style="font-size: 18px; margin-bottom: 10px;">
                                <span style="font-size: 30px;"><?php echo htmlspecialchars($websiteToDelete['logo']); ?></span>
                                <strong><?php echo htmlspecialchars($websiteToDelete['site_name']); ?></strong>
                            </p>
                            <p style="color: #666;">
                                <?php echo htmlspecialchars($websiteToDelete['domain']); ?>
                            </p>
                        </div>
                        
                        <p style="color: #666; margin: 20px 0;">
                            Are you sure you want to delete this website?<br>
                            <strong>This action cannot be undone!</strong>
                        </p>
                        
                        <form method="POST" style="margin-top: 30px;">
                            <input type="hidden" name="confirm_delete" value="1">
                            <button type="submit" class="btn btn-danger" style="margin-right: 10px;">
                                Yes, Delete Website
                            </button>
                            <a href="dashboard.php" class="btn btn-outline">
                                Cancel
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>