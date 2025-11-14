<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

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
            // Generate new ID
            $maxId = 0;
            foreach ($websites as $website) {
                if ($website['id'] > $maxId) {
                    $maxId = $website['id'];
                }
            }
            $newId = $maxId + 1;
            
            // Add new website
            $newWebsite = [
                'id' => $newId,
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
            
            $websites[] = $newWebsite;
            $configData['websites'] = $websites;
            
            // Save to JSON with pretty print
            $jsonContent = json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            if (file_put_contents($configFile, $jsonContent)) {
                $success = 'Website added successfully!';
                // Clear form
                $_POST = [];
            } else {
                $error = 'Failed to save. Check file permissions: chmod 644 ' . $configFile;
            }
        }
    } else {
        $error = 'Please fill all required fields';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Website - CMS</title>
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
                
                <form method="POST" class="cms-form">
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
                                <label for="logo">Logo Emoji</label>
                                <input type="text" id="logo" name="logo" value="<?php echo htmlspecialchars($_POST['logo'] ?? '🍋'); ?>" placeholder="🍋">
                                <small>Use any emoji as your logo</small>
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
                    
                    <!-- SEO Settings -->
                    <div class="form-section">
                        <h3>SEO Settings</h3>
                        
                        <div class="form-group">
                            <label for="seo_title">SEO Title *</label>
                            <input type="text" id="seo_title" name="seo_title" value="<?php echo htmlspecialchars($_POST['seo_title'] ?? ''); ?>" required>
                            <small>Recommended: 50-60 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="seo_description">SEO Description</label>
                            <textarea id="seo_description" name="seo_description" rows="3"><?php echo htmlspecialchars($_POST['seo_description'] ?? ''); ?></textarea>
                            <small>Recommended: 150-160 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="seo_keywords">SEO Keywords</label>
                            <textarea id="seo_keywords" name="seo_keywords" rows="2"><?php echo htmlspecialchars($_POST['seo_keywords'] ?? ''); ?></textarea>
                            <small>Comma-separated keywords</small>
                        </div>
                    </div>
                    
                    <!-- Sidebar Content -->
                    <div class="form-section">
                        <h3>Sidebar Content</h3>
                        
                        <div class="form-group">
                            <label for="sidebar_content">Right Sidebar HTML</label>
                            <textarea id="sidebar_content" name="sidebar_content" rows="8"><?php echo htmlspecialchars($_POST['sidebar_content'] ?? '<h3>About</h3><p>Add your content here...</p>'); ?></textarea>
                            <small>You can use HTML tags here</small>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Add Website</button>
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