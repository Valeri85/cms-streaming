<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$langCode = $_GET['code'] ?? null;
$error = '';
$success = '';

if (!$langCode) {
    header('Location: languages.php');
    exit;
}

$langDir = '/var/www/u1852176/data/www/streaming/config/lang/';
$langFile = $langDir . $langCode . '.json';
$englishFile = $langDir . 'en.json';

// Check if language file exists
if (!file_exists($langFile)) {
    header('Location: languages.php');
    exit;
}

// Load English (master) for reference
$englishData = [];
if (file_exists($englishFile)) {
    $englishData = json_decode(file_get_contents($englishFile), true);
}

// Load current language data
$langData = json_decode(file_get_contents($langFile), true);

if (!$langData) {
    $error = "❌ Failed to parse language file. Invalid JSON.";
    $langData = [];
}

$isEnglish = ($langCode === 'en');

// ==========================================
// HANDLE FORM SUBMISSION
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_translations'])) {
    
    // Update language info
    if (isset($_POST['lang_name'])) {
        $langData['language_info']['name'] = trim($_POST['lang_name']);
    }
    if (isset($_POST['lang_flag'])) {
        $langData['language_info']['flag'] = trim($_POST['lang_flag']);
    }
    
    // Update UI translations
    if (isset($_POST['ui']) && is_array($_POST['ui'])) {
        foreach ($_POST['ui'] as $key => $value) {
            $langData['ui'][$key] = $value;
        }
    }
    
    // Update messages translations
    if (isset($_POST['messages']) && is_array($_POST['messages'])) {
        foreach ($_POST['messages'] as $key => $value) {
            $langData['messages'][$key] = $value;
        }
    }
    
    // Update accessibility translations
    if (isset($_POST['accessibility']) && is_array($_POST['accessibility'])) {
        foreach ($_POST['accessibility'] as $key => $value) {
            $langData['accessibility'][$key] = $value;
        }
    }
    
    // Update footer translations
    if (isset($_POST['footer']) && is_array($_POST['footer'])) {
        foreach ($_POST['footer'] as $key => $value) {
            $langData['footer'][$key] = $value;
        }
    }
    
    // Update sports translations
    if (isset($_POST['sports']) && is_array($_POST['sports'])) {
        foreach ($_POST['sports'] as $key => $value) {
            $langData['sports'][$key] = $value;
        }
    }
    
    // Save to file
    $jsonContent = json_encode($langData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    if (file_put_contents($langFile, $jsonContent)) {
        $success = "✅ Translations saved successfully!";
    } else {
        $error = "❌ Failed to save translations. Check file permissions.";
    }
}

// Get language info
$langName = $langData['language_info']['name'] ?? $langCode;
$langFlag = $langData['language_info']['flag'] ?? '🏳️';

// ==========================================
// HELPER FUNCTION: Get English reference text
// ==========================================
function getEnglishText($section, $key, $englishData) {
    return $englishData[$section][$key] ?? '';
}

// ==========================================
// TRANSLATION SECTIONS CONFIGURATION
// ==========================================
$sections = [
    'ui' => [
        'title' => '🖥️ User Interface',
        'description' => 'Main navigation and interface elements',
        'keys' => [
            'home' => 'Home menu item',
            'favorites' => 'Favorites link',
            'sports' => 'Sports section title',
            'soon' => 'Soon tab filter',
            'tomorrow' => 'Tomorrow tab filter',
            'all' => 'All tab filter',
            'live_sports_streaming' => 'Main page title',
            'my_favorites' => 'Favorites page title'
        ]
    ],
    'messages' => [
        'title' => '💬 Messages',
        'description' => 'Dynamic messages shown to users',
        'keys' => [
            'no_games' => 'No games available message',
            'no_favorites' => 'Empty favorites message',
            'loading_favorites' => 'Loading state text',
            'available_streams' => 'Streams section header',
            'no_streams' => 'No streams available',
            'watch_stream' => 'Watch stream button',
            'error_loading_links' => 'Error loading links'
        ]
    ],
    'accessibility' => [
        'title' => '♿ Accessibility',
        'description' => 'Screen reader and ARIA labels',
        'keys' => [
            'toggle_dark_mode' => 'Dark mode button label',
            'toggle_menu' => 'Menu button label',
            'favorite_game' => 'Favorite star label',
            'favorite_league' => 'League favorite label',
            'time_filter' => 'Time tabs label',
            'live_games' => 'Games section label',
            'change_language' => 'Language switcher label'
        ]
    ],
    'footer' => [
        'title' => '📄 Footer',
        'description' => 'Footer section texts',
        'keys' => [
            'sports' => 'Sports section heading',
            'quick_links' => 'Quick links heading',
            'about' => 'About section heading',
            'about_us' => 'About us link',
            'contact' => 'Contact link',
            'privacy_policy' => 'Privacy policy link',
            'terms_of_service' => 'Terms of service link',
            'copyright' => 'Copyright text',
            'footer_description' => 'Site description in footer'
        ]
    ]
];

// Get sports list from English file
$sportsList = $englishData['sports'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit <?php echo htmlspecialchars($langName); ?> - Languages - CMS</title>
    <link rel="stylesheet" href="cms-style.css">
    <link rel="stylesheet" href="css/languages.css">
    <style>
        /* Additional styles for edit page */
        .language-edit-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            color: white;
        }
        
        .language-edit-flag {
            font-size: 64px;
        }
        
        .language-edit-info h2 {
            margin: 0 0 5px 0;
            font-size: 28px;
        }
        
        .language-edit-info p {
            margin: 0;
            opacity: 0.9;
        }
        
        .save-bar {
            position: sticky;
            bottom: 20px;
            background: white;
            padding: 15px 25px;
            border-radius: 12px;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            border: 2px solid #e9ecef;
        }
        
        .save-bar-info {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .english-reference {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 4px;
            font-style: italic;
        }
        
        .english-reference strong {
            color: #3498db;
        }
        
        .lang-info-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 2px solid #e9ecef;
        }
        
        .lang-info-section h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
        }
        
        .lang-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .section-nav {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .section-nav a {
            padding: 8px 16px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            color: #2c3e50;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .section-nav a:hover {
            border-color: #3498db;
            color: #3498db;
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
                <a href="languages.php" class="nav-item active">
                    <span>🌐</span> Languages
                </a>
            </nav>
            
            <div class="cms-user">
                <a href="logout.php" class="btn btn-sm btn-outline">Logout</a>
            </div>
        </aside>
        
        <main class="cms-main">
            <header class="cms-header">
                <h1>Edit Language</h1>
                <a href="languages.php" class="btn">← Back to Languages</a>
            </header>
            
            <div class="cms-content">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <!-- Language Header -->
                <div class="language-edit-header">
                    <span class="language-edit-flag"><?php echo htmlspecialchars($langFlag); ?></span>
                    <div class="language-edit-info">
                        <h2><?php echo htmlspecialchars($langName); ?></h2>
                        <p>Language Code: <strong><?php echo htmlspecialchars($langCode); ?></strong></p>
                    </div>
                </div>
                
                <!-- Quick Navigation -->
                <div class="section-nav">
                    <span style="padding: 8px 0; color: #7f8c8d;">Jump to:</span>
                    <a href="#section-info">ℹ️ Language Info</a>
                    <?php foreach ($sections as $sectionKey => $section): ?>
                        <a href="#section-<?php echo $sectionKey; ?>"><?php echo $section['title']; ?></a>
                    <?php endforeach; ?>
                    <a href="#section-sports">⚽ Sports</a>
                </div>
                
                <form method="POST" id="translationsForm">
                    <input type="hidden" name="save_translations" value="1">
                    
                    <!-- Language Info Section -->
                    <div class="lang-info-section" id="section-info">
                        <h3>ℹ️ Language Information</h3>
                        <div class="lang-info-grid">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="lang_name">Language Name</label>
                                <input type="text" id="lang_name" name="lang_name" value="<?php echo htmlspecialchars($langName); ?>" required>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="lang_flag">Flag Emoji</label>
                                <input type="text" id="lang_flag" name="lang_flag" value="<?php echo htmlspecialchars($langFlag); ?>" required style="font-size: 24px; text-align: center;">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Language Code</label>
                                <input type="text" value="<?php echo htmlspecialchars($langCode); ?>" disabled style="background: #e9ecef;">
                                <small>Cannot be changed</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Translation Sections -->
                    <?php foreach ($sections as $sectionKey => $section): ?>
                        <div class="translation-section" id="section-<?php echo $sectionKey; ?>">
                            <div class="translation-section-header">
                                <div class="translation-section-title">
                                    <?php echo $section['title']; ?>
                                    <span class="translation-section-count"><?php echo count($section['keys']); ?> items</span>
                                </div>
                            </div>
                            <div class="translation-section-body">
                                <p style="color: #7f8c8d; margin-bottom: 15px; font-size: 14px;"><?php echo $section['description']; ?></p>
                                
                                <?php foreach ($section['keys'] as $key => $description): 
                                    $currentValue = $langData[$sectionKey][$key] ?? '';
                                    $englishValue = getEnglishText($sectionKey, $key, $englishData);
                                ?>
                                    <div class="translation-row">
                                        <div class="translation-key" title="<?php echo htmlspecialchars($description); ?>">
                                            <?php echo htmlspecialchars($key); ?>
                                        </div>
                                        <div class="translation-value">
                                            <input type="text" 
                                                   name="<?php echo $sectionKey; ?>[<?php echo htmlspecialchars($key); ?>]" 
                                                   value="<?php echo htmlspecialchars($currentValue); ?>"
                                                   placeholder="<?php echo htmlspecialchars($englishValue); ?>">
                                            <?php if (!$isEnglish && $englishValue): ?>
                                                <div class="english-reference">
                                                    <strong>EN:</strong> <?php echo htmlspecialchars($englishValue); ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="english-reference"><?php echo htmlspecialchars($description); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Sports Section -->
                    <div class="translation-section" id="section-sports">
                        <div class="translation-section-header">
                            <div class="translation-section-title">
                                ⚽ Sports Names
                                <span class="translation-section-count"><?php echo count($sportsList); ?> items</span>
                            </div>
                        </div>
                        <div class="translation-section-body">
                            <p style="color: #7f8c8d; margin-bottom: 15px; font-size: 14px;">
                                Sport names displayed in the sidebar menu. The key (English name) is used for matching with game data.
                            </p>
                            
                            <div class="sports-translation-grid">
                                <?php foreach ($sportsList as $sportKey => $sportEnglish): 
                                    $currentValue = $langData['sports'][$sportKey] ?? $sportEnglish;
                                ?>
                                    <div class="sport-translation-item">
                                        <span class="sport-english-name" title="Key: <?php echo htmlspecialchars($sportKey); ?>">
                                            <?php echo htmlspecialchars($sportKey); ?>
                                        </span>
                                        <input type="text" 
                                               name="sports[<?php echo htmlspecialchars($sportKey); ?>]" 
                                               value="<?php echo htmlspecialchars($currentValue); ?>"
                                               placeholder="<?php echo htmlspecialchars($sportEnglish); ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sticky Save Bar -->
                    <div class="save-bar">
                        <div class="save-bar-info">
                            💡 Changes are saved when you click "Save All Translations"
                        </div>
                        <div>
                            <a href="languages.php" class="btn" style="margin-right: 10px;">Cancel</a>
                            <button type="submit" class="btn btn-primary">💾 Save All Translations</button>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script>
        // Smooth scroll to sections
        document.querySelectorAll('.section-nav a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                const target = document.getElementById(targetId);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
        
        // Warn before leaving with unsaved changes
        let formChanged = false;
        
        document.getElementById('translationsForm').addEventListener('input', function() {
            formChanged = true;
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        
        document.getElementById('translationsForm').addEventListener('submit', function() {
            formChanged = false;
        });
    </script>
</body>
</html>