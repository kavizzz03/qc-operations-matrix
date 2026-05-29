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
// ADD NEW TAB TO MASTER TABS
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Add new master tab
    if ($_POST['action'] === 'add_master_tab') {
        $tab_name = trim($_POST['tab_name']);
        $show_name = trim($_POST['show_name']);
        $description = trim($_POST['description']);
        $tab_url = trim($_POST['tab_url']);
        $tab_icon = trim($_POST['tab_icon']);
        $sort_order = intval($_POST['sort_order']);
        
        if (empty($tab_name) || empty($show_name) || empty($tab_url)) {
            $message = "Tab Name, Show Name, and URL are required!";
            $messageType = "error";
        } else {
            try {
                // Check if tab already exists
                $check = $pdo->prepare("SELECT * FROM master_tabs WHERE tab_name = ? OR tab_url = ?");
                $check->execute([$tab_name, $tab_url]);
                
                if ($check->rowCount() > 0) {
                    $message = "Tab with this name or URL already exists!";
                    $messageType = "error";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO master_tabs (tab_name, show_name, description, tab_url, tab_icon, sort_order, is_active) 
                                           VALUES (?, ?, ?, ?, ?, ?, 1)");
                    $stmt->execute([$tab_name, $show_name, $description, $tab_url, $tab_icon, $sort_order]);
                    $message = "New tab added to Master Tabs successfully!";
                    $messageType = "success";
                }
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
    
    // Assign tab from master list
    elseif ($_POST['action'] === 'assign_tab') {
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
    
    // Delete master tab
    elseif ($_POST['action'] === 'delete_master_tab') {
        $tab_id = $_POST['tab_id'];
        try {
            // First delete all assignments for this tab
            $pdo->prepare("DELETE FROM user_tabs WHERE tab_id = ?")->execute([$tab_id]);
            // Then delete from master_tabs
            $pdo->prepare("DELETE FROM master_tabs WHERE tab_id = ?")->execute([$tab_id]);
            $message = "Master tab and all its assignments deleted!";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Get all users
$users = $pdo->query("SELECT * FROM users ORDER BY username")->fetchAll();

// Get all available master tabs
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

<link rel="stylesheet" href="assets/css/style.css">

<style>
    .btn-add-tab {
        background: #10b981;
        color: white;
        margin-left: 10px;
    }
    .btn-add-tab:hover {
        background: #059669;
    }
    .master-tab-item {
        transition: all 0.3s;
    }
    .master-tab-item:hover {
        background: #fef2f2;
    }
    
    /* Enhanced Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.6);
        backdrop-filter: blur(5px);
        animation: fadeIn 0.3s ease;
    }
    
    .modal-content {
        background-color: white;
        margin: 3% auto;
        padding: 0;
        border-radius: 28px;
        width: 90%;
        max-width: 650px;
        animation: slideInUp 0.4s cubic-bezier(0.34, 1.2, 0.64, 1);
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3);
        overflow: hidden;
    }
    
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(50px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    .modal-header {
        background: linear-gradient(135deg, #dc2626, #991b1b);
        color: white;
        padding: 22px 28px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-header h3 {
        font-size: 1.3rem;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .modal-header h3 i {
        font-size: 1.4rem;
    }
    
    .close-modal {
        color: white;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.2s;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: rgba(255,255,255,0.1);
    }
    
    .close-modal:hover {
        background: rgba(255,255,255,0.3);
        transform: rotate(90deg);
    }
    
    .modal-body {
        padding: 28px;
        max-height: 60vh;
        overflow-y: auto;
    }
    
    .modal-body::-webkit-scrollbar {
        width: 6px;
    }
    
    .modal-body::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    
    .modal-body::-webkit-scrollbar-thumb {
        background: #dc2626;
        border-radius: 10px;
    }
    
    .modal-footer {
        padding: 18px 28px;
        border-top: 1px solid #e5e7eb;
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        background: #f9fafb;
    }
    
    /* Form Field Improvements */
    .input-group small {
        display: block;
        margin-top: 5px;
        color: #6b7280;
        font-size: 0.7rem;
    }
    
    .input-group input:focus, 
    .input-group select:focus, 
    .input-group textarea:focus {
        border-color: #dc2626;
        box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
    }
    
    /* Icon Preview */
    .icon-preview-box {
        display: inline-block;
        padding: 8px 12px;
        background: #fef2f2;
        border-radius: 12px;
        margin-top: 8px;
        font-size: 0.8rem;
    }
    
    .icon-preview-box i {
        color: #dc2626;
        margin-right: 8px;
    }
    
    /* Success/Error Message Animation */
    .message {
        animation: slideDown 0.4s ease;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Quick Action Tips */
    .tips-card {
        background: linear-gradient(135deg, #fef2f2, #fff5f5);
        border-left: 4px solid #dc2626;
    }
    
    .tips-card h4 {
        color: #dc2626;
        margin-bottom: 10px;
    }
    
    .tips-card ul {
        margin-left: 20px;
        color: #4b5563;
        font-size: 0.8rem;
    }
    
    .tips-card li {
        margin: 8px 0;
    }
</style>

<!-- Display Message -->
<?php if ($message): ?>
    <div class="message <?php echo $messageType; ?>">
        <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<!-- Quick Tips Card -->
<div class="form-card tips-card" style="margin-bottom: 20px;">
    <h4><i class="fas fa-lightbulb"></i> Quick Tips</h4>
    <ul>
        <li><i class="fas fa-plus-circle" style="color: #10b981;"></i> <strong>Add New Tab:</strong> Click the green "Add New System Tab" button to create a new tab</li>
        <li><i class="fas fa-link"></i> <strong>Assign Tab:</strong> Select a user and a tab from master list to assign access</li>
        <li><i class="fas fa-sort-numeric-down"></i> <strong>Sort Order:</strong> Lower numbers appear first in the sidebar menu</li>
        <li><i class="fas fa-trash-alt"></i> <strong>Remove Tab:</strong> Click delete icon to remove tab assignment from user</li>
    </ul>
</div>

<!-- Add New Master Tab Button -->
<div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
    <button onclick="openModalAndScroll()" class="btn btn-success" style="background: #10b981; padding: 12px 24px;">
        <i class="fas fa-plus-circle"></i> Add New System Tab
    </button>
</div>

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
                                                   class="sort-input" style="width: 70px; padding: 6px; text-align: center; border: 1px solid #e5e7eb; border-radius: 8px;">
                                            <button type="submit" class="btn btn-sm" style="padding: 4px 10px; background: #f3f4f6;">
                                                <i class="fas fa-save"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Remove this tab from user?')">
                                            <input type="hidden" name="action" value="remove_tab">
                                            <input type="hidden" name="assignment_id" value="<?php echo $assign['assignment_id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash-alt"></i>
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

<!-- Master Tabs List -->
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
                    <th width="80">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_tabs as $tab): ?>
                    <tr class="master-tab-item">
                        <td>#<?php echo $tab['tab_id']; ?></td>
                        <td><i class="<?php echo $tab['tab_icon']; ?>" style="color: #dc2626; font-size: 1.2rem;"></i></td>
                        <td><code><?php echo htmlspecialchars($tab['tab_name']); ?></code></td>
                        <td><strong><?php echo htmlspecialchars($tab['show_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($tab['tab_url']); ?></td>
                        <td style="font-size: 0.8rem;"><?php echo htmlspecialchars($tab['description']); ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Delete this master tab? All user assignments for this tab will also be deleted!')">
                                <input type="hidden" name="action" value="delete_master_tab">
                                <input type="hidden" name="tab_id" value="<?php echo $tab['tab_id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div style="margin-top: 15px; padding: 10px; background: #fef2f2; border-radius: 12px; font-size: 0.75rem;">
        <i class="fas fa-info-circle" style="color: #dc2626;"></i>
        <strong>Note:</strong> Master tabs are the main system tabs. Add new tabs using the green button above.
    </div>
</div>

<!-- Enhanced Modal for Adding New Master Tab -->
<div id="addTabModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>
                <i class="fas fa-plus-circle"></i> 
                Add New System Tab
            </h3>
            <span class="close-modal" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST" id="addTabForm">
            <input type="hidden" name="action" value="add_master_tab">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="input-group">
                        <label><i class="fas fa-code"></i> Tab Name (system) *</label>
                        <input type="text" name="tab_name" required placeholder="e.g., reports, analytics" 
                               onkeyup="generatePreview()">
                        <small>✓ Unique identifier, lowercase, no spaces (used in URL)</small>
                    </div>
                    
                    <div class="input-group">
                        <label><i class="fas fa-eye"></i> Show Name *</label>
                        <input type="text" name="show_name" required placeholder="e.g., Reports, Analytics"
                               onkeyup="generatePreview()">
                        <small>✓ Display name shown in sidebar menu</small>
                    </div>
                    
                    <div class="input-group">
                        <label><i class="fas fa-link"></i> Page URL *</label>
                        <input type="text" name="tab_url" required placeholder="e.g., reports.php">
                        <small>✓ PHP file name (must exist in the system)</small>
                    </div>
                    
                    <div class="input-group">
                        <label><i class="fas fa-icons"></i> Icon Class</label>
                        <input type="text" name="tab_icon" value="fas fa-link" placeholder="fas fa-chart-line"
                               onkeyup="updateIconPreview()">
                        <small>✓ Font Awesome icon (e.g., fas fa-chart-line, fas fa-file-alt)</small>
                        <div class="icon-preview-box" id="iconPreview">
                            <i class="fas fa-link"></i> Preview: fas fa-link
                        </div>
                    </div>
                    
                    <div class="input-group">
                        <label><i class="fas fa-sort-numeric-down"></i> Sort Order</label>
                        <input type="number" name="sort_order" value="99">
                        <small>✓ Lower numbers appear first in the menu</small>
                    </div>
                    
                    <div class="input-group">
                        <label><i class="fas fa-align-left"></i> Description</label>
                        <textarea name="description" rows="3" placeholder="Brief description of this tab's purpose"></textarea>
                        <small>✓ Optional - helps admins understand the tab's function</small>
                    </div>
                </div>
                
                <!-- Live Preview -->
                <div style="margin-top: 20px; padding: 15px; background: #fef2f2; border-radius: 16px;">
                    <h4 style="font-size: 0.85rem; margin-bottom: 10px; color: #dc2626;">
                        <i class="fas fa-eye"></i> Live Preview
                    </h4>
                    <div id="livePreview" style="display: flex; align-items: center; gap: 12px; padding: 10px; background: white; border-radius: 12px;">
                        <i class="fas fa-link" id="previewIcon" style="color: #dc2626; width: 30px;"></i>
                        <span id="previewText" style="font-weight: 500;">Reports</span>
                        <span style="font-size: 0.7rem; color: #6b7280;" id="previewUrl">(reports.php)</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Add Tab
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Open modal with smooth scroll and animation
    function openModalAndScroll() {
        let modal = document.getElementById('addTabModal');
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        
        // Smooth scroll to modal
        modal.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Focus on first input
        setTimeout(() => {
            let firstInput = modal.querySelector('input[name="tab_name"]');
            if (firstInput) firstInput.focus();
        }, 100);
    }
    
    function closeModal() {
        let modal = document.getElementById('addTabModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
        // Reset form
        document.getElementById('addTabForm').reset();
        document.getElementById('tab_icon').value = 'fas fa-link';
        updateIconPreview();
        generatePreview();
    }
    
    // Update icon preview
    function updateIconPreview() {
        let iconInput = document.querySelector('input[name="tab_icon"]');
        let iconValue = iconInput.value;
        let previewDiv = document.getElementById('iconPreview');
        let previewIconSpan = document.getElementById('previewIcon');
        
        if (iconValue) {
            previewDiv.innerHTML = `<i class="${iconValue}"></i> Preview: ${iconValue}`;
            if (previewIconSpan) {
                previewIconSpan.className = iconValue;
                previewIconSpan.style.color = '#dc2626';
            }
        } else {
            previewDiv.innerHTML = `<i class="fas fa-link"></i> Preview: fas fa-link`;
            if (previewIconSpan) {
                previewIconSpan.className = 'fas fa-link';
            }
        }
    }
    
    // Generate live preview
    function generatePreview() {
        let showName = document.querySelector('input[name="show_name"]').value;
        let tabUrl = document.querySelector('input[name="tab_url"]').value;
        let previewTextSpan = document.getElementById('previewText');
        let previewUrlSpan = document.getElementById('previewUrl');
        
        if (showName) {
            previewTextSpan.textContent = showName;
        } else {
            previewTextSpan.textContent = 'Tab Name';
        }
        
        if (tabUrl) {
            previewUrlSpan.textContent = `(${tabUrl})`;
        } else {
            previewUrlSpan.textContent = '(page.php)';
        }
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        let modal = document.getElementById('addTabModal');
        if (event.target == modal) {
            closeModal();
        }
    }
    
    // Close on ESC key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
    
    // Initialize preview on load
    document.addEventListener('DOMContentLoaded', function() {
        updateIconPreview();
        generatePreview();
    });
</script>

<?php include 'includes/footer.php'; ?>