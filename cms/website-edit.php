<?php
/**
 * Edit Website Settings
 * 
 * REFACTORED: Uses centralized config and functions
 */

session_start();

// ==========================================
// LOAD CENTRALIZED CONFIG AND FUNCTIONS
// ==========================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Check login (support both old and new session)
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get current user for owner display
$currentUser = getCurrentUser();

$websiteId = $_GET['id'] ?? null;
$error = '';
$success = '';

if (!$websiteId) {
    header('Location: dashboard.php');
    exit;
}

// Use constants from config.php (directories auto-created by config.php)
if (!file_exists(WEBSITES_CONFIG_FILE)) {
    die("Configuration file not found at: " . WEBSITES_CONFIG_FILE);
}

$availableLanguages = getAvailableLanguages();

$configContent = file_get_contents(WEBSITES_CONFIG_FILE);
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

// Get enabled languages for this website (default to all active if not set)
$enabledLanguages = $website['enabled_languages'] ?? array_keys($availableLanguages);

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
        
        // Get analytics and custom head code
        $googleAnalyticsId = trim($_POST['google_analytics_id'] ?? '');
        $analyticsUrl = trim($_POST['analytics_url'] ?? '');
        $customHeadCode = $_POST['custom_head_code'] ?? '';
        
        // Get owner selection (NEW)
        $ownerType = $_POST['owner_type'] ?? 'mine';
        if ($ownerType === 'shared') {
            $owner = 'shared';
        } else {
            $owner = getCurrentUserOwner() ?? 'shared';
        }
        
        // Get enabled languages (checkboxes)
        $postedEnabledLanguages = $_POST['enabled_languages'] ?? [];
        
        // Ensure default language is always enabled
        if (!in_array($language, $postedEnabledLanguages)) {
            $postedEnabledLanguages[] = $language;
        }
        
        // Ensure English is always in the list (as base language)
        if (!in_array('en', $postedEnabledLanguages)) {
            array_unshift($postedEnabledLanguages, 'en');
        }
        
        // Normalize domain (function from functions.php)
        $domain = normalizeDomain($domain);
        
        if ($siteName && $domain) {
            // Handle logo upload (function from functions.php, constant from config.php)
            if (isset($_FILES['logo_file']) && $_FILES['logo_file']['size'] > 0) {
                $uploadResult = handleLogoUpload($_FILES['logo_file'], LOGOS_DIR, $siteName);
                if (isset($uploadResult['success'])) {
                    if (!empty($website['logo'])) {
                        $oldLogoPath = LOGOS_DIR . $website['logo'];
                        if (file_exists($oldLogoPath) && $oldLogoPath !== LOGOS_DIR . $uploadResult['filename']) {
                            unlink($oldLogoPath);
                        }
                    }
                    $websites[$websiteIndex]['logo'] = $uploadResult['filename'];
                    $website['logo'] = $uploadResult['filename'];
                } else {
                    $error = $uploadResult['error'];
                }
            }
            
            // Handle favicon upload (function from functions.php, constant from config.php)
            if (isset($_FILES['favicon_file']) && $_FILES['favicon_file']['size'] > 0) {
                $faviconResult = generateFavicons($_FILES['favicon_file'], FAVICONS_DIR, $websiteId);
                if (isset($faviconResult['success'])) {
                    $websites[$websiteIndex]['favicon'] = $faviconResult['folder'];
                    $website['favicon'] = $faviconResult['folder'];
                    $success = 'Favicon generated successfully! ';
                } else {
                    $error = 'Favicon: ' . $faviconResult['error'];
                }
            }
            
            if (!$error) {
                // Rename logo file if site name changed
                if ($siteName !== $website['site_name'] && !empty($website['logo'])) {
                    $oldLogoFile = $website['logo'];
                    $oldLogoPath = LOGOS_DIR . $oldLogoFile;
                    
                    if (file_exists($oldLogoPath) && preg_match('/\.(webp|svg|avif)$/i', $oldLogoFile)) {
                        $extension = pathinfo($oldLogoFile, PATHINFO_EXTENSION);
                        $newLogoFilename = sanitizeSiteName($siteName) . '.' . $extension;
                        $newLogoPath = LOGOS_DIR . $newLogoFilename;
                        
                        if (rename($oldLogoPath, $newLogoPath)) {
                            $websites[$websiteIndex]['logo'] = $newLogoFilename;
                            $website['logo'] = $newLogoFilename;
                        }
                    }
                }
                
                // Generate canonical URL (function from functions.php)
                $canonicalUrl = generateCanonicalUrl($domain);
                
                $websites[$websiteIndex]['domain'] = $domain;
                $websites[$websiteIndex]['canonical_url'] = $canonicalUrl;
                $websites[$websiteIndex]['site_name'] = $siteName;
                $websites[$websiteIndex]['primary_color'] = $primaryColor;
                $websites[$websiteIndex]['secondary_color'] = $secondaryColor;
                $websites[$websiteIndex]['language'] = $language;
                $websites[$websiteIndex]['status'] = $status;
                
                // Save analytics and custom head code
                $websites[$websiteIndex]['google_analytics_id'] = $googleAnalyticsId;
                $websites[$websiteIndex]['analytics_url'] = $analyticsUrl;
                $websites[$websiteIndex]['custom_head_code'] = $customHeadCode;
                
                // Save owner
                $websites[$websiteIndex]['owner'] = $owner;
                
                // Save enabled languages
                $websites[$websiteIndex]['enabled_languages'] = array_values($postedEnabledLanguages);
                
                $previewDomain = $domain;
                
                $configData['websites'] = $websites;
                $jsonContent = json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                
                if (file_put_contents(WEBSITES_CONFIG_FILE, $jsonContent)) {
                    $success .= 'Website updated successfully!';
                    
                    $configContent = file_get_contents(WEBSITES_CONFIG_FILE);
                    $configData = json_decode($configContent, true);
                    $websites = $configData['websites'] ?? [];
                    
                    foreach ($websites as $site) {
                        if ($site['id'] == $websiteId) {
                            $website = $site;
                            break;
                        }
                    }
                    
                    // Update enabled languages after reload
                    $enabledLanguages = $website['enabled_languages'] ?? array_keys($availableLanguages);
                    
                    $previewDomain = $website['domain'];
                } else {
                    $error = 'Failed to save changes. Check file permissions: chmod 644 ' . WEBSITES_CONFIG_FILE;
                }
            }
        } else {
            $error = 'Please fill all required fields';
        }
    }
}

// Check if favicon exists - use constant from config.php
$hasFavicon = !empty($website['favicon']) && file_exists(FAVICONS_DIR . $website['favicon'] . '/favicon-32x32.png');

// Get current owner info
$currentOwner = $website['owner'] ?? 'shared';
$isShared = ($currentOwner === 'shared');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Website - <?php echo htmlspecialchars($website['site_name']); ?></title>
    <link rel="stylesheet" href="cms-style.css">
    <link rel="stylesheet" href="css/website-edit.css">
    <style>
        /* Enabled Languages Section Styles */
        .languages-grid-checkboxes {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 15px;
        }
        
        .language-checkbox-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .language-checkbox-item:hover {
            border-color: #3498db;
            background: #e3f2fd;
        }
        
        .language-checkbox-item.checked {
            background: #e8f5e9;
            border-color: #4caf50;
        }
        
        .language-checkbox-item.disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .language-checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .language-checkbox-item .lang-flag {
            font-size: 24px;
        }
        
        .language-checkbox-item .lang-info {
            flex: 1;
        }
        
        .language-checkbox-item .lang-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .language-checkbox-item .lang-code {
            font-size: 12px;
            color: #7f8c8d;
            font-family: monospace;
        }
        
        .language-checkbox-item .lang-badge {
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 600;
        }
        
        .lang-badge.default {
            background: #3498db;
            color: white;
        }
        
        .lang-badge.master {
            background: #9b59b6;
            color: white;
        }
        
        .enabled-languages-info {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .enabled-languages-info .info-icon {
            font-size: 20px;
        }
        
        .enabled-languages-info .info-text {
            font-size: 14px;
            color: #1565c0;
        }
        
        /* Confirmation Modal Styles */
        .language-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .language-modal-overlay.active {
            display: flex;
        }
        
        .language-modal {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        
        .language-modal-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .language-modal-title {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .language-modal-message {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 25px;
            line-height: 1.5;
        }
        
        .language-modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        
        .language-modal-actions .btn {
            min-width: 120px;
            padding: 12px 20px;
        }
        
        .btn-confirm-enable {
            background: #4caf50;
            color: white;
        }
        
        .btn-confirm-enable:hover {
            background: #43a047;
        }
        
        .btn-confirm-disable {
            background: #f44336;
            color: white;
        }
        
        .btn-confirm-disable:hover {
            background: #e53935;
        }
        
        .btn-cancel-modal {
            background: #e9ecef;
            color: #495057;
        }
        
        .btn-cancel-modal:hover {
            background: #dee2e6;
        }
        
        /* Owner selection styles */
        .owner-selection {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }
        .owner-option {
            flex: 1;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        .owner-option:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .owner-option.selected {
            border-color: #667eea;
            background: #f0f4ff;
        }
        .owner-option input[type="radio"] {
            display: none;
        }
        .owner-option .icon {
            font-size: 24px;
            margin-bottom: 8px;
        }
        .owner-option .label {
            font-weight: 600;
            color: #333;
        }
        .owner-option .desc {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        
        /* Analytics Section Styles */
        .analytics-section {
            background: linear-gradient(135deg, #fff9e6 0%, #fff 100%);
            border: 1px solid #ffe0b2;
        }
        
        .analytics-section h3 {
            color: #e65100;
        }
        
        .analytics-section .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .analytics-section .input-with-icon {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .analytics-section .input-with-icon input {
            flex: 1;
        }
        
        .analytics-section .input-icon-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #f9ab00, #e37400);
            border-radius: 8px;
            text-decoration: none;
            font-size: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .analytics-section .input-icon-link:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(249, 171, 0, 0.4);
        }
        
        .analytics-section .quick-links {
            display: flex;
            gap: 12px;
            margin-top: 8px;
        }
        
        .analytics-section .quick-link-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: linear-gradient(135deg, #4285f4, #34a853);
            color: white;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .analytics-section .quick-link-btn:nth-child(2) {
            background: linear-gradient(135deg, #f9ab00, #e37400);
        }
        
        .analytics-section .quick-link-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        /* Custom Head Code Section Styles */
        .custom-code-section {
            background: linear-gradient(135deg, #fce4ec 0%, #fff 100%);
            border: 1px solid #f8bbd9;
            margin-top: 20px;
        }
        
        .custom-code-section h3 {
            color: #c2185b;
        }
        
        .custom-code-section .section-description {
            color: #666;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .custom-code-section textarea {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace;
            font-size: 13px;
            background: #1e1e1e;
            color: #d4d4d4;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 15px;
            line-height: 1.5;
            resize: vertical;
            min-height: 150px;
        }
        
        .custom-code-section textarea::placeholder {
            color: #6a6a6a;
        }
        
        .custom-code-section textarea:focus {
            outline: none;
            border-color: #c2185b;
            box-shadow: 0 0 0 3px rgba(194, 24, 91, 0.1);
        }
        
        .custom-code-section .warning-text {
            display: block;
            margin-top: 10px;
            padding: 10px 15px;
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            border-radius: 0 6px 6px 0;
            color: #e65100;
            font-size: 13px;
        }
        
        @media (max-width: 768px) {
            .analytics-section .form-row {
                grid-template-columns: 1fr;
            }
            .analytics-section .quick-links {
                flex-direction: column;
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
                <a href="languages.php" class="nav-item">
                    <span>🌐</span> Languages
                </a>
                <a href="icons.php" class="nav-item">
                    <span>🖼️</span> Icons
                </a>
                <a href="users.php" class="nav-item">
                    <span>👥</span> Users
                </a>
            </nav>
            
            <div class="cms-user">
                <?php if ($currentUser): ?>
                    <p style="margin-bottom: 8px;"><strong><?php echo htmlspecialchars($currentUser['username']); ?></strong></p>
                    <a href="profile.php" class="btn btn-sm btn-outline" style="margin-bottom: 5px; display: block;">My Profile</a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-sm btn-outline">Logout</a>
            </div>
        </aside>
        
        <main class="cms-main">
            <header class="cms-header">
                <h1>Edit: <?php echo htmlspecialchars($website['site_name']); ?></h1>
                <div class="header-actions">
                    <a href="https://www.<?php echo htmlspecialchars($previewDomain); ?>" target="_blank" class="btn btn-sm">🔗 Preview Site</a>
                    <a href="website-pages.php?id=<?php echo $websiteId; ?>" class="btn btn-sm">📄 SEO Pages</a>
                    <a href="dashboard.php" class="btn">← Back</a>
                </div>
            </header>
            
            <div class="cms-content">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data" class="cms-form" id="websiteEditForm">
                    <!-- Owner Selection Section -->
                    <div class="form-section">
                        <h3>👤 Website Owner</h3>
                        <p style="color: #666; margin-bottom: 15px;">Who should have access to this website?</p>
                        
                        <div class="owner-selection">
                            <label class="owner-option <?php echo !$isShared ? 'selected' : ''; ?>" id="owner-mine">
                                <input type="radio" name="owner_type" value="mine" <?php echo !$isShared ? 'checked' : ''; ?>>
                                <div class="icon">👤</div>
                                <div class="label">My Website</div>
                                <div class="desc">Only you can see and manage</div>
                            </label>
                            
                            <label class="owner-option <?php echo $isShared ? 'selected' : ''; ?>" id="owner-shared">
                                <input type="radio" name="owner_type" value="shared" <?php echo $isShared ? 'checked' : ''; ?>>
                                <div class="icon">👥</div>
                                <div class="label">Shared</div>
                                <div class="desc">All users can see and manage</div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Basic Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="site_name">Site Name *</label>
                                <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($website['site_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="domain">Domain *</label>
                                <input type="text" id="domain" name="domain" value="<?php echo htmlspecialchars($website['domain']); ?>" required>
                                <small>Canonical URL: <code><?php echo htmlspecialchars($website['canonical_url'] ?? 'https://www.' . $website['domain']); ?></code></small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Logo Image</label>
                                <div id="logoPreview" class="logo-preview <?php echo (!empty($website['logo']) && preg_match('/\.(webp|svg|avif)$/i', $website['logo'])) ? '' : 'empty'; ?>">
                                    <?php 
                                    // Use constant from config.php
                                    $logoDataUrl = getLogoPreviewData($website['logo'] ?? '', LOGOS_DIR);
                                    if ($logoDataUrl): 
                                    ?>
                                        <img src="<?php echo $logoDataUrl; ?>" alt="Current Logo">
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
                            
                            <!-- Favicon Upload -->
                            <div class="form-group">
                                <label>Favicon</label>
                                <div id="faviconPreview" class="favicon-preview <?php echo $hasFavicon ? '' : 'empty'; ?>">
                                    <?php 
                                    // Use constant from config.php
                                    $faviconDataUrl = getFaviconPreviewData($website['favicon'] ?? '', FAVICONS_DIR);
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
                                <label for="language">Default Language</label>
                                <select id="language" name="language">
                                    <?php foreach ($availableLanguages as $code => $lang): ?>
                                        <option value="<?php echo htmlspecialchars($code); ?>" <?php echo ($website['language'] === $code) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($lang['flag'] . ' ' . $lang['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small>This is the language shown when user visits without language prefix</small>
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
                    
                    <!-- Enabled Languages Section -->
                    <div class="form-section">
                        <h3>🌐 Enabled Languages</h3>
                        
                        <div class="enabled-languages-info">
                            <span class="info-icon">💡</span>
                            <span class="info-text">
                                Select which languages are available for this website. Only enabled languages will appear in the language selector and sitemap. 
                                Disabled language URLs will return 404 for better SEO.
                            </span>
                        </div>
                        
                        <div class="languages-grid-checkboxes">
                            <?php foreach ($availableLanguages as $code => $lang): 
                                $isEnabled = in_array($code, $enabledLanguages);
                                $isDefault = ($website['language'] === $code);
                                $isMaster = ($code === 'en');
                            ?>
                                <label class="language-checkbox-item <?php echo $isEnabled ? 'checked' : ''; ?> <?php echo ($isDefault || $isMaster) ? 'disabled' : ''; ?>" 
                                        data-lang-code="<?php echo htmlspecialchars($code); ?>"
                                        data-lang-name="<?php echo htmlspecialchars($lang['name']); ?>"
                                        data-lang-flag="<?php echo htmlspecialchars($lang['flag']); ?>">
                                    <input type="checkbox" 
                                            name="enabled_languages[]" 
                                            value="<?php echo htmlspecialchars($code); ?>"
                                            <?php echo $isEnabled ? 'checked' : ''; ?>
                                            <?php echo ($isDefault || $isMaster) ? 'disabled' : ''; ?>
                                            class="lang-checkbox">
                                    <?php if ($isDefault || $isMaster): ?>
                                        <!-- Hidden input to ensure disabled checkboxes still submit -->
                                        <input type="hidden" name="enabled_languages[]" value="<?php echo htmlspecialchars($code); ?>">
                                    <?php endif; ?>
                                    <span class="lang-flag"><?php echo htmlspecialchars($lang['flag']); ?></span>
                                    <span class="lang-info">
                                        <span class="lang-name"><?php echo htmlspecialchars($lang['name']); ?></span>
                                        <span class="lang-code"><?php echo htmlspecialchars($code); ?></span>
                                    </span>
                                    <?php if ($isDefault): ?>
                                        <span class="lang-badge default">Default</span>
                                    <?php elseif ($isMaster): ?>
                                        <span class="lang-badge master">Master</span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        
                        <small style="display: block; margin-top: 15px; color: #7f8c8d;">
                            <strong>Note:</strong> English (master language) and the default language are always enabled and cannot be disabled.
                        </small>
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
                    
                    <!-- Analytics & Tracking Section -->
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
                                <label for="analytics_url">Analytics Dashboard URL</label>
                                <input type="url" id="analytics_url" name="analytics_url" 
                                       value="<?php echo htmlspecialchars($website['analytics_url'] ?? ''); ?>" 
                                       placeholder="https://analytics.google.com/analytics/web/#/p123456789/reports/"
                                       style="width: 100%;">
                                <small>Paste the full URL from your Analytics dashboard for quick access</small>
                            </div>
                        </div>
                        
                        <div class="form-group" style="margin-top: 15px;">
                            <label>Quick Links</label>
                            <div class="quick-links">
                                <a href="https://search.google.com/search-console?resource_id=sc-domain%3A<?php echo urlencode($website['domain']); ?>" 
                                   target="_blank" class="quick-link-btn">
                                    🔍 Search Console
                                </a>
                                <?php if (!empty($website['analytics_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($website['analytics_url']); ?>" 
                                       target="_blank" class="quick-link-btn">
                                        📊 Analytics Dashboard
                                    </a>
                                <?php else: ?>
                                    <a href="https://analytics.google.com/analytics/web/" 
                                       target="_blank" class="quick-link-btn">
                                        📊 Analytics (General)
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Custom Head Code Section -->
                    <div class="form-section custom-code-section">
                        <h3>🔧 Custom Head Code</h3>
                        <p class="section-description">Add custom code to the &lt;head&gt; section. Use for ads (AdSense), tracking pixels, custom meta tags, etc.</p>
                        
                        <div class="form-group">
                            <label for="custom_head_code">Custom &lt;head&gt; Code</label>
                            <textarea id="custom_head_code" name="custom_head_code" rows="10" 
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
    
    <!-- Language Confirmation Modal -->
    <div class="language-modal-overlay" id="languageModal">
        <div class="language-modal">
            <div class="language-modal-icon" id="modalIcon">🌐</div>
            <div class="language-modal-title" id="modalTitle">Enable Language?</div>
            <div class="language-modal-message" id="modalMessage">
                Do you want to enable <strong id="modalLangName">German</strong> for this website?
            </div>
            <div class="language-modal-actions">
                <button type="button" class="btn btn-cancel-modal" id="modalCancelBtn">No</button>
                <button type="button" class="btn" id="modalConfirmBtn">Yes</button>
            </div>
        </div>
    </div>
    
    <script src="js/website-edit.js"></script>
    <script>
        // Owner selection toggle
        document.querySelectorAll('.owner-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.owner-option').forEach(o => o.classList.remove('selected'));
                this.classList.add('selected');
            });
        });
        
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
        
        // ==========================================
        // Language Checkbox Confirmation Modal
        // ==========================================
        (function() {
            const modal = document.getElementById('languageModal');
            const modalIcon = document.getElementById('modalIcon');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            const modalLangName = document.getElementById('modalLangName');
            const modalConfirmBtn = document.getElementById('modalConfirmBtn');
            const modalCancelBtn = document.getElementById('modalCancelBtn');
            
            let currentCheckbox = null;
            let currentLabel = null;
            let isEnabling = false;
            
            // Get all non-disabled language checkboxes
            const checkboxes = document.querySelectorAll('.lang-checkbox:not([disabled])');
            
            checkboxes.forEach(function(checkbox) {
                checkbox.addEventListener('change', function(e) {
                    // Prevent the default change
                    e.preventDefault();
                    
                    // Revert the checkbox state (we'll change it after confirmation)
                    this.checked = !this.checked;
                    
                    // Store reference to current checkbox
                    currentCheckbox = this;
                    currentLabel = this.closest('.language-checkbox-item');
                    isEnabling = !this.checked; // If currently unchecked, we're enabling
                    
                    // Get language info from data attributes
                    const langCode = currentLabel.dataset.langCode;
                    const langName = currentLabel.dataset.langName;
                    const langFlag = currentLabel.dataset.langFlag;
                    
                    // Update modal content
                    if (isEnabling) {
                        modalIcon.textContent = '✅';
                        modalTitle.textContent = 'Enable Language?';
                        modalMessage.innerHTML = 'Do you want to enable <strong>' + langFlag + ' ' + langName + '</strong> for this website?';
                        modalConfirmBtn.textContent = 'Yes, enable ' + langName;
                        modalConfirmBtn.className = 'btn btn-confirm-enable';
                    } else {
                        modalIcon.textContent = '⚠️';
                        modalTitle.textContent = 'Disable Language?';
                        modalMessage.innerHTML = 'Do you want to disable <strong>' + langFlag + ' ' + langName + '</strong> for this website?<br><br><small style="color: #e74c3c;">Users visiting /' + langCode + '/ URLs will see a 404 error.</small>';
                        modalConfirmBtn.textContent = 'Yes, disable ' + langName;
                        modalConfirmBtn.className = 'btn btn-confirm-disable';
                    }
                    
                    // Show modal
                    modal.classList.add('active');
                });
            });
            
            // Confirm button
            modalConfirmBtn.addEventListener('click', function() {
                if (currentCheckbox && currentLabel) {
                    // Toggle the checkbox
                    currentCheckbox.checked = isEnabling;
                    
                    // Update visual state
                    if (isEnabling) {
                        currentLabel.classList.add('checked');
                    } else {
                        currentLabel.classList.remove('checked');
                    }
                }
                
                // Hide modal
                modal.classList.remove('active');
                currentCheckbox = null;
                currentLabel = null;
            });
            
            // Cancel button
            modalCancelBtn.addEventListener('click', function() {
                // Just close modal, checkbox state already reverted
                modal.classList.remove('active');
                currentCheckbox = null;
                currentLabel = null;
            });
            
            // Close modal on overlay click
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.classList.remove('active');
                    currentCheckbox = null;
                    currentLabel = null;
                }
            });
            
            // Close modal on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.classList.contains('active')) {
                    modal.classList.remove('active');
                    currentCheckbox = null;
                    currentLabel = null;
                }
            });
        })();
    </script>
</body>
</html>