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

$page_title = 'Tab Assignments';
$message = '';
$messageType = '';

// =============================================
// ASSIGN TAB TO USER (from master tabs)
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Assign tab from master list
    if ($_POST['action'] === 'assign_tab') {
        $user_id = $_POST['user_id'];
        $tab_id = $_POST['tab_id'];
        
        if (empty($user_id) || empty($tab_id)) {
            $message = "Please select both user and tab";
            $messageType = "error";
        } else {
            try {
                // Check if already assigned
                $check = $pdo->prepare("SELECT * FROM user_tabs WHERE user_id = ? AND tab_id = ?");
                $check->execute([$user_id, $tab_id]);
                
                if ($check->rowCount() > 0) {
                    $message = "This tab is already assigned to the user!";
                    $messageType = "error";
                } else {
                    // Get tab details from master_tabs
                    $stmt = $pdo->prepare("SELECT * FROM master_tabs WHERE tab_id = ?");
                    $stmt->execute([$tab_id]);
                    $tab = $stmt->fetch();
                    
                    // Insert into user_tabs
                    $insert = $pdo->prepare("INSERT INTO user_tabs (user_id, tab_id, tab_name, show_name, description, tab_url, tab_icon, sort_order) 
                                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $insert->execute([
                        $user_id, 
                        $tab_id,
                        $tab['tab_name'],
                        $tab['show_name'],
                        $tab['description'],
                        $tab['tab_url'],
                        $tab['tab_icon'],
                        $tab['sort_order']
                    ]);
                    
                    $message = "Tab assigned successfully!";
                    $messageType = "success";
                }
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
    
    // Remove tab assignment
    elseif ($_POST['action'] === 'remove_tab') {
        $assignment_id = $_POST['assignment_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM user_tabs WHERE assignment_id = ?");
            $stmt->execute([$assignment_id]);
            $message = "Tab assignment removed successfully!";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    }
    
    // Update sort order
    elseif ($_POST['action'] === 'update_sort') {
        $assignment_id = $_POST['assignment_id'];
        $sort_order = $_POST['sort_order'];
        try {
            $stmt = $pdo->prepare("UPDATE user_tabs SET sort_order = ? WHERE assignment_id = ?");
            $stmt->execute([$sort_order, $assignment_id]);
            $message = "Sort order updated!";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Get all users
$users = $pdo->query("SELECT * FROM users ORDER BY username")->fetchAll();

// Get all available master tabs (not assigned to specific user)
$all_tabs = $pdo->query("SELECT * FROM master_tabs WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

// Get all assignments with user and tab details
$assignments = $pdo->query("
    SELECT ut.*, u.username, u.full_name, u.role, mt.tab_id as master_tab_id
    FROM user_tabs ut 
    JOIN users u ON ut.user_id = u.user_id 
    LEFT JOIN master_tabs mt ON ut.tab_name = mt.tab_name
    ORDER BY u.username, ut.sort_order
")->fetchAll();

// Group assignments by user
$assignments_by_user = [];
foreach ($assignments as $assign) {
    $assignments_by_user[$assign['user_id']][] = $assign;
}

include 'includes/header.php';
?>

<style>
    .tab-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #fef2f2;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        color: #dc2626;
    }
    
    .drag-handle {
        cursor: move;
        color: #9ca3af;
    }
    
    .drag-handle:hover {
        color: #dc2626;
    }
    
    .sort-input {
        width: 60px;
        padding: 4px;
        text-align: center;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
    }
</style>

<!-- Display Message -->
<?php if ($message): ?>
    <div class="message <?php echo $messageType; ?>">
        <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<!-- Assign New Tab Section -->
<div class="form-card">
    <h2><i class="fas fa-plus-circle"></i> Assign Tab to User</h2>
    <form method="POST">
        <input type="hidden" name="action" value="assign_tab">
        
        <div class="form-grid">
            <div class="input-group">
                <label><i class="fas fa-user"></i> Select User *</label>
                <select name="user_id" required>
                    <option value="">-- Select User --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['user_id']; ?>">
                            <?php echo htmlspecialchars($user['username'] . ' - ' . $user['full_name'] . ' (' . ucfirst($user['role']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="input-group">
                <label><i class="fas fa-table-list"></i> Select Tab *</label>
                <select name="tab_id" required>
                    <option value="">-- Select Tab from Master List --</option>
                    <?php foreach ($all_tabs as $tab): ?>
                        <option value="<?php echo $tab['tab_id']; ?>">
                            <i class="<?php echo $tab['tab_icon']; ?>"></i> 
                            <?php echo htmlspecialchars($tab['show_name']); ?> (<?php echo $tab['tab_name']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-link"></i> Assign Tab to User
        </button>
    </form>
</div>

<!-- User Tab Assignments List -->
<div class="table-card">
    <h2><i class="fas fa-tasks"></i> User Tab Assignments</h2>
    
    <?php if (count($assignments_by_user) > 0): ?>
        <?php foreach ($assignments_by_user as $user_id => $user_assignments): 
            $user_name = $user_assignments[0]['username'];
            $full_name = $user_assignments[0]['full_name'];
            $role = $user_assignments[0]['role'];
        ?>
            <div class="form-card" style="margin-bottom: 20px; padding: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <h3 style="color: #1f2937;">
                            <i class="fas fa-user-circle" style="color: #dc2626;"></i>
                            <?php echo htmlspecialchars($full_name); ?> 
                            <span style="font-size: 0.8rem; color: #6b7280;">(@<?php echo htmlspecialchars($user_name); ?>)</span>
                        </h3>
                        <p style="font-size: 0.75rem; color: #6b7280; margin-top: 5px;">
                            Role: <span class="tab-badge"><?php echo ucfirst($role); ?></span>
                        </p>
                    </div>
                    <div>
                        <span class="tab-badge">
                            <i class="fas fa-layer-group"></i> <?php echo count($user_assignments); ?> Tabs Assigned
                        </span>
                    </div>
                </div>
                
                <div class="table-wrapper">
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th width="50">#</th>
                                <th><i class="fas fa-icons"></i> Tab</th>
                                <th><i class="fas fa-eye"></i> Show Name</th>
                                <th><i class="fas fa-link"></i> URL</th>
                                <th><i class="fas fa-sort-numeric-down"></i> Sort Order</th>
                                <th width="100">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_assignments as $index => $assign): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <i class="<?php echo $assign['tab_icon']; ?>" style="color: #dc2626; margin-right: 8px;"></i>
                                        <?php echo htmlspecialchars($assign['tab_name']); ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($assign['show_name']); ?></strong></td>
                                    <td><code style="font-size: 0.75rem;"><?php echo htmlspecialchars($assign['tab_url']); ?></code></td>
                                    <td>
                                        <form method="POST" style="display: flex; gap: 8px; align-items: center;">
                                            <input type="hidden" name="action" value="update_sort">
                                            <input type="hidden" name="assignment_id" value="<?php echo $assign['assignment_id']; ?>">
                                            <input type="number" name="sort_order" value="<?php echo $assign['sort_order']; ?>" 
                                                   class="sort-input" style="width: 70px;">
                                            <button type="submit" class="btn btn-sm" style="padding: 4px 8px;">
                                                <i class="fas fa-save"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Remove this tab from user?')">
                                            <input type="hidden" name="action" value="remove_tab">
                                            <input type="hidden" name="assignment_id" value="<?php echo $assign['assignment_id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash-alt"></i> Remove
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="text-align: center; padding: 50px;">
            <i class="fas fa-tasks" style="font-size: 3rem; color: #dc2626; opacity: 0.5; margin-bottom: 15px; display: block;"></i>
            <p>No tab assignments found. Assign tabs to users using the form above.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Master Tabs List (Read-only) -->
<div class="table-card">
    <h2><i class="fas fa-database"></i> Master Tabs List (Available System Tabs)</h2>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th><i class="fas fa-icons"></i> Icon</th>
                    <th>Tab Name</th>
                    <th>Show Name</th>
                    <th>URL</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_tabs as $tab): ?>
                    <tr>
                        <td>#<?php echo $tab['tab_id']; ?></td>
                        <td><i class="<?php echo $tab['tab_icon']; ?>" style="color: #dc2626; font-size: 1.2rem;"></i></td>
                        <td><code><?php echo htmlspecialchars($tab['tab_name']); ?></code></td>
                        <td><strong><?php echo htmlspecialchars($tab['show_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($tab['tab_url']); ?></td>
                        <td style="font-size: 0.8rem;"><?php echo htmlspecialchars($tab['description']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div style="margin-top: 15px; padding: 10px; background: #fef2f2; border-radius: 12px; font-size: 0.75rem;">
        <i class="fas fa-info-circle" style="color: #dc2626;"></i>
        <strong>Note:</strong> Master tabs are the main system tabs. To add new tabs to the system, insert them into the <code>master_tabs</code> table.
    </div>
</div>

<?php include 'includes/footer.php'; ?>