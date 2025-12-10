<?php
/**
 * My Profile Page
 * 
 * Allows users to change their OWN username and password only
 * Users cannot change other users' credentials
 * 
 * Location: /var/www/u1852176/data/www/watchlivesport.online/profile.php
 */

session_start();

// ==========================================
// LOAD CENTRALIZED CONFIG AND FUNCTIONS
// ==========================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get current user
$user = getCurrentUser();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// ==========================================
// HANDLE FORM SUBMISSION
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $configData = loadConfigData();
    $users = $configData['users'] ?? $configData['admins'] ?? [];
    
    // Find current user index
    $userIndex = null;
    foreach ($users as $index => $u) {
        if ($u['id'] == $_SESSION['user_id']) {
            $userIndex = $index;
            break;
        }
    }
    
    if ($userIndex === null) {
        $error = 'User not found. Please login again.';
    } else {
        // ==========================================
        // UPDATE USERNAME
        // ==========================================
        if (isset($_POST['update_username'])) {
            $newUsername = trim($_POST['new_username'] ?? '');
            $currentPassword = $_POST['current_password_username'] ?? '';
            
            if (empty($newUsername)) {
                $error = 'Username cannot be empty';
            } elseif (strlen($newUsername) < 3) {
                $error = 'Username must be at least 3 characters';
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $newUsername)) {
                $error = 'Username can only contain letters, numbers, and underscores';
            } elseif (!password_verify($currentPassword, $user['password'])) {
                $error = 'Current password is incorrect';
            } elseif (usernameExists($newUsername, $user['id'])) {
                $error = 'Username already taken by another user';
            } else {
                // Update username
                $users[$userIndex]['username'] = $newUsername;
                
                // Update in config (handle both old 'admins' and new 'users' structure)
                if (isset($configData['users'])) {
                    $configData['users'] = $users;
                } else {
                    $configData['admins'] = $users;
                }
                
                if (saveConfigData($configData)) {
                    // Update session
                    $_SESSION['user_username'] = $newUsername;
                    $success = 'Username updated successfully!';
                    
                    // Refresh user data
                    $user = getCurrentUser();
                } else {
                    $error = 'Failed to save changes. Check file permissions.';
                }
            }
        }
        
        // ==========================================
        // UPDATE PASSWORD
        // ==========================================
        if (isset($_POST['update_password'])) {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (!password_verify($currentPassword, $user['password'])) {
                $error = 'Current password is incorrect';
            } elseif (strlen($newPassword) < 6) {
                $error = 'New password must be at least 6 characters';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'New passwords do not match';
            } else {
                // Update password
                $users[$userIndex]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                
                // Update in config
                if (isset($configData['users'])) {
                    $configData['users'] = $users;
                } else {
                    $configData['admins'] = $users;
                }
                
                if (saveConfigData($configData)) {
                    $success = 'Password updated successfully!';
                    
                    // Refresh user data
                    $user = getCurrentUser();
                } else {
                    $error = 'Failed to save changes. Check file permissions.';
                }
            }
        }
        
        // ==========================================
        // UPDATE EMAIL
        // ==========================================
        if (isset($_POST['update_email'])) {
            $newEmail = trim($_POST['new_email'] ?? '');
            $currentPasswordEmail = $_POST['current_password_email'] ?? '';
            
            if (empty($newEmail)) {
                $error = 'Email cannot be empty';
            } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address';
            } elseif (!password_verify($currentPasswordEmail, $user['password'])) {
                $error = 'Current password is incorrect';
            } else {
                // Update email
                $users[$userIndex]['email'] = $newEmail;
                
                // Update in config
                if (isset($configData['users'])) {
                    $configData['users'] = $users;
                } else {
                    $configData['admins'] = $users;
                }
                
                if (saveConfigData($configData)) {
                    $success = 'Email updated successfully!';
                    
                    // Refresh user data
                    $user = getCurrentUser();
                } else {
                    $error = 'Failed to save changes. Check file permissions.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - CMS</title>
    <link rel="stylesheet" href="cms-style.css">
    <style>
        .profile-section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .profile-section h3 {
            margin: 0 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            color: #333;
        }
        .profile-info {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
        }
        .profile-details h2 {
            margin: 0 0 5px 0;
        }
        .profile-details p {
            margin: 0;
            color: #666;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        .current-value {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .current-value strong {
            color: #333;
        }
        .security-note {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="cms-layout">
        <!-- Sidebar -->
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
                <a href="languages.php" class="nav-item">
                    <span>🌐</span> Languages
                </a>
                <a href="icons.php" class="nav-item">
                    <span>🖼️</span> Icons
                </a>
                <a href="users.php" class="nav-item">
                    <span>👥</span> Users
                </a>
            </nav>
            
            <div class="cms-user">
                <p><strong><?php echo htmlspecialchars($user['username']); ?></strong></p>
                <a href="profile.php" class="btn btn-sm btn-primary" style="margin-bottom: 5px;">My Profile</a>
                <a href="logout.php" class="btn btn-sm btn-outline">Logout</a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="cms-main">
            <header class="cms-header">
                <h1>My Profile</h1>
            </header>
            
            <div class="cms-content">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <!-- Profile Overview -->
                <div class="profile-section">
                    <div class="profile-info">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                        </div>
                        <div class="profile-details">
                            <h2><?php echo htmlspecialchars($user['username']); ?></h2>
                            <p><?php echo htmlspecialchars($user['email'] ?? 'No email set'); ?></p>
                            <p style="font-size: 12px; color: #999; margin-top: 5px;">
                                <?php echo ($user['is_super_admin'] ?? false) ? '⭐ Super Admin' : '👤 User'; ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Change Username -->
                <div class="profile-section">
                    <h3>🏷️ Change Username</h3>
                    
                    <div class="current-value">
                        Current username: <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                    </div>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="new_username">New Username</label>
                            <input type="text" id="new_username" name="new_username" 
                                   pattern="[a-zA-Z0-9_]+" minlength="3"
                                   placeholder="Enter new username" required>
                            <small style="color: #666;">Letters, numbers, and underscores only. Minimum 3 characters.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="current_password_username">Current Password (to confirm)</label>
                            <input type="password" id="current_password_username" name="current_password_username" 
                                   placeholder="Enter your current password" required>
                        </div>
                        
                        <button type="submit" name="update_username" class="btn btn-primary">Update Username</button>
                    </form>
                </div>
                
                <!-- Change Email -->
                <div class="profile-section">
                    <h3>📧 Change Email</h3>
                    
                    <div class="current-value">
                        Current email: <strong><?php echo htmlspecialchars($user['email'] ?? 'Not set'); ?></strong>
                    </div>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="new_email">New Email</label>
                            <input type="email" id="new_email" name="new_email" 
                                   placeholder="Enter new email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="current_password_email">Current Password (to confirm)</label>
                            <input type="password" id="current_password_email" name="current_password_email" 
                                   placeholder="Enter your current password" required>
                        </div>
                        
                        <button type="submit" name="update_email" class="btn btn-primary">Update Email</button>
                    </form>
                </div>
                
                <!-- Change Password -->
                <div class="profile-section">
                    <h3>🔐 Change Password</h3>
                    
                    <div class="security-note">
                        ⚠️ Choose a strong password with at least 6 characters. After changing your password, you'll need to use the new password to log in.
                    </div>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" 
                                   placeholder="Enter your current password" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" 
                                       minlength="6" placeholder="Enter new password" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       minlength="6" placeholder="Confirm new password" required>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_password" class="btn btn-primary">Update Password</button>
                    </form>
                </div>
                
                <!-- Back to Dashboard -->
                <div style="margin-top: 20px;">
                    <a href="dashboard.php" class="btn btn-outline">← Back to Dashboard</a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>