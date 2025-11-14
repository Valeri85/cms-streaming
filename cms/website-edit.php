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

// Using absolute path
$configFile = '/var/www/u1852176/data/www/streaming/config/websites.json';

if (!file_exists($configFile)) {
    die("Configuration file not found at: " . $configFile);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $configContent = file_get_contents($configFile);
    $configData = json_decode($configContent, true);
    $websites = $configData['websites'] ?? [];
    
    $siteName = trim($_POST['site_name'] ?? '');
    $domain = trim($_POST['domain'] ?? '');
    $logo = trim($_POST['logo'] ?? '🍋');
    $primaryColor = trim($_POST['primary_color'] ?? '#FFA500');
    $secondaryColor = trim($_POST['secondary_color'] ?? '#FF8C00');
    $seoTitle = trim($_POST['seo_title'] ?? '');
    $seoDescription = trim($_POST['seo_description'] ?? '');
    $seoKeywords = trim($_POST['seo_keywords'] ?? '');
    $language = trim($_POST['language'] ?? 'en');
    $sidebarContent = trim($_POST['sidebar_content'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    if ($siteName && $domain && $seoTitle) {
        // Find and update website
        $updated = false;
        foreach ($websites as $key => $website) {
            if ($website['id'] == $websiteId) {
                $websites[$key] = [
                    'id' => (int)$websiteId,
                    'domain' => $domain,
                    'site_name' => $siteName,
                    'logo' => $logo,
                    'primary_color' => $primaryColor,
                    'secondary_color' => $secondaryColor,
                    'seo_title' => $seoTitle,
                    'seo_description' => $seoDescription,
                    'seo_keywords' => $seoKeywords,
                    'language' => $language,
                    'sidebar_content' => $sidebarContent,
                    'status' => $status
                ];
                $updated = true;
                break;
            }
        }
        
        if ($updated) {
            $configData['websites'] = $websites;
            
            // Save to JSON with pretty print
            $jsonContent = json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            if (file_put_contents($configFile, $jsonContent)) {
                $success = 'Website updated successfully! Check your website to see changes.';
            } else {
                $error = 'Failed to save changes. Check file permissions: chmod 644 ' . $configFile;
            }
        } else {
            $error = 'Website not found';
        }
    } else {
        $error = 'Please fill all required fields';
    }
}

// Load current website data
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Website - CMS</title>
    <link rel="stylesheet" href="cms-style.css">
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
                
                <form method="POST" class="cms-form">
                    <!-- Basic Info -->
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
                                <label for="logo">Logo Emoji</label>
                                <input type="text" id="logo" name="logo" value="<?php echo htmlspecialchars($website['logo']); ?>" placeholder="🍋">
                                <small>Use any emoji as your logo</small>
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
                    
                    <!-- Theme Colors -->
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
                    
                    <!-- SEO Settings -->
                    <div class="form-section">
                        <h3>SEO Settings</h3>
                        
                        <div class="form-group">
                            <label for="seo_title">SEO Title *</label>
                            <input type="text" id="seo_title" name="seo_title" value="<?php echo htmlspecialchars($website['seo_title']); ?>" required>
                            <small>Recommended: 50-60 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="seo_description">SEO Description</label>
                            <textarea id="seo_description" name="seo_description" rows="3"><?php echo htmlspecialchars($website['seo_description']); ?></textarea>
                            <small>Recommended: 150-160 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="seo_keywords">SEO Keywords</label>
                            <textarea id="seo_keywords" name="seo_keywords" rows="2"><?php echo htmlspecialchars($website['seo_keywords']); ?></textarea>
                            <small>Comma-separated keywords</small>
                        </div>
                    </div>
                    
                    <!-- Sidebar Content -->
                    <div class="form-section">
                        <h3>Sidebar Content</h3>
                        
                        <div class="form-group">
                            <label for="sidebar_content">Right Sidebar HTML</label>
                            <textarea id="sidebar_content" name="sidebar_content" rows="8"><?php echo htmlspecialchars($website['sidebar_content']); ?></textarea>
                            <small>You can use HTML tags here</small>
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
    
    <script>
        // Update color text inputs when color picker changes
        document.getElementById('primary_color').addEventListener('input', function(e) {
            e.target.nextElementSibling.value = e.target.value;
        });
        
        document.getElementById('secondary_color').addEventListener('input', function(e) {
            e.target.nextElementSibling.value = e.target.value;
        });
    </script>
</body>
</html>