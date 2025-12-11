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
 * REFACTORED Phase 3: Uses bootstrap.php, header.php, footer.php components
 * ALL FEATURES PRESERVED
 */

require_once __DIR__ . '/includes/bootstrap.php';

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

// ==========================================
// PAGE CONFIGURATION FOR HEADER
// ==========================================
$pageTitle = 'Users - CMS';
$currentPage = 'users';
$extraCss = ['css/users.css'];

include __DIR__ . '/includes/header.php';
?>

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

<?php include __DIR__ . '/includes/footer.php'; ?>