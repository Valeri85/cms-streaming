<?php
/**
 * Website Pages Management
 * 
 * REFACTORED: Now uses centralized config and functions
 * 
 * Changes made:
 * 1. Removed all function definitions (now in includes/functions.php)
 * 2. Removed all path constants (now in includes/config.php)
 * 3. Added require_once for the new include files
 */

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// ==========================================
// LOAD CENTRALIZED CONFIG AND FUNCTIONS
// ==========================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// ==========================================
// PAGE VARIABLES
// ==========================================
$websiteId = $_GET['id'] ?? null;
$error = '';
$success = '';

if (!$websiteId) {
    header('Location: dashboard.php');
    exit;
}

// Use constants from config.php instead of hardcoded paths
// OLD: $configFile = '/var/www/u1852176/data/www/streaming/config/websites.json';
// NEW: Use WEBSITES_CONFIG_FILE constant

// OLD: $masterIconsDir = '/var/www/u1852176/data/www/streaming/shared/icons/sports/';
// NEW: Use SPORT_ICONS_DIR constant

// OLD: $langDir = '/var/www/u1852176/data/www/streaming/config/lang/';
// NEW: Use LANG_DIR constant

// OLD: $flagsUrlPath = '/shared/icons/flags/';
// NEW: Use FLAGS_URL_PATH constant

if (!file_exists(WEBSITES_CONFIG_FILE)) {
    die("Configuration file not found at: " . WEBSITES_CONFIG_FILE);
}

// ==========================================
// REMOVED: All function definitions
// These are now in includes/functions.php:
// - sanitizeSportName()
// - getMasterIcon()
// - sendSlackNotification()
// - getHomeStatus()
// - getSportStatus()
// - getLanguageSeoStatus()
// - loadActiveLanguages()
// - getLanguageSeoData()
// - saveLanguageSeoData()
// ==========================================

// ==========================================
// LOAD DATA
// ==========================================

$configContent = file_get_contents(WEBSITES_CONFIG_FILE);
$configData = json_decode($configContent, true);
$websites = $configData['websites'] ?? [];

// Find the website
$website = null;
$websiteIndex = null;
foreach ($websites as $index => $site) {
    if ($site['id'] == $websiteId) {
        $website = $site;
        $websiteIndex = $index;
        break;
    }
}

if (!$website) {
    header('Location: dashboard.php');
    exit;
}

$previewDomain = $website['domain'] ?? '';

// Load active languages (function from functions.php)
// Now uses LANG_DIR constant automatically
$activeLanguages = loadActiveLanguages();

// ==========================================
// HANDLE POST REQUESTS
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $configContent = file_get_contents(WEBSITES_CONFIG_FILE);
    $configData = json_decode($configContent, true);
    $websites = $configData['websites'] ?? [];
    
    foreach ($websites as $index => $site) {
        if ($site['id'] == $websiteId) {
            $websiteIndex = $index;
            break;
        }
    }
    
    if ($websiteIndex !== null) {
        // UPDATE HOME PAGE (English - default)
        if (isset($_POST['update_home'])) {
            $websites[$websiteIndex]['pages_seo']['home'] = [
                'title' => trim($_POST['home_seo_title'] ?? ''),
                'description' => trim($_POST['home_seo_description'] ?? '')
            ];
            $success = "Home page SEO updated!";
        }
        
        // UPDATE HOME PAGE SEO FOR SPECIFIC LANGUAGE
        if (isset($_POST['update_home_lang'])) {
            $langCode = $_POST['lang_code'] ?? '';
            $title = trim($_POST['home_seo_title'] ?? '');
            $description = trim($_POST['home_seo_description'] ?? '');
            
            if ($langCode && $langCode !== 'en') {
                // Function from functions.php - now uses LANG_DIR automatically
                $result = saveLanguageSeoData($langCode, $previewDomain, 'home', null, $title, $description);
                if (isset($result['success'])) {
                    $success = "Home page SEO updated for " . ($activeLanguages[$langCode]['name'] ?? $langCode) . "!";
                } else {
                    $error = $result['error'];
                }
            }
        }
        
        // ADD NEW SPORT
        if (isset($_POST['add_sport'])) {
            $newSport = trim($_POST['new_sport_name'] ?? '');
            
            if ($newSport) {
                if (!in_array($newSport, $websites[$websiteIndex]['sports_categories'])) {
                    $websites[$websiteIndex]['sports_categories'][] = $newSport;
                    $success = "Sport category '{$newSport}' added!";
                    // Function from functions.php
                    sendSlackNotification($newSport);
                } else {
                    $error = "Sport category '{$newSport}' already exists!";
                }
            } else {
                $error = "Please enter a sport name";
            }
        }
        
        // UPDATE SPORT (SEO only - English default)
        if (isset($_POST['update_sport'])) {
            $sportName = $_POST['sport_name'] ?? '';
            // Function from functions.php
            $sportSlug = sanitizeSportName($sportName);
            
            $websites[$websiteIndex]['pages_seo']['sports'][$sportSlug] = [
                'title' => trim($_POST['seo_title'] ?? ''),
                'description' => trim($_POST['seo_description'] ?? '')
            ];
            
            $success = "'{$sportName}' SEO updated!";
        }
        
        // UPDATE SPORT SEO FOR SPECIFIC LANGUAGE
        if (isset($_POST['update_sport_lang'])) {
            $langCode = $_POST['lang_code'] ?? '';
            $sportName = $_POST['sport_name'] ?? '';
            $sportSlug = sanitizeSportName($sportName);
            $title = trim($_POST['seo_title'] ?? '');
            $description = trim($_POST['seo_description'] ?? '');
            
            if ($langCode && $langCode !== 'en' && $sportSlug) {
                $result = saveLanguageSeoData($langCode, $previewDomain, 'sport', $sportSlug, $title, $description);
                if (isset($result['success'])) {
                    $success = "'{$sportName}' SEO updated for " . ($activeLanguages[$langCode]['name'] ?? $langCode) . "!";
                } else {
                    $error = $result['error'];
                }
            }
        }
        
        // RENAME SPORT
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
                        
                        // Update SEO slug
                        $oldSlug = sanitizeSportName($oldName);
                        $newSlug = sanitizeSportName($newName);
                        
                        if (isset($websites[$websiteIndex]['pages_seo']['sports'][$oldSlug])) {
                            $websites[$websiteIndex]['pages_seo']['sports'][$newSlug] = $websites[$websiteIndex]['pages_seo']['sports'][$oldSlug];
                            unset($websites[$websiteIndex]['pages_seo']['sports'][$oldSlug]);
                        }
                        
                        $success = "Sport renamed: '{$oldName}' to '{$newName}'";
                    } else {
                        $error = "Sport category '{$newName}' already exists!";
                    }
                } else {
                    $error = "Sport category '{$oldName}' not found!";
                }
            }
        }
        
        // DELETE SPORT
        if (isset($_POST['delete_sport'])) {
            $sportToDelete = $_POST['sport_to_delete'] ?? '';
            
            if ($sportToDelete) {
                $sports = $websites[$websiteIndex]['sports_categories'];
                $index = array_search($sportToDelete, $sports);
                
                if ($index !== false) {
                    unset($sports[$index]);
                    $websites[$websiteIndex]['sports_categories'] = array_values($sports);
                    
                    // Also remove SEO data
                    $sportSlug = sanitizeSportName($sportToDelete);
                    if (isset($websites[$websiteIndex]['pages_seo']['sports'][$sportSlug])) {
                        unset($websites[$websiteIndex]['pages_seo']['sports'][$sportSlug]);
                    }
                    
                    $success = "Sport '{$sportToDelete}' deleted!";
                }
            }
        }
        
        // REORDER SPORTS
        if (isset($_POST['reorder_sports'])) {
            $newOrder = json_decode($_POST['sports_order'] ?? '[]', true);
            if (!empty($newOrder)) {
                $websites[$websiteIndex]['sports_categories'] = $newOrder;
                $success = "Sports order updated!";
            }
        }
        
        // Save configuration
        $configData['websites'] = $websites;
        $jsonContent = json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if (file_put_contents(WEBSITES_CONFIG_FILE, $jsonContent)) {
            // Refresh data after save
            if (empty($error)) {
                $configContent = file_get_contents(WEBSITES_CONFIG_FILE);
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
$pagesSeo = $website['pages_seo'] ?? [];
$homeStatus = getHomeStatus($pagesSeo);

// Get home icon from master - now uses SPORT_ICONS_DIR constant
$homeIconInfo = getMasterIcon('home');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pages - <?php echo htmlspecialchars($website['site_name']); ?></title>
    <link rel="stylesheet" href="cms-style.css">
    <link rel="stylesheet" href="css/website-pages.css">
</head>
<body data-preview-domain="<?php echo htmlspecialchars($previewDomain); ?>">
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
                <h1>Manage Pages: <?php echo htmlspecialchars($website['site_name']); ?></h1>
                <a href="dashboard.php" class="btn">Back to Dashboard</a>
            </header>
            
            <div class="cms-content">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <!-- HOME PAGE SECTION -->
                <div class="content-section home-page-section">
                    <div class="pages-accordion">
                        <details class="home-page-card" data-page-type="home">
                            <summary>
                                <span class="status-dot <?php echo $homeStatus; ?>"></span>
                                <span class="header-icon-small <?php echo $homeIconInfo['exists'] ? 'has-icon' : 'no-icon'; ?>">
                                    <?php if ($homeIconInfo['exists']): ?>
                                        <!-- Use constant for URL path -->
                                        <img src="<?php echo SPORT_ICONS_URL_PATH . htmlspecialchars($homeIconInfo['filename']); ?>?v=<?php echo time(); ?>" alt="Home" width="18" height="18">
                                    <?php else: ?>
                                        ?
                                    <?php endif; ?>
                                </span>
                                <span class="accordion-title">Home Page</span>
                            </summary>
                            
                            <div class="accordion-content">
                                <!-- LANGUAGE TABS FOR HOME -->
                                <div class="lang-tabs-container" data-page-type="home">
                                    <div class="lang-tabs">
                                        <?php foreach ($activeLanguages as $langCode => $langInfo): 
                                            $langSeoStatus = getLanguageSeoStatus($langCode, $previewDomain, 'home', null, null, $pagesSeo);
                                        ?>
                                            <button type="button" 
                                                    class="lang-tab seo-<?php echo $langSeoStatus; ?> <?php echo $langCode === 'en' ? 'active' : ''; ?>" 
                                                    data-lang="<?php echo htmlspecialchars($langCode); ?>"
                                                    title="<?php echo htmlspecialchars($langInfo['name']); ?>">
                                                <!-- Use constant for flags URL -->
                                                <img src="<?php echo FLAGS_URL_PATH . htmlspecialchars($langInfo['flag_code']); ?>.svg" 
                                                     alt="<?php echo htmlspecialchars($langInfo['name']); ?>" 
                                                     class="lang-tab-flag"
                                                     width="28" 
                                                     height="20">
                                                <span class="lang-tab-code"><?php echo strtoupper(htmlspecialchars($langCode)); ?></span>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Language SEO Content Panels -->
                                    <?php foreach ($activeLanguages as $langCode => $langInfo): 
                                        $isEnglish = ($langCode === 'en');
                                        $langHomeSeoData = $isEnglish 
                                            ? ['title' => $pagesSeo['home']['title'] ?? '', 'description' => $pagesSeo['home']['description'] ?? '']
                                            : getLanguageSeoData($langCode, $previewDomain, 'home');
                                    ?>
                                        <div class="lang-tab-content <?php echo $isEnglish ? 'active' : ''; ?>" data-lang="<?php echo htmlspecialchars($langCode); ?>">
                                            <form method="POST" class="home-form">
                                                <?php if ($isEnglish): ?>
                                                    <input type="hidden" name="update_home" value="1">
                                                <?php else: ?>
                                                    <input type="hidden" name="update_home_lang" value="1">
                                                    <input type="hidden" name="lang_code" value="<?php echo htmlspecialchars($langCode); ?>">
                                                <?php endif; ?>
                                                
                                                <div class="form-section-title">SEO Settings (<?php echo htmlspecialchars($langInfo['name']); ?>)</div>
                                                
                                                <div class="form-group">
                                                    <label for="home_seo_title_<?php echo $langCode; ?>">SEO Title</label>
                                                    <input type="text" 
                                                           id="home_seo_title_<?php echo $langCode; ?>" 
                                                           name="home_seo_title" 
                                                           value="<?php echo htmlspecialchars($langHomeSeoData['title']); ?>" 
                                                           placeholder="Live Sports Streaming - <?php echo htmlspecialchars($website['site_name']); ?>">
                                                    <small>Recommended: 50-60 characters</small>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="home_seo_description_<?php echo $langCode; ?>">SEO Description</label>
                                                    <textarea id="home_seo_description_<?php echo $langCode; ?>" 
                                                              name="home_seo_description" 
                                                              rows="3" 
                                                              placeholder="Watch live sports streams..."><?php echo htmlspecialchars($langHomeSeoData['description']); ?></textarea>
                                                    <small>Recommended: 150-160 characters</small>
                                                </div>
                                                
                                                <button type="submit" class="btn btn-primary btn-save">Save Home (<?php echo strtoupper($langCode); ?>)</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </details>
                    </div>
                </div>
                
                <!-- ADD NEW SPORT -->
                <div class="content-section add-sport-section">
                    <h3>Add New Sport Category</h3>
                    <form method="POST" class="add-sport-form">
                        <input type="hidden" name="add_sport" value="1">
                        <div class="add-sport-row">
                            <input type="text" name="new_sport_name" placeholder="Enter sport name (e.g., Basketball)" required>
                            <button type="submit" class="btn btn-primary">+ Add Sport</button>
                        </div>
                    </form>
                </div>
                
                <!-- SPORTS LIST -->
                <div class="content-section sports-section">
                    <h3>Sport Pages (<?php echo count($sports); ?>)</h3>
                    
                    <div class="pages-accordion" id="sportsAccordion">
                        <?php foreach ($sports as $sport): 
                            $sportSlug = sanitizeSportName($sport);
                            $status = getSportStatus($sport, $pagesSeo);
                            $seoTitle = $pagesSeo['sports'][$sportSlug]['title'] ?? '';
                            $seoDescription = $pagesSeo['sports'][$sportSlug]['description'] ?? '';
                            
                            // Get icon info using function from functions.php
                            $iconInfo = getMasterIcon($sport);
                            $hasIcon = $iconInfo['exists'];
                            $iconUrl = $hasIcon ? (SPORT_ICONS_URL_PATH . $iconInfo['filename']) : '';
                        ?>
                            <details class="sport-card" data-sport="<?php echo htmlspecialchars($sport); ?>">
                                <summary>
                                    <span class="drag-handle" title="Drag to reorder">||</span>
                                    <span class="status-dot <?php echo $status; ?>"></span>
                                    <span class="header-icon-small <?php echo $hasIcon ? 'has-icon' : 'no-icon'; ?>">
                                        <?php if ($hasIcon): ?>
                                            <img src="<?php echo $iconUrl; ?>?v=<?php echo time(); ?>" alt="<?php echo htmlspecialchars($sport); ?>" width="18" height="18">
                                        <?php else: ?>
                                            ?
                                        <?php endif; ?>
                                    </span>
                                    <span class="accordion-title"><?php echo htmlspecialchars($sport); ?></span>
                                </summary>
                                
                                <div class="accordion-content">
                                    <!-- LANGUAGE TABS -->
                                    <div class="lang-tabs-container" data-page-type="sport" data-sport="<?php echo htmlspecialchars($sport); ?>">
                                        <div class="lang-tabs">
                                            <?php foreach ($activeLanguages as $langCode => $langInfo): 
                                                $langSeoStatus = getLanguageSeoStatus($langCode, $previewDomain, 'sport', $sportSlug, null, $pagesSeo);
                                            ?>
                                                <button type="button" 
                                                        class="lang-tab seo-<?php echo $langSeoStatus; ?> <?php echo $langCode === 'en' ? 'active' : ''; ?>" 
                                                        data-lang="<?php echo htmlspecialchars($langCode); ?>"
                                                        title="<?php echo htmlspecialchars($langInfo['name']); ?>">
                                                    <img src="<?php echo FLAGS_URL_PATH . htmlspecialchars($langInfo['flag_code']); ?>.svg" 
                                                         alt="<?php echo htmlspecialchars($langInfo['name']); ?>" 
                                                         class="lang-tab-flag"
                                                         width="28" 
                                                         height="20">
                                                    <span class="lang-tab-code"><?php echo strtoupper(htmlspecialchars($langCode)); ?></span>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <!-- Language SEO Content Panels -->
                                        <?php foreach ($activeLanguages as $langCode => $langInfo): 
                                            $isEnglish = ($langCode === 'en');
                                            $langSportSeoData = $isEnglish 
                                                ? ['title' => $seoTitle, 'description' => $seoDescription]
                                                : getLanguageSeoData($langCode, $previewDomain, 'sport', $sportSlug);
                                        ?>
                                            <div class="lang-tab-content <?php echo $isEnglish ? 'active' : ''; ?>" data-lang="<?php echo htmlspecialchars($langCode); ?>">
                                                <form method="POST" class="sport-form">
                                                    <?php if ($isEnglish): ?>
                                                        <input type="hidden" name="update_sport" value="1">
                                                    <?php else: ?>
                                                        <input type="hidden" name="update_sport_lang" value="1">
                                                        <input type="hidden" name="lang_code" value="<?php echo htmlspecialchars($langCode); ?>">
                                                    <?php endif; ?>
                                                    <input type="hidden" name="sport_name" value="<?php echo htmlspecialchars($sport); ?>">
                                                    
                                                    <!-- SEO SECTION -->
                                                    <div class="form-section-title">SEO Settings (<?php echo htmlspecialchars($langInfo['name']); ?>)</div>
                                                    
                                                    <div class="form-group">
                                                        <label for="seo_title_<?php echo $sportSlug; ?>_<?php echo $langCode; ?>">SEO Title</label>
                                                        <input type="text" 
                                                               id="seo_title_<?php echo $sportSlug; ?>_<?php echo $langCode; ?>" 
                                                               name="seo_title" 
                                                               value="<?php echo htmlspecialchars($langSportSeoData['title']); ?>" 
                                                               placeholder="Live <?php echo htmlspecialchars($sport); ?> - <?php echo htmlspecialchars($website['site_name']); ?>">
                                                        <small>Recommended: 50-60 characters</small>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label for="seo_description_<?php echo $sportSlug; ?>_<?php echo $langCode; ?>">SEO Description</label>
                                                        <textarea id="seo_description_<?php echo $sportSlug; ?>_<?php echo $langCode; ?>" 
                                                                  name="seo_description" 
                                                                  rows="3" 
                                                                  placeholder="Watch <?php echo htmlspecialchars($sport); ?> live streams..."><?php echo htmlspecialchars($langSportSeoData['description']); ?></textarea>
                                                        <small>Recommended: 150-160 characters</small>
                                                    </div>
                                                    
                                                    <button type="submit" class="btn btn-primary btn-save">Save <?php echo htmlspecialchars($sport); ?> (<?php echo strtoupper($langCode); ?>)</button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- SPORT MANAGEMENT SECTION -->
                                    <div class="management-section">
                                        <div class="form-section-title">Sport Management</div>
                                        
                                        <div class="management-actions">
                                            <button type="button" class="btn-rename" onclick="openRenameModal('<?php echo htmlspecialchars($sport, ENT_QUOTES); ?>')">
                                                Rename Sport
                                            </button>
                                            <button type="button" class="btn-delete-sport" onclick="openDeleteModal('<?php echo htmlspecialchars($sport, ENT_QUOTES); ?>')">
                                                Delete Sport
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- RENAME MODAL -->
    <div class="modal" id="renameModal">
        <div class="modal-content modal-small">
            <h3>Rename Sport</h3>
            <p>Enter new name for: <strong id="currentSportName"></strong></p>
            <form method="POST" id="renameForm">
                <input type="hidden" name="rename_sport" value="1">
                <input type="hidden" name="old_sport_name" id="oldSportName">
                <div class="form-group">
                    <input type="text" name="new_sport_name" id="newSportName" placeholder="New sport name" required>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-outline" onclick="closeModal('renameModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Rename</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- DELETE MODAL -->
    <div class="modal" id="deleteModal">
        <div class="modal-content modal-small">
            <div class="modal-icon">!</div>
            <h3>Delete Sport</h3>
            <p>Are you sure you want to delete <strong id="deleteSportName"></strong>?</p>
            <div class="delete-warning">
                <strong>Warning:</strong> This will remove all SEO data for this sport. Type the sport name to confirm:
            </div>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="delete_sport" value="1">
                <input type="hidden" name="sport_to_delete" id="sportToDelete">
                <input type="hidden" id="expectedSportName">
                <div class="form-group">
                    <input type="text" id="confirmSportNameInput" placeholder="Type sport name to confirm" autocomplete="off">
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="confirmDeleteBtn" disabled>Delete</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- REORDER FORM (hidden) -->
    <form method="POST" id="reorderForm" style="display: none;">
        <input type="hidden" name="reorder_sports" value="1">
        <input type="hidden" name="sports_order" id="sportsOrderInput">
    </form>
    
    <script src="js/website-pages.js"></script>
</body>
</html>