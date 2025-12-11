<?php
/**
 * CMS Sidebar Component
 * 
 * Reusable sidebar navigation for all CMS pages.
 * 
 * Usage: 
 *   $currentPage = 'dashboard'; // Set before including
 *   include __DIR__ . '/includes/sidebar.php';
 */

// Get current page from variable or detect from URL
if (!isset($currentPage)) {
    $scriptName = basename($_SERVER['SCRIPT_NAME'], '.php');
    $currentPage = $scriptName;
}

// Navigation items configuration
$navItems = [
    'dashboard' => ['icon' => '🏠', 'label' => 'Dashboard', 'url' => 'dashboard.php'],
    'website-add' => ['icon' => '➕', 'label' => 'Add Website', 'url' => 'website-add.php'],
    'languages' => ['icon' => '🌐', 'label' => 'Languages', 'url' => 'languages.php'],
    'icons' => ['icon' => '🖼️', 'label' => 'Icons', 'url' => 'icons.php'],
    'users' => ['icon' => '👥', 'label' => 'Users', 'url' => 'users.php'],
];

// Map pages to their parent nav item
$pageGroups = [
    'dashboard' => 'dashboard',
    'website-add' => 'website-add',
    'website-edit' => 'dashboard',
    'website-delete' => 'dashboard',
    'website-pages' => 'dashboard',
    'languages' => 'languages',
    'language-add' => 'languages',
    'language-edit' => 'languages',
    'icons' => 'icons',
    'users' => 'users',
    'profile' => 'users',
];

// Find which nav item should be active
$activeNav = $pageGroups[$currentPage] ?? $currentPage;

// Get admin username from session
$adminUsername = $_SESSION['admin_username'] ?? $_SESSION['user_username'] ?? 'Admin';
?>
<aside class="cms-sidebar">
    <div class="cms-logo">
        <h2>🎯 CMS</h2>
    </div>
    
    <nav class="cms-nav">
        <?php foreach ($navItems as $key => $item): ?>
            <a href="<?php echo $item['url']; ?>" class="nav-item <?php echo ($activeNav === $key) ? 'active' : ''; ?>">
                <span><?php echo $item['icon']; ?></span> <?php echo $item['label']; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    
    <div class="cms-user">
        <p><strong><?php echo htmlspecialchars($adminUsername); ?></strong></p>
        <a href="profile.php" class="btn btn-sm btn-outline" style="margin-bottom: 5px; display: block;">My Profile</a>
        <a href="logout.php" class="btn btn-sm btn-outline">Logout</a>
    </div>
</aside>