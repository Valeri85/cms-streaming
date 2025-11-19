<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$websiteId = $_GET['id'] ?? null;
$error = '';
$success = '';

if (!$websiteId) {
    header('Location: dashboard.php');
    exit;
}

$configFile = '/var/www/u1852176/data/www/streaming/config/websites.json';
$uploadDir = '/var/www/u1852176/data/www/streaming/images/logos/';

if (!file_exists($configFile)) {
    die("Configuration file not found at: " . $configFile);
}

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// NEW FUNCTION: Generate canonical URL
function generateCanonicalUrl($domain) {
    // Normalize domain (remove www if present)
    $normalized = str_replace('www.', '', strtolower(trim($domain)));
    
    // Generate canonical URL with https:// and www.
    return 'https://www.' . $normalized;
}

function sanitizeSiteName($siteName) {
    $filename = strtolower($siteName);
    $filename = str_replace(' ', '-', $filename);
    $filename = preg_replace('/[^a-z0-9\-]/', '', $filename);
    $filename = preg_replace('/-+/', '-', $filename);
    $filename = trim($filename, '-');
    $filename = $filename . '-logo';
    return $filename;
}

function handleLogoUpload($file, $uploadDir, $siteName) {
    $allowedTypes = ['image/webp', 'image/svg+xml'];
    $allowedExtensions = ['webp', 'svg'];
    
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'No file uploaded'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (strpos($file['name'], '.avif') !== false) {
        $mimeType = 'image/avif';
        $allowedTypes[] = 'image/avif';
        $allowedExtensions[] = 'avif';
    }
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['error' => 'Invalid file type. Only WEBP, SVG, AVIF allowed'];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        return ['error' => 'Invalid file extension'];
    }
    
    $sanitizedName = sanitizeSiteName($siteName);
    $filename = $sanitizedName . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    
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
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['error' => 'Failed to save file'];
        }
    }
    
    return ['success' => true, 'filename' => $filename];
}

function normalizeDomain($domain) {
    return str_replace('www.', '', strtolower(trim($domain)));
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $websiteIndex = null;
    foreach ($websites as $key => $site) {
        if ($site['id'] == $websiteId) {
            $websiteIndex = $key;
            break;
        }
    }
    
    if ($websiteIndex !== null) {
        $siteName = trim($_POST['site_name'] ?? '');
        $domain = trim($_POST['domain'] ?? '');
        $primaryColor = trim($_POST['primary_color'] ?? '#FFA500');
        $secondaryColor = trim($_POST['secondary_color'] ?? '#FF8C00');
        $language = trim($_POST['language'] ?? 'en');
        $status = $_POST['status'] ?? 'active';
        
        // Normalize domain
        $domain = normalizeDomain($domain);
        
        if ($siteName && $domain) {
            if (isset($_FILES['logo_file']) && $_FILES['logo_file']['size'] > 0) {
                $uploadResult = handleLogoUpload($_FILES['logo_file'], $uploadDir, $siteName);
                if (isset($uploadResult['success'])) {
                    if (!empty($website['logo'])) {
                        $oldLogoPath = $uploadDir . $website['logo'];
                        if (file_exists($oldLogoPath) && $oldLogoPath !== $uploadDir . $uploadResult['filename']) {
                            unlink($oldLogoPath);
                        }
                    }
                    $websites[$websiteIndex]['logo'] = $uploadResult['filename'];
                    $website['logo'] = $uploadResult['filename'];
                } else {
                    $error = $uploadResult['error'];
                }
            }
            
            if (!$error) {
                if ($siteName !== $website['site_name'] && !empty($website['logo'])) {
                    $oldLogoFile = $website['logo'];
                    $oldLogoPath = $uploadDir . $oldLogoFile;
                    
                    if (file_exists($oldLogoPath) && preg_match('/\.(webp|svg|avif)$/i', $oldLogoFile)) {
                        $extension = pathinfo($oldLogoFile, PATHINFO_EXTENSION);
                        $newLogoFilename = sanitizeSiteName($siteName) . '.' . $extension;
                        $newLogoPath = $uploadDir . $newLogoFilename;
                        
                        if (rename($oldLogoPath, $newLogoPath)) {
                            $websites[$websiteIndex]['logo'] = $newLogoFilename;
                            $website['logo'] = $newLogoFilename;
                        }
                    }
                }
                
                // NEW: Generate canonical URL
                $canonicalUrl = generateCanonicalUrl($domain);
                
                $websites[$websiteIndex]['domain'] = $domain;
                $websites[$websiteIndex]['canonical_url'] = $canonicalUrl;
                $websites[$websiteIndex]['site_name'] = $siteName;
                $websites[$websiteIndex]['primary_color'] = $primaryColor;
                $websites[$websiteIndex]['secondary_color'] = $secondaryColor;
                $websites[$websiteIndex]['language'] = $language;
                $websites[$websiteIndex]['status'] = $status;
                $previewDomain = $domain;
                
                $configData['websites'] = $websites;
                $jsonContent = json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                
                if (file_put_contents($configFile, $jsonContent)) {
                    $success = 'Website updated successfully!';
                    
                    $configContent = file_get_contents($configFile);
                    $configData = json_decode($configContent, true);
                    $websites = $configData['websites'] ?? [];
                    
                    foreach ($websites as $site) {
                        if ($site['id'] == $websiteId) {
                            $website = $site;
                            break;
                        }
                    }
                    
                    $previewDomain = $website['domain'];
                } else {
                    $error = 'Failed to save changes. Check file permissions: chmod 644 ' . $configFile;
                }
            }
        } else {
            $error = 'Please fill all required fields';
        }
    }
}

function getLogoPreviewData($logoFilename, $uploadDir) {
    if (empty($logoFilename) || !preg_match('/\.(webp|svg|avif)$/i', $logoFilename)) {
        return null;
    }
    
    $filepath = $uploadDir . $logoFilename;
    
    if (!file_exists($filepath)) {
        return null;
    }
    
    $extension = strtolower(pathinfo($logoFilename, PATHINFO_EXTENSION));
    $mimeTypes = [
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'avif' => 'image/avif'
    ];
    $mimeType = $mimeTypes[$extension] ?? 'image/png';
    
    $imageData = file_get_contents($filepath);
    $base64 = base64_encode($imageData);
    
    return "data:{$mimeType};base64,{$base64}";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Website - CMS</title>
    <link rel="stylesheet" href="cms-style.css">
    <link rel="stylesheet" href="css/website-edit.css">
    <style>
        .tooltip-info {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 5px;
            color: #666;
            font-size: 13px;
        }
        
        .tooltip-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            background: #3498db;
            color: white;
            border-radius: 50%;
            font-size: 12px;
            font-weight: bold;
            flex-shrink: 0;
        }
        
        .canonical-preview {
            font-family: monospace;
            color: #3498db;
            font-weight: 600;
        }
        
        .current-canonical {
            background: #e8f5e9;
            padding: 10px 15px;
            border-radius: 8px;
            margin-top: 10px;
            border-left: 4px solid #4caf50;
        }
        
        .current-canonical strong {
            color: #2e7d32;
        }
        
        .current-canonical code {
            background: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            color: #2e7d32;
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
                <h1>Edit Website: <?php echo htmlspecialchars($website['site_name']); ?></h1>
                <a href="dashboard.php" class="btn">← Back to Dashboard</a>
            </header>
            
            <div class="cms-content">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <div class="icon-info">
                    <h3>📝 Domain Name Rules</h3>
                    <p><strong>✅ Correct format:</strong> <code>sportlemons.info</code> or <code>example.com</code></p>
                    <p><strong>❌ Don't include:</strong> <code>www.</code> prefix - it will be removed automatically</p>
                    <p><strong>Current domain:</strong> <code><?php echo htmlspecialchars($website['domain']); ?></code></p>
                    
                    <!-- NEW: Show current canonical URL -->
                    <?php if (isset($website['canonical_url'])): ?>
                        <div class="current-canonical">
                            <strong>🔗 Current Canonical URL:</strong> <code><?php echo htmlspecialchars($website['canonical_url']); ?></code>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="icon-info" style="margin-top: 15px;">
                    <h3>🖼️ Logo File Naming</h3>
                    <p><strong>Your logo is saved as: </strong>
                        <?php if (!empty($website['logo']) && preg_match('/\.(webp|svg|avif)$/i', $website['logo'])): ?>
                            <code><?php echo htmlspecialchars($website['logo']); ?></code>
                        <?php else: ?>
                            <em>No logo file</em>
                        <?php endif; ?>
                    </p>
                    <p style="margin-top: 10px;">✨ <strong>If you change the site name</strong>, the logo file will be automatically renamed to match!</p>
                </div>
                
                <form method="POST" enctype="multipart/form-data" class="cms-form">
                    <div class="form-section">
                        <h3>Basic Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="site_name">Site Name *</label>
                                <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($website['site_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="domain">Domain *</label>
                                <input type="text" id="domain" name="domain" value="<?php echo htmlspecialchars($website['domain']); ?>" required placeholder="example.com">
                                <small>Without http:// or www. (www. will be removed automatically)</small>
                                
                                <!-- NEW: Canonical URL Tooltip -->
                                <div class="tooltip-info">
                                    <span class="tooltip-icon">i</span>
                                    <span>Canonical URL will be: <span class="canonical-preview" id="canonicalPreview"><?php echo isset($website['canonical_url']) ? htmlspecialchars($website['canonical_url']) : 'https://www.example.com'; ?></span></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Logo Image</label>
                                <div id="logoPreview" class="logo-preview <?php echo empty($website['logo']) || !preg_match('/\.(webp|svg|avif)$/i', $website['logo']) ? 'empty' : ''; ?>">
                                    <?php 
                                    $logoDataUrl = getLogoPreviewData($website['logo'], $uploadDir);
                                    if ($logoDataUrl): 
                                    ?>
                                        <img src="<?php echo $logoDataUrl; ?>" alt="Current Logo" id="currentLogoImg" onerror="this.parentElement.innerHTML='?'; this.parentElement.classList.add('empty');">
                                    <?php else: ?>
                                        ?
                                    <?php endif; ?>
                                </div>
                                <div class="file-upload-wrapper">
                                    <label for="logo_file" class="file-upload-label">
                                        <span>📤</span>
                                        <span><?php echo (!empty($website['logo']) && preg_match('/\.(webp|svg|avif)$/i', $website['logo'])) ? 'Change Logo' : 'Choose Logo'; ?></span>
                                    </label>
                                    <input type="file" id="logo_file" name="logo_file" class="file-upload-input" accept=".webp,.svg,.avif">
                                    <div class="file-name-display" id="logoFileName">
                                        <?php echo (!empty($website['logo']) && preg_match('/\.(webp|svg|avif)$/i', $website['logo'])) ? htmlspecialchars($website['logo']) : 'No file chosen'; ?>
                                    </div>
                                </div>
                                <small>WEBP, SVG, AVIF • Recommended: 64x64px • Leave empty to keep current logo</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="language">Language</label>
                                <select id="language" name="language">
                                    <option value="en" <?php echo $website['language'] === 'en' ? 'selected' : ''; ?>>English</option>
                                    <option value="es" <?php echo $website['language'] === 'es' ? 'selected' : ''; ?>>Spanish</option>
                                    <option value="fr" <?php echo $website['language'] === 'fr' ? 'selected' : ''; ?>>French</option>
                                    <option value="de" <?php echo $website['language'] === 'de' ? 'selected' : ''; ?>>German</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="active" <?php echo $website['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $website['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Theme Colors</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="primary_color">Primary Color</label>
                                <div class="color-input">
                                    <input type="color" id="primary_color" name="primary_color" value="<?php echo htmlspecialchars($website['primary_color']); ?>">
                                    <input type="text" value="<?php echo htmlspecialchars($website['primary_color']); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="secondary_color">Secondary Color</label>
                                <div class="color-input">
                                    <input type="color" id="secondary_color" name="secondary_color" value="<?php echo htmlspecialchars($website['secondary_color']); ?>">
                                    <input type="text" value="<?php echo htmlspecialchars($website['secondary_color']); ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="dashboard.php" class="btn btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script src="js/website-edit.js"></script>
    <script>
        // NEW: Real-time canonical URL preview
        document.getElementById('domain').addEventListener('input', function(e) {
            let domain = e.target.value.trim();
            
            // Remove www. prefix if present
            domain = domain.replace(/^www\./i, '');
            
            // Remove http:// or https:// if present
            domain = domain.replace(/^https?:\/\//i, '');
            
            // Generate canonical URL preview
            let canonical = 'https://www.' + (domain || 'example.com');
            
            document.getElementById('canonicalPreview').textContent = canonical;
        });
    </script>
</body>
</html>