<?php
/**
 * Icons Management
 * 
 * UPDATED: 
 * - Renamed from "Sport Icons" to "Icons"
 * - Added Home icon upload section
 * - Home icon stored in /shared/icons/home.webp
 * - Sport icons stored in /shared/icons/sports/
 * 
 * REFACTORED Phase 3: Uses bootstrap.php, header.php, footer.php components
 * ALL FEATURES PRESERVED
 */

require_once __DIR__ . '/includes/bootstrap.php';

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

// Get home icon info
$homeIconInfo = getHomeIcon();

// ==========================================
// HANDLE FORM SUBMISSIONS
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Upload HOME icon
    if (isset($_POST['upload_home_icon'])) {
        if (isset($_FILES['home_icon_file']) && $_FILES['home_icon_file']['size'] > 0) {
            $result = handleHomeIconUpload($_FILES['home_icon_file']);
            
            if (isset($result['success'])) {
                $success = "✅ Home icon uploaded successfully!";
            } else {
                $error = "❌ " . $result['error'];
            }
        } else {
            $error = "❌ Please select a file to upload";
        }
    }
    
    // Delete HOME icon
    if (isset($_POST['delete_home_icon'])) {
        $homeIconInfo = getHomeIcon();
        
        if ($homeIconInfo['exists'] && file_exists($homeIconInfo['path'])) {
            unlink($homeIconInfo['path']);
            $success = "✅ Home icon deleted";
            $homeIconInfo = getHomeIcon(); // Refresh
        } else {
            $error = "❌ No home icon found";
        }
    }
    
    // Upload SPORT icon
    if (isset($_POST['upload_icon'])) {
        $sportName = $_POST['sport_name'] ?? '';
        
        if ($sportName && isset($_FILES['icon_file']) && $_FILES['icon_file']['size'] > 0) {
            $result = handleIconUpload($_FILES['icon_file'], $sportName, SPORT_ICONS_DIR);
            
            if (isset($result['success'])) {
                $success = "✅ Icon uploaded for '{$sportName}'";
            } else {
                $error = "❌ " . $result['error'];
            }
        } else {
            $error = "❌ Please select a file to upload";
        }
    }
    
    // Delete SPORT icon
    if (isset($_POST['delete_icon'])) {
        $sportName = $_POST['sport_name'] ?? '';
        
        if ($sportName) {
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

// Refresh home icon info after potential changes
$homeIconInfo = getHomeIcon();

// Count sport icons
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

// ==========================================
// PAGE CONFIGURATION FOR HEADER
// ==========================================
$pageTitle = 'Icons - CMS';
$currentPage = 'icons';
$extraCss = ['css/icons.css'];

include __DIR__ . '/includes/header.php';
?>

<header class="cms-header">
    <h1>🖼️ Icons</h1>
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
        <p><strong>Recommended size:</strong> 64x64 pixels</p>
    </div>
    
    <!-- ==========================================
         HOME PAGE ICON SECTION
         ========================================== -->
    <div class="icons-section home-icon-section">
        <h2>🏠 Home Page Icon</h2>
        <p class="section-description">This icon appears on the Home Page in all websites.</p>
        
        <div class="home-icon-card">
            <div class="home-icon-preview">
                <?php if ($homeIconInfo['exists']): ?>
                    <img src="<?php echo HOME_ICON_URL_PATH . htmlspecialchars($homeIconInfo['filename']); ?>?v=<?php echo time(); ?>" 
                         alt="Home Icon"
                         width="64"
                         height="64">
                <?php else: ?>
                    <span class="placeholder-icon">?</span>
                <?php endif; ?>
            </div>
            
            <div class="home-icon-info">
                <h4>Home</h4>
                <?php if ($homeIconInfo['exists']): ?>
                    <span class="icon-format"><?php echo strtoupper($homeIconInfo['extension']); ?></span>
                    <span class="icon-path">/shared/icons/<?php echo htmlspecialchars($homeIconInfo['filename']); ?></span>
                <?php else: ?>
                    <span class="icon-missing">No icon uploaded</span>
                <?php endif; ?>
            </div>
            
            <div class="home-icon-actions">
                <form method="POST" enctype="multipart/form-data" class="upload-form">
                    <input type="file" 
                           name="home_icon_file" 
                           id="home_icon_file" 
                           class="file-input" 
                           accept=".webp,.svg,.avif"
                           onchange="this.form.submit()">
                    <input type="hidden" name="upload_home_icon" value="1">
                    <label for="home_icon_file" class="btn btn-upload">
                        <?php echo $homeIconInfo['exists'] ? '🔄 Replace Icon' : '📤 Upload Icon'; ?>
                    </label>
                </form>
                
                <?php if ($homeIconInfo['exists']): ?>
                <form method="POST" class="delete-form" onsubmit="return confirm('Delete home icon?');">
                    <input type="hidden" name="delete_home_icon" value="1">
                    <button type="submit" class="btn btn-delete">🗑️ Delete</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- ==========================================
         SPORT ICONS SECTION
         ========================================== -->
    <div class="icons-section">
        <h2>🏅 Sport Icons</h2>
        
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
        <div class="icons-grid">
            <?php foreach ($sports as $sport): 
                $iconInfo = getIconPath($sport, SPORT_ICONS_DIR);
                $hasIcon = $iconInfo['exists'];
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
                        <input type="hidden" name="delete_icon" value="1">
                        <button type="submit" class="btn btn-sm btn-delete">🗑️</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>