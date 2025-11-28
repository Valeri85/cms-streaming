<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// Paths
$masterSportsFile = '/var/www/u1852176/data/www/streaming/config/master-sports.json';
$iconsDir = '/var/www/u1852176/data/www/streaming/shared/icons/sports/';

// Ensure icons directory exists
if (!file_exists($iconsDir)) {
    mkdir($iconsDir, 0755, true);
}

// Load master sports list
$sports = [];
if (file_exists($masterSportsFile)) {
    $content = file_get_contents($masterSportsFile);
    $data = json_decode($content, true);
    $sports = $data['sports'] ?? [];
}

// ==========================================
// HELPER FUNCTIONS
// ==========================================

function sanitizeSportName($sportName) {
    $filename = strtolower($sportName);
    $filename = str_replace(' ', '-', $filename);
    $filename = preg_replace('/[^a-z0-9\-]/', '', $filename);
    $filename = preg_replace('/-+/', '-', $filename);
    $filename = trim($filename, '-');
    return $filename;
}

function getIconPath($sportName, $iconsDir) {
    $sanitized = sanitizeSportName($sportName);
    $extensions = ['webp', 'svg', 'avif'];
    
    foreach ($extensions as $ext) {
        $path = $iconsDir . $sanitized . '.' . $ext;
        if (file_exists($path)) {
            return [
                'exists' => true,
                'filename' => $sanitized . '.' . $ext,
                'extension' => $ext,
                'path' => $path
            ];
        }
    }
    
    return [
        'exists' => false,
        'filename' => null,
        'extension' => null,
        'path' => null
    ];
}

function handleIconUpload($file, $iconsDir, $sportName) {
    $allowedTypes = ['image/webp', 'image/svg+xml', 'image/avif'];
    $allowedExtensions = ['webp', 'svg', 'avif'];
    
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'No file uploaded'];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowedExtensions)) {
        return ['error' => 'Invalid file extension. Only WEBP, SVG, AVIF allowed'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    // Handle AVIF detection issue
    if ($extension === 'avif') {
        $mimeType = 'image/avif';
    }
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['error' => 'Invalid file type. Only WEBP, SVG, AVIF allowed'];
    }
    
    $sanitizedName = sanitizeSportName($sportName);
    $filename = $sanitizedName . '.' . $extension;
    $filepath = $iconsDir . $filename;
    
    // Delete existing icon with any extension
    foreach (['webp', 'svg', 'avif'] as $ext) {
        $existingFile = $iconsDir . $sanitizedName . '.' . $ext;
        if (file_exists($existingFile)) {
            unlink($existingFile);
        }
    }
    
    // Process and resize image (for webp and avif)
    if (in_array($extension, ['webp', 'avif'])) {
        if (!extension_loaded('gd')) {
            return ['error' => 'GD extension not available'];
        }
        
        switch ($extension) {
            case 'webp':
                $sourceImage = @imagecreatefromwebp($file['tmp_name']);
                break;
            case 'avif':
                if (function_exists('imagecreatefromavif')) {
                    $sourceImage = @imagecreatefromavif($file['tmp_name']);
                } else {
                    return ['error' => 'AVIF format not supported on this server'];
                }
                break;
        }
        
        if (!$sourceImage) {
            return ['error' => 'Failed to process image'];
        }
        
        // Resize to 64x64
        $targetImage = imagecreatetruecolor(64, 64);
        
        // Preserve transparency
        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);
        $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
        imagefill($targetImage, 0, 0, $transparent);
        
        imagecopyresampled(
            $targetImage, $sourceImage,
            0, 0, 0, 0,
            64, 64,
            imagesx($sourceImage), imagesy($sourceImage)
        );
        
        switch ($extension) {
            case 'webp':
                imagewebp($targetImage, $filepath, 90);
                break;
            case 'avif':
                if (function_exists('imageavif')) {
                    imageavif($targetImage, $filepath, 90);
                }
                break;
        }
        
        imagedestroy($sourceImage);
        imagedestroy($targetImage);
    } else {
        // For SVG, just move the file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['error' => 'Failed to save file'];
        }
    }
    
    return ['success' => true, 'filename' => $filename];
}

// ==========================================
// HANDLE FORM SUBMISSIONS
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Upload icon
    if (isset($_POST['upload_icon'])) {
        $sportName = $_POST['sport_name'] ?? '';
        
        if ($sportName && isset($_FILES['icon_file']) && $_FILES['icon_file']['size'] > 0) {
            $result = handleIconUpload($_FILES['icon_file'], $iconsDir, $sportName);
            
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
            $iconInfo = getIconPath($sportName, $iconsDir);
            
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
    $iconInfo = getIconPath($sport, $iconsDir);
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
                            $iconInfo = getIconPath($sport, $iconsDir);
                            $hasIcon = $iconInfo['exists'];
                            $iconUrl = $hasIcon ? '/shared/icons/sports/' . $iconInfo['filename'] : null;
                            $sanitizedName = sanitizeSportName($sport);
                        ?>
                        <div class="icon-card <?php echo $hasIcon ? 'has-icon' : 'no-icon'; ?>">
                            <div class="icon-preview">
                                <?php if ($hasIcon): ?>
                                    <img src="https://www.sportlemons.info/shared/icons/sports/<?php echo htmlspecialchars($iconInfo['filename']); ?>?v=<?php echo time(); ?>" 
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