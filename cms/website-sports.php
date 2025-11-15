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

// Function to send Slack notification
function sendSlackNotification($sportName) {
    $slackConfigFile = '/var/www/u1852176/data/www/streaming/config/slack-config.json';
    if (file_exists($slackConfigFile)) {
        $slackConfig = json_decode(file_get_contents($slackConfigFile), true);
        $slackWebhookUrl = $slackConfig['webhook_url'] ?? '';
    }
    
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $configContent = file_get_contents($configFile);
    $configData = json_decode($configContent, true);
    $websites = $configData['websites'] ?? [];
    
    // Find website
    $websiteIndex = null;
    foreach ($websites as $key => $site) {
        if ($site['id'] == $websiteId) {
            $websiteIndex = $key;
            break;
        }
    }
    
    if ($websiteIndex !== null) {
        // Initialize sports_categories if not exists
        if (!isset($websites[$websiteIndex]['sports_categories'])) {
            $websites[$websiteIndex]['sports_categories'] = [];
        }
        
        // Initialize sports_icons if not exists
        if (!isset($websites[$websiteIndex]['sports_icons'])) {
            $websites[$websiteIndex]['sports_icons'] = [];
        }
        
        // Handle Add Sport
        if (isset($_POST['add_sport'])) {
            $newSport = trim($_POST['new_sport_name'] ?? '');
            $newIcon = trim($_POST['new_sport_icon'] ?? '');
            
            if ($newSport) {
                if (!in_array($newSport, $websites[$websiteIndex]['sports_categories'])) {
                    $websites[$websiteIndex]['sports_categories'][] = $newSport;
                    
                    // Add icon if provided
                    if ($newIcon) {
                        $websites[$websiteIndex]['sports_icons'][$newSport] = $newIcon;
                    }
                    
                    // Send Slack notification
                    $slackSent = sendSlackNotification($newSport);
                    
                    $success = "Sport category '{$newSport}' added successfully!";
                    if ($slackSent !== false) {
                        $success .= " Slack notification sent.";
                    }
                } else {
                    $error = "Sport category '{$newSport}' already exists!";
                }
            } else {
                $error = "Please enter a sport name";
            }
        }
        
        // Handle Edit Icon
        if (isset($_POST['edit_icon'])) {
            $sportName = $_POST['sport_name'] ?? '';
            $newIcon = trim($_POST['sport_icon'] ?? '');
            
            if ($sportName) {
                if ($newIcon) {
                    $websites[$websiteIndex]['sports_icons'][$sportName] = $newIcon;
                    $success = "Icon updated for '{$sportName}'";
                } else {
                    // Remove icon if empty
                    if (isset($websites[$websiteIndex]['sports_icons'][$sportName])) {
                        unset($websites[$websiteIndex]['sports_icons'][$sportName]);
                    }
                    $success = "Icon removed for '{$sportName}'";
                }
            }
        }
        
        // Handle Rename Sport
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
                        
                        // Move icon to new name
                        if (isset($websites[$websiteIndex]['sports_icons'][$oldName])) {
                            $websites[$websiteIndex]['sports_icons'][$newName] = $websites[$websiteIndex]['sports_icons'][$oldName];
                            unset($websites[$websiteIndex]['sports_icons'][$oldName]);
                        }
                        
                        $success = "Sport category renamed from '{$oldName}' to '{$newName}' successfully!";
                    } else {
                        $error = "Sport category '{$newName}' already exists!";
                    }
                } else {
                    $error = "Sport category '{$oldName}' not found!";
                }
            } else {
                $error = "Please enter a valid sport name";
            }
        }
        
        // Handle Delete Sport
        if (isset($_POST['delete_sport'])) {
            $sportToDelete = $_POST['sport_name'] ?? '';
            $sports = $websites[$websiteIndex]['sports_categories'];
            $sports = array_filter($sports, function($sport) use ($sportToDelete) {
                return $sport !== $sportToDelete;
            });
            $websites[$websiteIndex]['sports_categories'] = array_values($sports);
            
            // Remove icon
            if (isset($websites[$websiteIndex]['sports_icons'][$sportToDelete])) {
                unset($websites[$websiteIndex]['sports_icons'][$sportToDelete]);
            }
            
            $success = "Sport category '{$sportToDelete}' deleted successfully!";
        }
        
        // Handle Reorder Sports
        if (isset($_POST['reorder_sports'])) {
            $newOrder = json_decode($_POST['sports_order'], true);
            if (is_array($newOrder)) {
                $websites[$websiteIndex]['sports_categories'] = $newOrder;
                $success = "Sports order updated successfully!";
            }
        }
        
        // Save changes
        if ($success || $error) {
            $configData['websites'] = $websites;
            $jsonContent = json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            if (!file_put_contents($configFile, $jsonContent)) {
                $error = 'Failed to save changes. Check file permissions: chmod 644 ' . $configFile;
                $success = '';
            }
        }
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

// Get current sports and icons
$sports = $website['sports_categories'] ?? [];
$sportsIcons = $website['sports_icons'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sports - <?php echo htmlspecialchars($website['site_name']); ?></title>
    <link rel="stylesheet" href="cms-style.css">
    <style>
        .sports-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .sport-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            display: flex;
            flex-direction: column;
            gap: 10px;
            cursor: move;
            transition: all 0.3s;
        }
        .sport-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .sport-item.dragging {
            opacity: 0.5;
            cursor: grabbing;
        }
        .sport-header {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sport-icon-preview {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            flex-shrink: 0;
        }
        .sport-icon-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .sport-icon-preview.no-icon {
            color: #999;
            font-size: 20px;
        }
        .sport-name {
            font-weight: 600;
            color: #2c3e50;
            flex: 1;
        }
        .drag-handle {
            cursor: move;
            font-size: 20px;
            opacity: 0.5;
        }
        .add-sport-box {
            background: #e8f5e9;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .sport-actions {
            display: flex;
            gap: 5px;
        }
        .edit-icon-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            flex: 1;
        }
        .edit-icon-btn:hover {
            background: #2980b9;
        }
        .edit-btn {
            background: #f39c12;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            flex: 1;
        }
        .edit-btn:hover {
            background: #e67e22;
        }
        .delete-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            flex: 1;
        }
        .delete-btn:hover {
            background: #c0392b;
        }
        .slack-info {
            background: #fff3cd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #ffc107;
        }
        .slack-info h3 {
            color: #856404;
            margin-bottom: 10px;
        }
        .slack-info code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        .icon-info {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #2196f3;
        }
        .icon-info h3 {
            color: #1565c0;
            margin-bottom: 10px;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
        }
        .modal-content h3 {
            margin-bottom: 20px;
            color: #2c3e50;
        }
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
        }
        .icon-preview-large {
            width: 64px;
            height: 64px;
            margin: 10px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 8px;
        }
        .icon-preview-large img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .icon-preview-large.no-icon {
            color: #999;
            font-size: 32px;
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
            </nav>
            
            <div class="cms-user">
                <a href="logout.php" class="btn btn-sm btn-outline">Logout</a>
            </div>
        </aside>
        
        <main class="cms-main">
            <header class="cms-header">
                <h1>Manage Sports: <?php echo htmlspecialchars($website['site_name']); ?></h1>
                <div>
                    <a href="website-seo.php?id=<?php echo $websiteId; ?>" class="btn">← Back to SEO</a>
                    <a href="dashboard.php" class="btn">Dashboard</a>
                </div>
            </header>
            
            <div class="cms-content">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <!-- Icon Upload Instructions -->
                <div class="icon-info">
                    <h3>📁 How to Add Sport Icons</h3>
                    <p><strong>Step 1:</strong> Upload your sport icon images to <code>/images/sports/</code> folder via FTP</p>
                    <p><strong>Step 2:</strong> Use format: PNG, JPG, or SVG (recommended size: 64x64px)</p>
                    <p><strong>Step 3:</strong> Enter the filename below (e.g., <code>football.png</code>)</p>
                    <p style="margin-top: 10px;"><strong>Example:</strong> Upload <code>football.png</code> → Enter <code>football.png</code> in icon field</p>
                </div>
                
                <!-- Slack Integration Info -->
                <div class="slack-info">
                    <h3>📢 Slack Notifications</h3>
                    <p>To receive notifications when new sports are added, create a file: <code>/var/www/u1852176/data/www/streaming/config/slack-config.json</code></p>
                    <p>Content:</p>
                    <pre style="background: #fff; padding: 10px; border-radius: 4px; overflow-x: auto;">{
  "webhook_url": "https://hooks.slack.com/services/YOUR/WEBHOOK/URL"
}</pre>
                </div>
                
                <!-- Add Sport Form -->
                <div class="add-sport-box">
                    <h3 style="margin-bottom: 15px; color: #2e7d32;">➕ Add New Sport Category</h3>
                    <form method="POST">
                        <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 10px; align-items: flex-end;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="new_sport_name">Sport Name *</label>
                                <input type="text" id="new_sport_name" name="new_sport_name" placeholder="e.g., Rugby League" required>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="new_sport_icon">Icon Filename (optional)</label>
                                <input type="text" id="new_sport_icon" name="new_sport_icon" placeholder="e.g., rugby.png">
                            </div>
                            <button type="submit" name="add_sport" class="btn btn-primary">Add Sport</button>
                        </div>
                    </form>
                </div>
                
                <!-- Sports List -->
                <div class="content-section">
                    <div class="section-header">
                        <h2>Current Sports Categories (<?php echo count($sports); ?>)</h2>
                        <p style="color: #666; font-size: 14px;">💡 Drag and drop to reorder</p>
                    </div>
                    
                    <form method="POST" id="reorderForm">
                        <input type="hidden" name="reorder_sports" value="1">
                        <input type="hidden" name="sports_order" id="sportsOrder">
                    </form>
                    
                    <div class="sports-list" id="sportsList">
                        <?php foreach ($sports as $sport): 
                            $iconFile = $sportsIcons[$sport] ?? '';
                            $hasIcon = !empty($iconFile);
                        ?>
                            <div class="sport-item" draggable="true" data-sport="<?php echo htmlspecialchars($sport); ?>">
                                <div class="sport-header">
                                    <span class="drag-handle">⋮⋮</span>
                                    <div class="sport-icon-preview <?php echo $hasIcon ? '' : 'no-icon'; ?>">
                                        <?php if ($hasIcon): ?>
                                            <img src="/images/sports/<?php echo htmlspecialchars($iconFile); ?>" alt="<?php echo htmlspecialchars($sport); ?>">
                                        <?php else: ?>
                                            ?
                                        <?php endif; ?>
                                    </div>
                                    <span class="sport-name"><?php echo htmlspecialchars($sport); ?></span>
                                </div>
                                <div class="sport-actions">
                                    <button type="button" class="edit-icon-btn" onclick="openIconModal('<?php echo htmlspecialchars($sport, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($iconFile, ENT_QUOTES); ?>')">
                                        <?php echo $hasIcon ? '🖼️ Edit Icon' : '➕ Add Icon'; ?>
                                    </button>
                                    <button type="button" class="edit-btn" onclick="openRenameModal('<?php echo htmlspecialchars($sport, ENT_QUOTES); ?>')">Rename</button>
                                    <form method="POST" onsubmit="return confirm('Delete <?php echo htmlspecialchars($sport); ?>?');" style="margin: 0; flex: 1;">
                                        <input type="hidden" name="sport_name" value="<?php echo htmlspecialchars($sport); ?>">
                                        <button type="submit" name="delete_sport" class="delete-btn" style="width: 100%;">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (empty($sports)): ?>
                        <div style="text-align: center; padding: 40px; color: #999;">
                            <p>No sports categories yet. Add your first sport above!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Edit Icon Modal -->
    <div class="modal" id="iconModal">
        <div class="modal-content">
            <h3>Edit Sport Icon</h3>
            <form method="POST" id="iconForm">
                <input type="hidden" name="edit_icon" value="1">
                <input type="hidden" name="sport_name" id="iconSportName">
                
                <div id="iconPreviewContainer" class="icon-preview-large no-icon">?</div>
                
                <div class="form-group">
                    <label for="sportIconInput">Icon Filename</label>
                    <input type="text" id="sportIconInput" name="sport_icon" placeholder="e.g., football.png">
                    <small>Upload image to /images/sports/ first, then enter filename here. Leave empty to remove icon.</small>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeIconModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Icon</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Rename Modal -->
    <div class="modal" id="renameModal">
        <div class="modal-content">
            <h3>Rename Sport Category</h3>
            <form method="POST" id="renameForm">
                <input type="hidden" name="rename_sport" value="1">
                <input type="hidden" name="old_sport_name" id="oldSportName">
                <div class="form-group">
                    <label for="newSportNameInput">New Sport Name</label>
                    <input type="text" id="newSportNameInput" name="new_sport_name" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeRenameModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Rename</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Icon Modal Functions
        function openIconModal(sportName, currentIcon) {
            document.getElementById('iconSportName').value = sportName;
            document.getElementById('sportIconInput').value = currentIcon;
            
            const preview = document.getElementById('iconPreviewContainer');
            if (currentIcon) {
                preview.innerHTML = '<img src="/images/sports/' + currentIcon + '" alt="' + sportName + '">';
                preview.classList.remove('no-icon');
            } else {
                preview.innerHTML = '?';
                preview.classList.add('no-icon');
            }
            
            document.getElementById('iconModal').classList.add('active');
            document.getElementById('sportIconInput').focus();
        }
        
        function closeIconModal() {
            document.getElementById('iconModal').classList.remove('active');
        }
        
        // Preview icon as user types
        document.getElementById('sportIconInput').addEventListener('input', function(e) {
            const filename = e.target.value.trim();
            const preview = document.getElementById('iconPreviewContainer');
            
            if (filename) {
                preview.innerHTML = '<img src="/images/sports/' + filename + '" alt="Preview">';
                preview.classList.remove('no-icon');
            } else {
                preview.innerHTML = '?';
                preview.classList.add('no-icon');
            }
        });
        
        // Rename Modal Functions
        function openRenameModal(sportName) {
            document.getElementById('oldSportName').value = sportName;
            document.getElementById('newSportNameInput').value = sportName;
            document.getElementById('renameModal').classList.add('active');
            document.getElementById('newSportNameInput').focus();
            document.getElementById('newSportNameInput').select();
        }
        
        function closeRenameModal() {
            document.getElementById('renameModal').classList.remove('active');
        }
        
        // Close modals on outside click
        document.getElementById('iconModal').addEventListener('click', function(e) {
            if (e.target === this) closeIconModal();
        });
        
        document.getElementById('renameModal').addEventListener('click', function(e) {
            if (e.target === this) closeRenameModal();
        });
        
        // Drag and Drop functionality
        const sportsList = document.getElementById('sportsList');
        let draggedElement = null;
        
        sportsList.addEventListener('dragstart', (e) => {
            if (e.target.classList.contains('sport-item')) {
                draggedElement = e.target;
                e.target.classList.add('dragging');
            }
        });
        
        sportsList.addEventListener('dragend', (e) => {
            if (e.target.classList.contains('sport-item')) {
                e.target.classList.remove('dragging');
                draggedElement = null;
                saveNewOrder();
            }
        });
        
        sportsList.addEventListener('dragover', (e) => {
            e.preventDefault();
            const afterElement = getDragAfterElement(sportsList, e.clientY);
            const dragging = document.querySelector('.dragging');
            if (afterElement == null) {
                sportsList.appendChild(dragging);
            } else {
                sportsList.insertBefore(dragging, afterElement);
            }
        });
        
        function getDragAfterElement(container, y) {
            const draggableElements = [...container.querySelectorAll('.sport-item:not(.dragging)')];
            
            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }
        
        function saveNewOrder() {
            const sportItems = document.querySelectorAll('.sport-item');
            const newOrder = [];
            sportItems.forEach(item => {
                newOrder.push(item.dataset.sport);
            });
            
            document.getElementById('sportsOrder').value = JSON.stringify(newOrder);
            document.getElementById('reorderForm').submit();
        }
    </script>
</body>
</html>