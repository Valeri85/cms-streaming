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

// Default sports list
$defaultSports = [
    'Football', 'Basketball', 'Tennis', 'Ice Hockey', 'Baseball', 'Rugby', 'Cricket', 
    'American Football', 'Volleyball', 'Beach Volleyball', 'Handball', 'Beach Handball', 
    'Beach Soccer', 'Aussie Rules', 'Futsal', 'Badminton', 'Netball', 'Floorball', 
    'Combat', 'Boxing', 'MMA', 'Snooker', 'Billiard', 'Table Tennis', 'Padel Tennis', 
    'Squash', 'Motorsport', 'Racing', 'Cycling', 'Equestrianism', 'Golf', 'Field Hockey', 
    'Lacrosse', 'Athletics', 'Gymnastics', 'Weightlifting', 'Climbing', 'Winter Sports', 
    'Bandy', 'Curling', 'Water Sports', 'Water Polo', 'Sailing', 'Bowling', 'Darts', 
    'Chess', 'E-sports', 'Others'
];

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
            $websites[$websiteIndex]['sports_categories'] = $defaultSports;
        }
        
        // Handle Add Sport
        if (isset($_POST['add_sport'])) {
            $newSport = trim($_POST['new_sport_name'] ?? '');
            if ($newSport) {
                if (!in_array($newSport, $websites[$websiteIndex]['sports_categories'])) {
                    $websites[$websiteIndex]['sports_categories'][] = $newSport;
                    $success = "Sport category '{$newSport}' added successfully!";
                } else {
                    $error = "Sport category '{$newSport}' already exists!";
                }
            } else {
                $error = "Please enter a sport name";
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
            $success = "Sport category '{$sportToDelete}' deleted successfully!";
        }
        
        // Handle Reset to Default
        if (isset($_POST['reset_default'])) {
            $websites[$websiteIndex]['sports_categories'] = $defaultSports;
            $success = "Sports list reset to default (48 sports)!";
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

// Get current sports or use default
$sports = $website['sports_categories'] ?? $defaultSports;
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
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .sport-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .sport-name {
            font-weight: 600;
            color: #2c3e50;
        }
        .add-sport-box {
            background: #e8f5e9;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .reset-box {
            background: #fff3cd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .delete-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .delete-btn:hover {
            background: #c0392b;
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
                
                <!-- Add Sport Form -->
                <div class="add-sport-box">
                    <h3 style="margin-bottom: 15px; color: #2e7d32;">➕ Add New Sport Category</h3>
                    <form method="POST" style="display: flex; gap: 10px; align-items: flex-end;">
                        <div class="form-group" style="flex: 1; margin-bottom: 0;">
                            <label for="new_sport_name">Sport Name</label>
                            <input type="text" id="new_sport_name" name="new_sport_name" placeholder="e.g., Rugby League" required>
                        </div>
                        <button type="submit" name="add_sport" class="btn btn-primary">Add Sport</button>
                    </form>
                </div>
                
                <!-- Reset to Default -->
                <div class="reset-box">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="margin-bottom: 5px; color: #856404;">🔄 Reset to Default Sports</h3>
                            <p style="color: #856404; margin: 0;">This will restore the original 48 sports categories</p>
                        </div>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to reset to default 48 sports? This will remove any custom sports you added.');">
                            <button type="submit" name="reset_default" class="btn" style="background: #ffc107; color: #000;">Reset to Default</button>
                        </form>
                    </div>
                </div>
                
                <!-- Sports List -->
                <div class="content-section">
                    <div class="section-header">
                        <h2>Current Sports Categories (<?php echo count($sports); ?>)</h2>
                    </div>
                    
                    <div class="sports-list">
                        <?php foreach ($sports as $sport): 
                            $slug = strtolower(str_replace(' ', '-', $sport));
                        ?>
                            <div class="sport-item">
                                <span class="sport-name"><?php echo htmlspecialchars($sport); ?></span>
                                <form method="POST" onsubmit="return confirm('Delete <?php echo htmlspecialchars($sport); ?>?');" style="margin: 0;">
                                    <input type="hidden" name="sport_name" value="<?php echo htmlspecialchars($sport); ?>">
                                    <button type="submit" name="delete_sport" class="delete-btn">Delete</button>
                                </form>
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
</body>
</html>