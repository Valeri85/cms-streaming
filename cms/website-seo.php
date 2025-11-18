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

// Load current website data
$configContent = file_get_contents($configFile);
$configData = json_decode($configContent, true);
$websites = $configData['websites'] ?? [];

$website = null;
$websiteIndex = null;
foreach ($websites as $key => $site) {
    if ($site['id'] == $websiteId) {
        $website = $site;
        $websiteIndex = $key;
        break;
    }
}

if (!$website) {
    header('Location: dashboard.php');
    exit;
}

// Get sports list from website config
$sports = $website['sports_categories'] ?? [];

// Convert to array with name and slug
$sportsArray = [];
foreach ($sports as $sportName) {
    $sportSlug = strtolower(str_replace(' ', '-', $sportName));
    $sportsArray[] = [
        'name' => $sportName,
        'slug' => $sportSlug
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initialize pages_seo if it doesn't exist
    if (!isset($websites[$websiteIndex]['pages_seo'])) {
        $websites[$websiteIndex]['pages_seo'] = [];
    }
    
    // Update home page SEO
    $websites[$websiteIndex]['pages_seo']['home'] = [
        'title' => trim($_POST['home_title'] ?? ''),
        'description' => trim($_POST['home_description'] ?? '')
    ];
    
    // Update favorites page SEO
    $websites[$websiteIndex]['pages_seo']['favorites'] = [
        'title' => trim($_POST['favorites_title'] ?? ''),
        'description' => trim($_POST['favorites_description'] ?? '')
    ];
    
    // Update sports pages SEO
    if (!isset($websites[$websiteIndex]['pages_seo']['sports'])) {
        $websites[$websiteIndex]['pages_seo']['sports'] = [];
    }
    
    foreach ($sportsArray as $sport) {
        $slug = $sport['slug'];
        $websites[$websiteIndex]['pages_seo']['sports'][$slug] = [
            'title' => trim($_POST['sport_title_' . $slug] ?? ''),
            'description' => trim($_POST['sport_description_' . $slug] ?? '')
        ];
    }
    
    $configData['websites'] = $websites;
    
    // Save to JSON with pretty print
    $jsonContent = json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if (file_put_contents($configFile, $jsonContent)) {
        $success = 'SEO settings updated successfully for all ' . (count($sportsArray) + 2) . ' pages!';
        
        // CRITICAL: Reload website data after save to repopulate form
        $configContent = file_get_contents($configFile);
        $configData = json_decode($configContent, true);
        $websites = $configData['websites'] ?? [];
        foreach ($websites as $key => $site) {
            if ($site['id'] == $websiteId) {
                $website = $site;
                $websiteIndex = $key;
                break;
            }
        }
    } else {
        $error = 'Failed to save changes. Check file permissions: chmod 644 ' . $configFile;
    }
}

// Get current SEO settings or set defaults - AFTER potential reload
$pagesSeo = $website['pages_seo'] ?? [];
$homeSeo = $pagesSeo['home'] ?? ['title' => '', 'description' => ''];
$favoritesSeo = $pagesSeo['favorites'] ?? ['title' => '', 'description' => ''];
$sportsSeo = $pagesSeo['sports'] ?? [];

// Function to get status indicator
function getStatusIndicator($title, $description) {
    $titleFilled = !empty(trim($title));
    $descFilled = !empty(trim($description));
    
    if ($titleFilled && $descFilled) {
        return '🟢'; // Green - both filled
    } elseif ($titleFilled || $descFilled) {
        return '🟠'; // Orange - partially filled
    } else {
        return '🔴'; // Red - empty
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage SEO - <?php echo htmlspecialchars($website['site_name']); ?></title>
    <link rel="stylesheet" href="cms-style.css">
    <link rel="stylesheet" href="css/website-seo.css">
</head>
<body data-preview-domain="<?php echo htmlspecialchars($website['domain']); ?>">
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
                <h1>Manage SEO: <?php echo htmlspecialchars($website['site_name']); ?></h1>
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
                    <!-- Home Page SEO -->
                    <div class="page-seo-section">
                        <h3>🏠 Home Page SEO</h3>
                        <div class="seo-accordion">
                            <details>
                                <summary>
                                    <span class="status-indicator"><?php echo getStatusIndicator($homeSeo['title'], $homeSeo['description']); ?></span>
                                    <span class="accordion-title">Home Page</span>
                                </summary>
                                <div class="seo-accordion-content">
                                    <div class="form-group">
                                        <label for="home_title">SEO Title</label>
                                        <input type="text" id="home_title" name="home_title" value="<?php echo htmlspecialchars($homeSeo['title']); ?>" placeholder="Home - <?php echo htmlspecialchars($website['site_name']); ?>">
                                        <small>Recommended: 50-60 characters</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="home_description">SEO Description</label>
                                        <textarea id="home_description" name="home_description" rows="3" placeholder="Watch live sports streaming online..."><?php echo htmlspecialchars($homeSeo['description']); ?></textarea>
                                        <small>Recommended: 150-160 characters</small>
                                    </div>
                                </div>
                            </details>
                        </div>
                    </div>
                    
                    <!-- Favorites Page SEO -->
                    <div class="page-seo-section">
                        <h3>⭐ Favorites Page SEO</h3>
                        <div class="seo-accordion">
                            <details>
                                <summary>
                                    <span class="status-indicator"><?php echo getStatusIndicator($favoritesSeo['title'], $favoritesSeo['description']); ?></span>
                                    <span class="accordion-title">Favorites Page</span>
                                </summary>
                                <div class="seo-accordion-content">
                                    <div class="form-group">
                                        <label for="favorites_title">SEO Title</label>
                                        <input type="text" id="favorites_title" name="favorites_title" value="<?php echo htmlspecialchars($favoritesSeo['title']); ?>" placeholder="My Favorites - <?php echo htmlspecialchars($website['site_name']); ?>">
                                        <small>Recommended: 50-60 characters</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="favorites_description">SEO Description</label>
                                        <textarea id="favorites_description" name="favorites_description" rows="3" placeholder="Your favorite sports games and streams..."><?php echo htmlspecialchars($favoritesSeo['description']); ?></textarea>
                                        <small>Recommended: 150-160 characters</small>
                                    </div>
                                </div>
                            </details>
                        </div>
                    </div>
                    
                    <!-- Sports Pages SEO -->
                    <div class="page-seo-section">
                        <h3>⚽ Sports Pages SEO</h3>
                        <p style="color: #666; margin-bottom: 10px;">Configure SEO for each sport category page (e.g., /live-football, /live-basketball)</p>
                        
                        <div class="sports-count-info">
                            <span>✅ Found <?php echo count($sportsArray); ?> sports categories</span>
                            <a href="website-sports.php?id=<?php echo $websiteId; ?>" class="btn btn-sm" style="background: #3498db; color: white;">Manage Sports Categories</a>
                        </div>
                        
                        <div class="seo-accordion">
                            <?php foreach ($sportsArray as $sport): 
                                $slug = $sport['slug'];
                                $sportSeo = $sportsSeo[$slug] ?? ['title' => '', 'description' => ''];
                                $status = getStatusIndicator($sportSeo['title'], $sportSeo['description']);
                            ?>
                                <details>
                                    <summary>
                                        <span class="status-indicator"><?php echo $status; ?></span>
                                        <span class="accordion-title"><?php echo htmlspecialchars($sport['name']); ?></span>
                                    </summary>
                                    <div class="seo-accordion-content">
                                        <div class="form-group">
                                            <label for="sport_title_<?php echo $slug; ?>">SEO Title</label>
                                            <input type="text" id="sport_title_<?php echo $slug; ?>" name="sport_title_<?php echo $slug; ?>" value="<?php echo htmlspecialchars($sportSeo['title']); ?>" placeholder="Live <?php echo htmlspecialchars($sport['name']); ?> - <?php echo htmlspecialchars($website['site_name']); ?>">
                                            <small>Recommended: 50-60 characters</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="sport_description_<?php echo $slug; ?>">SEO Description</label>
                                            <textarea id="sport_description_<?php echo $slug; ?>" name="sport_description_<?php echo $slug; ?>" rows="3" placeholder="Watch <?php echo htmlspecialchars($sport['name']); ?> live streams..."><?php echo htmlspecialchars($sportSeo['description']); ?></textarea>
                                            <small>Recommended: 150-160 characters</small>
                                        </div>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save All SEO Settings (<?php echo count($sportsArray) + 2; ?> Pages)</button>
                        <a href="dashboard.php" class="btn btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>