<?php
session_start();

// Check login (support both old and new session)
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$langDir = '/var/www/u1852176/data/www/streaming/config/lang/';
$error = '';
$success = '';

// Create lang directory if it doesn't exist
if (!file_exists($langDir)) {
    mkdir($langDir, 0755, true);
}

// ==========================================
// MASTER FLAG LOOKUP - 50 Most Common Languages
// This ensures flags are ALWAYS emojis, never text codes
// ==========================================
$FLAG_LOOKUP = [
    // Major World Languages
    'en' => '🇬🇧', // English (UK flag as default)
    'es' => '🇪🇸', // Spanish
    'fr' => '🇫🇷', // French
    'de' => '🇩🇪', // German
    'it' => '🇮🇹', // Italian
    'pt' => '🇵🇹', // Portuguese
    'ru' => '🇷🇺', // Russian
    'zh' => '🇨🇳', // Chinese
    'ja' => '🇯🇵', // Japanese
    'ko' => '🇰🇷', // Korean
    'ar' => '🇸🇦', // Arabic
    'hi' => '🇮🇳', // Hindi
    
    // European Languages
    'nl' => '🇳🇱', // Dutch
    'pl' => '🇵🇱', // Polish
    'tr' => '🇹🇷', // Turkish
    'sv' => '🇸🇪', // Swedish
    'no' => '🇳🇴', // Norwegian
    'da' => '🇩🇰', // Danish
    'fi' => '🇫🇮', // Finnish
    'cs' => '🇨🇿', // Czech
    'el' => '🇬🇷', // Greek
    'hu' => '🇭🇺', // Hungarian
    'ro' => '🇷🇴', // Romanian
    'uk' => '🇺🇦', // Ukrainian
    'bg' => '🇧🇬', // Bulgarian
    'hr' => '🇭🇷', // Croatian
    'sk' => '🇸🇰', // Slovak
    'sl' => '🇸🇮', // Slovenian
    'sr' => '🇷🇸', // Serbian
    'et' => '🇪🇪', // Estonian
    'lv' => '🇱🇻', // Latvian
    'lt' => '🇱🇹', // Lithuanian
    'ca' => '🇪🇸', // Catalan (Spain flag)
    'eu' => '🇪🇸', // Basque (Spain flag)
    'gl' => '🇪🇸', // Galician (Spain flag)
    
    // Asian Languages
    'th' => '🇹🇭', // Thai
    'vi' => '🇻🇳', // Vietnamese
    'id' => '🇮🇩', // Indonesian
    'ms' => '🇲🇾', // Malay
    'tl' => '🇵🇭', // Filipino/Tagalog
    'bn' => '🇧🇩', // Bengali
    'ta' => '🇮🇳', // Tamil
    'te' => '🇮🇳', // Telugu
    'mr' => '🇮🇳', // Marathi
    'gu' => '🇮🇳', // Gujarati
    'kn' => '🇮🇳', // Kannada
    'ml' => '🇮🇳', // Malayalam
    'pa' => '🇮🇳', // Punjabi
    'ur' => '🇵🇰', // Urdu
    
    // Middle Eastern
    'he' => '🇮🇱', // Hebrew
    'fa' => '🇮🇷', // Persian/Farsi
    
    // African
    'sw' => '🇰🇪', // Swahili
    'af' => '🇿🇦', // Afrikaans
    
    // Regional Variants
    'pt-br' => '🇧🇷', // Brazilian Portuguese
    'en-us' => '🇺🇸', // American English
    'zh-tw' => '🇹🇼', // Traditional Chinese
];

// ==========================================
// 50 LANGUAGE PRESETS - Quick Selection
// Most commonly used languages for sports streaming
// ==========================================
$languagePresets = [
    // Tier 1: Most Popular (Top Row)
    'en' => ['name' => 'English', 'flag' => '🇬🇧'],
    'es' => ['name' => 'Español', 'flag' => '🇪🇸'],
    'fr' => ['name' => 'Français', 'flag' => '🇫🇷'],
    'de' => ['name' => 'Deutsch', 'flag' => '🇩🇪'],
    'it' => ['name' => 'Italiano', 'flag' => '🇮🇹'],
    'pt' => ['name' => 'Português', 'flag' => '🇵🇹'],
    'ru' => ['name' => 'Русский', 'flag' => '🇷🇺'],
    'zh' => ['name' => '中文', 'flag' => '🇨🇳'],
    'ja' => ['name' => '日本語', 'flag' => '🇯🇵'],
    'ko' => ['name' => '한국어', 'flag' => '🇰🇷'],
    'ar' => ['name' => 'العربية', 'flag' => '🇸🇦'],
    'hi' => ['name' => 'हिन्दी', 'flag' => '🇮🇳'],
    
    // Tier 2: European Languages
    'nl' => ['name' => 'Nederlands', 'flag' => '🇳🇱'],
    'pl' => ['name' => 'Polski', 'flag' => '🇵🇱'],
    'tr' => ['name' => 'Türkçe', 'flag' => '🇹🇷'],
    'sv' => ['name' => 'Svenska', 'flag' => '🇸🇪'],
    'no' => ['name' => 'Norsk', 'flag' => '🇳🇴'],
    'da' => ['name' => 'Dansk', 'flag' => '🇩🇰'],
    'fi' => ['name' => 'Suomi', 'flag' => '🇫🇮'],
    'cs' => ['name' => 'Čeština', 'flag' => '🇨🇿'],
    'el' => ['name' => 'Ελληνικά', 'flag' => '🇬🇷'],
    'hu' => ['name' => 'Magyar', 'flag' => '🇭🇺'],
    'ro' => ['name' => 'Română', 'flag' => '🇷🇴'],
    'uk' => ['name' => 'Українська', 'flag' => '🇺🇦'],
    'bg' => ['name' => 'Български', 'flag' => '🇧🇬'],
    'hr' => ['name' => 'Hrvatski', 'flag' => '🇭🇷'],
    'sk' => ['name' => 'Slovenčina', 'flag' => '🇸🇰'],
    'sl' => ['name' => 'Slovenščina', 'flag' => '🇸🇮'],
    'sr' => ['name' => 'Српски', 'flag' => '🇷🇸'],
    'et' => ['name' => 'Eesti', 'flag' => '🇪🇪'],
    'lv' => ['name' => 'Latviešu', 'flag' => '🇱🇻'],
    'lt' => ['name' => 'Lietuvių', 'flag' => '🇱🇹'],
    'ca' => ['name' => 'Català', 'flag' => '🇪🇸'],
    
    // Tier 3: Asian Languages
    'th' => ['name' => 'ไทย', 'flag' => '🇹🇭'],
    'vi' => ['name' => 'Tiếng Việt', 'flag' => '🇻🇳'],
    'id' => ['name' => 'Bahasa Indonesia', 'flag' => '🇮🇩'],
    'ms' => ['name' => 'Bahasa Melayu', 'flag' => '🇲🇾'],
    'tl' => ['name' => 'Filipino', 'flag' => '🇵🇭'],
    'bn' => ['name' => 'বাংলা', 'flag' => '🇧🇩'],
    'ta' => ['name' => 'தமிழ்', 'flag' => '🇮🇳'],
    'te' => ['name' => 'తెలుగు', 'flag' => '🇮🇳'],
    'ur' => ['name' => 'اردو', 'flag' => '🇵🇰'],
    
    // Tier 4: Middle Eastern & African
    'he' => ['name' => 'עברית', 'flag' => '🇮🇱'],
    'fa' => ['name' => 'فارسی', 'flag' => '🇮🇷'],
    'sw' => ['name' => 'Kiswahili', 'flag' => '🇰🇪'],
    'af' => ['name' => 'Afrikaans', 'flag' => '🇿🇦'],
    
    // Tier 5: Regional Variants (Popular for sports)
    'pt-br' => ['name' => 'Português (Brasil)', 'flag' => '🇧🇷'],
    'en-us' => ['name' => 'English (US)', 'flag' => '🇺🇸'],
    'zh-tw' => ['name' => '繁體中文', 'flag' => '🇹🇼'],
];

// Load existing languages for "copy from" dropdown
$existingLanguages = [];
if (is_dir($langDir)) {
    $files = glob($langDir . '*.json');
    
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        
        if ($data && isset($data['language_info'])) {
            $code = $data['language_info']['code'];
            $existingLanguages[$code] = [
                'name' => $data['language_info']['name'] ?? $code,
                'flag' => $data['language_info']['flag'] ?? '🏳️'
            ];
        }
    }
}

// Remove already existing languages from presets
foreach ($existingLanguages as $code => $info) {
    unset($languagePresets[$code]);
}

// ==========================================
// HELPER FUNCTION: Validate and fix flag
// Ensures flag is always an emoji, never text
// ==========================================
function validateFlag($flag, $langCode, $FLAG_LOOKUP) {
    // Check if flag looks like an emoji (contains special unicode chars)
    // Flag emojis are typically 4+ bytes
    if (mb_strlen($flag) >= 1 && mb_strlen($flag) <= 4 && strlen($flag) >= 4) {
        return $flag; // Looks like an emoji
    }
    
    // Flag is probably text (like "IT" or "EN"), look up correct emoji
    if (isset($FLAG_LOOKUP[$langCode])) {
        return $FLAG_LOOKUP[$langCode];
    }
    
    // Default fallback
    return '🏳️';
}

// ==========================================
// HANDLE FORM SUBMISSION
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_language'])) {
    $langCode = strtolower(trim($_POST['lang_code'] ?? ''));
    $langName = trim($_POST['lang_name'] ?? '');
    $langFlag = trim($_POST['lang_flag'] ?? '🏳️');
    $copyFrom = $_POST['copy_from'] ?? '';
    
    // Validation
    if (empty($langCode)) {
        $error = "❌ Language code is required.";
    } elseif (!preg_match('/^[a-z]{2,3}(-[a-z]{2})?$/', $langCode)) {
        $error = "❌ Language code must be 2-3 lowercase letters, optionally with region (e.g., 'fr', 'pt-br').";
    } elseif (empty($langName)) {
        $error = "❌ Language name is required.";
    } elseif (file_exists($langDir . $langCode . '.json')) {
        $error = "❌ Language '{$langCode}' already exists!";
    } else {
        // ✅ Validate and fix flag if needed
        $langFlag = validateFlag($langFlag, $langCode, $FLAG_LOOKUP);
        
        // Create new language file
        $newLangData = [
            'language_info' => [
                'code' => $langCode,
                'name' => $langName,
                'flag' => $langFlag,
                'active' => true
            ],
            'ui' => [],
            'messages' => [],
            'accessibility' => [],
            'footer' => [],
            'sports' => []
        ];
        
        // Copy from existing language if selected
        if (!empty($copyFrom) && file_exists($langDir . $copyFrom . '.json')) {
            $sourceData = json_decode(file_get_contents($langDir . $copyFrom . '.json'), true);
            
            if ($sourceData) {
                // Copy all translation sections
                $newLangData['ui'] = $sourceData['ui'] ?? [];
                $newLangData['messages'] = $sourceData['messages'] ?? [];
                $newLangData['accessibility'] = $sourceData['accessibility'] ?? [];
                $newLangData['footer'] = $sourceData['footer'] ?? [];
                $newLangData['sports'] = $sourceData['sports'] ?? [];
            }
        } else {
            // Create empty structure from English template
            $englishFile = $langDir . 'en.json';
            if (file_exists($englishFile)) {
                $englishData = json_decode(file_get_contents($englishFile), true);
                
                if ($englishData) {
                    // Copy structure with empty values (or English as placeholder)
                    foreach (['ui', 'messages', 'accessibility', 'footer', 'sports'] as $section) {
                        if (isset($englishData[$section])) {
                            foreach ($englishData[$section] as $key => $value) {
                                $newLangData[$section][$key] = ''; // Empty for translation
                            }
                        }
                    }
                }
            }
        }
        
        // Save new language file
        $newLangFile = $langDir . $langCode . '.json';
        $jsonContent = json_encode($newLangData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($newLangFile, $jsonContent)) {
            // Set proper permissions
            chmod($newLangFile, 0644);
            
            $copyMessage = !empty($copyFrom) ? " Translations copied from {$existingLanguages[$copyFrom]['name']}." : "";
            $success = "✅ Language '{$langName}' ({$langCode}) created successfully!{$copyMessage}";
            
            // Redirect to edit page after short delay
            header("Refresh: 2; url=language-edit.php?code={$langCode}");
        } else {
            $error = "❌ Failed to create language file. Check directory permissions.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Language - CMS</title>
    <link rel="stylesheet" href="cms-style.css">
    <link rel="stylesheet" href="css/languages.css">
    <style>
        .add-language-container {
            max-width: 900px;
        }
        
        .flag-preview-large {
            font-size: 80px;
            text-align: center;
            padding: 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            margin-bottom: 25px;
        }
        
        .presets-section {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .presets-section h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-size: 16px;
        }
        
        .presets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 10px;
            max-height: 400px;
            overflow-y: auto;
            padding-right: 10px;
        }
        
        .presets-grid::-webkit-scrollbar {
            width: 8px;
        }
        
        .presets-grid::-webkit-scrollbar-track {
            background: #e9ecef;
            border-radius: 4px;
        }
        
        .presets-grid::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 4px;
        }
        
        .preset-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-align: left;
        }
        
        .preset-btn:hover {
            border-color: #3498db;
            background: #e3f2fd;
        }
        
        .preset-btn.selected {
            border-color: #27ae60;
            background: #e8f5e9;
        }
        
        .preset-flag {
            font-size: 24px;
        }
        
        .preset-info {
            flex: 1;
            min-width: 0;
        }
        
        .preset-name {
            font-weight: 600;
            color: #2c3e50;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .preset-code {
            font-size: 11px;
            color: #7f8c8d;
        }
        
        .manual-input-section {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .manual-input-section h3 {
            margin: 0 0 20px 0;
            color: #2c3e50;
            font-size: 16px;
        }
        
        .input-row {
            display: grid;
            grid-template-columns: 1fr 1fr 120px;
            gap: 15px;
            align-items: end;
        }
        
        .copy-from-section {
            background: #e3f2fd;
            border: 2px solid #2196f3;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .copy-from-section h4 {
            margin: 0 0 15px 0;
            color: #1565c0;
        }
        
        .copy-options {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
        }
        
        .copy-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .copy-option:hover {
            border-color: #2196f3;
        }
        
        .copy-option.selected {
            border-color: #2196f3;
            background: #e3f2fd;
        }
        
        .copy-option input {
            margin: 0;
        }
        
        .copy-flag {
            font-size: 20px;
        }
        
        .copy-name {
            flex: 1;
            font-weight: 500;
        }
        
        .languages-count {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .flag-help {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 10px 15px;
            margin-top: 10px;
            font-size: 13px;
            color: #856404;
        }
        
        @media (max-width: 768px) {
            .input-row {
                grid-template-columns: 1fr;
            }
            
            .presets-grid {
                grid-template-columns: repeat(2, 1fr);
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
                <a href="languages.php" class="nav-item active">
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
                <a href="profile.php" class="btn btn-sm btn-outline" style="margin-bottom: 5px; display: block;">My Profile</a>
                <a href="logout.php" class="btn btn-sm btn-outline">Logout</a>
            </div>
        </aside>
        
        <main class="cms-main">
            <header class="cms-header">
                <h1>➕ Add New Language</h1>
                <a href="languages.php" class="btn">← Back to Languages</a>
            </header>
            
            <div class="cms-content">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                        <br><small>Redirecting to edit page...</small>
                    </div>
                <?php endif; ?>
                
                <div class="add-language-container">
                    <!-- Flag Preview -->
                    <div class="flag-preview-large" id="flagPreview">🏳️</div>
                    
                    <form method="POST" id="addLanguageForm">
                        <input type="hidden" name="create_language" value="1">
                        
                        <!-- Quick Presets -->
                        <?php if (!empty($languagePresets)): ?>
                            <div class="presets-section">
                                <h3>🚀 Quick Select - 50 Most Popular Languages <span class="languages-count"><?php echo count($languagePresets); ?> available</span></h3>
                                <div class="presets-grid">
                                    <?php foreach ($languagePresets as $code => $preset): ?>
                                        <button type="button" class="preset-btn" 
                                                data-code="<?php echo htmlspecialchars($code); ?>"
                                                data-name="<?php echo htmlspecialchars($preset['name']); ?>"
                                                data-flag="<?php echo htmlspecialchars($preset['flag']); ?>">
                                            <span class="preset-flag"><?php echo htmlspecialchars($preset['flag']); ?></span>
                                            <div class="preset-info">
                                                <div class="preset-name"><?php echo htmlspecialchars($preset['name']); ?></div>
                                                <div class="preset-code"><?php echo htmlspecialchars($code); ?></div>
                                            </div>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Manual Input -->
                        <div class="manual-input-section">
                            <h3>✏️ Language Details</h3>
                            
                            <div class="input-row">
                                <div class="form-group">
                                    <label for="lang_code">Language Code *</label>
                                    <input type="text" id="lang_code" name="lang_code" 
                                           placeholder="e.g., fr, de, pt-br" 
                                           pattern="[a-z]{2,3}(-[a-z]{2})?" 
                                           maxlength="6"
                                           required
                                           value="<?php echo htmlspecialchars($_POST['lang_code'] ?? ''); ?>">
                                    <small>2-3 lowercase letters, optionally with region (ISO 639-1)</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="lang_name">Language Name *</label>
                                    <input type="text" id="lang_name" name="lang_name" 
                                           placeholder="e.g., Français, Deutsch"
                                           required
                                           value="<?php echo htmlspecialchars($_POST['lang_name'] ?? ''); ?>">
                                    <small>Display name in native language</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="lang_flag">Flag *</label>
                                    <input type="text" id="lang_flag" name="lang_flag" 
                                           placeholder="🇫🇷"
                                           required
                                           style="font-size: 28px; text-align: center;"
                                           value="<?php echo htmlspecialchars($_POST['lang_flag'] ?? '🏳️'); ?>">
                                </div>
                            </div>
                            
                            <div class="flag-help">
                                💡 <strong>Tip:</strong> Use the Quick Select buttons above to auto-fill with correct flag emojis. 
                                If you enter a text code (like "IT"), the system will automatically convert it to the correct emoji.
                            </div>
                        </div>
                        
                        <!-- Copy From Section -->
                        <?php if (!empty($existingLanguages)): ?>
                        <div class="copy-from-section">
                            <h4>📋 Copy Translations From</h4>
                            <p style="color: #1565c0; margin-bottom: 15px; font-size: 14px;">
                                Start with existing translations and then translate them to your new language.
                            </p>
                            
                            <div class="copy-options">
                                <label class="copy-option selected">
                                    <input type="radio" name="copy_from" value="" checked>
                                    <span class="copy-flag">📄</span>
                                    <span class="copy-name">Empty (Fresh Start)</span>
                                </label>
                                
                                <?php foreach ($existingLanguages as $code => $lang): ?>
                                    <label class="copy-option">
                                        <input type="radio" name="copy_from" value="<?php echo htmlspecialchars($code); ?>">
                                        <span class="copy-flag"><?php echo htmlspecialchars($lang['flag']); ?></span>
                                        <span class="copy-name"><?php echo htmlspecialchars($lang['name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Info Box -->
                        <div class="info-box info" style="margin-bottom: 25px;">
                            <div class="info-box-icon">💡</div>
                            <div class="info-box-content">
                                <strong>What happens next?</strong>
                                The language will be immediately available to assign to websites. You'll be redirected to the translation editor where you can add all the text translations.
                            </div>
                        </div>
                        
                        <!-- Submit Buttons -->
                        <div class="form-actions" style="margin-top: 30px;">
                            <button type="submit" class="btn btn-primary">🌐 Create Language</button>
                            <a href="languages.php" class="btn">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Preset buttons auto-fill
        document.querySelectorAll('.preset-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const code = this.dataset.code;
                const name = this.dataset.name;
                const flag = this.dataset.flag;
                
                document.getElementById('lang_code').value = code;
                document.getElementById('lang_name').value = name;
                document.getElementById('lang_flag').value = flag;
                document.getElementById('flagPreview').textContent = flag;
                
                // Update selected state
                document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('selected'));
                this.classList.add('selected');
            });
        });
        
        // Update flag preview on input
        document.getElementById('lang_flag').addEventListener('input', function() {
            document.getElementById('flagPreview').textContent = this.value || '🏳️';
        });
        
        // Copy option selection styling
        document.querySelectorAll('.copy-option input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.copy-option').forEach(opt => opt.classList.remove('selected'));
                this.closest('.copy-option').classList.add('selected');
            });
        });
        
        // Lowercase language code and allow hyphens for regional variants
        document.getElementById('lang_code').addEventListener('input', function() {
            this.value = this.value.toLowerCase().replace(/[^a-z-]/g, '');
        });
    </script>
</body>
</html>