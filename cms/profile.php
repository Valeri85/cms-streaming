<?php
/**
 * My Profile Page
 * 
 * Allows users to change their OWN username and password only
 * Users cannot change other users' credentials
 * 
 * REFACTORED Phase 3: Uses bootstrap.php, header.php, footer.php components
 * ALL FEATURES PRESERVED
 */

require_once __DIR__ . '/includes/bootstrap.php';

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

// ==========================================
// PAGE CONFIGURATION FOR HEADER
// ==========================================
$pageTitle = 'My Profile - CMS';
$currentPage = 'profile';
$extraCss = ['css/profile.css'];

include __DIR__ . '/includes/header.php';
?>

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

<?php include __DIR__ . '/includes/footer.php'; ?>