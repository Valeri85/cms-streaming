<?php
/**
 * Sport Icons Management
 * 
 * REFACTORED: Uses centralized config and functions
 */

session_start();

// ==========================================
// LOAD CENTRALIZED CONFIG AND FUNCTIONS
// ==========================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// Directories are auto-created by config.php via ensureDirectoryExists()

// Load master sports list
$sports = [];
if (file_exists(MASTER_SPORTS_FILE)) {
    $content = file_get_contents(MASTER_SPORTS_FILE);
    $data = json_decode($content, true);
    $sports = $data['sports'] ?? [];
}

// ==========================================
// HANDLE FORM SUBMISSIONS
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Upload icon
    if (isset($_POST['upload_icon'])) {
        $sportName = $_POST['sport_name'] ?? '';
        
        if ($sportName && isset($_FILES['icon_file']) && $_FILES['icon_file']['size'] > 0) {
            // Use function from functions.php with constant from config.php
            $result = handleIconUpload($_FILES['icon_file'], SPORT_ICONS_DIR, $sportName);
            
            if (isset($result['success'])) {
                $success = "✅ Icon uploaded for '{$sportName}'";
            } else {
                $error = "❌ " . $result['error'];
            }
        } else {
            $error = "❌ Please select a file to upload";
        }
    }
    
    // Delete icon
    if (isset($_POST['delete_icon'])) {
        $sportName = $_POST['sport_name'] ?? '';
        
        if ($sportName) {
            // Use function from functions.php with constant from config.php
            $iconInfo = getIconPath($sportName, SPORT_ICONS_DIR);
            
            if ($iconInfo['exists'] && file_exists($iconInfo['path'])) {
                unlink($iconInfo['path']);
                $success = "✅ Icon deleted for '{$sportName}'";
            } else {
                $error = "❌ No icon found for '{$sportName}'";
            }
        }
    }
}

// Count icons
$totalSports = count($sports);
$iconsUploaded = 0;
$iconsMissing = 0;

foreach ($sports as $sport) {
    $iconInfo = getIconPath($sport, SPORT_ICONS_DIR);
    if ($iconInfo['exists']) {
        $iconsUploaded++;
    } else {
        $iconsMissing++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sport Icons - CMS</title>
    <link rel="stylesheet" href="cms-style.css">
    <link rel="stylesheet" href="css/icons.css">
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
                <a href="icons.php" class="nav-item active">
                    <span>🖼️</span> Sport Icons
                </a>
            </nav>
            
            <div class="cms-user">
                <a href="logout.php" class="btn btn-sm btn-outline">Logout</a>
            </div>
        </aside>
        
        <main class="cms-main">
            <header class="cms-header">
                <h1>🖼️ Sport Icons</h1>
            </header>
            
            <div class="cms-content">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <!-- Info Box -->
                <div class="info-box">
                    <h3>ℹ️ Master Icons</h3>
                    <p>These icons are shared across <strong>all websites</strong>. When you update an icon here, it updates everywhere automatically.</p>
                    <p><strong>Supported formats:</strong> WebP, SVG, AVIF</p>
                    <p><strong>Recommended size:</strong> 64x64 pixels (auto-resized on upload)</p>
                </div>
                
                <!-- Stats Overview -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">🏅</div>
                        <div class="stat-info">
                            <h3><?php echo $totalSports; ?></h3>
                            <p>Total Sports</p>
                        </div>
                    </div>
                    
                    <div class="stat-card stat-success">
                        <div class="stat-icon">✅</div>
                        <div class="stat-info">
                            <h3><?php echo $iconsUploaded; ?></h3>
                            <p>Icons Uploaded</p>
                        </div>
                    </div>
                    
                    <div class="stat-card <?php echo $iconsMissing > 0 ? 'stat-warning' : ''; ?>">
                        <div class="stat-icon">⚠️</div>
                        <div class="stat-info">
                            <h3><?php echo $iconsMissing; ?></h3>
                            <p>Missing Icons</p>
                        </div>
                    </div>
                </div>
                
                <!-- Icons Grid -->
                <div class="icons-section">
                    <h2>All Sport Icons</h2>
                    
                    <div class="icons-grid">
                        <?php foreach ($sports as $sport): 
                            // Use functions from functions.php
                            $iconInfo = getIconPath($sport, SPORT_ICONS_DIR);
                            $hasIcon = $iconInfo['exists'];
                            // Use constant from config.php for URL path
                            $iconUrl = $hasIcon ? (SPORT_ICONS_URL_PATH . $iconInfo['filename']) : null;
                            $sanitizedName = sanitizeSportName($sport);
                        ?>
                        <div class="icon-card <?php echo $hasIcon ? 'has-icon' : 'no-icon'; ?>">
                            <div class="icon-preview">
                                <?php if ($hasIcon): ?>
                                    <img src="<?php echo $iconUrl; ?>?v=<?php echo time(); ?>" 
                                         alt="<?php echo htmlspecialchars($sport); ?>">
                                <?php else: ?>
                                    <span class="placeholder-icon">?</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="icon-info">
                                <h4><?php echo htmlspecialchars($sport); ?></h4>
                                <?php if ($hasIcon): ?>
                                    <span class="icon-format"><?php echo strtoupper($iconInfo['extension']); ?></span>
                                <?php else: ?>
                                    <span class="icon-missing">No icon</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="icon-actions">
                                <form method="POST" enctype="multipart/form-data" class="upload-form">
                                    <input type="hidden" name="sport_name" value="<?php echo htmlspecialchars($sport); ?>">
                                    <input type="file" 
                                            name="icon_file" 
                                            id="file_<?php echo $sanitizedName; ?>" 
                                            class="file-input" 
                                            accept=".webp,.svg,.avif"
                                            onchange="this.form.submit()">
                                    <input type="hidden" name="upload_icon" value="1">
                                    <label for="file_<?php echo $sanitizedName; ?>" class="btn btn-sm btn-upload">
                                        <?php echo $hasIcon ? '🔄 Replace' : '📤 Upload'; ?>
                                    </label>
                                </form>
                                
                                <?php if ($hasIcon): ?>
                                <form method="POST" class="delete-form" onsubmit="return confirm('Delete icon for <?php echo htmlspecialchars($sport); ?>?');">
                                    <input type="hidden" name="sport_name" value="<?php echo htmlspecialchars($sport); ?>">
                                    <button type="submit" name="delete_icon" class="btn btn-sm btn-delete">🗑️</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>