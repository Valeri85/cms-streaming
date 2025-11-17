<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$websiteId = $_GET['id'] ?? null;
$error = '';
$success = '';
$debugInfo = ''; // For debugging upload issues

if (!$websiteId) {
    header('Location: dashboard.php');
    exit;
}

$configFile = '/var/www/u1852176/data/www/streaming/config/websites.json';
$uploadDir = '/var/www/u1852176/data/www/streaming/images/sports/';

if (!file_exists($configFile)) {
    die("Configuration file not found at: " . $configFile);
}

// Create upload directory if it doesn't exist
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        die("Failed to create upload directory: " . $uploadDir);
    }
}

// Check if upload directory is writable
if (!is_writable($uploadDir)) {
    die("Upload directory is not writable: " . $uploadDir . " - Please run: chmod 755 " . $uploadDir);
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

// ONLY WEBP, SVG, AVIF allowed
function handleImageUpload($file, $uploadDir, &$debugInfo) {
    global $error;
    
    $debugInfo .= "=== UPLOAD DEBUG START ===\n";
    $debugInfo .= "Upload Dir: $uploadDir\n";
    $debugInfo .= "Upload Dir Writable: " . (is_writable($uploadDir) ? 'YES' : 'NO') . "\n";
    
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        $debugInfo .= "ERROR: No file tmp_name\n";
        return ['error' => 'No file uploaded'];
    }
    
    if (!is_uploaded_file($file['tmp_name'])) {
        $debugInfo .= "ERROR: Not an uploaded file\n";
        return ['error' => 'Invalid upload'];
    }
    
    $debugInfo .= "File uploaded successfully to temp: {$file['tmp_name']}\n";
    $debugInfo .= "Original filename: {$file['name']}\n";
    $debugInfo .= "File size: {$file['size']} bytes\n";
    $debugInfo .= "Upload error code: {$file['error']}\n";
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'PHP extension stopped upload'
        ];
        $errorMsg = $uploadErrors[$file['error']] ?? 'Unknown upload error';
        $debugInfo .= "ERROR: Upload error - $errorMsg\n";
        return ['error' => $errorMsg];
    }
    
    $allowedTypes = ['image/webp', 'image/svg+xml', 'image/avif'];
    $allowedExtensions = ['webp', 'svg', 'avif'];
    
    // Get file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $debugInfo .= "File extension: $extension\n";
    
    if (!in_array($extension, $allowedExtensions)) {
        $debugInfo .= "ERROR: Extension not allowed\n";
        return ['error' => 'Invalid file extension. Only WEBP, SVG, AVIF allowed'];
    }
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $debugInfo .= "MIME type detected: $mimeType\n";
    
    // Special handling for AVIF
    if ($extension === 'avif') {
        $mimeType = 'image/avif';
        $allowedTypes[] = 'image/avif';
    }
    
    if (!in_array($mimeType, $allowedTypes)) {
        $debugInfo .= "ERROR: MIME type not allowed\n";
        return ['error' => 'Invalid file type. Only WEBP, SVG, AVIF allowed'];
    }
    
    // Generate filename
    $filename = uniqid('sport_', true) . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    $debugInfo .= "Target filename: $filename\n";
    $debugInfo .= "Target filepath: $filepath\n";
    
    // Process image
    if (in_array($extension, ['webp', 'avif'])) {
        $debugInfo .= "Processing raster image...\n";
        
        if (!extension_loaded('gd')) {
            $debugInfo .= "ERROR: GD extension not available\n";
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
                    $debugInfo .= "ERROR: AVIF not supported\n";
                    return ['error' => 'AVIF format not supported'];
                }
                break;
        }
        
        if (!$sourceImage) {
            $debugInfo .= "ERROR: Failed to create image from source\n";
            return ['error' => 'Failed to process image'];
        }
        
        $debugInfo .= "Source image created successfully\n";
        
        // Create 64x64 target
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
        
        $debugInfo .= "Image resized to 64x64\n";
        
        // Save image
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
            $debugInfo .= "ERROR: Failed to save processed image\n";
            return ['error' => 'Failed to save processed image'];
        }
        
        $debugInfo .= "Image saved successfully\n";
        
    } else { // SVG
        $debugInfo .= "Processing SVG...\n";
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            $debugInfo .= "ERROR: Failed to move uploaded file\n";
            return ['error' => 'Failed to save file'];
        }
        
        $debugInfo .= "SVG moved successfully\n";
    }
    
    // Set permissions
    if (file_exists($filepath)) {
        chmod($filepath, 0644);
        $debugInfo .= "File permissions set to 0644\n";
        $debugInfo .= "File size on disk: " . filesize($filepath) . " bytes\n";
        $debugInfo .= "File exists: YES\n";
    } else {
        $debugInfo .= "ERROR: File does not exist after save!\n";
        return ['error' => 'File was not saved'];
    }
    
    $debugInfo .= "=== UPLOAD DEBUG END ===\n";
    
    return ['success' => true, 'filename' => $filename];
}

// Load website data first
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

// Get preview URL - use actual website domain for correct icon display
$previewDomain = $website['domain'];

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
        
        if (isset($_POST['add_sport'])) {
            $newSport = trim($_POST['new_sport_name'] ?? '');
            
            if ($newSport) {
                if (!in_array($newSport, $websites[$websiteIndex]['sports_categories'])) {
                    $websites[$websiteIndex]['sports_categories'][] = $newSport;
                    
                    if (isset($_FILES['new_sport_icon']) && $_FILES['new_sport_icon']['size'] > 0) {
                        $uploadResult = handleImageUpload($_FILES['new_sport_icon'], $uploadDir, $debugInfo);
                        if (isset($uploadResult['success'])) {
                            $websites[$websiteIndex]['sports_icons'][$newSport] = $uploadResult['filename'];
                            $success = "Sport category '{$newSport}' added successfully with icon: {$uploadResult['filename']}";
                        } else {
                            $error = $uploadResult['error'];
                        }
                    } else {
                        $success = "Sport category '{$newSport}' added successfully (no icon)";
                    }
                    
                    if ($success) {
                        sendSlackNotification($newSport);
                    }
                } else {
                    $error = "Sport category '{$newSport}' already exists!";
                }
            } else {
                $error = "Please enter a sport name";
            }
        }
        
        if (isset($_POST['edit_icon'])) {
            $sportName = $_POST['sport_name'] ?? '';
            
            if ($sportName && isset($_FILES['sport_icon_file']) && $_FILES['sport_icon_file']['size'] > 0) {
                $uploadResult = handleImageUpload($_FILES['sport_icon_file'], $uploadDir, $debugInfo);
                if (isset($uploadResult['success'])) {
                    if (isset($websites[$websiteIndex]['sports_icons'][$sportName])) {
                        $oldFile = $uploadDir . $websites[$websiteIndex]['sports_icons'][$sportName];
                        if (file_exists($oldFile)) {
                            unlink($oldFile);
                        }
                    }
                    
                    $websites[$websiteIndex]['sports_icons'][$sportName] = $uploadResult['filename'];
                    $success = "Icon updated for '{$sportName}' - File: {$uploadResult['filename']}";
                } else {
                    $error = $uploadResult['error'];
                }
            } else {
                $error = "Please select an icon file";
            }
        }
        
        if (isset($_POST['delete_icon'])) {
            $sportName = $_POST['sport_name'] ?? '';
            
            if ($sportName && isset($websites[$websiteIndex]['sports_icons'][$sportName])) {
                $iconFile = $uploadDir . $websites[$websiteIndex]['sports_icons'][$sportName];
                if (file_exists($iconFile)) {
                    unlink($iconFile);
                }
                unset($websites[$websiteIndex]['sports_icons'][$sportName]);
                $success = "Icon deleted for '{$sportName}'";
            }
        }
        
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
                            $websites[$websiteIndex]['sports_icons'][$newName] = $websites[$websiteIndex]['sports_icons'][$oldName];
                            unset($websites[$websiteIndex]['sports_icons'][$oldName]);
                        }
                        
                        $success = "Sport category renamed from '{$oldName}' to '{$newName}' successfully!";
                    } else {
                        $error = "Sport category '{$newName}' already exists!";
                    }
                } else {
                    $error = "Sport category '{$oldName}' not found!";
                }
            } else {
                $error = "Please enter a valid sport name";
            }
        }
        
        if (isset($_POST['delete_sport'])) {
            $sportToDelete = $_POST['sport_name'] ?? '';
            
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
            
            $success = "Sport category '{$sportToDelete}' deleted successfully!";
        }
        
        if (isset($_POST['reorder_sports'])) {
            $newOrder = json_decode($_POST['sports_order'], true);
            if (is_array($newOrder)) {
                $websites[$websiteIndex]['sports_categories'] = $newOrder;
                $success = "Sports order updated successfully!";
            }
        }
        
        if ($success || $error) {
            $configData['websites'] = $websites;
            $jsonContent = json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            if (!file_put_contents($configFile, $jsonContent)) {
                $error = 'Failed to save changes. Check file permissions: chmod 644 ' . $configFile;
                $success = '';
            } else {
                // Reload website data after successful save
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sports - <?php echo htmlspecialchars($website['site_name']); ?></title>
    <link rel="stylesheet" href="cms-style.css">
    <style>
        .sports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .sport-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            padding: 20px;
            border-radius: 12px;
            border: 2px solid #e9ecef;
            display: flex;
            flex-direction: column;
            gap: 15px;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .sport-card:hover {
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
            transform: translateY(-3px);
        }
        
        .sport-card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .sport-icon-display {
            width: 64px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .sport-icon-display img {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }
        
        .sport-icon-display.no-icon {
            font-size: 36px;
            color: white;
        }
        
        .sport-card-info {
            flex: 1;
        }
        
        .sport-card-name {
            font-weight: 700;
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .sport-card-meta {
            font-size: 13px;
            color: #7f8c8d;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .sport-card-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .sport-card-actions button,
        .sport-card-actions .btn-delete {
            padding: 10px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .btn-edit-icon {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }
        
        .btn-edit-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
        }
        
        .btn-rename {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
        }
        
        .btn-rename:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(243, 156, 18, 0.4);
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            grid-column: 1 / -1;
        }
        
        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4);
        }
        
        .add-sport-card {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 2px solid #81c784;
            box-shadow: 0 4px 12px rgba(129, 199, 132, 0.2);
        }
        
        .add-sport-form {
            display: grid;
            grid-template-columns: 2fr 2fr 1fr;
            gap: 15px;
            align-items: end;
        }
        
        .file-upload-wrapper {
            position: relative;
        }
        
        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .file-upload-label:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .file-upload-input {
            display: none;
        }
        
        .file-name-display {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            text-align: center;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
        }
        
        .upload-preview-area {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            text-align: center;
        }
        
        .preview-icon-large {
            width: 128px;
            height: 128px;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        }
        
        .preview-icon-large img {
            max-width: 96px;
            max-height: 96px;
            object-fit: contain;
        }
        
        .preview-icon-large.no-icon {
            font-size: 64px;
            color: white;
        }
        
        .icon-info {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #2196f3;
        }
        
        .icon-info h3 {
            color: #1565c0;
            margin-bottom: 10px;
        }
        
        .slack-info {
            background: #fff3cd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #ffc107;
        }
        
        .slack-info h3 {
            color: #856404;
            margin-bottom: 10px;
        }
        
        .debug-info {
            background: #f0f0f0;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            font-size: 11px;
            font-family: monospace;
            color: #333;
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 300px;
            overflow-y: auto;
        }
        
        @media (max-width: 768px) {
            .add-sport-form {
                grid-template-columns: 1fr;
            }
            
            .sports-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
            </nav>
            
            <div class="cms-user">
                <a href="logout.php" class="btn btn-sm btn-outline">Logout</a>
            </div>
        </aside>
        
        <main class="cms-main">
            <header class="cms-header">
                <h1>Manage Sports: <?php echo htmlspecialchars($website['site_name']); ?></h1>
                <div>
                    <a href="website-seo.php?id=<?php echo $websiteId; ?>" class="btn">← Back to SEO</a>
                    <a href="dashboard.php" class="btn">Dashboard</a>
                </div>
            </header>
            
            <div class="cms-content">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <?php if (!empty($debugInfo)): ?>
                    <div class="alert" style="background: #fff3cd; border-left: 4px solid #ffc107;">
                        <h4 style="margin-bottom: 10px;">Upload Debug Information:</h4>
                        <div class="debug-info"><?php echo htmlspecialchars($debugInfo); ?></div>
                    </div>
                <?php endif; ?>
                
                <div class="icon-info">
                    <h3>📁 Sport Icon Upload</h3>
                    <p><strong>✅ Supported formats:</strong> WEBP, SVG, AVIF only</p>
                    <p><strong>📐 Auto-resize:</strong> Images will be automatically resized to 64x64px (except SVG)</p>
                    <p><strong>💡 Tip:</strong> Use transparent backgrounds for best results</p>
                    <p><strong>📍 Storage path:</strong> <code><?php echo $uploadDir; ?></code></p>
                    <p><strong>✅ Directory writable:</strong> <?php echo is_writable($uploadDir) ? '<span style="color: green;">YES</span>' : '<span style="color: red;">NO - Fix permissions!</span>'; ?></p>
                </div>
                
                <div class="slack-info">
                    <h3>📢 Slack Notifications</h3>
                    <p>To receive notifications when new sports are added, create: <code>/var/www/u1852176/data/www/streaming/config/slack-config.json</code></p>
                </div>
                
                <div class="add-sport-card">
                    <h3 style="margin-bottom: 20px; color: #2e7d32; font-size: 20px;">➕ Add New Sport Category</h3>
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
                
                <div class="content-section">
                    <div class="section-header">
                        <h2>Current Sports Categories (<?php echo count($sports); ?>)</h2>
                    </div>
                    
                    <div class="sports-grid">
                        <?php foreach ($sports as $sport): 
                            $iconFile = $sportsIcons[$sport] ?? '';
                            $hasIcon = !empty($iconFile);
                            $iconPath = $hasIcon ? $uploadDir . $iconFile : '';
                            $iconExists = $hasIcon && file_exists($iconPath);
                            $iconUrl = 'https://' . htmlspecialchars($previewDomain) . '/images/sports/' . htmlspecialchars($iconFile);
                        ?>
                            <div class="sport-card" data-sport-name="<?php echo htmlspecialchars($sport); ?>">
                                <div class="sport-card-header">
                                    <div class="sport-icon-display <?php echo $hasIcon ? '' : 'no-icon'; ?>">
                                        <?php if ($hasIcon): ?>
                                            <img src="<?php echo $iconUrl; ?>?v=<?php echo time(); ?>" alt="<?php echo htmlspecialchars($sport); ?>" onerror="this.parentElement.innerHTML='?'; this.parentElement.classList.add('no-icon');">
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
                                                <span>•</span>
                                                <span>File exists: <?php echo $iconExists ? '✅' : '❌'; ?></span>
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
                                    
                                    <button type="button" class="btn-rename" onclick="openRenameModal('<?php echo htmlspecialchars($sport, ENT_QUOTES); ?>')">
                                        ✏️ Rename
                                    </button>
                                    
                                    <form method="POST" onsubmit="return confirm('Delete <?php echo htmlspecialchars($sport); ?>?');" style="margin: 0; grid-column: 1 / -1;">
                                        <input type="hidden" name="sport_name" value="<?php echo htmlspecialchars($sport); ?>">
                                        <button type="submit" name="delete_sport" class="btn-delete">
                                            🗑️ Delete Sport
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (empty($sports)): ?>
                        <div style="text-align: center; padding: 60px; color: #999;">
                            <div style="font-size: 80px; margin-bottom: 20px;">⚽</div>
                            <h3>No sports categories yet</h3>
                            <p>Add your first sport category above!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <div class="modal" id="iconModal">
        <div class="modal-content">
            <h3>Edit Sport Icon</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="edit_icon" value="1">
                <input type="hidden" name="sport_name" id="iconSportName">
                
                <div class="upload-preview-area">
                    <div id="iconPreviewContainer" class="preview-icon-large no-icon">?</div>
                    <p style="color: #666; margin-top: 10px;" id="currentIconName">No icon</p>
                </div>
                
                <div class="form-group">
                    <label for="sportIconFile">Upload New Icon</label>
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
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this icon?');">
                        <input type="hidden" name="delete_icon" value="1">
                        <input type="hidden" name="sport_name" id="deleteIconSportName">
                        <button type="submit" class="btn btn-danger" id="deleteIconBtn" style="display: none;">Delete Icon</button>
                    </form>
                    <button type="button" class="btn btn-outline" onclick="closeIconModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Icon</button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="modal" id="renameModal">
        <div class="modal-content">
            <h3>Rename Sport Category</h3>
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
    
    <script>
        const previewDomain = '<?php echo htmlspecialchars($previewDomain); ?>';
        
        // File upload preview for new sport
        document.getElementById('new_sport_icon').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || 'No file chosen';
            document.getElementById('newSportFileName').textContent = fileName;
        });
        
        // File upload preview for edit icon
        document.getElementById('sportIconFile').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || 'No file chosen';
            document.getElementById('editSportFileName').textContent = fileName;
            
            if (e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const preview = document.getElementById('iconPreviewContainer');
                    preview.innerHTML = '<img src="' + event.target.result + '" alt="Preview">';
                    preview.classList.remove('no-icon');
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });
        
        function openIconModal(sportName, currentIcon) {
            document.getElementById('iconSportName').value = sportName;
            document.getElementById('deleteIconSportName').value = sportName;
            
            const preview = document.getElementById('iconPreviewContainer');
            const iconName = document.getElementById('currentIconName');
            const deleteBtn = document.getElementById('deleteIconBtn');
            
            if (currentIcon) {
                const iconUrl = 'https://' + previewDomain + '/images/sports/' + currentIcon + '?v=' + Date.now();
                preview.innerHTML = '<img src="' + iconUrl + '" alt="' + sportName + '" onerror="this.parentElement.innerHTML=\'?\'; this.parentElement.classList.add(\'no-icon\');">';
                preview.classList.remove('no-icon');
                iconName.textContent = 'Current: ' + currentIcon;
                deleteBtn.style.display = 'inline-block';
            } else {
                preview.innerHTML = '?';
                preview.classList.add('no-icon');
                iconName.textContent = 'No icon';
                deleteBtn.style.display = 'none';
            }
            
            document.getElementById('editSportFileName').textContent = 'No file chosen';
            document.getElementById('sportIconFile').value = '';
            document.getElementById('iconModal').classList.add('active');
        }
        
        function closeIconModal() {
            document.getElementById('iconModal').classList.remove('active');
        }
        
        function openRenameModal(sportName) {
            document.getElementById('oldSportName').value = sportName;
            document.getElementById('newSportNameInput').value = sportName;
            document.getElementById('renameModal').classList.add('active');
            document.getElementById('newSportNameInput').focus();
            document.getElementById('newSportNameInput').select();
        }
        
        function closeRenameModal() {
            document.getElementById('renameModal').classList.remove('active');
        }
        
        document.getElementById('iconModal').addEventListener('click', function(e) {
            if (e.target === this) closeIconModal();
        });
        
        document.getElementById('renameModal').addEventListener('click', function(e) {
            if (e.target === this) closeRenameModal();
        });
    </script>
</body>
</html>