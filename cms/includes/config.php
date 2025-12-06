<?php
/**
 * CMS Configuration File
 * 
 * This file contains all path constants and configuration settings.
 * Include this file at the top of every CMS page.
 * 
 * Usage: require_once __DIR__ . '/includes/config.php';
 * (from cms/ directory)
 * 
 * Or: require_once dirname(__DIR__) . '/includes/config.php';
 * (from subdirectories)
 */

// ==========================================
// BASE PATHS
// ==========================================

// Root path for all streaming websites
define('STREAMING_ROOT', '/var/www/u1852176/data/www/streaming');

// CMS domain path
define('CMS_ROOT', '/var/www/u1852176/data/www/watchlivesport.online');

// ==========================================
// CONFIGURATION FILES
// ==========================================

define('CONFIG_DIR', STREAMING_ROOT . '/config');
define('WEBSITES_CONFIG_FILE', CONFIG_DIR . '/websites.json');
define('MASTER_SPORTS_FILE', CONFIG_DIR . '/master-sports.json');
define('SLACK_CONFIG_FILE', CONFIG_DIR . '/slack-config.json');

// ==========================================
// LANGUAGE FILES
// ==========================================

define('LANG_DIR', CONFIG_DIR . '/lang/');

// ==========================================
// IMAGE DIRECTORIES
// ==========================================

define('IMAGES_DIR', STREAMING_ROOT . '/images');
define('LOGOS_DIR', IMAGES_DIR . '/logos/');
define('FAVICONS_DIR', IMAGES_DIR . '/favicons/');

// ==========================================
// SHARED RESOURCES
// ==========================================

define('SHARED_DIR', STREAMING_ROOT . '/shared');
define('ICONS_DIR', SHARED_DIR . '/icons');
define('SPORT_ICONS_DIR', ICONS_DIR . '/sports/');
define('FLAGS_DIR', ICONS_DIR . '/flags/');

// URL paths (for use in HTML)
define('FLAGS_URL_PATH', '/shared/icons/flags/');
define('SPORT_ICONS_URL_PATH', '/shared/icons/sports/');

// ==========================================
// ALLOWED FILE TYPES
// ==========================================

// Logo uploads
define('LOGO_ALLOWED_TYPES', ['image/webp', 'image/svg+xml', 'image/avif']);
define('LOGO_ALLOWED_EXTENSIONS', ['webp', 'svg', 'avif']);

// Favicon uploads
define('FAVICON_ALLOWED_TYPES', ['image/png', 'image/jpeg', 'image/webp']);
define('FAVICON_ALLOWED_EXTENSIONS', ['png', 'jpg', 'jpeg', 'webp']);

// Icon uploads
define('ICON_ALLOWED_TYPES', ['image/webp', 'image/svg+xml', 'image/avif']);
define('ICON_ALLOWED_EXTENSIONS', ['webp', 'svg', 'avif']);

// ==========================================
// FAVICON SIZES (for generation)
// ==========================================

define('FAVICON_SIZES', [
    16 => 'favicon-16x16.png',
    32 => 'favicon-32x32.png',
    180 => 'apple-touch-icon.png',
    192 => 'android-chrome-192x192.png',
    512 => 'android-chrome-512x512.png'
]);

// ==========================================
// HELPER: Ensure directories exist
// ==========================================

/**
 * Create directory if it doesn't exist
 * 
 * @param string $path Directory path
 * @return bool True if directory exists or was created
 */
function ensureDirectoryExists($path) {
    if (!file_exists($path)) {
        return mkdir($path, 0755, true);
    }
    return true;
}

// Auto-create essential directories
ensureDirectoryExists(LOGOS_DIR);
ensureDirectoryExists(FAVICONS_DIR);
ensureDirectoryExists(SPORT_ICONS_DIR);