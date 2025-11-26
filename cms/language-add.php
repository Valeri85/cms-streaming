<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
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

// Common language presets for quick selection
$languagePresets = [
    'fr' => ['name' => 'Français', 'flag' => '🇫🇷'],
    'de' => ['name' => 'Deutsch', 'flag' => '🇩🇪'],
    'it' => ['name' => 'Italiano', 'flag' => '🇮🇹'],
    'pt' => ['name' => 'Português', 'flag' => '🇵🇹'],
    'nl' => ['name' => 'Nederlands', 'flag' => '🇳🇱'],
    'pl' => ['name' => 'Polski', 'flag' => '🇵🇱'],
    'ru' => ['name' => 'Русский', 'flag' => '🇷🇺'],
    'tr' => ['name' => 'Türkçe', 'flag' => '🇹🇷'],
    'ar' => ['name' => 'العربية', 'flag' => '🇸🇦'],
    'ja' => ['name' => '日本語', 'flag' => '🇯🇵'],
    'ko' => ['name' => '한국어', 'flag' => '🇰🇷'],
    'zh' => ['name' => '中文', 'flag' => '🇨🇳'],
    'hi' => ['name' => 'हिन्दी', 'flag' => '🇮🇳'],
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
    'id' => ['name' => 'Bahasa Indonesia', 'flag' => '🇮🇩'],
    'ms' => ['name' => 'Bahasa Melayu', 'flag' => '🇲🇾'],
    'th' => ['name' => 'ไทย', 'flag' => '🇹🇭'],
    'vi' => ['name' => 'Tiếng Việt', 'flag' => '🇻🇳'],
    'he' => ['name' => 'עברית', 'flag' => '🇮🇱'],
    'fa' => ['name' => 'فارسی', 'flag' => '🇮🇷']
];

// Remove already existing languages from presets
foreach ($existingLanguages as $code => $info) {
    unset($languagePresets[$code]);
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
    } elseif (!preg_match('/^[a-z]{2,3}$/', $langCode)) {
        $error = "❌ Language code must be 2-3 lowercase letters (e.g., 'fr', 'de').";
    } elseif (empty($langName)) {
        $error = "❌ Language name is required.";
    } elseif (file_exists($langDir . $langCode . '.json')) {
        $error = "❌ Language '{$langCode}' already exists!";
    } else {
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
            max-width: 700px;
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
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 10px;
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
            font-family: monospace;
        }
        
        .copy-from-section {
            background: #e3f2fd;
            border: 2px solid #90caf9;
            border-radius: 12px;
            padding: 20px;
            margin-top: 25px;
        }
        
        .copy-from-section h4 {
            color: #1976d2;
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .copy-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .copy-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .copy-option:hover {
            border-color: #3498db;
        }
        
        .copy-option.selected {
            border-color: #1976d2;
            background: #e3f2fd;
        }
        
        .copy-option input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .copy-option-flag {
            font-size: 28px;
        }
        
        .copy-option-info {
            flex: 1;
        }
        
        .copy-option-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .copy-option-desc {
            font-size: 12px;
            color: #7f8c8d;
        }
        
        .manual-input-section {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 25px;
            margin-top: 25px;
        }
        
        .manual-input-section h3 {
            margin: 0 0 20px 0;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .input-row {
            display: grid;
            grid-template-columns: 1fr 1fr 120px;
            gap: 15px;
            align-items: end;
        }
        
        @media (max-width: 600px) {
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
            </nav>
            
            <div class="cms-user">
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
                                <h3>🚀 Quick Select (click to auto-fill)</h3>
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
                                           placeholder="e.g., fr, de, it" 
                                           pattern="[a-z]{2,3}" 
                                           maxlength="3"
                                           required
                                           value="<?php echo htmlspecialchars($_POST['lang_code'] ?? ''); ?>">
                                    <small>2-3 lowercase letters (ISO 639-1)</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="lang_name">Language Name *</label>
                                    <input type="text" id="lang_name" name="lang_name" 
                                           placeholder="e.g., Français, Deutsch"
                                           required
                                           value="<?php echo htmlspecialchars($_POST['lang_name'] ?? ''); ?>">
                                    <small>Display name in CMS</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="lang_flag">Flag Emoji *</label>
                                    <input type="text" id="lang_flag" name="lang_flag" 
                                           placeholder="🇫🇷"
                                           required
                                           style="font-size: 28px; text-align: center;"
                                           value="<?php echo htmlspecialchars($_POST['lang_flag'] ?? '🏳️'); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Copy From Section -->
                        <div class="copy-from-section">
                            <h4>📋 Copy Translations From</h4>
                            <p style="color: #1565c0; margin-bottom: 15px; font-size: 14px;">
                                Start with existing translations and then translate them to your new language.
                            </p>
                            
                            <div class="copy-options">
                                <label class="copy-option selected">
                                    <input type="radio" name="copy_from" value="en" checked>
                                    <span class="copy-option-flag">🇬🇧</span>
                                    <div class="copy-option-info">
                                        <div class="copy-option-name">English (Recommended)</div>
                                        <div class="copy-option-desc">Copy all translations from English as starting point</div>
                                    </div>
                                </label>
                                
                                <?php foreach ($existingLanguages as $code => $lang): 
                                    if ($code === 'en') continue;
                                ?>
                                    <label class="copy-option">
                                        <input type="radio" name="copy_from" value="<?php echo htmlspecialchars($code); ?>">
                                        <span class="copy-option-flag"><?php echo htmlspecialchars($lang['flag']); ?></span>
                                        <div class="copy-option-info">
                                            <div class="copy-option-name"><?php echo htmlspecialchars($lang['name']); ?></div>
                                            <div class="copy-option-desc">Copy translations from <?php echo htmlspecialchars($lang['name']); ?></div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                                
                                <label class="copy-option">
                                    <input type="radio" name="copy_from" value="">
                                    <span class="copy-option-flag">📝</span>
                                    <div class="copy-option-info">
                                        <div class="copy-option-name">Start Empty</div>
                                        <div class="copy-option-desc">Create empty translation file (all fields blank)</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Info Box -->
                        <div class="info-box info" style="margin-top: 25px;">
                            <div class="info-box-icon">💡</div>
                            <div class="info-box-content">
                                <strong>What happens next?</strong>
                                After creating the language, you'll be redirected to the edit page where you can translate all texts. The language will be immediately available to assign to websites.
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
        
        // Lowercase language code
        document.getElementById('lang_code').addEventListener('input', function() {
            this.value = this.value.toLowerCase().replace(/[^a-z]/g, '');
        });
    </script>
</body>
</html>