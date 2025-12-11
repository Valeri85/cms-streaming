<?php
/**
 * Delete Website
 * 
 * REFACTORED Phase 3: Uses bootstrap.php, header.php, footer.php components
 * ALL FEATURES PRESERVED
 */

require_once __DIR__ . '/includes/bootstrap.php';

$websiteId = $_GET['id'] ?? null;

if (!$websiteId) {
    header('Location: dashboard.php');
    exit;
}

// Use constant from config.php
if (!file_exists(WEBSITES_CONFIG_FILE)) {
    die("Configuration file not found at: " . WEBSITES_CONFIG_FILE);
}

$configContent = file_get_contents(WEBSITES_CONFIG_FILE);
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
    
    if (file_put_contents(WEBSITES_CONFIG_FILE, $jsonContent)) {
        $_SESSION['delete_success'] = 'Website deleted successfully!';
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Failed to delete. Check file permissions: chmod 644 ' . WEBSITES_CONFIG_FILE;
    }
}

// ==========================================
// PAGE CONFIGURATION FOR HEADER
// ==========================================
$pageTitle = 'Delete Website - CMS';
$currentPage = 'dashboard';
$extraCss = [];

include __DIR__ . '/includes/header.php';
?>

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

<?php include __DIR__ . '/includes/footer.php'; ?>