<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

$configFile = '/var/www/u1852176/data/www/streaming/config/websites.json';
$masterSportsFile = '/var/www/u1852176/data/www/streaming/config/master-sports.json';
$uploadDir = '/var/www/u1852176/data/www/streaming/images/logos/';

if (!file_exists($configFile)) {
    die("Configuration file not found at: " . $configFile);
}

if (!file_exists($masterSportsFile)) {
    die("Master sports file not found at: " . $masterSportsFile);
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
                    return ['error' => 'AVIF format not supported on this server'];
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

function getSportsListForNewWebsite($websites) {
    if (!empty($websites)) {
        $firstWebsite = $websites[0];
        return $firstWebsite['sports_categories'] ?? [];
    }
    
    return [
        'Football', 'Basketball', 'Tennis', 'Ice Hockey', 'Baseball', 'Rugby', 'Cricket', 
        'American Football', 'Volleyball', 'Beach Volleyball', 'Handball', 'Beach Handball', 
        'Beach Soccer', 'Aussie Rules', 'Futsal', 'Badminton', 'Netball', 'Floorball', 
        'Combat', 'Boxing', 'MMA', 'Snooker', 'Billiard', 'Table Tennis', 'Padel Tennis', 
        'Squash', 'Motorsport', 'Racing', 'Cycling', 'Equestrianism', 'Golf', 'Field Hockey', 
        'Lacrosse', 'Athletics', 'Gymnastics', 'Weightlifting', 'Climbing', 'Winter Sports', 
        'Bandy', 'Curling', 'Water Sports', 'Water Polo', 'Sailing', 'Bowling', 'Darts', 
        'Chess', 'E-sports', 'Others'
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $configContent = file_get_contents($configFile);
    $configData = json_decode($configContent, true);
    $websites = $configData['websites'] ?? [];
    
    $siteName = trim($_POST['site_name'] ?? '');
    $domain = trim($_POST['domain'] ?? '');
    $primaryColor = trim($_POST['primary_color'] ?? '#FFA500');
    $secondaryColor = trim($_POST['secondary_color'] ?? '#FF8C00');
    $language = trim($_POST['language'] ?? 'en');
    $status = $_POST['status'] ?? 'active';
    
    $logo = '';
    
    if ($siteName && $domain) {
        // Normalize domain (remove www. prefix)
        $normalizedDomain = str_replace('www.', '', strtolower(trim($domain)));
        
        $domainExists = false;
        foreach ($websites as $website) {
            if ($website['domain'] === $normalizedDomain) {
                $domainExists = true;
                break;
            }
        }
        
        if ($domainExists) {
            $error = 'Domain already exists!';
        } else {
            if (isset($_FILES['logo_file']) && $_FILES['logo_file']['size'] > 0) {
                $uploadResult = handleLogoUpload($_FILES['logo_file'], $uploadDir, $siteName);
                if (isset($uploadResult['success'])) {
                    $logo = $uploadResult['filename'];
                } else {
                    $error = $uploadResult['error'];
                }
            }
            
            if (!$error) {
                $maxId = 0;
                foreach ($websites as $website) {
                    if ($website['id'] > $maxId) {
                        $maxId = $website['id'];
                    }
                }
                $newId = $maxId + 1;
                
                $sportsList = getSportsListForNewWebsite($websites);
                
                // NEW: Generate canonical URL
                $canonicalUrl = generateCanonicalUrl($normalizedDomain);
                
                $newWebsite = [
                    'id' => $newId,
                    'domain' => $normalizedDomain,
                    'canonical_url' => $canonicalUrl,
                    'site_name' => $siteName,
                    'logo' => $logo,
                    'primary_color' => $primaryColor,
                    'secondary_color' => $secondaryColor,
                    'seo_title' => '',
                    'seo_description' => '',
                    'language' => $language,
                    'sidebar_content' => '',
                    'status' => $status,
                    'sports_categories' => $sportsList,
                    'sports_icons' => []
                ];
                
                $websites[] = $newWebsite;
                $configData['websites'] = $websites;
                
                $jsonContent = json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                
                if (file_put_contents($configFile, $jsonContent)) {
                    $sportsCount = count($sportsList);
                    $success = "Website added successfully with {$sportsCount} sport categories!";
                    $_POST = [];
                } else {
                    $error = 'Failed to save. Check file permissions: chmod 644 ' . $configFile;
                }
            }
        }
    } else {
        $error = 'Please fill all required fields';
    }
}

$configContent = file_get_contents($configFile);
$configData = json_decode($configContent, true);
$existingWebsites = $configData['websites'] ?? [];
$currentSportsCount = count(getSportsListForNewWebsite($existingWebsites));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Website - CMS</title>
    <link rel="stylesheet" href="cms-style.css">
    <link rel="stylesheet" href="css/website-add.css">
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
                <a href="website-add.php" class="nav-item active">
                    <span>➕</span> Add Website
                </a>
            </nav>
            
            <div class="cms-user">
                <a href="logout.php" class="btn btn-sm btn-outline">Logout</a>
            </div>
        </aside>
        
        <main class="cms-main">
            <header class="cms-header">
                <h1>Add New Website</h1>
                <a href="dashboard.php" class="btn">← Back to Dashboard</a>
            </header>
            
            <div class="cms-content">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                        <br><a href="dashboard.php">Go to Dashboard</a>
                    </div>
                <?php endif; ?>
                
                <div class="icon-info">
                    <h3>📝 Logo File Naming</h3>
                    <p><strong>Logos are now saved with meaningful names based on site name:</strong></p>
                    <ul style="margin: 10px 0 0 20px;">
                        <li>"SportLemons" → <code>sportlemons-logo.webp</code></li>
                        <li>"Watch Live Sport" → <code>watch-live-sport-logo.webp</code></li>
                        <li>"My Sports TV" → <code>my-sports-tv-logo.svg</code></li>
                    </ul>
                </div>
                
                <form method="POST" enctype="multipart/form-data" class="cms-form">
                    <div class="form-section">
                        <h3>Basic Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="site_name">Site Name *</label>
                                <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($_POST['site_name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="domain">Domain *</label>
                                <input type="text" id="domain" name="domain" value="<?php echo htmlspecialchars($_POST['domain'] ?? ''); ?>" required placeholder="example.com">
                                <small>Without http:// or www. (www. will be removed automatically)</small>
                                
                                <!-- NEW: Canonical URL Tooltip -->
                                <div class="tooltip-info">
                                    <span class="tooltip-icon">i</span>
                                    <span>Canonical URL will be: <span class="canonical-preview" id="canonicalPreview">https://www.example.com</span></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Logo Image</label>
                                <div id="logoPreview" class="logo-preview empty">?</div>
                                <div class="file-upload-wrapper">
                                    <label for="logo_file" class="file-upload-label">
                                        <span>📤</span>
                                        <span>Choose Logo</span>
                                    </label>
                                    <input type="file" id="logo_file" name="logo_file" class="file-upload-input" accept=".webp,.svg,.avif">
                                    <div class="file-name-display" id="logoFileName">No file chosen</div>
                                </div>
                                <small>WEBP, SVG, AVIF • Recommended: 64x64px</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="language">Language</label>
                                <select id="language" name="language">
                                    <option value="en" selected>English</option>
                                    <option value="es">Spanish</option>
                                    <option value="fr">French</option>
                                    <option value="de">German</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Theme Colors</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="primary_color">Primary Color</label>
                                <div class="color-input">
                                    <input type="color" id="primary_color" name="primary_color" value="#FFA500">
                                    <input type="text" value="#FFA500" readonly>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="secondary_color">Secondary Color</label>
                                <div class="color-input">
                                    <input type="color" id="secondary_color" name="secondary_color" value="#FF8C00">
                                    <input type="text" value="#FF8C00" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Create Website</button>
                        <a href="dashboard.php" class="btn btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script src="js/website-add.js"></script>
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