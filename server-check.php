<?php
/**
 * Server Capability Check for Favicon Generation
 * Upload this file to your CMS folder and run it once
 * Delete after checking!
 * 
 * Location: /var/www/u1852176/data/www/watchlivesport.online/server-check.php
 */

echo "<h1>🔍 Server Capability Check</h1>";
echo "<style>body{font-family:Arial,sans-serif;padding:20px;max-width:800px;margin:0 auto;} .ok{color:green;font-weight:bold;} .fail{color:red;font-weight:bold;} .warn{color:orange;font-weight:bold;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>";

// 1. Check PHP Version
echo "<h2>1. PHP Version</h2>";
$phpVersion = phpversion();
echo "<p>PHP Version: <strong>$phpVersion</strong></p>";
if (version_compare($phpVersion, '7.4', '>=')) {
    echo "<p class='ok'>✅ PHP version is OK</p>";
} else {
    echo "<p class='fail'>❌ PHP version too old (need 7.4+)</p>";
}

// 2. Check GD Library
echo "<h2>2. GD Library (Image Processing)</h2>";
if (extension_loaded('gd')) {
    echo "<p class='ok'>✅ GD Library is installed</p>";
    
    $gdInfo = gd_info();
    echo "<pre>";
    echo "GD Version: " . ($gdInfo['GD Version'] ?? 'Unknown') . "\n";
    echo "PNG Support: " . ($gdInfo['PNG Support'] ? 'Yes' : 'No') . "\n";
    echo "JPEG Support: " . ($gdInfo['JPEG Support'] ? 'Yes' : 'No') . "\n";
    echo "WebP Support: " . (($gdInfo['WebP Support'] ?? false) ? 'Yes' : 'No') . "\n";
    echo "</pre>";
    
    // Check if we can create and resize images
    echo "<h3>Testing Image Creation...</h3>";
    try {
        // Create a test image
        $testImg = imagecreatetruecolor(512, 512);
        if ($testImg) {
            echo "<p class='ok'>✅ Can create images</p>";
            
            // Test resize
            $resized = imagecreatetruecolor(32, 32);
            if (imagecopyresampled($resized, $testImg, 0, 0, 0, 0, 32, 32, 512, 512)) {
                echo "<p class='ok'>✅ Can resize images</p>";
            } else {
                echo "<p class='fail'>❌ Cannot resize images</p>";
            }
            
            imagedestroy($testImg);
            imagedestroy($resized);
        } else {
            echo "<p class='fail'>❌ Cannot create images</p>";
        }
    } catch (Exception $e) {
        echo "<p class='fail'>❌ Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p class='fail'>❌ GD Library is NOT installed</p>";
}

// 3. Check Imagick (alternative)
echo "<h2>3. Imagick Library (Alternative)</h2>";
if (extension_loaded('imagick')) {
    echo "<p class='ok'>✅ Imagick is installed</p>";
    $imagick = new Imagick();
    echo "<p>Imagick Version: " . $imagick->getVersion()['versionString'] . "</p>";
} else {
    echo "<p class='warn'>⚠️ Imagick is NOT installed (not required if GD works)</p>";
}

// 4. Check Write Permissions
echo "<h2>4. Write Permissions</h2>";

$testDirs = [
    '/var/www/u1852176/data/www/streaming/images/' => 'Images directory',
    '/var/www/u1852176/data/www/streaming/images/favicons/' => 'Favicons directory (may not exist yet)',
];

foreach ($testDirs as $dir => $name) {
    if (file_exists($dir)) {
        if (is_writable($dir)) {
            echo "<p class='ok'>✅ $name is writable</p>";
        } else {
            echo "<p class='fail'>❌ $name exists but NOT writable</p>";
        }
    } else {
        // Try to create it
        if (@mkdir($dir, 0755, true)) {
            echo "<p class='ok'>✅ $name created successfully</p>";
            rmdir($dir); // Clean up
        } else {
            echo "<p class='warn'>⚠️ $name doesn't exist (will be created when needed)</p>";
        }
    }
}

// 5. Check file upload settings
echo "<h2>5. File Upload Settings</h2>";
echo "<pre>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "\n";
echo "</pre>";

// 6. Summary
echo "<h2>📋 Summary</h2>";
if (extension_loaded('gd')) {
    echo "<p class='ok' style='font-size:18px;'>✅ Your server CAN generate favicons using GD Library</p>";
    echo "<p>I will use GD for favicon generation (most compatible).</p>";
} elseif (extension_loaded('imagick')) {
    echo "<p class='ok' style='font-size:18px;'>✅ Your server CAN generate favicons using Imagick</p>";
    echo "<p>I will use Imagick for favicon generation.</p>";
} else {
    echo "<p class='fail' style='font-size:18px;'>❌ Your server CANNOT generate favicons automatically</p>";
    echo "<p>Alternative: You'll need to upload pre-sized favicons manually, or ask your hosting to enable GD.</p>";
}

echo "<hr>";
echo "<p style='color:#999;'>⚠️ Delete this file after checking: <code>server-check.php</code></p>";
?>