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

// Calculate status indicator for Home page
function getHomeStatusIndicator($pagesSeo, $homeIcon) {
    $seoData = $pagesSeo['home'] ?? [];
    $hasTitle = !empty(trim($seoData['title'] ?? ''));
    $hasDescription = !empty(trim($seoData['description'] ?? ''));
    $hasIcon = !empty($homeIcon);
    
    if ($hasTitle && $hasDescription && $hasIcon) {
        return '🟢';
    } elseif (!$hasTitle && !$hasDescription && !$hasIcon) {
        return '🔴';
    } else {
        return '🟠';
    }
}

// Calculate status indicator based on SEO + Icon
function getStatusIndicator($sportName, $pagesSeo, $sportsIcons) {
    $sportSlug = strtolower(str_replace(' ', '-', $sportName));
    
    $seoData = $pagesSeo['sports'][$sportSlug] ?? [];
    $hasTitle = !empty(trim($seoData['title'] ?? ''));
    $hasDescription = !empty(trim($seoData['description'] ?? ''));
    $hasIcon = isset($sportsIcons[$sportName]) && !empty($sportsIcons[$sportName]);
    
    if ($hasTitle && $hasDescription && $hasIcon) {
        return '🟢';
    } elseif (!$hasTitle && !$hasDescription && !$hasIcon) {
        return '🔴';
    } else {
        return '🟠';
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
        
        // UPDATE HOME (SEO + Icon)
        if (isset($_POST['update_home'])) {
            $websites[$websiteIndex]['pages_seo']['home'] = [
                'title' => trim($_POST['home_seo_title'] ?? ''),
                'description' => trim($_POST['home_seo_description'] ?? '')
            ];
            
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
                    $success = "✅ Home page updated with new icon!";
                } else {
                    $error = "❌ Icon upload failed: " . $uploadResult['error'];
                }
            } else {
                $success = "✅ Home page SEO updated!";
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
                            $success = "✅ Sport category '{$newSport}' added with icon!";
                        } else {
                            $error = $uploadResult['error'];
                        }
                    } else {
                        $success = "✅ Sport category '{$newSport}' added!";
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
        
        // UPDATE SPORT (SEO + Icon)
        if (isset($_POST['update_sport'])) {
            $sportName = $_POST['sport_name'] ?? '';
            $sportSlug = strtolower(str_replace(' ', '-', $sportName));
            
            $websites[$websiteIndex]['pages_seo']['sports'][$sportSlug] = [
                'title' => trim($_POST['seo_title'] ?? ''),
                'description' => trim($_POST['seo_description'] ?? '')
            ];
            
            if (isset($_FILES['sport_icon_file']) && $_FILES['sport_icon_file']['size'] > 0) {
                $uploadResult = handleImageUpload($_FILES['sport_icon_file'], $uploadDir, $sportName, $debugInfo);
                if (isset($uploadResult['success'])) {
                    if (isset($websites[$websiteIndex]['sports_icons'][$sportName])) {
                        $oldFile = $uploadDir . $websites[$websiteIndex]['sports_icons'][$sportName];
                        if (file_exists($oldFile) && $oldFile !== $uploadDir . $uploadResult['filename']) {
                            unlink($oldFile);
                        }
                    }
                    $websites[$websiteIndex]['sports_icons'][$sportName] = $uploadResult['filename'];
                    $success = "✅ '{$sportName}' updated with new icon!";
                } else {
                    $error = "❌ Icon upload failed: " . $uploadResult['error'];
                }
            } else {
                $success = "✅ '{$sportName}' SEO updated!";
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
            $confirmName = $_POST['confirm_sport_name'] ?? '';
            
            if ($sportToDelete === $confirmName) {
                if (isset($websites[$websiteIndex]['sports_icons'][$sportToDelete])) {
                    $iconFile = $uploadDir . $websites[$websiteIndex]['sports_icons'][$sportToDelete];
                    if (file_exists($iconFile)) {
                        unlink($iconFile);
                    }
                    unset($websites[$websiteIndex]['sports_icons'][$sportToDelete]);
                }
                
                $sports = $websites[$websiteIndex]['sports_categories'];
                $sports = array_filter($sports, function($sport) use ($sportToDelete) {
                    return $sport !== $sportToDelete;
                });
                $websites[$websiteIndex]['sports_categories'] = array_values($sports);
                
                $sportSlug = strtolower(str_replace(' ', '-', $sportToDelete));
                if (isset($websites[$websiteIndex]['pages_seo']['sports'][$sportSlug])) {
                    unset($websites[$websiteIndex]['pages_seo']['sports'][$sportSlug]);
                }
                
                $success = "✅ Sport category '{$sportToDelete}' deleted";
            } else {
                $error = "❌ Sport name doesn't match. Deletion cancelled.";
            }
        }
        
        // REORDER SPORTS
        if (isset($_POST['reorder_sports'])) {
            $newOrder = json_decode($_POST['sports_order'] ?? '[]', true);
            
            if (is_array($newOrder) && count($newOrder) > 0) {
                $websites[$websiteIndex]['sports_categories'] = $newOrder;
                $success = "✅ Sports order updated!";
            } else {
                $error = "❌ Invalid sports order data";
            }
        }
        
        // Save changes
        if ($success || $error) {
            $configData['websites'] = $websites;
            $jsonContent = json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            if (!file_put_contents($configFile, $jsonContent)) {
                $error = '❌ Failed to save changes. Check permissions: chmod 644 ' . $configFile;
                $success = '';
            } else {
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
                
                <!-- HOME PAGE SECTION -->
                <div class="content-section home-page-section">
                    <div class="pages-accordion">
                        <details class="home-page-card" data-page-type="home">
                            <summary>
                                <span class="status-indicator"><?php echo $homeStatus; ?></span>
                                <span class="header-icon-small <?php echo !empty($homeIcon) ? 'has-icon' : 'no-icon'; ?>">
                                    <?php if (!empty($homeIcon)): 
                                        $iconUrl = 'https://www.' . htmlspecialchars($previewDomain) . '/images/sports/' . htmlspecialchars($homeIcon);
                                    ?>
                                        <img src="<?php echo $iconUrl; ?>?v=<?php echo time(); ?>" alt="Home">
                                    <?php else: ?>
                                        🏠
                                    <?php endif; ?>
                                </span>
                                <span class="accordion-title">
                                    <span class="home-badge">HOME</span>
                                    Home Page
                                </span>
                            </summary>
                            
                            <div class="accordion-content">
                                <form method="POST" enctype="multipart/form-data" class="sport-form">
                                    <input type="hidden" name="update_home" value="1">
                                    
                                    <!-- SEO SECTION -->
                                    <div class="form-section-title">🔍 SEO Settings</div>
                                    
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
                                    
                                    <!-- ICON SECTION -->
                                    <div class="form-section-title">🖼️ Icon</div>
                                    
                                    <div class="icon-management-row">
                                        <div class="icon-preview-box <?php echo !empty($homeIcon) ? 'has-icon' : 'no-icon'; ?>">
                                            <?php if (!empty($homeIcon)): ?>
                                                <img src="<?php echo $iconUrl; ?>?v=<?php echo time(); ?>" alt="Home Icon">
                                            <?php else: ?>
                                                🏠
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="icon-upload-area">
                                            <label for="home_icon_file" class="file-upload-label-inline">
                                                <span>📤</span>
                                                <span><?php echo !empty($homeIcon) ? 'Change Icon' : 'Upload Icon'; ?></span>
                                            </label>
                                            <input type="file" id="home_icon_file" name="home_icon_file" class="file-upload-input" accept=".webp,.svg,.avif">
                                            <span class="file-name-inline" id="homeIconFileName"><?php echo !empty($homeIcon) ? htmlspecialchars($homeIcon) : 'No file chosen'; ?></span>
                                        </div>
                                        
                                        <?php if (!empty($homeIcon)): ?>
                                            <button type="button" class="btn-delete-icon" onclick="openDeleteIconModal('home', 'Home')">🗑️ Delete</button>
                                        <?php endif; ?>
                                    </div>
                                    <small>WEBP, SVG, AVIF • Auto-resize to 64x64px</small>
                                    
                                    <button type="submit" class="btn btn-primary btn-save">💾 Save Home Page</button>
                                </form>
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
                
                <!-- SPORTS LIST -->
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
                                    <span class="header-icon-small <?php echo $hasIcon ? 'has-icon' : 'no-icon'; ?>">
                                        <?php if ($hasIcon): ?>
                                            <img src="<?php echo $iconUrl; ?>?v=<?php echo time(); ?>" alt="<?php echo htmlspecialchars($sport); ?>">
                                        <?php else: ?>
                                            ?
                                        <?php endif; ?>
                                    </span>
                                    <span class="accordion-title"><?php echo htmlspecialchars($sport); ?></span>
                                </summary>
                                
                                <div class="accordion-content">
                                    <form method="POST" enctype="multipart/form-data" class="sport-form">
                                        <input type="hidden" name="update_sport" value="1">
                                        <input type="hidden" name="sport_name" value="<?php echo htmlspecialchars($sport); ?>">
                                        
                                        <!-- SEO SECTION -->
                                        <div class="form-section-title">🔍 SEO Settings</div>
                                        
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
                                        
                                        <!-- ICON SECTION -->
                                        <div class="form-section-title">🖼️ Icon</div>
                                        
                                        <div class="icon-management-row">
                                            <div class="icon-preview-box <?php echo $hasIcon ? 'has-icon' : 'no-icon'; ?>">
                                                <?php if ($hasIcon): ?>
                                                    <img src="<?php echo $iconUrl; ?>?v=<?php echo time(); ?>" alt="<?php echo htmlspecialchars($sport); ?>">
                                                <?php else: ?>
                                                    ?
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="icon-upload-area">
                                                <label for="sport_icon_<?php echo $sportSlug; ?>" class="file-upload-label-inline">
                                                    <span>📤</span>
                                                    <span><?php echo $hasIcon ? 'Change Icon' : 'Upload Icon'; ?></span>
                                                </label>
                                                <input type="file" id="sport_icon_<?php echo $sportSlug; ?>" name="sport_icon_file" class="file-upload-input" accept=".webp,.svg,.avif">
                                                <span class="file-name-inline"><?php echo $hasIcon ? htmlspecialchars($iconFile) : 'No file chosen'; ?></span>
                                            </div>
                                            
                                            <?php if ($hasIcon): ?>
                                                <button type="button" class="btn-delete-icon" onclick="openDeleteIconModal('<?php echo htmlspecialchars($sport, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($sport, ENT_QUOTES); ?>')">🗑️ Delete</button>
                                            <?php endif; ?>
                                        </div>
                                        <small>WEBP, SVG, AVIF • Auto-resize to 64x64px</small>
                                        
                                        <button type="submit" class="btn btn-primary btn-save">💾 Save <?php echo htmlspecialchars($sport); ?></button>
                                    </form>
                                    
                                    <!-- SPORT MANAGEMENT SECTION -->
                                    <div class="management-section">
                                        <div class="form-section-title">⚙️ Sport Management</div>
                                        
                                        <button type="button" class="btn-action" onclick="openRenameModal('<?php echo htmlspecialchars($sport, ENT_QUOTES); ?>')">
                                            ✏️ Rename Sport
                                        </button>
                                    </div>
                                    
                                    <!-- DANGER ZONE -->
                                    <div class="danger-zone">
                                        <div class="form-section-title">🗑️ Danger Zone</div>
                                        <p>Permanently delete this sport category, its icon, and all SEO data.</p>
                                        <button type="button" class="btn-danger-action" onclick="openDeleteModal('<?php echo htmlspecialchars($sport, ENT_QUOTES); ?>')">
                                            🗑️ Delete Sport
                                        </button>
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
    
    <!-- DELETE ICON MODAL -->
    <div class="modal" id="deleteIconModal">
        <div class="modal-content modal-small">
            <div class="modal-icon">🗑️</div>
            <h3>Delete Icon?</h3>
            <p id="deleteIconMessage">Are you sure you want to delete this icon?</p>
            <form method="POST" id="deleteIconForm">
                <input type="hidden" name="delete_icon" value="1">
                <input type="hidden" name="sport_name" id="deleteIconSportName">
                <div class="modal-buttons">
                    <button type="button" class="btn btn-outline" onclick="closeModal('deleteIconModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Icon</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- RENAME MODAL -->
    <div class="modal" id="renameModal">
        <div class="modal-content modal-small">
            <h3>✏️ Rename Sport</h3>
            <p>Enter a new name for this sport category.</p>
            <form method="POST" id="renameForm">
                <input type="hidden" name="rename_sport" value="1">
                <input type="hidden" name="old_sport_name" id="renameOldSportName">
                <div class="form-group">
                    <label for="renameNewSportName">New Name</label>
                    <input type="text" id="renameNewSportName" name="new_sport_name" required>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-outline" onclick="closeModal('renameModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Rename</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- DELETE SPORT MODAL -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-icon">⚠️</div>
            <h3>Delete Sport Category?</h3>
            <p>This will permanently delete the sport category, its icon, and all SEO data.</p>
            <p class="delete-warning">To confirm, type the sport name: <strong id="deleteSportNameDisplay"></strong></p>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="delete_sport" value="1">
                <input type="hidden" name="sport_name" id="deleteSportName">
                <div class="form-group">
                    <input type="text" id="confirmSportNameInput" name="confirm_sport_name" placeholder="Type sport name to confirm" required autocomplete="off">
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="confirmDeleteBtn" disabled>Delete Sport</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- SAVE ORDER MODAL -->
    <div class="modal" id="saveOrderModal">
        <div class="modal-content modal-small">
            <div class="modal-icon">💾</div>
            <h3>Save Changes?</h3>
            <p>Do you want to save the new sport order?</p>
            <div class="modal-buttons">
                <button type="button" class="btn btn-outline" onclick="cancelOrderChange()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="confirmSaveOrder()">Yes, Save Order</button>
            </div>
        </div>
    </div>
    
    <script src="js/website-pages.js"></script>
</body>
</html>