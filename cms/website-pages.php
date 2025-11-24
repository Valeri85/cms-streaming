<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$websiteId = $_GET['id'] ?? null;
$error = '';
$success = '';
$debugInfo = '';

if (!$websiteId) {
    header('Location: dashboard.php');
    exit;
}

$configFile = '/var/www/u1852176/data/www/streaming/config/websites.json';
$uploadDir = '/var/www/u1852176/data/www/streaming/images/sports/';

if (!file_exists($configFile)) {
    die("Configuration file not found at: " . $configFile);
}

if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        die("Failed to create upload directory: " . $uploadDir);
    }
}

if (!is_writable($uploadDir)) {
    die("Upload directory is not writable: " . $uploadDir . " - Please run: chmod 755 " . $uploadDir);
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

function handleImageUpload($file, $uploadDir, $sportName, &$debugInfo) {
    $debugInfo .= "=== UPLOAD DEBUG START ===\n";
    $debugInfo .= "Sport Name: $sportName\n";
    
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $debugInfo .= "ERROR: No file uploaded\n";
        return ['error' => 'No file uploaded'];
    }
    
    $allowedTypes = ['image/webp', 'image/svg+xml', 'image/avif'];
    $allowedExtensions = ['webp', 'svg', 'avif'];
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $debugInfo .= "File extension: $extension\n";
    
    if (!in_array($extension, $allowedExtensions)) {
        return ['error' => 'Invalid file extension. Only WEBP, SVG, AVIF allowed'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if ($extension === 'avif') {
        $mimeType = 'image/avif';
        $allowedTypes[] = 'image/avif';
    }
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['error' => 'Invalid file type. Only WEBP, SVG, AVIF allowed'];
    }
    
    $sanitizedName = sanitizeSportName($sportName);
    $filename = $sanitizedName . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    $debugInfo .= "Target filename: $filename\n";
    
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    
    if (in_array($extension, ['webp', 'avif'])) {
        if (!extension_loaded('gd')) {
            return ['error' => 'GD extension not available'];
        }
        
        $sourceImage = null;
        switch ($extension) {
            case 'webp':
                $sourceImage = @imagecreatefromwebp($file['tmp_name']);
                break;
            case 'avif':
                if (function_exists('imagecreatefromavif')) {
                    $sourceImage = @imagecreatefromavif($file['tmp_name']);
                } else {
                    return ['error' => 'AVIF format not supported'];
                }
                break;
        }
        
        if (!$sourceImage) {
            return ['error' => 'Failed to process image'];
        }
        
        $targetImage = imagecreatetruecolor(64, 64);
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
        
        $saveResult = false;
        switch ($extension) {
            case 'webp':
                $saveResult = imagewebp($targetImage, $filepath, 90);
                break;
            case 'avif':
                if (function_exists('imageavif')) {
                    $saveResult = imageavif($targetImage, $filepath, 90);
                }
                break;
        }
        
        imagedestroy($sourceImage);
        imagedestroy($targetImage);
        
        if (!$saveResult) {
            return ['error' => 'Failed to save processed image'];
        }
    } else {
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['error' => 'Failed to save file'];
        }
    }
    
    if (file_exists($filepath)) {
        chmod($filepath, 0644);
    }
    
    $debugInfo .= "=== UPLOAD DEBUG END ===\n";
    return ['success' => true, 'filename' => $filename];
}

function sendSlackNotification($sportName) {
    $slackConfigFile = '/var/www/u1852176/data/www/streaming/config/slack-config.json';
    if (!file_exists($slackConfigFile)) {
        return false;
    }
    
    $slackConfig = json_decode(file_get_contents($slackConfigFile), true);
    $slackWebhookUrl = $slackConfig['webhook_url'] ?? '';
    
    if (empty($slackWebhookUrl)) {
        return false;
    }
    
    $message = [
        'text' => "🚨 *New Sport Category Added*",
        'blocks' => [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*New Sport Category:* " . $sportName . "\n\n⚠️ Please add SEO for new sport page in CMS."
                ]
            ]
        ]
    ];
    
    $ch = curl_init($slackWebhookUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

// NEW: Calculate status indicator for Home page
function getHomeStatusIndicator($pagesSeo, $homeIcon) {
    $seoData = $pagesSeo['home'] ?? [];
    $hasTitle = !empty(trim($seoData['title'] ?? ''));
    $hasDescription = !empty(trim($seoData['description'] ?? ''));
    $hasIcon = !empty($homeIcon);
    
    if ($hasTitle && $hasDescription && $hasIcon) {
        return '🟢'; // Perfect
    } elseif (!$hasTitle && !$hasDescription && !$hasIcon) {
        return '🔴'; // Everything empty
    } else {
        return '🟠'; // Partial
    }
}

// Calculate status indicator based on SEO + Icon
function getStatusIndicator($sportName, $pagesSeo, $sportsIcons) {
    $sportSlug = strtolower(str_replace(' ', '-', $sportName));
    
    // Check SEO
    $seoData = $pagesSeo['sports'][$sportSlug] ?? [];
    $hasTitle = !empty(trim($seoData['title'] ?? ''));
    $hasDescription = !empty(trim($seoData['description'] ?? ''));
    
    // Check Icon
    $hasIcon = isset($sportsIcons[$sportName]) && !empty($sportsIcons[$sportName]);
    
    // Status Logic:
    // 🟢 Green = All 3 present (title + description + icon)
    // 🟠 Orange = At least 1 present, but not all 3
    // 🔴 Red = All empty
    
    if ($hasTitle && $hasDescription && $hasIcon) {
        return '🟢'; // Perfect
    } elseif (!$hasTitle && !$hasDescription && !$hasIcon) {
        return '🔴'; // Everything empty
    } else {
        return '🟠'; // Partial
    }
}

// ==========================================
// LOAD WEBSITE DATA
// ==========================================

$configContent = file_get_contents($configFile);
$configData = json_decode($configContent, true);
$websites = $configData['websites'] ?? [];

$website = null;
foreach ($websites as $site) {
    if ($site['id'] == $websiteId) {
        $website = $site;
        break;
    }
}

if (!$website) {
    header('Location: dashboard.php');
    exit;
}

$previewDomain = $website['domain'];

// ==========================================
// HANDLE FORM SUBMISSIONS
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $websiteIndex = null;
    foreach ($websites as $key => $site) {
        if ($site['id'] == $websiteId) {
            $websiteIndex = $key;
            break;
        }
    }
    
    if ($websiteIndex !== null) {
        if (!isset($websites[$websiteIndex]['sports_categories'])) {
            $websites[$websiteIndex]['sports_categories'] = [];
        }
        
        if (!isset($websites[$websiteIndex]['sports_icons'])) {
            $websites[$websiteIndex]['sports_icons'] = [];
        }
        
        if (!isset($websites[$websiteIndex]['pages_seo'])) {
            $websites[$websiteIndex]['pages_seo'] = [];
        }
        
        if (!isset($websites[$websiteIndex]['pages_seo']['sports'])) {
            $websites[$websiteIndex]['pages_seo']['sports'] = [];
        }
        
        if (!isset($websites[$websiteIndex]['pages_seo']['home'])) {
            $websites[$websiteIndex]['pages_seo']['home'] = [
                'title' => '',
                'description' => ''
            ];
        }
        
        if (!isset($websites[$websiteIndex]['home_icon'])) {
            $websites[$websiteIndex]['home_icon'] = '';
        }
        
        // UPDATE HOME SEO
        if (isset($_POST['update_home_seo'])) {
            $websites[$websiteIndex]['pages_seo']['home'] = [
                'title' => trim($_POST['home_seo_title'] ?? ''),
                'description' => trim($_POST['home_seo_description'] ?? '')
            ];
            
            $success = "✅ Home page SEO updated";
        }
        
        // UPLOAD HOME ICON
        if (isset($_POST['upload_home_icon'])) {
            if (isset($_FILES['home_icon_file']) && $_FILES['home_icon_file']['size'] > 0) {
                $uploadResult = handleImageUpload($_FILES['home_icon_file'], $uploadDir, 'home', $debugInfo);
                if (isset($uploadResult['success'])) {
                    if (!empty($websites[$websiteIndex]['home_icon'])) {
                        $oldFile = $uploadDir . $websites[$websiteIndex]['home_icon'];
                        if (file_exists($oldFile) && $oldFile !== $uploadDir . $uploadResult['filename']) {
                            unlink($oldFile);
                        }
                    }
                    
                    $websites[$websiteIndex]['home_icon'] = $uploadResult['filename'];
                    $success = "✅ Home icon uploaded: {$uploadResult['filename']}";
                } else {
                    $error = "❌ Upload failed: " . $uploadResult['error'];
                }
            } else {
                $error = "❌ Please select an icon file to upload";
            }
        }
        
        // DELETE HOME ICON
        if (isset($_POST['delete_home_icon'])) {
            if (!empty($websites[$websiteIndex]['home_icon'])) {
                $iconFile = $uploadDir . $websites[$websiteIndex]['home_icon'];
                if (file_exists($iconFile)) {
                    unlink($iconFile);
                }
                $websites[$websiteIndex]['home_icon'] = '';
                $success = "✅ Home icon deleted";
            } else {
                $error = "❌ No home icon found";
            }
        }
        
        // ADD NEW SPORT
        if (isset($_POST['add_sport'])) {
            $newSport = trim($_POST['new_sport_name'] ?? '');
            
            if ($newSport) {
                if (!in_array($newSport, $websites[$websiteIndex]['sports_categories'])) {
                    $websites[$websiteIndex]['sports_categories'][] = $newSport;
                    
                    if (isset($_FILES['new_sport_icon']) && $_FILES['new_sport_icon']['size'] > 0) {
                        $uploadResult = handleImageUpload($_FILES['new_sport_icon'], $uploadDir, $newSport, $debugInfo);
                        if (isset($uploadResult['success'])) {
                            $websites[$websiteIndex]['sports_icons'][$newSport] = $uploadResult['filename'];
                            $success = "✅ Sport category '{$newSport}' added with icon: {$uploadResult['filename']}";
                        } else {
                            $error = $uploadResult['error'];
                        }
                    } else {
                        $success = "✅ Sport category '{$newSport}' added (no icon uploaded)";
                    }
                    
                    if ($success) {
                        sendSlackNotification($newSport);
                    }
                } else {
                    $error = "❌ Sport category '{$newSport}' already exists!";
                }
            } else {
                $error = "❌ Please enter a sport name";
            }
        }
        
        // UPDATE SEO
        if (isset($_POST['update_seo'])) {
            $sportName = $_POST['sport_name'] ?? '';
            $sportSlug = strtolower(str_replace(' ', '-', $sportName));
            
            $websites[$websiteIndex]['pages_seo']['sports'][$sportSlug] = [
                'title' => trim($_POST['seo_title'] ?? ''),
                'description' => trim($_POST['seo_description'] ?? '')
            ];
            
            $success = "✅ SEO updated for '{$sportName}'";
        }
        
        // EDIT ICON (UPLOAD NEW ICON)
        if (isset($_POST['edit_icon'])) {
            $sportName = $_POST['sport_name'] ?? '';
            
            if ($sportName && isset($_FILES['sport_icon_file']) && $_FILES['sport_icon_file']['size'] > 0) {
                $uploadResult = handleImageUpload($_FILES['sport_icon_file'], $uploadDir, $sportName, $debugInfo);
                if (isset($uploadResult['success'])) {
                    if (isset($websites[$websiteIndex]['sports_icons'][$sportName])) {
                        $oldFile = $uploadDir . $websites[$websiteIndex]['sports_icons'][$sportName];
                        if (file_exists($oldFile) && $oldFile !== $uploadDir . $uploadResult['filename']) {
                            unlink($oldFile);
                        }
                    }
                    
                    $websites[$websiteIndex]['sports_icons'][$sportName] = $uploadResult['filename'];
                    $success = "✅ Icon uploaded for '{$sportName}': {$uploadResult['filename']}";
                } else {
                    $error = "❌ Upload failed: " . $uploadResult['error'];
                }
            } else {
                $error = "❌ Please select an icon file to upload";
            }
        }
        
        // DELETE ICON
        if (isset($_POST['delete_icon'])) {
            $sportName = $_POST['sport_name'] ?? '';
            
            if ($sportName && isset($websites[$websiteIndex]['sports_icons'][$sportName])) {
                $iconFile = $uploadDir . $websites[$websiteIndex]['sports_icons'][$sportName];
                if (file_exists($iconFile)) {
                    unlink($iconFile);
                }
                unset($websites[$websiteIndex]['sports_icons'][$sportName]);
                $success = "✅ Icon deleted for '{$sportName}'";
            } else {
                $error = "❌ No icon found for '{$sportName}'";
            }
        }
        
        // RENAME SPORT
        if (isset($_POST['rename_sport'])) {
            $oldName = $_POST['old_sport_name'] ?? '';
            $newName = trim($_POST['new_sport_name'] ?? '');
            
            if ($oldName && $newName) {
                $sports = $websites[$websiteIndex]['sports_categories'];
                $index = array_search($oldName, $sports);
                
                if ($index !== false) {
                    if (!in_array($newName, $sports)) {
                        $sports[$index] = $newName;
                        $websites[$websiteIndex]['sports_categories'] = $sports;
                        
                        // Rename icon file if exists
                        if (isset($websites[$websiteIndex]['sports_icons'][$oldName])) {
                            $oldIconFile = $websites[$websiteIndex]['sports_icons'][$oldName];
                            $oldIconPath = $uploadDir . $oldIconFile;
                            
                            $extension = pathinfo($oldIconFile, PATHINFO_EXTENSION);
                            $newIconFilename = sanitizeSportName($newName) . '.' . $extension;
                            $newIconPath = $uploadDir . $newIconFilename;
                            
                            if (file_exists($oldIconPath)) {
                                rename($oldIconPath, $newIconPath);
                            }
                            
                            $websites[$websiteIndex]['sports_icons'][$newName] = $newIconFilename;
                            unset($websites[$websiteIndex]['sports_icons'][$oldName]);
                        }
                        
                        // Rename SEO key if exists
                        $oldSlug = strtolower(str_replace(' ', '-', $oldName));
                        $newSlug = strtolower(str_replace(' ', '-', $newName));
                        
                        if (isset($websites[$websiteIndex]['pages_seo']['sports'][$oldSlug])) {
                            $websites[$websiteIndex]['pages_seo']['sports'][$newSlug] = $websites[$websiteIndex]['pages_seo']['sports'][$oldSlug];
                            unset($websites[$websiteIndex]['pages_seo']['sports'][$oldSlug]);
                        }
                        
                        $success = "✅ Sport renamed: '{$oldName}' → '{$newName}'";
                    } else {
                        $error = "❌ Sport category '{$newName}' already exists!";
                    }
                } else {
                    $error = "❌ Sport category '{$oldName}' not found!";
                }
            } else {
                $error = "❌ Please enter a valid sport name";
            }
        }
        
        // DELETE SPORT
        if (isset($_POST['delete_sport'])) {
            $sportToDelete = $_POST['sport_name'] ?? '';
            
            // Delete icon file
            if (isset($websites[$websiteIndex]['sports_icons'][$sportToDelete])) {
                $iconFile = $uploadDir . $websites[$websiteIndex]['sports_icons'][$sportToDelete];
                if (file_exists($iconFile)) {
                    unlink($iconFile);
                }
                unset($websites[$websiteIndex]['sports_icons'][$sportToDelete]);
            }
            
            // Delete from categories
            $sports = $websites[$websiteIndex]['sports_categories'];
            $sports = array_filter($sports, function($sport) use ($sportToDelete) {
                return $sport !== $sportToDelete;
            });
            $websites[$websiteIndex]['sports_categories'] = array_values($sports);
            
            // Delete SEO data
            $sportSlug = strtolower(str_replace(' ', '-', $sportToDelete));
            if (isset($websites[$websiteIndex]['pages_seo']['sports'][$sportSlug])) {
                unset($websites[$websiteIndex]['pages_seo']['sports'][$sportSlug]);
            }
            
            $success = "✅ Sport category '{$sportToDelete}' deleted";
        }
        
        // REORDER SPORTS
        if (isset($_POST['reorder_sports'])) {
            $newOrder = json_decode($_POST['sports_order'] ?? '[]', true);
            
            if (is_array($newOrder) && count($newOrder) > 0) {
                $websites[$websiteIndex]['sports_categories'] = $newOrder;
                $success = "✅ Sports order updated successfully!";
            } else {
                $error = "❌ Invalid sports order data";
            }
        }
        
        // Save changes to config file
        if ($success || $error) {
            $configData['websites'] = $websites;
            $jsonContent = json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            if (!file_put_contents($configFile, $jsonContent)) {
                $error = '❌ Failed to save changes. Check permissions: chmod 644 ' . $configFile;
                $success = '';
            } else {
                // Reload website data
                $configContent = file_get_contents($configFile);
                $configData = json_decode($configContent, true);
                $websites = $configData['websites'] ?? [];
                foreach ($websites as $site) {
                    if ($site['id'] == $websiteId) {
                        $website = $site;
                        break;
                    }
                }
            }
        }
    }
}

$sports = $website['sports_categories'] ?? [];
$sportsIcons = $website['sports_icons'] ?? [];
$pagesSeo = $website['pages_seo'] ?? [];
$homeIcon = $website['home_icon'] ?? '';
$homeStatus = getHomeStatusIndicator($pagesSeo, $homeIcon);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pages - <?php echo htmlspecialchars($website['site_name']); ?></title>
    <link rel="stylesheet" href="cms-style.css">
    <link rel="stylesheet" href="css/website-pages.css">
</head>
<body data-preview-domain="<?php echo htmlspecialchars($previewDomain); ?>">
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
            </nav>
            
            <div class="cms-user">
                <a href="logout.php" class="btn btn-sm btn-outline">Logout</a>
            </div>
        </aside>
        
        <main class="cms-main">
            <header class="cms-header">
                <h1>Manage Pages: <?php echo htmlspecialchars($website['site_name']); ?></h1>
                <a href="dashboard.php" class="btn">← Back to Dashboard</a>
            </header>
            
            <div class="cms-content">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <!-- HOME PAGE SECTION (NEW) -->
                <div class="content-section home-page-section">
                    <div class="pages-accordion">
                        <details class="home-page-card" data-page-type="home">
                            <summary>
                                <span class="status-indicator"><?php echo $homeStatus; ?></span>
                                <span class="accordion-title">
                                    <span class="home-badge">HOME</span>
                                    Home Page
                                </span>
                            </summary>
                            
                            <div class="accordion-content">
                                <!-- SEO SECTION -->
                                <div class="seo-section">
                                    <h4>🔍 SEO Settings</h4>
                                    <form method="POST">
                                        <input type="hidden" name="update_home_seo" value="1">
                                        
                                        <div class="form-group">
                                            <label for="home_seo_title">SEO Title</label>
                                            <input type="text" id="home_seo_title" name="home_seo_title" value="<?php echo htmlspecialchars($pagesSeo['home']['title'] ?? ''); ?>" placeholder="<?php echo htmlspecialchars($website['site_name']); ?> - Live Sports Streaming">
                                            <small>Recommended: 50-60 characters</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="home_seo_description">SEO Description</label>
                                            <textarea id="home_seo_description" name="home_seo_description" rows="3" placeholder="Watch live sports streams..."><?php echo htmlspecialchars($pagesSeo['home']['description'] ?? ''); ?></textarea>
                                            <small>Recommended: 150-160 characters</small>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">Save SEO</button>
                                    </form>
                                </div>
                                
                                <!-- HOME ICON MANAGEMENT -->
                                <div class="sport-management-section">
                                    <h4>🏠 Home Icon Management</h4>
                                    
                                    <div class="sport-card">
                                        <div class="sport-card-header">
                                            <div class="sport-icon-display <?php echo !empty($homeIcon) ? '' : 'no-icon'; ?>">
                                                <?php if (!empty($homeIcon)): 
                                                    $iconUrl = 'https://www.' . htmlspecialchars($previewDomain) . '/images/sports/' . htmlspecialchars($homeIcon);
                                                ?>
                                                    <img src="<?php echo $iconUrl; ?>?v=<?php echo time(); ?>" 
                                                         alt="Home Icon" 
                                                         onerror="this.parentElement.classList.add('no-icon'); this.parentElement.innerHTML='🏠';">
                                                <?php else: ?>
                                                    🏠
                                                <?php endif; ?>
                                            </div>
                                            <div class="sport-card-info">
                                                <div class="sport-card-name">Home Page Icon</div>
                                                <div class="sport-card-meta">
                                                    <?php if (!empty($homeIcon)): ?>
                                                        <span>✅ Has icon</span>
                                                        <span>•</span>
                                                        <span><?php echo htmlspecialchars($homeIcon); ?></span>
                                                    <?php else: ?>
                                                        <span style="color: #e74c3c;">⚠️ No icon (using default 🏠)</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="sport-card-actions">
                                            <form method="POST" enctype="multipart/form-data" style="grid-column: 1 / -1;">
                                                <input type="hidden" name="upload_home_icon" value="1">
                                                <div class="form-group" style="margin-bottom: 15px;">
                                                    <label>Upload/Change Icon</label>
                                                    <div class="file-upload-wrapper">
                                                        <label for="home_icon_file" class="file-upload-label">
                                                            <span>📤</span>
                                                            <span><?php echo !empty($homeIcon) ? 'Change Icon' : 'Upload Icon'; ?></span>
                                                        </label>
                                                        <input type="file" id="home_icon_file" name="home_icon_file" class="file-upload-input" accept=".webp,.svg,.avif" required>
                                                        <div class="file-name-display" id="homeIconFileName">No file chosen</div>
                                                    </div>
                                                    <small>WEBP, SVG, AVIF • Auto-resize to 64x64px</small>
                                                </div>
                                                <button type="submit" class="btn btn-primary" style="width: 100%;">
                                                    <?php echo !empty($homeIcon) ? '🖼️ Update Icon' : '📤 Upload Icon'; ?>
                                                </button>
                                            </form>
                                            
                                            <?php if (!empty($homeIcon)): ?>
                                                <form method="POST" onsubmit="return confirm('Delete home icon?');" style="grid-column: 1 / -1; margin-top: 10px;">
                                                    <input type="hidden" name="delete_home_icon" value="1">
                                                    <button type="submit" class="btn-delete">
                                                        🗑️ Delete Icon
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </details>
                    </div>
                </div>
                
                <!-- ADD NEW SPORT SECTION -->
                <div class="add-sport-card">
                    <h3>➕ Add New Sport Category</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="add-sport-form">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="new_sport_name">Sport Name *</label>
                                <input type="text" id="new_sport_name" name="new_sport_name" placeholder="e.g., Rugby League" required>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Sport Icon</label>
                                <div class="file-upload-wrapper">
                                    <label for="new_sport_icon" class="file-upload-label">
                                        <span>📤</span>
                                        <span>Choose Image</span>
                                    </label>
                                    <input type="file" id="new_sport_icon" name="new_sport_icon" class="file-upload-input" accept=".webp,.svg,.avif">
                                    <div class="file-name-display" id="newSportFileName">No file chosen</div>
                                </div>
                            </div>
                            
                            <button type="submit" name="add_sport" class="btn btn-primary" style="height: fit-content;">Add Sport</button>
                        </div>
                    </form>
                </div>
                
                <!-- SPORTS LIST WITH ACCORDIONS -->
                <div class="content-section">
                    <div class="section-header">
                        <h2>Sport Pages (<?php echo count($sports); ?>)</h2>
                    </div>
                    
                    <div class="sports-count-info">
                        <span>💡 Drag accordions to reorder (affects left menu on website)</span>
                    </div>
                    
                    <div class="pages-accordion" id="pagesAccordions">
                        <?php foreach ($sports as $sport): 
                            $sportSlug = strtolower(str_replace(' ', '-', $sport));
                            $iconFile = $sportsIcons[$sport] ?? '';
                            $hasIcon = !empty($iconFile);
                            $iconUrl = 'https://www.' . htmlspecialchars($previewDomain) . '/images/sports/' . htmlspecialchars($iconFile);
                            
                            $seoData = $pagesSeo['sports'][$sportSlug] ?? [];
                            $seoTitle = $seoData['title'] ?? '';
                            $seoDescription = $seoData['description'] ?? '';
                            
                            $status = getStatusIndicator($sport, $pagesSeo, $sportsIcons);
                        ?>
                            <details data-sport-name="<?php echo htmlspecialchars($sport); ?>">
                                <summary>
                                    <span class="drag-handle" title="Drag to reorder">⋮⋮</span>
                                    <span class="status-indicator"><?php echo $status; ?></span>
                                    <span class="accordion-title"><?php echo htmlspecialchars($sport); ?></span>
                                </summary>
                                
                                <div class="accordion-content">
                                    <!-- SEO SECTION -->
                                    <div class="seo-section">
                                        <h4>🔍 SEO Settings</h4>
                                        <form method="POST">
                                            <input type="hidden" name="update_seo" value="1">
                                            <input type="hidden" name="sport_name" value="<?php echo htmlspecialchars($sport); ?>">
                                            
                                            <div class="form-group">
                                                <label for="seo_title_<?php echo $sportSlug; ?>">SEO Title</label>
                                                <input type="text" id="seo_title_<?php echo $sportSlug; ?>" name="seo_title" value="<?php echo htmlspecialchars($seoTitle); ?>" placeholder="Live <?php echo htmlspecialchars($sport); ?> - <?php echo htmlspecialchars($website['site_name']); ?>">
                                                <small>Recommended: 50-60 characters</small>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="seo_description_<?php echo $sportSlug; ?>">SEO Description</label>
                                                <textarea id="seo_description_<?php echo $sportSlug; ?>" name="seo_description" rows="3" placeholder="Watch <?php echo htmlspecialchars($sport); ?> live streams..."><?php echo htmlspecialchars($seoDescription); ?></textarea>
                                                <small>Recommended: 150-160 characters</small>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">Save SEO</button>
                                        </form>
                                    </div>
                                    
                                    <!-- SPORT MANAGEMENT SECTION -->
                                    <div class="sport-management-section">
                                        <h4>⚽ Sport Management</h4>
                                        
                                        <!-- Sport Card (from original sports page) -->
                                        <div class="sport-card">
                                            <div class="sport-card-header">
                                                <div class="sport-icon-display <?php echo $hasIcon ? '' : 'no-icon'; ?>">
                                                    <?php if ($hasIcon): ?>
                                                        <img src="<?php echo $iconUrl; ?>?v=<?php echo time(); ?>" 
                                                             alt="<?php echo htmlspecialchars($sport); ?>" 
                                                             onerror="this.parentElement.classList.add('no-icon'); this.parentElement.innerHTML='?';">
                                                    <?php else: ?>
                                                        ?
                                                    <?php endif; ?>
                                                </div>
                                                <div class="sport-card-info">
                                                    <div class="sport-card-name"><?php echo htmlspecialchars($sport); ?></div>
                                                    <div class="sport-card-meta">
                                                        <?php if ($hasIcon): ?>
                                                            <span>✅ Has icon</span>
                                                            <span>•</span>
                                                            <span><?php echo htmlspecialchars($iconFile); ?></span>
                                                        <?php else: ?>
                                                            <span style="color: #e74c3c;">⚠️ No icon</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="sport-card-actions">
                                                <button type="button" class="btn-edit-icon" onclick="openIconModal('<?php echo htmlspecialchars($sport, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($iconFile, ENT_QUOTES); ?>')">
                                                    🖼️ <?php echo $hasIcon ? 'Change' : 'Add'; ?> Icon
                                                </button>
                                                
                                                <?php if ($hasIcon): ?>
                                                    <form method="POST" style="margin: 0;" onsubmit="return confirm('Delete icon for <?php echo htmlspecialchars($sport); ?>?');">
                                                        <input type="hidden" name="delete_icon" value="1">
                                                        <input type="hidden" name="sport_name" value="<?php echo htmlspecialchars($sport); ?>">
                                                        <button type="submit" class="btn-delete-icon">
                                                            🗑️ Delete Icon
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <div></div>
                                                <?php endif; ?>
                                                
                                                <button type="button" class="btn-rename" onclick="openRenameModal('<?php echo htmlspecialchars($sport, ENT_QUOTES); ?>')">
                                                    ✏️ Rename
                                                </button>
                                                
                                                <form method="POST" onsubmit="return confirm('Delete <?php echo htmlspecialchars($sport); ?>? This will also delete SEO data.');" style="margin: 0;">
                                                    <input type="hidden" name="sport_name" value="<?php echo htmlspecialchars($sport); ?>">
                                                    <button type="submit" name="delete_sport" class="btn-delete">
                                                        🗑️ Delete Sport
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (empty($sports)): ?>
                        <div style="text-align: center; padding: 60px; color: #999;">
                            <div style="font-size: 80px; margin-bottom: 20px;">⚽</div>
                            <h3>No sport categories yet</h3>
                            <p>Add your first sport category above!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- ICON UPLOAD MODAL -->
    <div class="modal" id="iconModal">
        <div class="modal-content">
            <h3 id="iconModalTitle">Upload/Change Sport Icon</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="edit_icon" value="1">
                <input type="hidden" name="sport_name" id="iconSportName">
                
                <div class="upload-preview-area">
                    <div id="iconPreviewContainer" class="preview-icon-large no-icon">?</div>
                    <p style="color: #666; margin-top: 10px;" id="currentIconName">No icon</p>
                </div>
                
                <div class="form-group">
                    <label for="sportIconFile">Upload New Icon *</label>
                    <div class="file-upload-wrapper">
                        <label for="sportIconFile" class="file-upload-label">
                            <span>📤</span>
                            <span>Choose New Image</span>
                        </label>
                        <input type="file" id="sportIconFile" name="sport_icon_file" class="file-upload-input" accept=".webp,.svg,.avif" required>
                        <div class="file-name-display" id="editSportFileName">No file chosen</div>
                    </div>
                    <small>WEBP, SVG, AVIF • Auto-resize to 64x64px</small>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end;">
                    <button type="button" class="btn btn-outline" onclick="closeIconModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload Icon</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- RENAME MODAL -->
    <div class="modal" id="renameModal">
        <div class="modal-content">
            <h3 id="renameModalTitle">Rename Sport Category</h3>
            <form method="POST">
                <input type="hidden" name="rename_sport" value="1">
                <input type="hidden" name="old_sport_name" id="oldSportName">
                <div class="form-group">
                    <label for="newSportNameInput">New Sport Name</label>
                    <input type="text" id="newSportNameInput" name="new_sport_name" required>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end;">
                    <button type="button" class="btn btn-outline" onclick="closeRenameModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Rename</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- SAVE ORDER CONFIRMATION MODAL -->
    <div class="modal confirmation-modal" id="saveOrderModal">
        <div class="modal-content">
            <div class="modal-icon">💾</div>
            <h3>Save Changes?</h3>
            <p style="color: #666; margin: 20px 0;">Do you want to save the new sport order?</p>
            <div class="modal-buttons">
                <button type="button" class="btn btn-outline" onclick="cancelOrderChange()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="confirmSaveOrder()">Yes, Save Order</button>
            </div>
        </div>
    </div>
    
    <script src="js/website-pages.js"></script>
    <script>
        // Home icon file upload preview
        document.getElementById('home_icon_file').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || 'No file chosen';
            document.getElementById('homeIconFileName').textContent = fileName;
        });
    </script>
</body>
</html>