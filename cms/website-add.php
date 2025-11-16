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

// Create upload directory if it doesn't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Function to handle logo upload - ONLY WEBP, SVG, AVIF
function handleLogoUpload($file, $uploadDir) {
    $allowedTypes = ['image/webp', 'image/svg+xml'];
    $allowedExtensions = ['webp', 'svg'];
    
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'No file uploaded'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    // Check AVIF
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
    
    $filename = uniqid('logo_', true) . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // For raster images (WEBP, AVIF), resize to 64x64
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
        // For SVG, just copy
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['error' => 'Failed to save file'];
        }
    }
    
    return ['success' => true, 'filename' => $filename];
}

// Function to get sports from existing websites or create default list
function getSportsListForNewWebsite($websites) {
    // If there are existing websites, copy sports from the first one
    if (!empty($websites)) {
        $firstWebsite = $websites[0];
        return $firstWebsite['sports_categories'] ?? [];
    }
    
    // If no websites exist, return default comprehensive list
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
    
    $logo = ''; // Will be set after upload
    
    if ($siteName && $domain) {
        // Check if domain already exists
        $domainExists = false;
        foreach ($websites as $website) {
            if ($website['domain'] === $domain) {
                $domainExists = true;
                break;
            }
        }
        
        if ($domainExists) {
            $error = 'Domain already exists!';
        } else {
            // Handle logo upload
            if (isset($_FILES['logo_file']) && $_FILES['logo_file']['size'] > 0) {
                $uploadResult = handleLogoUpload($_FILES['logo_file'], $uploadDir);
                if (isset($uploadResult['success'])) {
                    $logo = $uploadResult['filename'];
                } else {
                    $error = $uploadResult['error'];
                }
            }
            
            if (!$error) {
                // Generate new ID
                $maxId = 0;
                foreach ($websites as $website) {
                    if ($website['id'] > $maxId) {
                        $maxId = $website['id'];
                    }
                }
                $newId = $maxId + 1;
                
                // Get sports list from existing websites or default list
                $sportsList = getSportsListForNewWebsite($websites);
                
                // Add new website
                $newWebsite = [
                    'id' => $newId,
                    'domain' => $domain,
                    'site_name' => $siteName,
                    'logo' => $logo,
                    'primary_color' => $primaryColor,
                    'secondary_color' => $secondaryColor,
                    'seo_title' => '', // Empty
                    'seo_description' => '', // Empty
                    'language' => $language,
                    'sidebar_content' => '', // Empty
                    'status' => $status,
                    'sports_categories' => $sportsList,
                    'sports_icons' => []
                ];
                
                $websites[] = $newWebsite;
                $configData['websites'] = $websites;
                
                // Save to JSON with pretty print
                $jsonContent = json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                
                if (file_put_contents($configFile, $jsonContent)) {
                    $sportsCount = count($sportsList);
                    $success = "Website added successfully with {$sportsCount} sport categories!";
                    // Clear form
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

// Get current sports count for display
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
    <style>
        .logo-preview {
            width: 64px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            margin: 10px 0;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        .logo-preview img {
            max-width: 48px;
            max-height: 48px;
            object-fit: contain;
        }
        .logo-preview.empty {
            color: white;
            font-size: 32px;
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
                
                <form method="POST" enctype="multipart/form-data" class="cms-form">
                    <!-- Basic Info -->
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
                                <small>Without http:// or www.</small>
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
                    
                    <!-- Theme Colors -->
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
                    
                    <!-- What Happens Next -->
                    <div class="form-section" style="background: #e3f2fd; border-left: 4px solid #2196f3;">
                        <h3 style="color: #1565c0;">ℹ️ What happens after you create the website?</h3>
                        <ul style="margin-left: 20px; color: #424242;">
                            <?php if (!empty($existingWebsites)): ?>
                                <li style="margin-bottom: 10px;">✅ Website will be created with <strong><?php echo $currentSportsCount; ?> sport categories</strong> (copied from your first website)</li>
                            <?php else: ?>
                                <li style="margin-bottom: 10px;">✅ Website will be created with <strong><?php echo $currentSportsCount; ?> default sport categories</strong></li>
                            <?php endif; ?>
                            <li style="margin-bottom: 10px;">✅ SEO settings will be empty (you can configure later)</li>
                            <li style="margin-bottom: 10px;">✅ Sidebar content will be empty (you can configure later)</li>
                            <li style="margin-bottom: 10px;">📝 You can customize everything from the dashboard:
                                <ul style="margin-left: 20px; margin-top: 5px;">
                                    <li>Manage sports categories and icons</li>
                                    <li>Configure SEO for each page</li>
                                    <li>Edit website settings</li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Create Website</button>
                        <a href="dashboard.php" class="btn btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script>
        // Update color text inputs when color picker changes
        document.getElementById('primary_color').addEventListener('input', function(e) {
            e.target.nextElementSibling.value = e.target.value;
        });
        
        document.getElementById('secondary_color').addEventListener('input', function(e) {
            e.target.nextElementSibling.value = e.target.value;
        });
        
        // Logo preview
        document.getElementById('logo_file').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || 'No file chosen';
            document.getElementById('logoFileName').textContent = fileName;
            
            if (e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const preview = document.getElementById('logoPreview');
                    preview.innerHTML = '<img src="' + event.target.result + '" alt="Logo Preview">';
                    preview.classList.remove('empty');
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });
    </script>
</body>
</html>