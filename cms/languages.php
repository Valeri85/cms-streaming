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

// ==========================================
// HANDLE ACTIONS
// ==========================================

// Toggle language active status
if (isset($_POST['toggle_status'])) {
    $langCode = $_POST['lang_code'] ?? '';
    $langFile = $langDir . $langCode . '.json';
    
    if (file_exists($langFile)) {
        $langData = json_decode(file_get_contents($langFile), true);
        
        // Toggle the active status
        $langData['language_info']['active'] = !($langData['language_info']['active'] ?? true);
        
        $jsonContent = json_encode($langData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($langFile, $jsonContent)) {
            $newStatus = $langData['language_info']['active'] ? 'activated' : 'deactivated';
            $success = "✅ Language '{$langData['language_info']['name']}' has been {$newStatus}!";
        } else {
            $error = "❌ Failed to update language status. Check file permissions.";
        }
    } else {
        $error = "❌ Language file not found.";
    }
}

// Delete language
if (isset($_POST['delete_language'])) {
    $langCode = $_POST['lang_code'] ?? '';
    $confirmCode = $_POST['confirm_code'] ?? '';
    
    // Prevent deleting English (master language)
    if ($langCode === 'en') {
        $error = "❌ Cannot delete English. It's the master language!";
    } elseif ($langCode !== $confirmCode) {
        $error = "❌ Language code doesn't match. Deletion cancelled.";
    } else {
        $langFile = $langDir . $langCode . '.json';
        
        if (file_exists($langFile)) {
            $langData = json_decode(file_get_contents($langFile), true);
            $langName = $langData['language_info']['name'] ?? $langCode;
            
            if (unlink($langFile)) {
                $success = "✅ Language '{$langName}' has been deleted!";
            } else {
                $error = "❌ Failed to delete language file. Check file permissions.";
            }
        } else {
            $error = "❌ Language file not found.";
        }
    }
}

// ==========================================
// LOAD ALL LANGUAGES
// ==========================================

$languages = [];

if (is_dir($langDir)) {
    $files = glob($langDir . '*.json');
    
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        
        if ($data && isset($data['language_info'])) {
            $langCode = $data['language_info']['code'];
            
            // Count translations
            $uiCount = isset($data['ui']) ? count($data['ui']) : 0;
            $messagesCount = isset($data['messages']) ? count($data['messages']) : 0;
            $footerCount = isset($data['footer']) ? count($data['footer']) : 0;
            $sportsCount = isset($data['sports']) ? count($data['sports']) : 0;
            $accessibilityCount = isset($data['accessibility']) ? count($data['accessibility']) : 0;
            
            $languages[$langCode] = [
                'info' => $data['language_info'],
                'file' => basename($file),
                'stats' => [
                    'ui' => $uiCount,
                    'messages' => $messagesCount,
                    'footer' => $footerCount,
                    'sports' => $sportsCount,
                    'accessibility' => $accessibilityCount,
                    'total' => $uiCount + $messagesCount + $footerCount + $sportsCount + $accessibilityCount
                ]
            ];
        }
    }
}

// Sort languages: English first, then alphabetically
uksort($languages, function($a, $b) {
    if ($a === 'en') return -1;
    if ($b === 'en') return 1;
    return strcmp($languages[$a]['info']['name'] ?? $a, $languages[$b]['info']['name'] ?? $b);
});

// Count active languages
$activeCount = count(array_filter($languages, function($lang) {
    return $lang['info']['active'] ?? false;
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Languages - CMS</title>
    <link rel="stylesheet" href="cms-style.css">
    <link rel="stylesheet" href="css/languages.css">
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
                    <span>🖼️</span> Sport Icons
                </a>
            </nav>
            
            <div class="cms-user">
                <a href="logout.php" class="btn btn-sm btn-outline">Logout</a>
            </div>
        </aside>
        
        <main class="cms-main">
            <header class="cms-header">
                <h1>🌐 Languages</h1>
                <a href="language-add.php" class="btn btn-primary">+ Add Language</a>
            </header>
            
            <div class="cms-content">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <!-- Stats Overview -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">🌐</div>
                        <div class="stat-info">
                            <h3><?php echo count($languages); ?></h3>
                            <p>Total Languages</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">✅</div>
                        <div class="stat-info">
                            <h3><?php echo $activeCount; ?></h3>
                            <p>Active Languages</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">📝</div>
                        <div class="stat-info">
                            <h3><?php echo isset($languages['en']) ? $languages['en']['stats']['total'] : 0; ?></h3>
                            <p>Translation Keys</p>
                        </div>
                    </div>
                </div>
                
                <!-- Info Box -->
                <div class="info-box info">
                    <div class="info-box-icon">💡</div>
                    <div class="info-box-content">
                        <strong>How Languages Work</strong>
                        English is the master language. When you add a new language, you can copy all translations from English and then translate them. Each website uses the language set in its settings.
                    </div>
                </div>
                
                <!-- Languages Grid -->
                <div class="content-section">
                    <div class="section-header">
                        <h2>Available Languages</h2>
                    </div>
                    
                    <div class="languages-grid">
                        <?php foreach ($languages as $code => $lang): 
                            $isActive = $lang['info']['active'] ?? false;
                            $isMaster = ($code === 'en');
                        ?>
                            <div class="language-card <?php echo !$isActive ? 'inactive' : ''; ?>">
                                <div class="language-status">
                                    <span class="status-badge <?php echo $isActive ? 'active' : 'inactive'; ?>">
                                        <?php echo $isActive ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </div>
                                
                                <div class="language-header">
                                    <span class="language-flag"><?php echo htmlspecialchars($lang['info']['flag'] ?? '🏳️'); ?></span>
                                    <div class="language-info">
                                        <h3 class="language-name">
                                            <?php echo htmlspecialchars($lang['info']['name'] ?? $code); ?>
                                            <?php if ($isMaster): ?>
                                                <span class="master-badge">Master</span>
                                            <?php endif; ?>
                                        </h3>
                                        <span class="language-code"><?php echo htmlspecialchars($code); ?></span>
                                    </div>
                                </div>
                                
                                <div class="language-stats">
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $lang['stats']['ui'] + $lang['stats']['messages']; ?></div>
                                        <div class="stat-label">UI Texts</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $lang['stats']['sports']; ?></div>
                                        <div class="stat-label">Sports</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $lang['stats']['total']; ?></div>
                                        <div class="stat-label">Total</div>
                                    </div>
                                </div>
                                
                                <div class="language-actions">
                                    <a href="language-edit.php?code=<?php echo urlencode($code); ?>" class="btn btn-sm btn-edit">
                                        ✏️ Edit
                                    </a>
                                    
                                    <?php if (!$isMaster): ?>
                                        <form method="POST" style="display: inline; flex: 1;">
                                            <input type="hidden" name="toggle_status" value="1">
                                            <input type="hidden" name="lang_code" value="<?php echo htmlspecialchars($code); ?>">
                                            <button type="submit" class="btn btn-sm btn-toggle" style="width: 100%;">
                                                <?php echo $isActive ? '🔒 Deactivate' : '🔓 Activate'; ?>
                                            </button>
                                        </form>
                                        
                                        <button type="button" class="btn btn-sm btn-delete" onclick="openDeleteModal('<?php echo htmlspecialchars($code); ?>', '<?php echo htmlspecialchars($lang['info']['name'] ?? $code); ?>')">
                                            🗑️
                                        </button>
                                    <?php else: ?>
                                        <span class="btn btn-sm" style="flex: 1; text-align: center; cursor: not-allowed; opacity: 0.5;">
                                            🔒 Protected
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Add New Language Card -->
                        <a href="language-add.php" class="add-language-card">
                            <div class="add-language-icon">➕</div>
                            <div class="add-language-text">Add New Language</div>
                        </a>
                    </div>
                    
                    <?php if (empty($languages)): ?>
                        <div style="text-align: center; padding: 60px; color: #999;">
                            <div style="font-size: 80px; margin-bottom: 20px;">🌐</div>
                            <h3>No languages found</h3>
                            <p>Click "Add Language" to create your first language file.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-icon">⚠️</div>
            <h3>Delete Language?</h3>
            <p>This will permanently delete this language and all its translations.</p>
            
            <div class="delete-language-warning">
                <h4>⚠️ Warning</h4>
                <ul>
                    <li>All translations for this language will be lost</li>
                    <li>Websites using this language will fall back to English</li>
                    <li>This action cannot be undone</li>
                </ul>
            </div>
            
            <p>To confirm, type the language code: <strong id="deleteLangCodeDisplay"></strong></p>
            
            <form method="POST" id="deleteForm">
                <input type="hidden" name="delete_language" value="1">
                <input type="hidden" name="lang_code" id="deleteLangCode">
                <div class="form-group">
                    <input type="text" id="confirmLangCodeInput" name="confirm_code" placeholder="Type language code to confirm" required autocomplete="off" style="text-align: center;">
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="confirmDeleteBtn" disabled>Delete Language</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Delete Modal Functions
        function openDeleteModal(code, name) {
            document.getElementById('deleteModal').classList.add('active');
            document.getElementById('deleteLangCode').value = code;
            document.getElementById('deleteLangCodeDisplay').textContent = code;
            document.getElementById('confirmLangCodeInput').value = '';
            document.getElementById('confirmDeleteBtn').disabled = true;
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        // Confirm input validation
        document.getElementById('confirmLangCodeInput').addEventListener('input', function() {
            const typed = this.value.trim();
            const expected = document.getElementById('deleteLangCode').value;
            document.getElementById('confirmDeleteBtn').disabled = (typed !== expected);
        });
        
        // Close modal on outside click
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>