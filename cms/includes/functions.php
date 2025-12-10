<?php
/**
 * CMS Shared Functions
 * 
 * This file contains all helper functions used across the CMS.
 * Include this file after config.php.
 * 
 * Usage: require_once __DIR__ . '/includes/functions.php';
 * (from cms/ directory)
 */

// Ensure config is loaded (functions depend on constants)
if (!defined('STREAMING_ROOT')) {
    die('Error: config.php must be included before functions.php');
}

// ==========================================
// STRING SANITIZATION FUNCTIONS
// ==========================================

/**
 * Sanitize a sport name for use as filename/slug
 * 
 * Example: "Ice Hockey" → "ice-hockey"
 * 
 * @param string $sportName The sport name to sanitize
 * @return string Sanitized sport name
 */
function sanitizeSportName($sportName) {
    $filename = strtolower($sportName);
    $filename = str_replace(' ', '-', $filename);
    $filename = preg_replace('/[^a-z0-9\-]/', '', $filename);
    $filename = preg_replace('/-+/', '-', $filename);
    $filename = trim($filename, '-');
    return $filename;
}

/**
 * Sanitize a site name for use as logo filename
 * 
 * Example: "My Sports Site" → "my-sports-site-logo"
 * 
 * @param string $siteName The site name to sanitize
 * @return string Sanitized site name with "-logo" suffix
 */
function sanitizeSiteName($siteName) {
    $filename = strtolower($siteName);
    $filename = str_replace(' ', '-', $filename);
    $filename = preg_replace('/[^a-z0-9\-]/', '', $filename);
    $filename = preg_replace('/-+/', '-', $filename);
    $filename = trim($filename, '-');
    $filename = $filename . '-logo';
    return $filename;
}

// ==========================================
// URL FUNCTIONS
// ==========================================

/**
 * Generate canonical URL from domain
 * 
 * Example: "example.com" → "https://www.example.com"
 * Example: "www.example.com" → "https://www.example.com"
 * 
 * @param string $domain The domain name
 * @return string Canonical URL with https:// and www.
 */
function generateCanonicalUrl($domain) {
    $normalized = str_replace('www.', '', strtolower(trim($domain)));
    return 'https://www.' . $normalized;
}

/**
 * Normalize domain (remove www. prefix)
 * 
 * @param string $domain The domain name
 * @return string Normalized domain without www.
 */
function normalizeDomain($domain) {
    return str_replace('www.', '', strtolower(trim($domain)));
}

// ==========================================
// ICON FUNCTIONS
// ==========================================

/**
 * Get icon info for a sport from master icons directory
 * 
 * @param string $sportName The sport name
 * @param string|null $iconsDir Optional custom icons directory (uses SPORT_ICONS_DIR if not provided)
 * @return array ['exists' => bool, 'filename' => string|null, 'extension' => string|null]
 */
function getMasterIcon($sportName, $iconsDir = null) {
    $iconsDir = $iconsDir ?? SPORT_ICONS_DIR;
    $sanitized = sanitizeSportName($sportName);
    $extensions = ['webp', 'svg', 'avif'];
    
    foreach ($extensions as $ext) {
        $path = $iconsDir . $sanitized . '.' . $ext;
        if (file_exists($path)) {
            return [
                'exists' => true,
                'filename' => $sanitized . '.' . $ext,
                'extension' => $ext,
                'path' => $path
            ];
        }
    }
    
    return [
        'exists' => false,
        'filename' => null,
        'extension' => null,
        'path' => null
    ];
}

/**
 * Get icon path info (alias for getMasterIcon with more details)
 * 
 * @param string $sportName The sport name
 * @param string|null $iconsDir Optional custom icons directory
 * @return array Icon information
 */
function getIconPath($sportName, $iconsDir = null) {
    return getMasterIcon($sportName, $iconsDir);
}

/**
 * Get home icon info from the shared icons directory
 * Home icon is stored in /shared/icons/home.webp (not in sports subfolder)
 * 
 * @return array ['exists' => bool, 'filename' => string|null, 'extension' => string|null, 'path' => string|null]
 */
function getHomeIcon() {
    $iconsDir = ICONS_DIR . '/';
    $allowedExtensions = ['webp', 'svg', 'avif'];
    
    foreach ($allowedExtensions as $ext) {
        $filename = 'home.' . $ext;
        $filepath = $iconsDir . $filename;
        
        if (file_exists($filepath)) {
            return [
                'exists' => true,
                'filename' => $filename,
                'extension' => $ext,
                'path' => $filepath
            ];
        }
    }
    
    return [
        'exists' => false,
        'filename' => null,
        'extension' => null,
        'path' => null
    ];
}

/**
 * Handle home icon upload
 * Home icon is stored in /shared/icons/home.webp
 * 
 * @param array $file The $_FILES array element
 * @return array ['success' => bool, 'filename' => string|null, 'error' => string|null]
 */
function handleHomeIconUpload($file) {
    $iconsDir = ICONS_DIR . '/';
    $allowedTypes = ICON_ALLOWED_TYPES;
    $allowedExtensions = ICON_ALLOWED_EXTENSIONS;
    
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'No file uploaded'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    // Handle AVIF detection issue
    if (strpos($file['name'], '.avif') !== false) {
        $mimeType = 'image/avif';
    }
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['error' => 'Invalid file type. Only WEBP, SVG, AVIF allowed'];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        return ['error' => 'Invalid file extension'];
    }
    
    // Delete old home icons with any extension
    foreach ($allowedExtensions as $ext) {
        $oldFile = $iconsDir . 'home.' . $ext;
        if (file_exists($oldFile)) {
            unlink($oldFile);
        }
    }
    
    $filename = 'home.' . $extension;
    $filepath = $iconsDir . $filename;
    
    // Process raster images (webp, avif) - resize to 64x64
    if (in_array($extension, ['webp', 'avif'])) {
        if (!extension_loaded('gd')) {
            return ['error' => 'GD extension not available'];
        }
        
        switch ($extension) {
            case 'webp':
                $sourceImage = @imagecreatefromwebp($file['tmp_name']);
                break;
            case 'avif':
                if (function_exists('imagecreatefromavif')) {
                    $sourceImage = @imagecreatefromavif($file['tmp_name']);
                } else {
                    return ['error' => 'AVIF format not supported'];
                }
                break;
        }
        
        if (!$sourceImage) {
            return ['error' => 'Failed to process image'];
        }
        
        $targetImage = imagecreatetruecolor(64, 64);
        
        // Preserve transparency
        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);
        $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
        imagefill($targetImage, 0, 0, $transparent);
        
        imagecopyresampled(
            $targetImage, $sourceImage,
            0, 0, 0, 0,
            64, 64,
            imagesx($sourceImage), imagesy($sourceImage)
        );
        
        switch ($extension) {
            case 'webp':
                imagewebp($targetImage, $filepath, 90);
                break;
            case 'avif':
                if (function_exists('imageavif')) {
                    imageavif($targetImage, $filepath, 90);
                }
                break;
        }
        
        imagedestroy($sourceImage);
        imagedestroy($targetImage);
    } else {
        // SVG - just move the file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['error' => 'Failed to save file'];
        }
    }
    
    return ['success' => true, 'filename' => $filename];
}

// ==========================================
// FILE UPLOAD FUNCTIONS
// ==========================================

/**
 * Handle logo file upload
 * 
 * @param array $file The $_FILES array element
 * @param string $uploadDir Directory to upload to (default: LOGOS_DIR)
 * @param string $siteName Site name for filename generation
 * @return array ['success' => bool, 'filename' => string] or ['error' => string]
 */
function handleLogoUpload($file, $uploadDir, $siteName) {
    $allowedTypes = LOGO_ALLOWED_TYPES;
    $allowedExtensions = LOGO_ALLOWED_EXTENSIONS;
    
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'No file uploaded'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    // Handle AVIF detection issue
    if (strpos($file['name'], '.avif') !== false) {
        $mimeType = 'image/avif';
    }
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['error' => 'Invalid file type. Only WEBP, SVG, AVIF allowed'];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        return ['error' => 'Invalid file extension'];
    }
    
    $sanitizedName = sanitizeSiteName($siteName);
    $filename = $sanitizedName . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Delete existing file if exists
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    
    // Process raster images (webp, avif) - resize to 64x64
    if (in_array($extension, ['webp', 'avif'])) {
        if (!extension_loaded('gd')) {
            return ['error' => 'GD extension not available'];
        }
        
        switch ($extension) {
            case 'webp':
                $sourceImage = @imagecreatefromwebp($file['tmp_name']);
                break;
            case 'avif':
                if (function_exists('imagecreatefromavif')) {
                    $sourceImage = @imagecreatefromavif($file['tmp_name']);
                } else {
                    return ['error' => 'AVIF format not supported'];
                }
                break;
        }
        
        if (!$sourceImage) {
            return ['error' => 'Failed to process image'];
        }
        
        $targetImage = imagecreatetruecolor(64, 64);
        
        // Preserve transparency
        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);
        $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
        imagefill($targetImage, 0, 0, $transparent);
        
        imagecopyresampled(
            $targetImage, $sourceImage,
            0, 0, 0, 0,
            64, 64,
            imagesx($sourceImage), imagesy($sourceImage)
        );
        
        switch ($extension) {
            case 'webp':
                imagewebp($targetImage, $filepath, 90);
                break;
            case 'avif':
                if (function_exists('imageavif')) {
                    imageavif($targetImage, $filepath, 90);
                }
                break;
        }
        
        imagedestroy($sourceImage);
        imagedestroy($targetImage);
    } else {
        // SVG - just move the file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['error' => 'Failed to save file'];
        }
    }
    
    return ['success' => true, 'filename' => $filename];
}

/**
 * Handle sport icon file upload
 * 
 * @param array $file The $_FILES array element
 * @param string $iconsDir Directory to upload to (default: SPORT_ICONS_DIR)
 * @param string $sportName Sport name for filename generation
 * @return array ['success' => bool, 'filename' => string] or ['error' => string]
 */
function handleIconUpload($file, $iconsDir, $sportName) {
    $allowedTypes = ICON_ALLOWED_TYPES;
    $allowedExtensions = ICON_ALLOWED_EXTENSIONS;
    
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'No file uploaded'];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowedExtensions)) {
        return ['error' => 'Invalid file extension. Only WEBP, SVG, AVIF allowed'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    // Handle AVIF detection issue
    if ($extension === 'avif') {
        $mimeType = 'image/avif';
    }
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['error' => 'Invalid file type. Only WEBP, SVG, AVIF allowed'];
    }
    
    $sanitizedName = sanitizeSportName($sportName);
    $filename = $sanitizedName . '.' . $extension;
    $filepath = $iconsDir . $filename;
    
    // Delete existing icon with any extension
    foreach (ICON_ALLOWED_EXTENSIONS as $ext) {
        $existingFile = $iconsDir . $sanitizedName . '.' . $ext;
        if (file_exists($existingFile)) {
            unlink($existingFile);
        }
    }
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['error' => 'Failed to save file'];
    }
    
    return ['success' => true, 'filename' => $filename];
}

/**
 * Generate all favicon sizes from uploaded image
 * 
 * @param array $file The $_FILES array element
 * @param string $faviconDir Base favicon directory (default: FAVICONS_DIR)
 * @param string $websiteId Website ID for subdirectory
 * @return array ['success' => bool, 'folder' => string, 'files' => array] or ['error' => string]
 */
function generateFavicons($file, $faviconDir, $websiteId) {
    $allowedTypes = FAVICON_ALLOWED_TYPES;
    $allowedExtensions = FAVICON_ALLOWED_EXTENSIONS;
    
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'No file uploaded'];
    }
    
    // Check GD library
    if (!extension_loaded('gd')) {
        return ['error' => 'GD extension not available. Cannot generate favicons.'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['error' => 'Invalid file type. Only PNG, JPG, WEBP allowed for favicon'];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        return ['error' => 'Invalid file extension'];
    }
    
    // Create website-specific favicon directory
    $websiteFaviconDir = $faviconDir . $websiteId . '/';
    if (!file_exists($websiteFaviconDir)) {
        mkdir($websiteFaviconDir, 0755, true);
    }
    
    // Load source image
    switch ($mimeType) {
        case 'image/png':
            $sourceImage = @imagecreatefrompng($file['tmp_name']);
            break;
        case 'image/jpeg':
            $sourceImage = @imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/webp':
            $sourceImage = @imagecreatefromwebp($file['tmp_name']);
            break;
        default:
            return ['error' => 'Unsupported image format'];
    }
    
    if (!$sourceImage) {
        return ['error' => 'Failed to load image'];
    }
    
    $sourceWidth = imagesx($sourceImage);
    $sourceHeight = imagesy($sourceImage);
    
    // Check minimum size
    if ($sourceWidth < 512 || $sourceHeight < 512) {
        imagedestroy($sourceImage);
        return ['error' => 'Image must be at least 512x512 pixels. Uploaded: ' . $sourceWidth . 'x' . $sourceHeight];
    }
    
    $generatedFiles = [];
    
    foreach (FAVICON_SIZES as $size => $filename) {
        $targetImage = imagecreatetruecolor($size, $size);
        
        // Preserve transparency
        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);
        $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
        imagefill($targetImage, 0, 0, $transparent);
        
        // Resize
        imagecopyresampled(
            $targetImage, $sourceImage,
            0, 0, 0, 0,
            $size, $size,
            $sourceWidth, $sourceHeight
        );
        
        $filepath = $websiteFaviconDir . $filename;
        
        // Save as PNG
        if (imagepng($targetImage, $filepath, 9)) {
            $generatedFiles[] = $filename;
        }
        
        imagedestroy($targetImage);
    }
    
    // Save original as favicon.png (512x512)
    $originalPath = $websiteFaviconDir . 'favicon.png';
    imagepng($sourceImage, $originalPath, 9);
    $generatedFiles[] = 'favicon.png';
    
    imagedestroy($sourceImage);
    
    if (empty($generatedFiles)) {
        return ['error' => 'Failed to generate any favicon sizes'];
    }
    
    return [
        'success' => true,
        'folder' => $websiteId,
        'files' => $generatedFiles
    ];
}

// ==========================================
// LANGUAGE FUNCTIONS
// ==========================================

/**
 * Load all active languages from language files
 * 
 * FIXED: Now uses 'flag' field from JSON for flag_code instead of uppercase language code
 * This ensures Danish (da) uses DK.svg, not DA.svg
 * 
 * @param string|null $langDir Language directory (default: LANG_DIR)
 * @return array Associative array of language code => language info
 */
function loadActiveLanguages($langDir = null) {
    $langDir = $langDir ?? LANG_DIR;
    $languages = [];
    
    if (is_dir($langDir)) {
        $files = glob($langDir . '*.json');
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            
            if ($data && isset($data['language_info']) && ($data['language_info']['active'] ?? false)) {
                $code = $data['language_info']['code'];
                
                // FIXED: Use 'flag' field for flag_code, fallback to uppercase language code only if 'flag' is missing
                // The 'flag' field contains the country code (e.g., "DK" for Danish)
                // Previously this used strtoupper($code) which gave wrong results (e.g., "DA" for Danish)
                $flagCode = $data['language_info']['flag_code'] 
                    ?? $data['language_info']['flag'] 
                    ?? strtoupper($code);
                
                $languages[$code] = [
                    'code' => $code,
                    'name' => $data['language_info']['name'] ?? $code,
                    'flag' => $data['language_info']['flag'] ?? '🏳️',
                    'flag_code' => $flagCode
                ];
            }
        }
    }
    
    // Sort: English first, then alphabetically by name
    uksort($languages, function($a, $b) use ($languages) {
        if ($a === 'en') return -1;
        if ($b === 'en') return 1;
        return strcmp($languages[$a]['name'], $languages[$b]['name']);
    });
    
    return $languages;
}

/**
 * Get available languages (alias for loadActiveLanguages)
 * Returns in format compatible with website-edit.php
 * 
 * @return array Associative array of language code => language info
 */
function getAvailableLanguages() {
    return loadActiveLanguages();
}

// ==========================================
// SEO STATUS FUNCTIONS
// ==========================================

/**
 * Calculate SEO status for Home page (English)
 * 
 * @param array $pagesSeo The pages_seo data from website config
 * @return string 'green', 'orange', or 'red'
 */
function getHomeStatus($pagesSeo) {
    $seoData = $pagesSeo['home'] ?? [];
    $hasTitle = !empty(trim($seoData['title'] ?? ''));
    $hasDescription = !empty(trim($seoData['description'] ?? ''));
    
    if ($hasTitle && $hasDescription) {
        return 'green';
    } elseif ($hasTitle || $hasDescription) {
        return 'orange';
    }
    return 'red';
}

/**
 * Calculate SEO status for sport page (English)
 * 
 * @param string $sportName The sport name
 * @param array $pagesSeo The pages_seo data from website config
 * @return string 'green', 'orange', or 'red'
 */
function getSportStatus($sportName, $pagesSeo) {
    $sportSlug = sanitizeSportName($sportName);
    $seoData = $pagesSeo['sports'][$sportSlug] ?? [];
    $hasTitle = !empty(trim($seoData['title'] ?? ''));
    $hasDescription = !empty(trim($seoData['description'] ?? ''));
    
    if ($hasTitle && $hasDescription) {
        return 'green';
    } elseif ($hasTitle || $hasDescription) {
        return 'orange';
    }
    return 'red';
}

/**
 * Calculate SEO status for language-specific page
 * 
 * @param string $langCode Language code
 * @param string $domain Website domain
 * @param string $pageType 'home' or 'sport'
 * @param string|null $sportSlug Sport slug (required if pageType is 'sport')
 * @param string|null $langDir Language directory
 * @param array $pagesSeo The pages_seo data (for English)
 * @return string 'green', 'orange', or 'red'
 */
function getLanguageSeoStatus($langCode, $domain, $pageType, $sportSlug, $langDir = null, $pagesSeo = []) {
    $langDir = $langDir ?? LANG_DIR;
    
    // For English, check websites.json
    if ($langCode === 'en') {
        if ($pageType === 'home') {
            return getHomeStatus($pagesSeo);
        } else {
            $seoData = $pagesSeo['sports'][$sportSlug] ?? [];
            $hasTitle = !empty(trim($seoData['title'] ?? ''));
            $hasDescription = !empty(trim($seoData['description'] ?? ''));
            
            if ($hasTitle && $hasDescription) return 'green';
            if ($hasTitle || $hasDescription) return 'orange';
            return 'red';
        }
    }
    
    // For other languages, check language file
    $langFile = $langDir . $langCode . '.json';
    if (!file_exists($langFile)) {
        return 'red';
    }
    
    $langData = json_decode(file_get_contents($langFile), true);
    if (!$langData || !isset($langData['seo'])) {
        return 'red';
    }
    
    // Normalize domain
    $normalizedDomain = normalizeDomain($domain);
    
    // Find domain SEO data
    $seoData = null;
    foreach ($langData['seo'] as $key => $value) {
        $normalizedKey = normalizeDomain($key);
        if ($normalizedKey === $normalizedDomain) {
            $seoData = $value;
            break;
        }
    }
    
    if (!$seoData) {
        return 'red';
    }
    
    // Check SEO fields based on page type
    if ($pageType === 'home') {
        $title = $seoData['home']['title'] ?? '';
        $description = $seoData['home']['description'] ?? '';
    } elseif ($pageType === 'sport' && $sportSlug) {
        $title = '';
        $description = '';
        if (isset($seoData['sports'])) {
            foreach ($seoData['sports'] as $sKey => $sValue) {
                if (strtolower($sKey) === strtolower($sportSlug)) {
                    $title = $sValue['title'] ?? '';
                    $description = $sValue['description'] ?? '';
                    break;
                }
            }
        }
    } else {
        return 'red';
    }
    
    $hasTitle = !empty(trim($title));
    $hasDescription = !empty(trim($description));
    
    if ($hasTitle && $hasDescription) return 'green';
    if ($hasTitle || $hasDescription) return 'orange';
    return 'red';
}

/**
 * Get SEO data for a specific language and page
 * 
 * @param string $langCode Language code
 * @param string $domain Website domain
 * @param string $pageType 'home' or 'sport'
 * @param string|null $sportSlug Sport slug (required if pageType is 'sport')
 * @param string|null $langDir Language directory
 * @return array ['title' => string, 'description' => string]
 */
function getLanguageSeoData($langCode, $domain, $pageType, $sportSlug = null, $langDir = null) {
    $langDir = $langDir ?? LANG_DIR;
    
    // For English, return empty - it's handled by websites.json
    if ($langCode === 'en') {
        return ['title' => '', 'description' => ''];
    }
    
    $langFile = $langDir . $langCode . '.json';
    if (!file_exists($langFile)) {
        return ['title' => '', 'description' => ''];
    }
    
    $langData = json_decode(file_get_contents($langFile), true);
    if (!$langData || !isset($langData['seo'])) {
        return ['title' => '', 'description' => ''];
    }
    
    // Normalize domain for comparison
    $normalizedDomain = normalizeDomain($domain);
    
    // Find matching domain key
    $seoData = null;
    foreach ($langData['seo'] as $key => $value) {
        $normalizedKey = normalizeDomain($key);
        if ($normalizedKey === $normalizedDomain) {
            $seoData = $value;
            break;
        }
    }
    
    if (!$seoData) {
        return ['title' => '', 'description' => ''];
    }
    
    if ($pageType === 'home') {
        return [
            'title' => $seoData['home']['title'] ?? '',
            'description' => $seoData['home']['description'] ?? ''
        ];
    } elseif ($pageType === 'sport' && $sportSlug) {
        // Check case-insensitive for sport slug
        if (isset($seoData['sports'])) {
            foreach ($seoData['sports'] as $sKey => $sValue) {
                if (strtolower($sKey) === strtolower($sportSlug)) {
                    return [
                        'title' => $sValue['title'] ?? '',
                        'description' => $sValue['description'] ?? ''
                    ];
                }
            }
        }
    }
    
    return ['title' => '', 'description' => ''];
}

/**
 * Save SEO data for a specific language
 * 
 * @param string $langCode Language code
 * @param string $domain Website domain
 * @param string $pageType 'home' or 'sport'
 * @param string|null $sportSlug Sport slug
 * @param string $title SEO title
 * @param string $description SEO description
 * @param string|null $langDir Language directory
 * @return array ['success' => bool] or ['error' => string]
 */
function saveLanguageSeoData($langCode, $domain, $pageType, $sportSlug, $title, $description, $langDir = null) {
    $langDir = $langDir ?? LANG_DIR;
    $langFile = $langDir . $langCode . '.json';
    
    if (!file_exists($langFile)) {
        return ['error' => "Language file not found: {$langCode}.json"];
    }
    
    $langData = json_decode(file_get_contents($langFile), true);
    if (!$langData) {
        return ['error' => "Invalid JSON in language file: {$langCode}.json"];
    }
    
    // Normalize domain for comparison
    $normalizedDomain = normalizeDomain($domain);
    
    // Initialize seo structure if not exists
    if (!isset($langData['seo'])) {
        $langData['seo'] = [];
    }
    
    // Find existing domain key (preserve original case/format)
    $domainKey = null;
    foreach ($langData['seo'] as $key => $value) {
        $normalizedKey = normalizeDomain($key);
        if ($normalizedKey === $normalizedDomain) {
            $domainKey = $key;
            break;
        }
    }
    
    // If no existing key, use the original domain
    if (!$domainKey) {
        $domainKey = $domain;
        $langData['seo'][$domainKey] = [];
    }
    
    // Update SEO data
    if ($pageType === 'home') {
        $langData['seo'][$domainKey]['home'] = [
            'title' => $title,
            'description' => $description
        ];
    } elseif ($pageType === 'sport' && $sportSlug) {
        if (!isset($langData['seo'][$domainKey]['sports'])) {
            $langData['seo'][$domainKey]['sports'] = [];
        }
        
        // Find existing sport key (preserve original case)
        $sportKey = $sportSlug;
        foreach ($langData['seo'][$domainKey]['sports'] as $sKey => $sValue) {
            if (strtolower($sKey) === strtolower($sportSlug)) {
                $sportKey = $sKey;
                break;
            }
        }
        
        $langData['seo'][$domainKey]['sports'][$sportKey] = [
            'title' => $title,
            'description' => $description
        ];
    }
    
    // Save to file
    $jsonContent = json_encode($langData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($langFile, $jsonContent)) {
        return ['success' => true];
    } else {
        return ['error' => "Failed to save language file. Check permissions."];
    }
}

// ==========================================
// NOTIFICATION FUNCTIONS
// ==========================================

/**
 * Send Slack notification for new sport category
 * 
 * @param string $sportName The sport name
 * @return bool|string Result of curl_exec or false
 */
function sendSlackNotification($sportName) {
    if (!file_exists(SLACK_CONFIG_FILE)) {
        return false;
    }
    
    $slackConfig = json_decode(file_get_contents(SLACK_CONFIG_FILE), true);
    $slackWebhookUrl = $slackConfig['webhook_url'] ?? '';
    
    if (empty($slackWebhookUrl)) {
        return false;
    }
    
    $message = [
        'text' => "*New Sport Category Added*",
        'blocks' => [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*New Sport Category:* " . $sportName . "\n\nPlease add SEO for new sport page in CMS."
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

// ==========================================
// IMAGE PREVIEW FUNCTIONS
// ==========================================

/**
 * Get logo preview as base64 data URL
 * 
 * @param string $logoFilename The logo filename
 * @param string|null $uploadDir The upload directory (default: LOGOS_DIR)
 * @return string|null Base64 data URL or null if not found
 */
function getLogoPreviewData($logoFilename, $uploadDir = null) {
    $uploadDir = $uploadDir ?? LOGOS_DIR;
    
    if (empty($logoFilename)) {
        return null;
    }
    
    $logoPath = $uploadDir . $logoFilename;
    
    if (!file_exists($logoPath)) {
        return null;
    }
    
    $extension = strtolower(pathinfo($logoFilename, PATHINFO_EXTENSION));
    
    if ($extension === 'svg') {
        $svgContent = file_get_contents($logoPath);
        $base64 = base64_encode($svgContent);
        return "data:image/svg+xml;base64,{$base64}";
    } else {
        $imageData = file_get_contents($logoPath);
        $base64 = base64_encode($imageData);
        $mimeType = ($extension === 'avif') ? 'image/avif' : 'image/' . $extension;
        return "data:{$mimeType};base64,{$base64}";
    }
}

/**
 * Get favicon preview as base64 data URL
 * 
 * @param string $faviconFolder The favicon folder name (website ID)
 * @param string|null $faviconDir The favicon base directory (default: FAVICONS_DIR)
 * @return string|null Base64 data URL or null if not found
 */
function getFaviconPreviewData($faviconFolder, $faviconDir = null) {
    $faviconDir = $faviconDir ?? FAVICONS_DIR;
    
    if (empty($faviconFolder)) {
        return null;
    }
    
    $faviconPath = $faviconDir . $faviconFolder . '/favicon-32x32.png';
    
    if (!file_exists($faviconPath)) {
        return null;
    }
    
    $imageData = file_get_contents($faviconPath);
    $base64 = base64_encode($imageData);
    
    return "data:image/png;base64,{$base64}";
}

// ==========================================
// SPORTS LIST FUNCTIONS
// ==========================================

/**
 * Get sports list for new website from master sports file
 * 
 * @return array Array of sport names
 */
function getSportsListForNewWebsite() {
    if (!file_exists(MASTER_SPORTS_FILE)) {
        return [];
    }
    
    $content = file_get_contents(MASTER_SPORTS_FILE);
    $data = json_decode($content, true);
    
    return $data['sports'] ?? [];
}
// ==========================================
// USER MANAGEMENT FUNCTIONS (NEW - Dec 2024)
// Added for multi-user support with ownership
// ==========================================

/**
 * Load configuration data from websites.json
 * 
 * @return array|null Config data or null on failure
 */
function loadConfigData() {
    if (!defined('WEBSITES_CONFIG_FILE') || !file_exists(WEBSITES_CONFIG_FILE)) {
        return null;
    }
    
    $content = file_get_contents(WEBSITES_CONFIG_FILE);
    return json_decode($content, true);
}

/**
 * Save configuration data to websites.json
 * 
 * @param array $configData Config data to save
 * @return bool True on success
 */
function saveConfigData($configData) {
    if (!defined('WEBSITES_CONFIG_FILE')) {
        return false;
    }
    
    $jsonContent = json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return file_put_contents(WEBSITES_CONFIG_FILE, $jsonContent) !== false;
}

/**
 * Get current logged-in user
 * 
 * @return array|null User data or null if not logged in
 */
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    $configData = loadConfigData();
    if (!$configData) {
        return null;
    }
    
    // Support both old 'admins' and new 'users' structure
    $users = $configData['users'] ?? $configData['admins'] ?? [];
    
    foreach ($users as $user) {
        if ($user['id'] == $_SESSION['user_id']) {
            return $user;
        }
    }
    
    return null;
}

/**
 * Get user by ID
 * 
 * @param int $userId User ID
 * @return array|null User data or null if not found
 */
function getUserById($userId) {
    $configData = loadConfigData();
    if (!$configData) {
        return null;
    }
    
    $users = $configData['users'] ?? $configData['admins'] ?? [];
    
    foreach ($users as $user) {
        if ($user['id'] == $userId) {
            return $user;
        }
    }
    
    return null;
}

/**
 * Get all users
 * 
 * @return array Array of users
 */
function getAllUsers() {
    $configData = loadConfigData();
    if (!$configData) {
        return [];
    }
    
    return $configData['users'] ?? $configData['admins'] ?? [];
}

/**
 * Check if username already exists (excluding current user)
 * 
 * @param string $username Username to check
 * @param int|null $excludeUserId User ID to exclude from check (for editing)
 * @return bool True if username exists
 */
function usernameExists($username, $excludeUserId = null) {
    $users = getAllUsers();
    
    foreach ($users as $user) {
        if (strtolower($user['username']) === strtolower($username)) {
            if ($excludeUserId !== null && $user['id'] == $excludeUserId) {
                continue;
            }
            return true;
        }
    }
    
    return false;
}

/**
 * Generate next user ID
 * 
 * @return int Next available user ID
 */
function getNextUserId() {
    $users = getAllUsers();
    
    if (empty($users)) {
        return 1;
    }
    
    $maxId = 0;
    foreach ($users as $user) {
        if ($user['id'] > $maxId) {
            $maxId = $user['id'];
        }
    }
    
    return $maxId + 1;
}

/**
 * Get owner identifier for current user
 * 
 * @return string|null Owner identifier (e.g., "user_1") or null if not logged in
 */
function getCurrentUserOwner() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    return 'user_' . $_SESSION['user_id'];
}

/**
 * Check if current user can access a website
 * 
 * @param array $website Website data
 * @return bool True if user can access
 */
function canAccessWebsite($website) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $owner = $website['owner'] ?? 'shared';
    $currentUserOwner = getCurrentUserOwner();
    
    return ($owner === $currentUserOwner || $owner === 'shared');
}

/**
 * Filter websites for current user
 * 
 * @param array $websites All websites
 * @return array Filtered websites
 */
function filterWebsitesForUser($websites) {
    if (!isset($_SESSION['user_id'])) {
        return [];
    }
    
    $currentUserOwner = getCurrentUserOwner();
    $filtered = [];
    
    foreach ($websites as $website) {
        $owner = $website['owner'] ?? 'shared';
        
        if ($owner === $currentUserOwner || $owner === 'shared') {
            $filtered[] = $website;
        }
    }
    
    return $filtered;
}

/**
 * Get website counts for current user
 * 
 * @param array $websites All websites
 * @return array ['own' => count, 'shared' => count, 'total' => count]
 */
function getWebsiteCountsForUser($websites) {
    if (!isset($_SESSION['user_id'])) {
        return ['own' => 0, 'shared' => 0, 'total' => 0];
    }
    
    $currentUserOwner = getCurrentUserOwner();
    $own = 0;
    $shared = 0;
    
    foreach ($websites as $website) {
        $owner = $website['owner'] ?? 'shared';
        
        if ($owner === $currentUserOwner) {
            $own++;
        } elseif ($owner === 'shared') {
            $shared++;
        }
    }
    
    return [
        'own' => $own,
        'shared' => $shared,
        'total' => $own + $shared
    ];
}

/**
 * Get owner display name
 * 
 * @param string $owner Owner identifier (e.g., "user_1", "shared")
 * @return string Display name
 */
function getOwnerDisplayName($owner) {
    if ($owner === 'shared') {
        return 'Shared';
    }
    
    if (strpos($owner, 'user_') === 0) {
        $userId = (int) str_replace('user_', '', $owner);
        $user = getUserById($userId);
        
        if ($user) {
            return $user['username'];
        }
    }
    
    return 'Unknown';
}

/**
 * Check if current user is the owner of a website
 * 
 * @param array $website Website data
 * @return bool True if current user owns the website
 */
function isWebsiteOwner($website) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $owner = $website['owner'] ?? 'shared';
    $currentUserOwner = getCurrentUserOwner();
    
    return ($owner === $currentUserOwner);
}

/**
 * Set flash message in session
 * 
 * @param string $type Message type (success, error, warning, info)
 * @param string $message Message text
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_' . $type] = $message;
}

/**
 * Get and clear flash message
 * 
 * @param string $type Message type
 * @return string|null Message or null
 */
function getFlashMessage($type) {
    $key = 'flash_' . $type;
    
    if (isset($_SESSION[$key])) {
        $message = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $message;
    }
    
    return null;
}

/**
 * Format date for display
 * 
 * @param string $date Date string
 * @param string $format Output format
 * @return string Formatted date
 */
function formatDate($date, $format = 'M j, Y') {
    if (empty($date)) {
        return '';
    }
    
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : $date;
}