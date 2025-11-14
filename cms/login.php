<?php
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        // Load admins from JSON - using absolute path
        $configFile = '/var/www/u1852176/data/www/streaming/config/websites.json';
        
        if (file_exists($configFile)) {
            $configContent = file_get_contents($configFile);
            $configData = json_decode($configContent, true);
            $admins = $configData['admins'] ?? [];
            
            foreach ($admins as $admin) {
                if ($admin['username'] === $username && password_verify($password, $admin['password'])) {
                    // Login successful
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    
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
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>
            
            <div class="login-info">
                <p><strong>Default Login:</strong></p>
                <p>Username: <code>admin</code></p>
                <p>Password: <code>admin123</code></p>
                <p class="text-warning">⚠️ Please change the password after first login!</p>
            </div>
        </div>
    </div>
</body>
</html>