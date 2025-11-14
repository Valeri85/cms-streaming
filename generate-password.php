<?php
// Upload this file to your CMS folder and visit it in browser
// It will generate the correct password hash for your server

$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>Password Hash Generator</h2>";
echo "<p><strong>Password:</strong> " . htmlspecialchars($password) . "</p>";
echo "<p><strong>Hash:</strong> <code>" . htmlspecialchars($hash) . "</code></p>";

echo "<hr>";
echo "<h3>Test the hash:</h3>";
$testPassword = 'admin123';
if (password_verify($testPassword, $hash)) {
    echo "<p style='color: green;'>✓ Password verification works!</p>";
} else {
    echo "<p style='color: red;'>✗ Password verification failed!</p>";
}

echo "<hr>";
echo "<h3>Copy this JSON:</h3>";
echo "<pre>";
echo json_encode([
    "id" => 1,
    "username" => "admin",
    "password" => $hash,
    "email" => "admin@example.com"
], JSON_PRETTY_PRINT);
echo "</pre>";

echo "<hr>";
echo "<p>Copy the hash above and update your websites.json file</p>";
?>