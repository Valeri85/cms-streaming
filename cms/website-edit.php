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
$faviconDir = '/var/www/u1852176/data/www/streaming/images/favicons/';

if (!file_exists($configFile)) {
    die("Configuration file not found at: " . $configFile);
}

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!file_exists($faviconDir)) {
    mkdir($faviconDir, 0755, true);
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

// ==========================================
// FAVICON GENERATION FUNCTION
// ==========================================
function generateFavicons($file, $faviconDir, $websiteId) {
    $allowedTypes = ['image/png', 'image/jpeg', 'image/webp'];
    $allowedExtensions = ['png', 'jpg', 'jpeg', 'webp'];
    
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'No file uploaded'];
    }
    
    // Check GD library
    if (!extension_loaded('gd')) {
        return ['error' => 'GD extension not available. Cannot generate favicons.'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['error' => 'Invalid file type. Only PNG, JPG, WEBP allowed for favicon'];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        return ['error' => 'Invalid file extension'];
    }
    
    // Create website-specific favicon directory
    $websiteFaviconDir = $faviconDir . $websiteId . '/';
    if (!file_exists($websiteFaviconDir)) {
        mkdir($websiteFaviconDir, 0755, true);
    }
    
    // Load source image
    switch ($mimeType) {
        case 'image/png':
            $sourceImage = @imagecreatefrompng($file['tmp_name']);
            break;
        case 'image/jpeg':
            $sourceImage = @imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/webp':
            $sourceImage = @imagecreatefromwebp($file['tmp_name']);
            break;
        default:
            return ['error' => 'Unsupported image format'];
    }
    
    if (!$sourceImage) {
        return ['error' => 'Failed to load image'];
    }
    
    $sourceWidth = imagesx($sourceImage);
    $sourceHeight = imagesy($sourceImage);
    
    // Check minimum size
    if ($sourceWidth < 512 || $sourceHeight < 512) {
        imagedestroy($sourceImage);
        return ['error' => 'Image must be at least 512x512 pixels. Uploaded: ' . $sourceWidth . 'x' . $sourceHeight];
    }
    
    // Sizes to generate
    $sizes = [
        16 => 'favicon-16x16.png',
        32 => 'favicon-32x32.png',
        180 => 'apple-touch-icon.png',
        192 => 'android-chrome-192x192.png',
        512 => 'android-chrome-512x512.png'
    ];
    
    $generatedFiles = [];
    
    foreach ($sizes as $size => $filename) {
        $targetImage = imagecreatetruecolor($size, $size);
        
        // Preserve transparency
        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);
        $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
        imagefill($targetImage, 0, 0, $transparent);
        
        // Resize
        imagecopyresampled(
            $targetImage, $sourceImage,
            0, 0, 0, 0,
            $size, $size,
            $sourceWidth, $sourceHeight
        );
        
        $filepath = $websiteFaviconDir . $filename;
        
        // Save as PNG
        if (imagepng($targetImage, $filepath, 9)) {
            $generatedFiles[] = $filename;
        }
        
        imagedestroy($targetImage);
    }
    
    // Save original as favicon.png (512x512)
    $originalPath = $websiteFaviconDir . 'favicon-original.png';
    imagepng($sourceImage, $originalPath, 9);
    
    imagedestroy($sourceImage);
    
    if (count($generatedFiles) === count($sizes)) {
        return ['success' => true, 'files' => $generatedFiles, 'folder' => $websiteId];
    } else {
        return ['error' => 'Some favicon sizes failed to generate'];
    }
}

function normalizeDomain($domain) {
    return str_replace('www.', '', strtolower(trim($domain)));
}

// Load available languages
function getAvailableLanguages() {
    $langDir = '/var/www/u1852176/data/www/streaming/config/lang/';
    $languages = [];
    
    if (is_dir($langDir)) {
        $files = glob($langDir . '*.json');
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            
            if ($data && isset($data['language_info']) && ($data['language_info']['active'] ?? false)) {
                $code = $data['language_info']['code'];
                $languages[$code] = [
                    'name' => $data['language_info']['name'] ?? $code,
                    'flag' => $data['language_info']['flag'] ?? '🏳️'
                ];
            }
        }
    }
    
    // Fallback if no languages found
    if (empty($languages)) {
        $languages = [
            'en' => ['name' => 'English', 'flag' => '🇬🇧'],
            'es' => ['name' => 'Spanish', 'flag' => '🇪🇸'],
            'fr' => ['name' => 'French', 'flag' => '🇫🇷'],
            'de' => ['name' => 'German', 'flag' => '🇩🇪']
        ];
    }
    
    return $languages;
}

// Get favicon preview data
function getFaviconPreviewData($websiteId, $faviconDir) {
    $faviconPath = $faviconDir . $websiteId . '/favicon-32x32.png';
    
    if (!file_exists($faviconPath)) {
        return null;
    }
    
    $imageData = file_get_contents($faviconPath);
    $base64 = base64_encode($imageData);
    
    return "data:image/png;base64,{$base64}";
}

$availableLanguages = getAvailableLanguages();

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
        
        // NEW: Get analytics and custom head code
        $googleAnalyticsId = trim($_POST['google_analytics_id'] ?? '');
        $customHeadCode = $_POST['custom_head_code'] ?? '';
        
        // Normalize domain
        $domain = normalizeDomain($domain);
        
        if ($siteName && $domain) {
            // Handle logo upload
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
            
            // Handle favicon upload
            if (isset($_FILES['favicon_file']) && $_FILES['favicon_file']['size'] > 0) {
                $faviconResult = generateFavicons($_FILES['favicon_file'], $faviconDir, $websiteId);
                if (isset($faviconResult['success'])) {
                    $websites[$websiteIndex]['favicon'] = $faviconResult['folder'];
                    $website['favicon'] = $faviconResult['folder'];
                    $success = 'Favicon generated successfully! ';
                } else {
                    $error = 'Favicon: ' . $faviconResult['error'];
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
                
                // Generate canonical URL
                $canonicalUrl = generateCanonicalUrl($domain);
                
                $websites[$websiteIndex]['domain'] = $domain;
                $websites[$websiteIndex]['canonical_url'] = $canonicalUrl;
                $websites[$websiteIndex]['site_name'] = $siteName;
                $websites[$websiteIndex]['primary_color'] = $primaryColor;
                $websites[$websiteIndex]['secondary_color'] = $secondaryColor;
                $websites[$websiteIndex]['language'] = $language;
                $websites[$websiteIndex]['status'] = $status;
                
                // NEW: Save analytics and custom head code
                $websites[$websiteIndex]['google_analytics_id'] = $googleAnalyticsId;
                $websites[$websiteIndex]['custom_head_code'] = $customHeadCode;
                
                $previewDomain = $domain;
                
                $configData['websites'] = $websites;
                $jsonContent = json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                
                if (file_put_contents($configFile, $jsonContent)) {
                    $success .= 'Website updated successfully!';
                    
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

// Check if favicon exists
$hasFavicon = !empty($website['favicon']) && file_exists($faviconDir . $website['favicon'] . '/favicon-32x32.png');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Website - CMS</title>
    <link rel="stylesheet" href="cms-style.css">
    <link rel="stylesheet" href="css/website-edit.css">
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
                <a href="icons.php" class="nav-item">
                    <span>🖼️</span> Sport Icons
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
                                <small>WEBP, SVG, AVIF • Recommended: 64x64px</small>
                            </div>
                            
                            <!-- NEW: Favicon Upload -->
                            <div class="form-group">
                                <label>Favicon</label>
                                <div id="faviconPreview" class="favicon-preview <?php echo $hasFavicon ? '' : 'empty'; ?>">
                                    <?php 
                                    $faviconDataUrl = getFaviconPreviewData($website['favicon'] ?? '', $faviconDir);
                                    if ($faviconDataUrl): 
                                    ?>
                                        <img src="<?php echo $faviconDataUrl; ?>" alt="Current Favicon" id="currentFaviconImg">
                                    <?php else: ?>
                                        ?
                                    <?php endif; ?>
                                </div>
                                <div class="file-upload-wrapper">
                                    <label for="favicon_file" class="file-upload-label favicon-upload-label">
                                        <span>🖼️</span>
                                        <span><?php echo $hasFavicon ? 'Change Favicon' : 'Choose Favicon'; ?></span>
                                    </label>
                                    <input type="file" id="favicon_file" name="favicon_file" class="file-upload-input" accept=".png,.jpg,.jpeg,.webp">
                                    <div class="file-name-display" id="faviconFileName">
                                        <?php echo $hasFavicon ? 'Favicon uploaded' : 'No file chosen'; ?>
                                    </div>
                                </div>
                                <small>PNG, JPG, WEBP • <strong>Minimum: 512x512px</strong><br>Auto-generates all sizes (16, 32, 180, 192, 512)</small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="language">Language</label>
                                <select id="language" name="language">
                                    <?php foreach ($availableLanguages as $code => $lang): ?>
                                        <option value="<?php echo htmlspecialchars($code); ?>" <?php echo ($website['language'] === $code) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($lang['flag'] . ' ' . $lang['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small><a href="languages.php">Manage languages</a></small>
                            </div>
                            
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <option value="active" <?php echo $website['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $website['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
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
                    
                    <!-- NEW SECTION: Analytics & Tracking -->
                    <div class="form-section analytics-section">
                        <h3>📊 Analytics & Tracking</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="google_analytics_id">Google Analytics Measurement ID</label>
                                <div class="input-with-icon">
                                    <input type="text" id="google_analytics_id" name="google_analytics_id" 
                                           value="<?php echo htmlspecialchars($website['google_analytics_id'] ?? ''); ?>" 
                                           placeholder="G-XXXXXXXXXX">
                                    <?php if (!empty($website['google_analytics_id'])): ?>
                                        <a href="https://analytics.google.com/analytics/web/" target="_blank" class="input-icon-link" title="Open Google Analytics">
                                            📊
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <small>Find your ID in Google Analytics → Admin → Data Streams → Your Stream</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Quick Links</label>
                                <div class="quick-links">
                                    <a href="https://search.google.com/search-console?resource_id=sc-domain%3A<?php echo urlencode($website['domain']); ?>" 
                                       target="_blank" class="quick-link-btn">
                                        🔍 Search Console
                                    </a>
                                    <a href="https://analytics.google.com/analytics/web/" 
                                       target="_blank" class="quick-link-btn">
                                        📊 Analytics
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- NEW SECTION: Custom Head Code -->
                    <div class="form-section custom-code-section">
                        <h3>🔧 Custom Head Code</h3>
                        <p class="section-description">Add custom code to the &lt;head&gt; section. Use for ads (AdSense), tracking pixels, custom meta tags, etc.</p>
                        
                        <div class="form-group">
                            <label for="custom_head_code">Custom &lt;head&gt; Code</label>
                            <textarea id="custom_head_code" name="custom_head_code" rows="8" 
                                      placeholder="<!-- Paste your AdSense, tracking pixels, or other head code here -->"><?php echo htmlspecialchars($website['custom_head_code'] ?? ''); ?></textarea>
                            <small class="warning-text">⚠️ Be careful! Invalid code can break your website. Test after saving.</small>
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
        // Favicon file preview
        document.getElementById('favicon_file')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('faviconPreview');
            const fileName = document.getElementById('faviconFileName');
            
            if (file) {
                fileName.textContent = file.name;
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Favicon Preview">';
                    preview.classList.remove('empty');
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>