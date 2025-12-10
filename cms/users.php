<?php
/**
 * Users Management Page
 * 
 * Allows super admins to:
 * - View all users
 * - Add new users
 * - Delete users (except themselves)
 * 
 * Users can only change their OWN credentials via profile.php
 * 
 * Location: /var/www/u1852176/data/www/watchlivesport.online/users.php
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
$currentUser = getCurrentUser();

if (!$currentUser) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// ==========================================
// HANDLE FORM SUBMISSIONS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $configData = loadConfigData();
    $users = $configData['users'] ?? $configData['admins'] ?? [];
    
    // ==========================================
    // ADD NEW USER
    // ==========================================
    if (isset($_POST['add_user'])) {
        $newUsername = trim($_POST['username'] ?? '');
        $newEmail = trim($_POST['email'] ?? '');
        $newPassword = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $isSuperAdmin = isset($_POST['is_super_admin']);
        
        // Validation
        if (empty($newUsername)) {
            $error = 'Username is required';
        } elseif (strlen($newUsername) < 3) {
            $error = 'Username must be at least 3 characters';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $newUsername)) {
            $error = 'Username can only contain letters, numbers, and underscores';
        } elseif (usernameExists($newUsername)) {
            $error = 'Username already exists';
        } elseif (empty($newPassword)) {
            $error = 'Password is required';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Password must be at least 6 characters';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match';
        } elseif (!empty($newEmail) && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } else {
            // Create new user
            $newUser = [
                'id' => getNextUserId(),
                'username' => $newUsername,
                'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                'email' => $newEmail,
                'is_super_admin' => $isSuperAdmin,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $currentUser['id']
            ];
            
            $users[] = $newUser;
            
            // Save to config
            if (isset($configData['users'])) {
                $configData['users'] = $users;
            } else {
                // Migrate from 'admins' to 'users'
                unset($configData['admins']);
                $configData['users'] = $users;
            }
            
            if (saveConfigData($configData)) {
                $success = 'User "' . htmlspecialchars($newUsername) . '" created successfully!';
            } else {
                $error = 'Failed to save. Check file permissions.';
            }
        }
    }
    
    // ==========================================
    // DELETE USER
    // ==========================================
    if (isset($_POST['delete_user'])) {
        $deleteUserId = (int) ($_POST['user_id'] ?? 0);
        
        // Cannot delete yourself
        if ($deleteUserId == $currentUser['id']) {
            $error = 'You cannot delete your own account';
        } else {
            // Find and remove user
            $found = false;
            $deletedUsername = '';
            $newUsers = [];
            
            foreach ($users as $u) {
                if ($u['id'] == $deleteUserId) {
                    $found = true;
                    $deletedUsername = $u['username'];
                } else {
                    $newUsers[] = $u;
                }
            }
            
            if (!$found) {
                $error = 'User not found';
            } else {
                // Save updated users list
                if (isset($configData['users'])) {
                    $configData['users'] = $newUsers;
                } else {
                    $configData['admins'] = $newUsers;
                }
                
                if (saveConfigData($configData)) {
                    $success = 'User "' . htmlspecialchars($deletedUsername) . '" deleted successfully!';
                } else {
                    $error = 'Failed to delete. Check file permissions.';
                }
            }
        }
    }
}

// Reload users after any changes
$configData = loadConfigData();
$users = $configData['users'] ?? $configData['admins'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - CMS</title>
    <link rel="stylesheet" href="cms-style.css">
    <style>
        .user-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .user-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            font-weight: bold;
        }
        .user-avatar.current {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .user-details h3 {
            margin: 0 0 5px 0;
            font-size: 16px;
        }
        .user-details p {
            margin: 0;
            font-size: 13px;
            color: #666;
        }
        .user-badges {
            display: flex;
            gap: 8px;
            margin-top: 5px;
        }
        .badge {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge.super-admin {
            background: #fff3e0;
            color: #e65100;
        }
        .badge.you {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .user-actions {
            display: flex;
            gap: 10px;
        }
        
        /* Add User Form */
        .add-user-section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .add-user-section h3 {
            margin: 0 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        /* Delete confirmation modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            text-align: center;
        }
        .modal-content h3 {
            margin: 0 0 15px 0;
            color: #dc3545;
        }
        .modal-content p {
            margin: 0 0 20px 0;
            color: #666;
        }
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
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
                <a href="users.php" class="nav-item active">
                    <span>👥</span> Users
                </a>
            </nav>
            
            <div class="cms-user">
                <p><strong><?php echo htmlspecialchars($currentUser['username']); ?></strong></p>
                <a href="profile.php" class="btn btn-sm btn-outline" style="margin-bottom: 5px;">My Profile</a>
                <a href="logout.php" class="btn btn-sm btn-outline">Logout</a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="cms-main">
            <header class="cms-header">
                <h1>Users Management</h1>
            </header>
            
            <div class="cms-content">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <!-- Add New User -->
                <div class="add-user-section">
                    <h3>➕ Add New User</h3>
                    
                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="username">Username *</label>
                                <input type="text" id="username" name="username" 
                                       pattern="[a-zA-Z0-9_]+" minlength="3"
                                       placeholder="Enter username" required>
                                <small style="color: #666;">Letters, numbers, underscores. Min 3 chars.</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email (optional)</label>
                                <input type="email" id="email" name="email" 
                                       placeholder="Enter email">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="password">Password *</label>
                                <input type="password" id="password" name="password" 
                                       minlength="6" placeholder="Enter password" required>
                                <small style="color: #666;">Minimum 6 characters</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password *</label>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       minlength="6" placeholder="Confirm password" required>
                            </div>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_super_admin" name="is_super_admin" checked>
                            <label for="is_super_admin">Super Admin (can manage users)</label>
                        </div>
                        
                        <div style="margin-top: 20px;">
                            <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                        </div>
                    </form>
                </div>
                
                <!-- Users List -->
                <div class="content-section">
                    <h2 style="margin-bottom: 20px;">👥 All Users (<?php echo count($users); ?>)</h2>
                    
                    <?php foreach ($users as $user): ?>
                        <?php $isCurrentUser = ($user['id'] == $currentUser['id']); ?>
                        <div class="user-card">
                            <div class="user-info">
                                <div class="user-avatar <?php echo $isCurrentUser ? 'current' : ''; ?>">
                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                </div>
                                <div class="user-details">
                                    <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                                    <p><?php echo htmlspecialchars($user['email'] ?? 'No email'); ?></p>
                                    <div class="user-badges">
                                        <?php if ($user['is_super_admin'] ?? false): ?>
                                            <span class="badge super-admin">⭐ Super Admin</span>
                                        <?php endif; ?>
                                        <?php if ($isCurrentUser): ?>
                                            <span class="badge you">✓ You</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($user['created_at'])): ?>
                                        <p style="font-size: 11px; color: #999; margin-top: 5px;">
                                            Created: <?php echo formatDate($user['created_at']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="user-actions">
                                <?php if ($isCurrentUser): ?>
                                    <a href="profile.php" class="btn btn-sm">Edit My Profile</a>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')">
                                        Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-content">
            <h3>⚠️ Delete User</h3>
            <p>Are you sure you want to delete user "<strong id="deleteUsername"></strong>"?</p>
            <p style="font-size: 13px; color: #999;">This action cannot be undone.</p>
            
            <form method="POST" action="" id="deleteForm">
                <input type="hidden" name="user_id" id="deleteUserId">
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                    <button type="submit" name="delete_user" class="btn btn-danger">Delete User</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function confirmDelete(userId, username) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUsername').textContent = username;
            document.getElementById('deleteModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        // Close modal on outside click
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>