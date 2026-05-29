<?php
require_once 'config/database.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$page_title = 'QC Modes Management';

// Check if user has access to this page
$allowed_tabs = getUserTabs($pdo);
$has_access = false;
foreach ($allowed_tabs as $tab) {
    if ($tab['tab_url'] == 'qc_modes.php') {
        $has_access = true;
        break;
    }
}
if (!$has_access && $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$message = '';
$messageType = '';

// CREATE Mode
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $mode_name = trim($_POST['mode_name']);
        $description = trim($_POST['description']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($mode_name)) {
            $message = "Mode name is required.";
            $messageType = "error";
        } else {
            try {
                // Check if mode name already exists
                $check = $pdo->prepare("SELECT COUNT(*) FROM qc_modes WHERE mode_name = ?");
                $check->execute([$mode_name]);
                if ($check->fetchColumn() > 0) {
                    $message = "Mode name already exists!";
                    $messageType = "error";
                } else {
                    $sql = "INSERT INTO qc_modes (mode_name, description, is_active) VALUES (:name, :desc, :active)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':name' => $mode_name,
                        ':desc' => $description,
                        ':active' => $is_active
                    ]);
                    $message = "QC Mode added successfully!";
                    $messageType = "success";
                }
            } catch (PDOException $e) {
                $message = "Error adding mode: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
    
    // UPDATE Mode
    elseif ($_POST['action'] === 'edit') {
        $mode_id = $_POST['mode_id'];
        $mode_name = trim($_POST['mode_name']);
        $description = trim($_POST['description']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($mode_name)) {
            $message = "Mode name is required.";
            $messageType = "error";
        } else {
            try {
                // Check if mode name exists for other records
                $check = $pdo->prepare("SELECT COUNT(*) FROM qc_modes WHERE mode_name = ? AND mode_id != ?");
                $check->execute([$mode_name, $mode_id]);
                if ($check->fetchColumn() > 0) {
                    $message = "Mode name already exists!";
                    $messageType = "error";
                } else {
                    $sql = "UPDATE qc_modes SET mode_name = :name, description = :desc, is_active = :active WHERE mode_id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':name' => $mode_name,
                        ':desc' => $description,
                        ':active' => $is_active,
                        ':id' => $mode_id
                    ]);
                    $message = "QC Mode updated successfully!";
                    $messageType = "success";
                }
            } catch (PDOException $e) {
                $message = "Error updating mode: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
    
    // DELETE Mode
    elseif ($_POST['action'] === 'delete') {
        $mode_id = $_POST['mode_id'];
        try {
            // Check if mode is being used in qc_damage_main
            $check = $pdo->prepare("SELECT COUNT(*) FROM qc_damage_main WHERE mode_id = ?");
            $check->execute([$mode_id]);
            if ($check->fetchColumn() > 0) {
                $message = "Cannot delete this mode because it is being used in existing records!";
                $messageType = "error";
            } else {
                $stmt = $pdo->prepare("DELETE FROM qc_modes WHERE mode_id = :id");
                $stmt->execute([':id' => $mode_id]);
                $message = "QC Mode deleted successfully!";
                $messageType = "success";
            }
        } catch (PDOException $e) {
            $message = "Error deleting mode: " . $e->getMessage();
            $messageType = "error";
        }
    }
    
    // Toggle Status
    elseif ($_POST['action'] === 'toggle_status') {
        $mode_id = $_POST['mode_id'];
        $current_status = $_POST['current_status'];
        $new_status = $current_status == 1 ? 0 : 1;
        
        try {
            $stmt = $pdo->prepare("UPDATE qc_modes SET is_active = :active WHERE mode_id = :id");
            $stmt->execute([':active' => $new_status, ':id' => $mode_id]);
            $status_text = $new_status == 1 ? "activated" : "deactivated";
            $message = "Mode $status_text successfully!";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Error updating status: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Fetch all QC modes
$modes = [];
try {
    $stmt = $pdo->query("SELECT * FROM qc_modes ORDER BY mode_id DESC");
    $modes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching modes: " . $e->getMessage();
    $messageType = "error";
}

// Get mode data for editing
$editMode = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM qc_modes WHERE mode_id = :id");
    $stmt->execute([':id' => $_GET['edit_id']]);
    $editMode = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get statistics
$total_modes = count($modes);
$active_modes = 0;
$inactive_modes = 0;
foreach ($modes as $mode) {
    if ($mode['is_active'] == 1) {
        $active_modes++;
    } else {
        $inactive_modes++;
    }
}

include 'includes/header.php';
?>

<style>
    /* QC Modes Page Styles */
    .modes-container {
        max-width: 1400px;
        margin: 0 auto;
    }

    /* Stats Cards */
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

    .input-group input, .input-group textarea, .input-group select {
        padding: 0.75rem 1rem;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-size: 0.875rem;
        transition: all 0.2s;
        font-family: inherit;
        background: #f9fafb;
    }

    .input-group input:focus, .input-group textarea:focus, .input-group select:focus {
        outline: none;
        border-color: #dc2626;
        background: white;
        box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
    }

    .input-group textarea {
        resize: vertical;
        min-height: 80px;
    }

    .input-group small {
        font-size: 0.7rem;
        color: #9ca3af;
        margin-top: 0.25rem;
    }

    .checkbox-group {
        flex-direction: row;
        align-items: center;
        gap: 15px;
    }

    .checkbox-group label {
        margin-bottom: 0;
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

    .table-card h2 {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .table-card h2 i {
        color: #dc2626;
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

    /* ID Badge */
    .id-badge {
        background: linear-gradient(135deg, #fef2f2, #fee2e2);
        padding: 4px 12px;
        border-radius: 30px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
        color: #dc2626;
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

    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

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
        text-decoration: none;
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
        transform: translateY(-1px);
    }

    .btn-warning {
        background: #f59e0b;
        color: white;
    }

    .btn-warning:hover {
        background: #d97706;
    }

    .btn-sm {
        padding: 0.35rem 1rem;
        font-size: 0.7rem;
        border-radius: 10px;
    }

    .btn-success {
        background: #10b981;
        color: white;
    }

    .btn-success:hover {
        background: #059669;
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

    .mode-description {
        max-width: 250px;
        word-break: break-word;
        color: #6b7280;
        font-size: 0.8rem;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
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

<div class="modes-container">
    
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
                <i class="fas fa-microscope"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $total_modes; ?></h3>
                <p>Total QC Modes</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #ecfdf5; color: #10b981;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $active_modes; ?></h3>
                <p>Active Modes</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef2f2; color: #6b7280;">
                <i class="fas fa-ban"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $inactive_modes; ?></h3>
                <p>Inactive Modes</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #eff6ff; color: #3b82f6;">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-info">
                <h3>QC</h3>
                <p>Quality Control</p>
            </div>
        </div>
    </div>

    <!-- ADD / EDIT FORM -->
    <div class="form-card">
        <h2>
            <?php if ($editMode): ?>
                <i class="fas fa-edit"></i> Edit QC Mode
            <?php else: ?>
                <i class="fas fa-plus-circle"></i> Add New QC Mode
            <?php endif; ?>
        </h2>
        <form method="POST" action="">
            <?php if ($editMode): ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="mode_id" value="<?php echo $editMode['mode_id']; ?>">
            <?php else: ?>
                <input type="hidden" name="action" value="add">
            <?php endif; ?>
            
            <div class="form-grid">
                <div class="input-group">
                    <label><i class="fas fa-tag"></i> Mode Name *</label>
                    <input type="text" name="mode_name" required 
                           value="<?php echo $editMode ? htmlspecialchars($editMode['mode_name']) : ''; ?>"
                           placeholder="e.g., Standard QC, Advanced QC, etc.">
                    <small>Unique name for this QC mode</small>
                </div>
                
                <div class="input-group">
                    <label><i class="fas fa-align-left"></i> Description</label>
                    <textarea name="description" placeholder="Describe this QC mode..."><?php echo $editMode ? htmlspecialchars($editMode['description']) : ''; ?></textarea>
                    <small>Optional description of this QC mode</small>
                </div>
                
                <div class="input-group checkbox-group">
                    <label><i class="fas fa-power-off"></i> Status</label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="is_active" value="1" 
                               <?php echo ($editMode && $editMode['is_active'] == 1) ? 'checked' : (!$editMode ? 'checked' : ''); ?>>
                        <span>Active Mode</span>
                    </label>
                    <small>Inactive modes cannot be selected for new records</small>
                </div>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 1.5rem;">
                <?php if ($editMode): ?>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Mode</button>
                    <a href="qc_modes.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel Edit</a>
                <?php else: ?>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Mode</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- QC MODES LIST -->
    <div class="table-card">
        <h2>
            <i class="fas fa-list-ul"></i> QC Modes List
            <span style="font-size: 0.7rem; font-weight: 500; background: #f3f4f6; padding: 3px 12px; border-radius: 30px; color: #6b7280; margin-left: 10px;">
                Total: <?php echo $total_modes; ?>
            </span>
        </h2>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th width="60">ID</th>
                        <th>Mode Name</th>
                        <th>Description</th>
                        <th width="100">Status</th>
                        <th width="100">Created</th>
                        <th width="150">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($modes) > 0): ?>
                        <?php foreach ($modes as $mode): ?>
                            <tr>
                                <td><span class="id-badge">#<?php echo $mode['mode_id']; ?></span></td>
                                <td>
                                    <div style="font-weight: 600; color: #1f2937;"><?php echo htmlspecialchars($mode['mode_name']); ?></div>
                                </td>
                                <td class="mode-description">
                                    <?php if ($mode['description']): ?>
                                        <i class="fas fa-quote-left" style="color: #9ca3af; margin-right: 4px; font-size: 0.7rem;"></i>
                                        <?php echo htmlspecialchars(substr($mode['description'], 0, 60)) . (strlen($mode['description'] ?? '') > 60 ? '...' : ''); ?>
                                    <?php else: ?>
                                        <span style="color: #9ca3af;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <?php if ($mode['is_active'] == 1): ?>
                                            <span class="status-badge status-active">
                                                <i class="fas fa-check-circle"></i> Active
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-inactive">
                                                <i class="fas fa-ban"></i> Inactive
                                            </span>
                                        <?php endif; ?>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="mode_id" value="<?php echo $mode['mode_id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $mode['is_active']; ?>">
                                            <label class="toggle-switch">
                                                <input type="submit" style="display: none;">
                                                <span class="toggle-slider" onclick="this.parentElement.querySelector('input').click();"></span>
                                            </label>
                                        </form>
                                    </div>
                                </td>
                                <td style="font-size: 0.7rem; color: #6b7280;">
                                    <i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($mode['created_at'])); ?>
                                </td>
                                <td class="action-buttons">
                                    <a href="?edit_id=<?php echo $mode['mode_id']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <form method="POST" action="" onsubmit="return confirm('⚠️ Delete QC Mode \'<?php echo htmlspecialchars(addslashes($mode['mode_name'])); ?>\'? This action cannot be undone.');" style="display:inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="mode_id" value="<?php echo $mode['mode_id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="empty-state">
                                <i class="fas fa-microscope"></i>
                                <p>No QC modes found.</p>
                                <small>Add your first QC mode using the form above.</small>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="note" style="margin-top: 1rem; text-align: right; border-top: 1px solid #f3f4f6; padding-top: 0.75rem; font-size: 0.7rem; color: #9ca3af;">
            <i class="fas fa-info-circle"></i> 
            QC Modes define different quality control workflows. Modes that are in use cannot be deleted.
        </div>
    </div>
</div>

<script>
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