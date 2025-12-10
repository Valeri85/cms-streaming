<?php
/**
 * CMS Login Page
 * 
 * UPDATED: Now uses 'users' array instead of 'admins'
 * UPDATED: Session uses 'user_id' instead of 'admin_id'
 * 
 * Location: /var/www/u1852176/data/www/watchlivesport.online/login.php
 */

session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        // Load users from JSON - using absolute path
        $configFile = '/var/www/u1852176/data/www/streaming/config/websites.json';
        
        if (file_exists($configFile)) {
            $configContent = file_get_contents($configFile);
            $configData = json_decode($configContent, true);
            
            // Try 'users' array first (new structure), fall back to 'admins' (old structure)
            $users = $configData['users'] ?? $configData['admins'] ?? [];
            
            foreach ($users as $user) {
                if ($user['username'] === $username && password_verify($password, $user['password'])) {
                    // Login successful - set BOTH old and new session variables
                    // New variables (for updated pages)
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_username'] = $user['username'];
                    
                    // Old variables (for backward compatibility with pages not yet updated)
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_username'] = $user['username'];
                    
                    header('Location: dashboard.php');
                    exit;
                }
            }
            
            $error = 'Invalid username or password';
        } else {
            $error = 'Configuration file not found at: ' . $configFile;
        }
    } else {
        $error = 'Please enter username and password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS Login</title>
    <link rel="stylesheet" href="cms-style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <h1>🎯 CMS Login</h1>
            <p class="login-subtitle">Manage Your Streaming Websites</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autofocus 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>
        </div>
    </div>
</body>
</html>