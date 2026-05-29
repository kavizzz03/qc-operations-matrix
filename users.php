<?php
require_once 'config/database.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Only admin can access this page
if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$page_title = 'User Management';
$message = '';
$messageType = '';

// Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $errors = [];
        if (empty($username)) $errors[] = "Username is required";
        if (empty($password)) $errors[] = "Password is required";
        if (empty($full_name)) $errors[] = "Full name is required";
        if (empty($email)) $errors[] = "Email is required";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
        
        $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetchColumn() > 0) $errors[] = "Username already exists";
        
        $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetchColumn() > 0) $errors[] = "Email already exists";
        
        if (count($errors) > 0) {
            $message = implode(", ", $errors);
            $messageType = "error";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $password, $full_name, $email, $role, $is_active]);
                $message = "User added successfully!";
                $messageType = "success";
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
    
    // Update User
    elseif ($_POST['action'] === 'edit') {
        $user_id = $_POST['user_id'];
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $errors = [];
        if (empty($username)) $errors[] = "Username is required";
        if (empty($full_name)) $errors[] = "Full name is required";
        if (empty($email)) $errors[] = "Email is required";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
        
        $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND user_id != ?");
        $check->execute([$username, $user_id]);
        if ($check->fetchColumn() > 0) $errors[] = "Username already exists";
        
        $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
        $check->execute([$email, $user_id]);
        if ($check->fetchColumn() > 0) $errors[] = "Email already exists";
        
        if (count($errors) > 0) {
            $message = implode(", ", $errors);
            $messageType = "error";
        } else {
            $password_update = "";
            $params = [$username, $full_name, $email, $role, $is_active];
            
            if (!empty($_POST['password'])) {
                $password_update = ", password = ?";
                $params[] = $_POST['password'];
            }
            $params[] = $user_id;
            
            try {
                $sql = "UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, is_active = ? $password_update WHERE user_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $message = "User updated successfully!";
                $messageType = "success";
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
    
    // Delete User
    elseif ($_POST['action'] === 'delete') {
        $user_id = $_POST['user_id'];
        
        if ($user_id == $_SESSION['user_id']) {
            $message = "You cannot delete your own account!";
            $messageType = "error";
        } else {
            try {
                $pdo->prepare("DELETE FROM user_tabs WHERE user_id = ?")->execute([$user_id]);
                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $message = "User deleted successfully!";
                $messageType = "success";
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
    
    // Toggle User Status
    elseif ($_POST['action'] === 'toggle_status') {
        $user_id = $_POST['user_id'];
        $current_status = $_POST['current_status'];
        $new_status = $current_status == 1 ? 0 : 1;
        
        if ($user_id == $_SESSION['user_id'] && $new_status == 0) {
            $message = "You cannot deactivate your own account!";
            $messageType = "error";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
                $stmt->execute([$new_status, $user_id]);
                $status_text = $new_status == 1 ? "activated" : "deactivated";
                $message = "User $status_text successfully!";
                $messageType = "success";
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
    
    // Bulk Delete
    elseif ($_POST['action'] === 'bulk_delete') {
        if (isset($_POST['selected_users']) && is_array($_POST['selected_users'])) {
            $deleted_count = 0;
            foreach ($_POST['selected_users'] as $user_id) {
                if ($user_id != $_SESSION['user_id']) {
                    $pdo->prepare("DELETE FROM user_tabs WHERE user_id = ?")->execute([$user_id]);
                    $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$user_id]);
                    $deleted_count++;
                }
            }
            $message = "$deleted_count user(s) deleted successfully!";
            $messageType = "success";
        } else {
            $message = "No users selected for deletion";
            $messageType = "error";
        }
    }
}

// Fetch users with search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role_filter']) ? $_GET['role_filter'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role_filter && $role_filter != 'all') {
    $sql .= " AND role = ?";
    $params[] = $role_filter;
}

if ($status_filter && $status_filter != 'all') {
    $sql .= " AND is_active = ?";
    $params[] = $status_filter == 'active' ? 1 : 0;
}

$sql .= " ORDER BY user_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get edit data
$editUser = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_GET['edit_id']]);
    $editUser = $stmt->fetch();
}

// Get statistics
$total_admins = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$total_managers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'manager'")->fetchColumn();
$total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$active_users = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
$inactive_users = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 0")->fetchColumn();

include 'includes/header.php';
?>

<!-- User Management Page Styles - Matching Sidebar Design -->
<style>
    /* Stats Cards - Matching sidebar theme */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: 1px solid rgba(0,0,0,0.05);
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }

    .stat-icon {
        width: 55px;
        height: 55px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .stat-info h3 {
        font-size: 1.75rem;
        font-weight: 700;
        color: #1f2937;
        margin: 0;
        line-height: 1.2;
    }

    .stat-info p {
        color: #6b7280;
        font-size: 0.8rem;
        margin: 0.25rem 0 0;
        font-weight: 500;
    }

    /* Form Card */
    .form-card {
        background: white;
        border-radius: 20px;
        padding: 1.75rem;
        margin-bottom: 2rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: 1px solid rgba(0,0,0,0.05);
    }

    .form-card h2 {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #f3f4f6;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-card h2 i {
        color: #dc2626;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.25rem;
    }

    .input-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .input-group label {
        font-weight: 600;
        font-size: 0.85rem;
        color: #374151;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .input-group label i {
        color: #dc2626;
        width: 1rem;
    }

    .input-group input, .input-group select {
        padding: 0.75rem 1rem;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-size: 0.875rem;
        transition: all 0.2s;
        font-family: inherit;
        background: #f9fafb;
    }

    .input-group input:focus, .input-group select:focus {
        outline: none;
        border-color: #dc2626;
        background: white;
        box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
    }

    .input-group small {
        font-size: 0.7rem;
        color: #9ca3af;
    }

    /* Table Card */
    .table-card {
        background: white;
        border-radius: 20px;
        padding: 1.75rem;
        margin-bottom: 2rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: 1px solid rgba(0,0,0,0.05);
    }

    .table-wrapper {
        overflow-x: auto;
        border-radius: 12px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    thead {
        background: #f9fafb;
        border-bottom: 2px solid #e5e7eb;
    }

    th {
        text-align: left;
        padding: 1rem;
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #6b7280;
    }

    td {
        padding: 1rem;
        border-bottom: 1px solid #f3f4f6;
        font-size: 0.875rem;
        vertical-align: middle;
    }

    tr:hover {
        background: #f9fafb;
    }

    /* Role Badges */
    .role-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.25rem 0.85rem;
        border-radius: 30px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .role-admin {
        background: linear-gradient(135deg, #fef2f2, #fee2e2);
        color: #dc2626;
    }

    .role-manager {
        background: linear-gradient(135deg, #fffbeb, #fef3c7);
        color: #d97706;
    }

    .role-user {
        background: linear-gradient(135deg, #eff6ff, #dbeafe);
        color: #2563eb;
    }

    /* Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.25rem 0.75rem;
        border-radius: 30px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .status-active {
        background: #ecfdf5;
        color: #059669;
    }

    .status-inactive {
        background: #fef2f2;
        color: #dc2626;
    }

    /* Toggle Switch */
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 46px;
        height: 24px;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #d1d5db;
        transition: 0.3s;
        border-radius: 34px;
    }

    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: 0.3s;
        border-radius: 50%;
    }

    .toggle-switch input:checked + .toggle-slider {
        background-color: #10b981;
    }

    .toggle-switch input:checked + .toggle-slider:before {
        transform: translateX(22px);
    }

    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1.3rem;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.2s;
        cursor: pointer;
        border: none;
        font-family: inherit;
    }

    .btn-primary {
        background: #dc2626;
        color: white;
        box-shadow: 0 2px 8px rgba(220,38,38,0.2);
    }

    .btn-primary:hover {
        background: #b91c1c;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(220,38,38,0.3);
    }

    .btn-secondary {
        background: #f3f4f6;
        color: #374151;
        border: 1px solid #e5e7eb;
    }

    .btn-secondary:hover {
        background: #e5e7eb;
    }

    .btn-danger {
        background: #ef4444;
        color: white;
    }

    .btn-danger:hover {
        background: #dc2626;
    }

    .btn-sm {
        padding: 0.35rem 0.9rem;
        font-size: 0.7rem;
        border-radius: 10px;
    }

    .btn-export {
        background: #10b981;
        color: white;
        padding: 0.45rem 1rem;
        font-size: 0.75rem;
        border-radius: 12px;
    }

    .btn-export:hover {
        background: #059669;
        transform: translateY(-1px);
    }

    /* Avatar */
    .avatar-placeholder {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #fef2f2, #fee2e2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.75rem;
        font-size: 1rem;
        color: #dc2626;
    }

    /* Message */
    .message {
        padding: 0.9rem 1.2rem;
        border-radius: 16px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.6rem;
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .message.success {
        background: #ecfdf5;
        color: #059669;
        border-left: 4px solid #059669;
    }

    .message.error {
        background: #fef2f2;
        color: #dc2626;
        border-left: 4px solid #dc2626;
    }

    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 0.5rem;
        justify-content: center;
        flex-wrap: wrap;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 3rem !important;
        color: #9ca3af;
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        display: block;
        opacity: 0.5;
    }

    /* Progress Bar */
    .progress-container {
        margin-top: 1.5rem;
    }

    .progress-labels {
        display: flex;
        justify-content: space-between;
        font-size: 0.7rem;
        margin-bottom: 0.5rem;
        opacity: 0.85;
    }

    .progress-bar-bg {
        height: 8px;
        background: rgba(255,255,255,0.25);
        border-radius: 4px;
        overflow: hidden;
    }

    .progress-bar-fill {
        height: 100%;
        background: white;
        border-radius: 4px;
        transition: width 0.5s ease;
    }

    /* Filter inputs */
    .filter-input {
        padding: 0.7rem 1rem;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-family: inherit;
        background: white;
        transition: all 0.2s;
    }

    .filter-input:focus {
        outline: none;
        border-color: #dc2626;
        box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .action-buttons {
            flex-direction: column;
            align-items: stretch;
        }
        
        .table-card, .form-card {
            padding: 1.25rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
        }
    }
</style>

<div style="max-width: 1400px; margin: 0 auto;">
    
    <!-- Display Message -->
    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef2f2; color: #dc2626;">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo count($users); ?></h3>
                <p>Total Users</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fffbeb; color: #f59e0b;">
                <i class="fas fa-crown"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $total_admins; ?></h3>
                <p>Administrators</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #ecfdf5; color: #10b981;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $active_users; ?></h3>
                <p>Active Users</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef2f2; color: #6b7280;">
                <i class="fas fa-ban"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $inactive_users; ?></h3>
                <p>Inactive Users</p>
            </div>
        </div>
    </div>

    <!-- Add/Edit User Form -->
    <div class="form-card">
        <h2>
            <?php if ($editUser): ?>
                <i class="fas fa-user-edit"></i> Edit User
            <?php else: ?>
                <i class="fas fa-user-plus"></i> Add New User
            <?php endif; ?>
        </h2>
        
        <form method="POST">
            <?php if ($editUser): ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" value="<?php echo $editUser['user_id']; ?>">
            <?php else: ?>
                <input type="hidden" name="action" value="add">
            <?php endif; ?>
            
            <div class="form-grid">
                <div class="input-group">
                    <label><i class="fas fa-user"></i> Username *</label>
                    <input type="text" name="username" required 
                           value="<?php echo $editUser ? htmlspecialchars($editUser['username']) : ''; ?>"
                           placeholder="Enter username">
                    <small>Unique login identifier</small>
                </div>
                
                <div class="input-group">
                    <label><i class="fas fa-lock"></i> Password <?php echo $editUser ? '<span style="font-size:0.7rem;">(Leave blank to keep current)</span>' : '*'; ?></label>
                    <input type="text" name="password" <?php echo !$editUser ? 'required' : ''; ?>
                           placeholder="<?php echo $editUser ? 'Enter new password' : 'Enter password'; ?>">
                    <small>Minimum 4 characters recommended</small>
                </div>
                
                <div class="input-group">
                    <label><i class="fas fa-user-circle"></i> Full Name *</label>
                    <input type="text" name="full_name" required 
                           value="<?php echo $editUser ? htmlspecialchars($editUser['full_name']) : ''; ?>"
                           placeholder="Enter full name">
                    <small>Display name in system</small>
                </div>
                
                <div class="input-group">
                    <label><i class="fas fa-envelope"></i> Email *</label>
                    <input type="email" name="email" required 
                           value="<?php echo $editUser ? htmlspecialchars($editUser['email']) : ''; ?>"
                           placeholder="Enter email address">
                    <small>Valid email for notifications</small>
                </div>
                
                <div class="input-group">
                    <label><i class="fas fa-tag"></i> Role *</label>
                    <select name="role" required>
                        <option value="user" <?php echo ($editUser && $editUser['role'] == 'user') ? 'selected' : ''; ?>>👤 User</option>
                        <option value="manager" <?php echo ($editUser && $editUser['role'] == 'manager') ? 'selected' : ''; ?>>📊 Manager</option>
                        <option value="admin" <?php echo ($editUser && $editUser['role'] == 'admin') ? 'selected' : ''; ?>>⚡ Admin</option>
                    </select>
                    <small>Determines access permissions</small>
                </div>
                
                <div class="input-group" style="flex-direction: row; align-items: center; gap: 15px; flex-wrap: wrap;">
                    <label style="margin-bottom: 0;"><i class="fas fa-power-off"></i> Account Status</label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="is_active" value="1" 
                               <?php echo ($editUser && $editUser['is_active'] == 1) ? 'checked' : (!$editUser ? 'checked' : ''); ?>
                               style="width: 18px; height: 18px;">
                        <span>Active User</span>
                    </label>
                    <small>Inactive users cannot login</small>
                </div>
            </div>
            
            <div style="display: flex; gap: 12px; margin-top: 1.75rem;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas <?php echo $editUser ? 'fa-save' : 'fa-plus'; ?>"></i>
                    <?php echo $editUser ? 'Update User' : 'Add User'; ?>
                </button>
                <?php if ($editUser): ?>
                    <a href="users.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- User List Section -->
    <div class="table-card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-list" style="color: #dc2626;"></i> User List
                <span style="font-size: 0.7rem; font-weight: 500; background: #f3f4f6; padding: 3px 12px; border-radius: 30px; color: #6b7280;">
                    Total: <?php echo count($users); ?>
                </span>
            </h2>
            
            <div style="display: flex; gap: 8px;">
                <button onclick="exportToCSV()" class="btn btn-export">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
                <button onclick="window.print()" class="btn btn-export" style="background: #6b7280;">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
        
        <!-- Search and Filter -->
        <form method="GET" style="margin-bottom: 1.5rem;">
            <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                <div style="flex: 2; min-width: 200px; position: relative;">
                    <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #9ca3af;"></i>
                    <input type="text" name="search" placeholder="Search by username, name or email..." 
                           value="<?php echo htmlspecialchars($search); ?>"
                           style="width: 100%; padding: 10px 15px 10px 45px; border: 2px solid #e5e7eb; border-radius: 12px; font-family: inherit;">
                </div>
                
                <select name="role_filter" class="filter-input">
                    <option value="all">📋 All Roles</option>
                    <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>⚡ Admin</option>
                    <option value="manager" <?php echo $role_filter == 'manager' ? 'selected' : ''; ?>>📊 Manager</option>
                    <option value="user" <?php echo $role_filter == 'user' ? 'selected' : ''; ?>>👤 User</option>
                </select>
                
                <select name="status_filter" class="filter-input">
                    <option value="all">🔄 All Status</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>✅ Active</option>
                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>❌ Inactive</option>
                </select>
                
                <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">
                    <i class="fas fa-filter"></i> Apply
                </button>
                
                <?php if ($search || $role_filter != 'all' || $status_filter != 'all'): ?>
                    <a href="users.php" class="btn btn-secondary" style="padding: 10px 20px;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
        
        <!-- Bulk Delete -->
        <form method="POST" id="bulkDeleteForm" onsubmit="return confirm('⚠️ Delete selected users? This action cannot be undone.');">
            <input type="hidden" name="action" value="bulk_delete">
            
            <div style="display: flex; gap: 15px; align-items: center; margin-bottom: 1.25rem;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 5px 12px; background: #f9fafb; border-radius: 30px;">
                    <input type="checkbox" id="selectAllCheckbox" style="width: 16px; height: 16px;">
                    <span style="font-size: 0.8rem; font-weight: 500;">Select All</span>
                </label>
                <button type="submit" class="btn btn-danger" id="bulkDeleteBtn" style="display: none; padding: 0.4rem 1rem; font-size: 0.75rem;">
                    <i class="fas fa-trash-alt"></i> Delete Selected
                </button>
            </div>
            
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th width="35"><input type="checkbox" id="selectAllHeader" style="width: 16px; height: 16px;"></th>
                            <th>ID</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_users[]" value="<?php echo $user['user_id']; ?>" 
                                               class="user-checkbox" <?php echo $user['user_id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                    </td>
                                    <td style="font-weight: 600; color: #374151;">#<?php echo $user['user_id']; ?></td>
                                    <td>
                                        <div style="display: flex; align-items: center;">
                                            <div class="avatar-placeholder">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; color: #1f2937;"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                <div style="font-size: 0.65rem; color: #9ca3af;">@<?php echo htmlspecialchars($user['username']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <i class="fas fa-envelope" style="color: #9ca3af; margin-right: 6px; font-size: 0.7rem;"></i>
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($user['role'] == 'admin') {
                                            echo '<span class="role-badge role-admin"><i class="fas fa-crown"></i> Admin</span>';
                                        } elseif ($user['role'] == 'manager') {
                                            echo '<span class="role-badge role-manager"><i class="fas fa-chart-line"></i> Manager</span>';
                                        } else {
                                            echo '<span class="role-badge role-user"><i class="fas fa-user"></i> User</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                            <?php if ($user['is_active'] == 1): ?>
                                                <span class="status-badge status-active">
                                                    <i class="fas fa-check-circle"></i> Active
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge status-inactive">
                                                    <i class="fas fa-ban"></i> Inactive
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <input type="hidden" name="current_status" value="<?php echo $user['is_active']; ?>">
                                                    <label class="toggle-switch">
                                                        <input type="submit" style="display: none;">
                                                        <span class="toggle-slider" onclick="this.parentElement.querySelector('input').click();"></span>
                                                    </label>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="font-size: 0.7rem; color: #6b7280;">
                                        <i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <div class="action-buttons">
                                            <a href="?edit_id=<?php echo $user['user_id']; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $role_filter != 'all' ? '&role_filter='.$role_filter : ''; ?><?php echo $status_filter != 'all' ? '&status_filter='.$status_filter : ''; ?>" 
                                               class="btn btn-sm" style="background: #fef2f2; color: #dc2626; text-decoration: none;">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ Delete user \'<?php echo htmlspecialchars($user['username']); ?>\'? This cannot be undone.');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        <i class="fas fa-trash-alt"></i> Delete
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <i class="fas fa-users"></i>
                                    <p>No users found</p>
                                    <?php if ($search || $role_filter != 'all' || $status_filter != 'all'): ?>
                                        <a href="users.php" class="btn btn-primary btn-sm" style="margin-top: 10px;">Clear Filters</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    <!-- Statistics Footer -->
    <div class="form-card" style="background: linear-gradient(135deg, #dc2626, #991b1b); color: white; border: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <div>
                <h3 style="color: white; margin-bottom: 5px; font-size: 1.1rem;">
                    <i class="fas fa-chart-pie"></i> System Overview
                </h3>
                <p style="opacity: 0.85; font-size: 0.7rem;">User distribution by role</p>
            </div>
            <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                <div style="text-align: center;">
                    <div style="font-size: 1.6rem; font-weight: 700;"><?php echo $total_admins; ?></div>
                    <div style="font-size: 0.65rem; opacity: 0.8;">Administrators</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 1.6rem; font-weight: 700;"><?php echo $total_managers; ?></div>
                    <div style="font-size: 0.65rem; opacity: 0.8;">Managers</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 1.6rem; font-weight: 700;"><?php echo $total_users; ?></div>
                    <div style="font-size: 0.65rem; opacity: 0.8;">Regular Users</div>
                </div>
            </div>
        </div>
        
        <div class="progress-container">
            <div class="progress-labels">
                <span><i class="fas fa-check-circle"></i> Active Accounts (<?php echo $active_users; ?>)</span>
                <span><i class="fas fa-ban"></i> Inactive Accounts (<?php echo $inactive_users; ?>)</span>
            </div>
            <div class="progress-bar-bg">
                <div class="progress-bar-fill" style="width: <?php echo count($users) > 0 ? ($active_users / count($users)) * 100 : 0; ?>%;"></div>
            </div>
            <div style="text-align: center; margin-top: 10px; font-size: 0.65rem; opacity: 0.7;">
                Active Rate: <?php echo count($users) > 0 ? round(($active_users / count($users)) * 100, 1) : 0; ?>%
            </div>
        </div>
    </div>
</div>

<script>
    // Select All functionality
    const selectAllHeader = document.getElementById('selectAllHeader');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const userCheckboxes = document.querySelectorAll('.user-checkbox');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    
    function updateBulkDeleteButton() {
        const checkedCount = document.querySelectorAll('.user-checkbox:checked').length;
        bulkDeleteBtn.style.display = checkedCount > 0 ? 'inline-flex' : 'none';
    }
    
    function updateSelectAll() {
        const allCheckboxes = Array.from(userCheckboxes).filter(cb => !cb.disabled);
        const allChecked = allCheckboxes.length > 0 && allCheckboxes.every(cb => cb.checked);
        
        if (selectAllHeader) selectAllHeader.checked = allChecked;
        if (selectAllCheckbox) selectAllCheckbox.checked = allChecked;
        
        updateBulkDeleteButton();
    }
    
    if (selectAllHeader) {
        selectAllHeader.addEventListener('change', function() {
            userCheckboxes.forEach(cb => {
                if (!cb.disabled) cb.checked = selectAllHeader.checked;
            });
            updateSelectAll();
        });
    }
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            userCheckboxes.forEach(cb => {
                if (!cb.disabled) cb.checked = selectAllCheckbox.checked;
            });
            updateSelectAll();
        });
    }
    
    userCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateSelectAll);
    });
    
    updateSelectAll();
    
    // Export to CSV
    function exportToCSV() {
        let csv = "ID,Username,Full Name,Email,Role,Status,Created Date\n";
        <?php foreach ($users as $user): ?>
            csv += `"<?php echo $user['user_id']; ?>","<?php echo addslashes($user['username']); ?>","<?php echo addslashes($user['full_name']); ?>","<?php echo $user['email']; ?>","<?php echo ucfirst($user['role']); ?>","<?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>","<?php echo $user['created_at']; ?>"\n`;
        <?php endforeach; ?>
        
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'users_export_' + new Date().toISOString().slice(0,19).replace(/:/g, '-') + '.csv';
        a.click();
        URL.revokeObjectURL(url);
    }
    
    // Auto hide message after 5 seconds
    setTimeout(() => {
        const msg = document.querySelector('.message');
        if (msg) {
            msg.style.opacity = '0';
            msg.style.transition = 'opacity 0.5s';
            setTimeout(() => msg.remove(), 500);
        }
    }, 5000);
</script>

<?php include 'includes/footer.php'; ?>