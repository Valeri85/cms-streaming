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
$masterIconsDir = '/var/www/u1852176/data/www/streaming/shared/icons/sports/';

if (!file_exists($configFile)) {
    die("Configuration file not found at: " . $configFile);
}

// ==========================================
// HELPER FUNCTIONS
// ==========================================

function sanitizeSportName($sportName) {
    $filename = strtolower($sportName);
    $filename = str_replace(' ', '-', $filename);
    $filename = preg_replace('/[^a-z0-9\-]/', '', $filename);
    $filename = preg_replace('/-+/', '-', $filename);
    $filename = trim($filename, '-');
    return $filename;
}

// Check if master icon exists for a sport
function getMasterIcon($sportName, $masterIconsDir) {
    $sanitized = sanitizeSportName($sportName);
    $extensions = ['webp', 'svg', 'avif'];
    
    foreach ($extensions as $ext) {
        $path = $masterIconsDir . $sanitized . '.' . $ext;
        if (file_exists($path)) {
            return [
                'exists' => true,
                'filename' => $sanitized . '.' . $ext,
                'extension' => $ext
            ];
        }
    }
    
    return [
        'exists' => false,
        'filename' => null,
        'extension' => null
    ];
}

function sendSlackNotification($sportName) {
    $slackConfigFile = '/var/www/u1852176/data/www/streaming/config/slack-config.json';
    if (!file_exists($slackConfigFile)) {
        return false;
    }
    
    $slackConfig = json_decode(file_get_contents($slackConfigFile), true);
    $slackWebhookUrl = $slackConfig['webhook_url'] ?? '';
    
    if (empty($slackWebhookUrl)) {
        return false;
    }
    
    $message = [
        'text' => "🚨 *New Sport Category Added*",
        'blocks' => [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*New Sport Category:* " . $sportName . "\n\n⚠️ Please add SEO for new sport page in CMS."
                ]
            ]
        ]
    ];
    
    $ch = curl_init($slackWebhookUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

// Calculate status indicator for Home page (SEO only - no icon check)
function getHomeStatusIndicator($pagesSeo) {
    $seoData = $pagesSeo['home'] ?? [];
    $hasTitle = !empty(trim($seoData['title'] ?? ''));
    $hasDescription = !empty(trim($seoData['description'] ?? ''));
    
    if ($hasTitle && $hasDescription) {
        return '✅';
    } elseif ($hasTitle || $hasDescription) {
        return '⚠️';
    }
    return '❌';
}

// Calculate status indicator for sport pages
function getStatusIndicator($sportName, $pagesSeo) {
    $sportSlug = strtolower(str_replace(' ', '-', $sportName));
    $seoData = $pagesSeo['sports'][$sportSlug] ?? [];
    $hasTitle = !empty(trim($seoData['title'] ?? ''));
    $hasDescription = !empty(trim($seoData['description'] ?? ''));
    
    if ($hasTitle && $hasDescription) {
        return '✅';
    } elseif ($hasTitle || $hasDescription) {
        return '⚠️';
    }
    return '❌';
}

// ==========================================
// LOAD DATA
// ==========================================

$configContent = file_get_contents($configFile);
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

// ==========================================
// HANDLE POST REQUESTS
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $configContent = file_get_contents($configFile);
    $configData = json_decode($configContent, true);
    $websites = $configData['websites'] ?? [];
    
    foreach ($websites as $index => $site) {
        if ($site['id'] == $websiteId) {
            $websiteIndex = $index;
            break;
        }
    }
    
    if ($websiteIndex !== null) {
        // UPDATE HOME PAGE
        if (isset($_POST['update_home'])) {
            $websites[$websiteIndex]['pages_seo']['home'] = [
                'title' => trim($_POST['home_seo_title'] ?? ''),
                'description' => trim($_POST['home_seo_description'] ?? '')
            ];
            $success = "✅ Home page SEO updated!";
        }
        
        // ADD NEW SPORT
        if (isset($_POST['add_sport'])) {
            $newSport = trim($_POST['new_sport_name'] ?? '');
            
            if ($newSport) {
                if (!in_array($newSport, $websites[$websiteIndex]['sports_categories'])) {
                    $websites[$websiteIndex]['sports_categories'][] = $newSport;
                    $success = "✅ Sport category '{$newSport}' added!";
                    sendSlackNotification($newSport);
                } else {
                    $error = "❌ Sport category '{$newSport}' already exists!";
                }
            } else {
                $error = "❌ Please enter a sport name";
            }
        }
        
        // UPDATE SPORT (SEO only)
        if (isset($_POST['update_sport'])) {
            $sportName = $_POST['sport_name'] ?? '';
            $sportSlug = strtolower(str_replace(' ', '-', $sportName));
            
            $websites[$websiteIndex]['pages_seo']['sports'][$sportSlug] = [
                'title' => trim($_POST['seo_title'] ?? ''),
                'description' => trim($_POST['seo_description'] ?? '')
            ];
            
            $success = "✅ '{$sportName}' SEO updated!";
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
                        $oldSlug = strtolower(str_replace(' ', '-', $oldName));
                        $newSlug = strtolower(str_replace(' ', '-', $newName));
                        
                        if (isset($websites[$websiteIndex]['pages_seo']['sports'][$oldSlug])) {
                            $websites[$websiteIndex]['pages_seo']['sports'][$newSlug] = $websites[$websiteIndex]['pages_seo']['sports'][$oldSlug];
                            unset($websites[$websiteIndex]['pages_seo']['sports'][$oldSlug]);
                        }
                        
                        $success = "✅ Sport renamed: '{$oldName}' → '{$newName}'";
                    } else {
                        $error = "❌ Sport category '{$newName}' already exists!";
                    }
                } else {
                    $error = "❌ Sport category '{$oldName}' not found!";
                }
            } else {
                $error = "❌ Please enter a valid sport name";
            }
        }
        
        // DELETE SPORT
        if (isset($_POST['delete_sport'])) {
            $sportToDelete = $_POST['sport_name'] ?? '';
            $confirmName = $_POST['confirm_sport_name'] ?? '';
            
            if ($sportToDelete === $confirmName) {
                $sports = $websites[$websiteIndex]['sports_categories'];
                $sports = array_filter($sports, function($sport) use ($sportToDelete) {
                    return $sport !== $sportToDelete;
                });
                $websites[$websiteIndex]['sports_categories'] = array_values($sports);
                
                $sportSlug = strtolower(str_replace(' ', '-', $sportToDelete));
                if (isset($websites[$websiteIndex]['pages_seo']['sports'][$sportSlug])) {
                    unset($websites[$websiteIndex]['pages_seo']['sports'][$sportSlug]);
                }
                
                $success = "✅ Sport category '{$sportToDelete}' deleted";
            } else {
                $error = "❌ Sport name doesn't match. Deletion cancelled.";
            }
        }
        
        // REORDER SPORTS
        if (isset($_POST['reorder_sports'])) {
            $newOrder = json_decode($_POST['sports_order'] ?? '[]', true);
            
            if (is_array($newOrder) && count($newOrder) > 0) {
                $websites[$websiteIndex]['sports_categories'] = $newOrder;
                $success = "✅ Sports order updated!";
            } else {
                $error = "❌ Invalid sports order data";
            }
        }
        
        // Save changes
        if ($success || $error) {
            $configData['websites'] = $websites;
            $jsonContent = json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            if (!file_put_contents($configFile, $jsonContent)) {
                $error = '❌ Failed to save changes. Check permissions: chmod 644 ' . $configFile;
                $success = '';
            } else {
                $configContent = file_get_contents($configFile);
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
$homeStatus = getHomeStatusIndicator($pagesSeo);

// Get home icon from master
$homeIconInfo = getMasterIcon('home', $masterIconsDir);
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
                <a href="dashboard.php" class="btn">← Back to Dashboard</a>
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
                                <span class="status-indicator"><?php echo $homeStatus; ?></span>
                                <span class="header-icon-small <?php echo $homeIconInfo['exists'] ? 'has-icon' : 'no-icon'; ?>">
                                    <?php if ($homeIconInfo['exists']): ?>
                                        <img src="/shared/icons/sports/<?php echo htmlspecialchars($homeIconInfo['filename']); ?>?v=<?php echo time(); ?>" alt="Home">
                                    <?php else: ?>
                                        🏠
                                    <?php endif; ?>
                                </span>
                                <span class="accordion-title">
                                    <span class="home-badge">HOME</span>
                                    Home Page
                                </span>
                            </summary>
                            
                            <div class="accordion-content">
                                <form method="POST" class="sport-form">
                                    <input type="hidden" name="update_home" value="1">
                                    
                                    <!-- SEO SECTION -->
                                    <div class="form-section-title">🔍 SEO Settings</div>
                                    
                                    <div class="form-group">
                                        <label for="home_seo_title">SEO Title</label>
                                        <input type="text" id="home_seo_title" name="home_seo_title" value="<?php echo htmlspecialchars($pagesSeo['home']['title'] ?? ''); ?>" placeholder="<?php echo htmlspecialchars($website['site_name']); ?> - Live Sports Streaming">
                                        <small>Recommended: 50-60 characters</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="home_seo_description">SEO Description</label>
                                        <textarea id="home_seo_description" name="home_seo_description" rows="3" placeholder="Watch live sports streams..."><?php echo htmlspecialchars($pagesSeo['home']['description'] ?? ''); ?></textarea>
                                        <small>Recommended: 150-160 characters</small>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary btn-save">💾 Save Home Page</button>
                                </form>
                            </div>
                        </details>
                    </div>
                </div>
                
                <!-- ADD NEW SPORT SECTION -->
                <div class="add-sport-card">
                    <h3>➕ Add New Sport Category</h3>
                    <form method="POST" class="add-sport-form-wrapper">
                        <div class="add-sport-form">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="new_sport_name">Sport Name *</label>
                                <input type="text" id="new_sport_name" name="new_sport_name" placeholder="e.g., Rugby League" required>
                            </div>
                            
                            <button type="submit" name="add_sport" class="btn btn-primary" style="height: fit-content;">Add Sport</button>
                        </div>
                        <small style="display: block; margin-top: 10px; color: #666;">💡 Icons are managed globally in <a href="icons.php" style="color: #2196f3;">Sport Icons</a></small>
                    </form>
                </div>
                
                <!-- SPORTS LIST -->
                <div class="content-section">
                    <div class="section-header">
                        <h2>Sport Pages (<?php echo count($sports); ?>)</h2>
                    </div>
                    
                    <div class="sports-count-info">
                        <span>💡 Drag accordions to reorder (affects left menu on website)</span>
                    </div>
                    
                    <div class="pages-accordion" id="pagesAccordions">
                        <?php foreach ($sports as $sport): 
                            $sportSlug = strtolower(str_replace(' ', '-', $sport));
                            
                            // Get master icon
                            $iconInfo = getMasterIcon($sport, $masterIconsDir);
                            $hasIcon = $iconInfo['exists'];
                            $iconUrl = $hasIcon ? '/shared/icons/sports/' . $iconInfo['filename'] : '';
                            
                            $seoData = $pagesSeo['sports'][$sportSlug] ?? [];
                            $seoTitle = $seoData['title'] ?? '';
                            $seoDescription = $seoData['description'] ?? '';
                            
                            $status = getStatusIndicator($sport, $pagesSeo);
                        ?>
                            <details data-sport-name="<?php echo htmlspecialchars($sport); ?>">
                                <summary>
                                    <span class="drag-handle" title="Drag to reorder">⋮⋮</span>
                                    <span class="status-indicator"><?php echo $status; ?></span>
                                    <span class="header-icon-small <?php echo $hasIcon ? 'has-icon' : 'no-icon'; ?>">
                                        <?php if ($hasIcon): ?>
                                            <img src="<?php echo $iconUrl; ?>?v=<?php echo time(); ?>" alt="<?php echo htmlspecialchars($sport); ?>">
                                        <?php else: ?>
                                            ?
                                        <?php endif; ?>
                                    </span>
                                    <span class="accordion-title"><?php echo htmlspecialchars($sport); ?></span>
                                </summary>
                                
                                <div class="accordion-content">
                                    <form method="POST" class="sport-form">
                                        <input type="hidden" name="update_sport" value="1">
                                        <input type="hidden" name="sport_name" value="<?php echo htmlspecialchars($sport); ?>">
                                        
                                        <!-- SEO SECTION -->
                                        <div class="form-section-title">🔍 SEO Settings</div>
                                        
                                        <div class="form-group">
                                            <label for="seo_title_<?php echo $sportSlug; ?>">SEO Title</label>
                                            <input type="text" id="seo_title_<?php echo $sportSlug; ?>" name="seo_title" value="<?php echo htmlspecialchars($seoTitle); ?>" placeholder="Live <?php echo htmlspecialchars($sport); ?> - <?php echo htmlspecialchars($website['site_name']); ?>">
                                            <small>Recommended: 50-60 characters</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="seo_description_<?php echo $sportSlug; ?>">SEO Description</label>
                                            <textarea id="seo_description_<?php echo $sportSlug; ?>" name="seo_description" rows="3" placeholder="Watch <?php echo htmlspecialchars($sport); ?> live streams..."><?php echo htmlspecialchars($seoDescription); ?></textarea>
                                            <small>Recommended: 150-160 characters</small>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary btn-save">💾 Save <?php echo htmlspecialchars($sport); ?></button>
                                    </form>
                                    
                                    <!-- SPORT MANAGEMENT SECTION -->
                                    <div class="management-section">
                                        <div class="form-section-title">⚙️ Sport Management</div>
                                        
                                        <div class="management-actions">
                                            <button type="button" class="btn-rename" onclick="openRenameModal('<?php echo htmlspecialchars($sport, ENT_QUOTES); ?>')">
                                                ✏️ Rename Sport
                                            </button>
                                            <button type="button" class="btn-delete-sport" onclick="openDeleteModal('<?php echo htmlspecialchars($sport, ENT_QUOTES); ?>')">
                                                🗑️ Delete Sport
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
    
    <!-- HIDDEN FORM FOR REORDER -->
    <form id="reorderForm" method="POST" style="display: none;">
        <input type="hidden" name="reorder_sports" value="1">
        <input type="hidden" name="sports_order" id="sportsOrderInput">
    </form>
    
    <!-- RENAME MODAL -->
    <div class="modal" id="renameModal">
        <div class="modal-content">
            <h3>✏️ Rename Sport</h3>
            <form method="POST">
                <input type="hidden" name="rename_sport" value="1">
                <input type="hidden" name="old_sport_name" id="oldSportName">
                <div class="form-group">
                    <label>Current Name</label>
                    <input type="text" id="currentNameDisplay" disabled>
                </div>
                <div class="form-group">
                    <label for="newSportNameInput">New Name</label>
                    <input type="text" id="newSportNameInput" name="new_sport_name" required>
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
        <div class="modal-content">
            <div class="modal-icon">🗑️</div>
            <h3>Delete Sport Category</h3>
            <p>This will permanently delete the sport category and all SEO data.</p>
            <p class="delete-warning">To confirm, type the sport name: <strong id="deleteSportNameDisplay"></strong></p>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="delete_sport" value="1">
                <input type="hidden" name="sport_name" id="deleteSportName">
                <div class="form-group">
                    <input type="text" id="confirmSportNameInput" name="confirm_sport_name" placeholder="Type sport name to confirm" required autocomplete="off">
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="confirmDeleteBtn" disabled>Delete Sport</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- SAVE ORDER MODAL -->
    <div class="modal" id="saveOrderModal">
        <div class="modal-content modal-small">
            <div class="modal-icon">💾</div>
            <h3>Save Changes?</h3>
            <p>Do you want to save the new sport order?</p>
            <div class="modal-buttons">
                <button type="button" class="btn btn-outline" onclick="cancelOrderChange()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="confirmSaveOrder()">Yes, Save Order</button>
            </div>
        </div>
    </div>
    
    <script src="js/website-pages.js"></script>
</body>
</html>