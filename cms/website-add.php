<?php
/**
 * Add New Website
 * 
 * REFACTORED Phase 3: Uses bootstrap.php, header.php, footer.php components
 * ALL FEATURES PRESERVED
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Get current user for owner assignment
$currentUser = getCurrentUser();

$error = '';
$success = '';

// Use constants from config.php
if (!file_exists(WEBSITES_CONFIG_FILE)) {
    die("Configuration file not found at: " . WEBSITES_CONFIG_FILE);
}

if (!file_exists(MASTER_SPORTS_FILE)) {
    die("Master sports file not found at: " . MASTER_SPORTS_FILE);
}

// Directories are auto-created by config.php via ensureDirectoryExists()

// ==========================================
// PAGE-SPECIFIC FUNCTIONS
// ==========================================

/**
 * Send Slack notification when master-sports.json is missing
 */
function sendMasterSportsNotFoundNotification() {
    if (!file_exists(SLACK_CONFIG_FILE)) {
        return false;
    }
    
    $slackConfig = json_decode(file_get_contents(SLACK_CONFIG_FILE), true);
    $slackWebhookUrl = $slackConfig['webhook_url'] ?? '';
    
    if (empty($slackWebhookUrl)) {
        return false;
    }
    
    $message = [
        'text' => "🚨 *CRITICAL: master-sports.json Not Found*",
        'blocks' => [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "⛔ *CRITICAL ERROR*\n\nAttempted to add new website but `master-sports.json` file is missing or invalid!"
                ]
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Expected location:*\n`" . MASTER_SPORTS_FILE . "`"
                ]
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Action Required:*\nPlease create or restore the `master-sports.json` file."
                ]
            ]
        ]
    ];
    
    $ch = curl_init($slackWebhookUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
    
    return true;
}

/**
 * Create config PHP file for new website
 */
function createConfigFile($domain, $siteName, $logo, $primaryColor, $secondaryColor, $language) {
    $configDir = CONFIG_DIR . '/websites/';
    
    if (!file_exists($configDir)) {
        mkdir($configDir, 0755, true);
    }
    
    $filename = strtolower(str_replace('.', '-', $domain)) . '.php';
    $filepath = $configDir . $filename;
    
    $configContent = "<?php\n";
    $configContent .= "// Auto-generated config for {$siteName}\n";
    $configContent .= "// Domain: {$domain}\n";
    $configContent .= "// Created: " . date('Y-m-d H:i:s') . "\n\n";
    $configContent .= "\$siteConfig = [\n";
    $configContent .= "    'domain' => '" . addslashes($domain) . "',\n";
    $configContent .= "    'site_name' => '" . addslashes($siteName) . "',\n";
    $configContent .= "    'logo' => '" . addslashes($logo) . "',\n";
    $configContent .= "    'primary_color' => '" . addslashes($primaryColor) . "',\n";
    $configContent .= "    'secondary_color' => '" . addslashes($secondaryColor) . "',\n";
    $configContent .= "    'language' => '" . addslashes($language) . "',\n";
    $configContent .= "];\n";
    
    if (file_put_contents($filepath, $configContent)) {
        return ['success' => true, 'filename' => $filename];
    } else {
        return ['error' => 'Failed to create config file. Check permissions for: ' . $configDir];
    }
}

// Get available languages (from functions.php)
$availableLanguages = getAvailableLanguages();

// ==========================================
// HANDLE FORM SUBMISSION
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $configContent = file_get_contents(WEBSITES_CONFIG_FILE);
    $configData = json_decode($configContent, true);
    $websites = $configData['websites'] ?? [];
    
    $siteName = trim($_POST['site_name'] ?? '');
    $domain = trim($_POST['domain'] ?? '');
    $primaryColor = trim($_POST['primary_color'] ?? '#FFA500');
    $secondaryColor = trim($_POST['secondary_color'] ?? '#FF8C00');
    $language = trim($_POST['language'] ?? 'en');
    $status = $_POST['status'] ?? 'active';
    
    // Get owner selection
    $ownerType = $_POST['owner_type'] ?? 'mine';
    if ($ownerType === 'shared') {
        $owner = 'shared';
    } else {
        $owner = getCurrentUserOwner() ?? 'shared';
    }
    
    $logo = '';
    
    if ($siteName && $domain) {
        // Normalize domain (remove www. prefix)
        $normalizedDomain = normalizeDomain($domain);
        
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
            // Handle logo upload (function from functions.php)
            if (isset($_FILES['logo_file']) && $_FILES['logo_file']['size'] > 0) {
                $uploadResult = handleLogoUpload($_FILES['logo_file'], LOGOS_DIR, $siteName);
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
                
                // Load sports from master-sports.json (function from functions.php)
                $sportsList = getSportsListForNewWebsite();
                
                // Generate canonical URL (function from functions.php)
                $canonicalUrl = generateCanonicalUrl($normalizedDomain);
                
                // Create config PHP file
                $configFileResult = createConfigFile($normalizedDomain, $siteName, $logo, $primaryColor, $secondaryColor, $language);
                
                if (isset($configFileResult['error'])) {
                    $error = $configFileResult['error'];
                } else {
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
                        'enabled_languages' => ['en'],
                        'owner' => $owner,
                        'google_analytics_id' => '',
                        'analytics_url' => '',
                        'custom_head_code' => ''
                    ];
                    
                    $websites[] = $newWebsite;
                    $configData['websites'] = $websites;
                    
                    $jsonContent = json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    
                    if (file_put_contents(WEBSITES_CONFIG_FILE, $jsonContent)) {
                        $sportsCount = count($sportsList);
                        $success = "Website added successfully with {$sportsCount} sport categories! Config file created: {$configFileResult['filename']}. Languages: English only (add more in Settings after translating).";
                        $_POST = [];
                    } else {
                        $error = 'Failed to save. Check file permissions: chmod 644 ' . WEBSITES_CONFIG_FILE;
                    }
                }
            }
        }
    } else {
        $error = 'Please fill all required fields';
    }
}

$configContent = file_get_contents(WEBSITES_CONFIG_FILE);
$configData = json_decode($configContent, true);
$existingWebsites = $configData['websites'] ?? [];
$currentSportsCount = count(getSportsListForNewWebsite());

// ==========================================
// PAGE CONFIGURATION FOR HEADER
// ==========================================
$pageTitle = 'Add Website - CMS';
$currentPage = 'website-add';
$extraCss = ['css/website-add.css'];

include __DIR__ . '/includes/header.php';
?>

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
        <!-- Owner Selection Section -->
        <div class="form-section">
            <h3>👤 Website Owner</h3>
            <p style="color: #666; margin-bottom: 15px;">Who should have access to this website?</p>
            
            <div class="owner-selection" style="display: flex; gap: 15px;">
                <label class="owner-option selected" style="flex: 1; padding: 15px; border: 2px solid #667eea; border-radius: 8px; cursor: pointer; text-align: center; background: #f0f4ff;">
                    <input type="radio" name="owner_type" value="mine" checked style="display: none;">
                    <div style="font-size: 24px; margin-bottom: 8px;">👤</div>
                    <div style="font-weight: 600; color: #333;">My Website</div>
                    <div style="font-size: 12px; color: #666; margin-top: 4px;">Only you can see and manage</div>
                </label>
                
                <label class="owner-option" style="flex: 1; padding: 15px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; text-align: center;">
                    <input type="radio" name="owner_type" value="shared" style="display: none;">
                    <div style="font-size: 24px; margin-bottom: 8px;">👥</div>
                    <div style="font-weight: 600; color: #333;">Shared</div>
                    <div style="font-size: 12px; color: #666; margin-top: 4px;">All users can see and manage</div>
                </label>
            </div>
        </div>
        
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
                    <small>Enter domain without www. or https:// (e.g., sportlemons.info)</small>
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
                    <label for="language">Default Language</label>
                    <select id="language" name="language">
                        <?php foreach ($availableLanguages as $code => $lang): ?>
                            <option value="<?php echo htmlspecialchars($code); ?>" <?php echo ($code === 'en') ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lang['flag'] . ' ' . $lang['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small><a href="languages.php">Manage languages</a></small>
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
        
        <div class="form-section" style="background: #e8f5e9; border: 2px solid #81c784;">
            <h3>📋 Automatic Configuration</h3>
            <p style="margin-bottom: 10px;">When you create this website, the system will automatically:</p>
            <ul style="list-style: none; padding-left: 0;">
                <li style="padding: 8px 0;">✅ Load <strong><?php echo $currentSportsCount; ?> sport categories</strong> from master-sports.json</li>
                <li style="padding: 8px 0;">✅ Create config file: <code><?php echo htmlspecialchars($_POST['domain'] ?? 'example.com'); ?>.php</code></li>
                <li style="padding: 8px 0;">✅ Generate canonical URL: <code>https://www.<?php echo htmlspecialchars($_POST['domain'] ?? 'example.com'); ?></code></li>
                <li style="padding: 8px 0;">✅ Set up basic SEO structure</li>
                <li style="padding: 8px 0;">🌐 <strong>Enable only English</strong> - Add more languages in Settings after translating</li>
            </ul>
            <p style="margin-top: 15px; color: #2e7d32; font-weight: 600;">⚠️ After creation, remember to configure SEO for each sport page!</p>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Website</button>
            <a href="dashboard.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<script src="js/website-add.js"></script>
<script>
    // Owner selection toggle
    document.querySelectorAll('.owner-option').forEach(option => {
        option.addEventListener('click', function() {
            document.querySelectorAll('.owner-option').forEach(o => {
                o.style.borderColor = '#ddd';
                o.style.background = 'white';
            });
            this.style.borderColor = '#667eea';
            this.style.background = '#f0f4ff';
        });
    });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>