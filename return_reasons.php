<?php
require_once 'config/database.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$page_title = 'Return Reasons';

// Check if user has access to this page
$allowed_tabs = getUserTabs($pdo);
$has_access = false;
foreach ($allowed_tabs as $tab) {
    if ($tab['tab_url'] == 'return_reasons.php') {
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

// CREATE Reason
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $reason_text = trim($_POST['reason_text']);
        
        if (empty($reason_text)) {
            $message = "Reason text cannot be empty.";
            $messageType = "error";
        } else {
            try {
                $sql = "INSERT INTO return_reasons (reason_text) VALUES (:reason_text)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':reason_text' => $reason_text]);
                $message = "Return reason added successfully!";
                $messageType = "success";
            } catch (PDOException $e) {
                $message = "Error adding reason: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
    
    // UPDATE Reason
    elseif ($_POST['action'] === 'edit') {
        $reason_id = $_POST['reason_id'];
        $reason_text = trim($_POST['reason_text']);
        
        if (empty($reason_text)) {
            $message = "Reason text cannot be empty.";
            $messageType = "error";
        } else {
            try {
                $sql = "UPDATE return_reasons SET reason_text = :reason_text WHERE reason_id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':reason_text' => $reason_text,
                    ':id' => $reason_id
                ]);
                $message = "Return reason updated successfully!";
                $messageType = "success";
            } catch (PDOException $e) {
                $message = "Error updating reason: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
    
    // DELETE Reason
    elseif ($_POST['action'] === 'delete') {
        $reason_id = $_POST['reason_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM return_reasons WHERE reason_id = :id");
            $stmt->execute([':id' => $reason_id]);
            $message = "Return reason deleted successfully!";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Error deleting reason: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Fetch all return reasons
$reasons = [];
try {
    $stmt = $pdo->query("SELECT * FROM return_reasons ORDER BY reason_id DESC");
    $reasons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching reasons: " . $e->getMessage();
    $messageType = "error";
}

// Get reason data for editing (if edit_id is set)
$editReason = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM return_reasons WHERE reason_id = :id");
    $stmt->execute([':id' => $_GET['edit_id']]);
    $editReason = $stmt->fetch(PDO::FETCH_ASSOC);
}

include 'includes/header.php';
?>

<style>
    /* Page specific styles that match the sidebar theme */
    .reasons-container {
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

    .form-group {
        margin-bottom: 1.25rem;
    }

    .form-group label {
        font-weight: 600;
        font-size: 0.85rem;
        color: #374151;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }

    .form-group label i {
        color: #dc2626;
    }

    .form-group input, .form-group textarea {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-size: 0.875rem;
        transition: all 0.2s;
        font-family: inherit;
        background: #f9fafb;
    }

    .form-group input:focus, .form-group textarea:focus {
        outline: none;
        border-color: #dc2626;
        background: white;
        box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
    }

    .form-group textarea {
        resize: vertical;
        min-height: 100px;
    }

    .form-group small {
        font-size: 0.7rem;
        color: #9ca3af;
        display: block;
        margin-top: 0.5rem;
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

    /* Reason Text */
    .reason-text {
        font-weight: 500;
        color: #1f2937;
        line-height: 1.4;
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

    .note {
        font-size: 0.7rem;
        color: #9ca3af;
        margin-top: 1rem;
        padding-top: 0.75rem;
        border-top: 1px solid #f3f4f6;
        text-align: right;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .stats-grid {
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

<div class="reasons-container">
    
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
                <i class="fas fa-exchange-alt"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo count($reasons); ?></h3>
                <p>Total Reasons</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #eff6ff; color: #3b82f6;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo count($reasons); ?></h3>
                <p>Active Reasons</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fffbeb; color: #f59e0b;">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo date('Y'); ?></h3>
                <p>Current Year</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #f3e8ff; color: #9333ea;">
                <i class="fas fa-database"></i>
            </div>
            <div class="stat-info">
                <h3>return_qc</h3>
                <p>Database</p>
            </div>
        </div>
    </div>

    <!-- ADD / EDIT FORM -->
    <div class="form-card">
        <h2>
            <?php if ($editReason): ?>
                <i class="fas fa-pen-alt"></i> Edit Return Reason
            <?php else: ?>
                <i class="fas fa-plus-circle"></i> Add New Return Reason
            <?php endif; ?>
        </h2>
        <form method="POST" action="">
            <?php if ($editReason): ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="reason_id" value="<?php echo $editReason['reason_id']; ?>">
            <?php else: ?>
                <input type="hidden" name="action" value="add">
            <?php endif; ?>
            
            <div class="form-group">
                <label><i class="fas fa-comment-dots"></i> Reason Text *</label>
                <textarea name="reason_text" 
                          placeholder="Enter return reason description (e.g., 'Damaged product', 'Wrong item shipped', 'Expired product', etc.)"
                          required><?php echo $editReason ? htmlspecialchars($editReason['reason_text']) : ''; ?></textarea>
                <small><i class="fas fa-info-circle"></i> Describe the reason why customers return products</small>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 10px;">
                <?php if ($editReason): ?>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Reason</button>
                    <a href="return_reasons.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel Edit</a>
                <?php else: ?>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Reason</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- RETURN REASONS LIST -->
    <div class="table-card">
        <h2>
            <i class="fas fa-list-ul"></i> All Return Reasons
            <span style="font-size: 0.7rem; font-weight: 500; background: #f3f4f6; padding: 3px 12px; border-radius: 30px; color: #6b7280; margin-left: 10px;">
                Total: <?php echo count($reasons); ?>
            </span>
        </h2>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th width="80">ID</th>
                        <th>Reason Text</th>
                        <th width="180">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($reasons) > 0): ?>
                        <?php foreach ($reasons as $reason): ?>
                            <tr>
                                <td><span class="id-badge">#<?php echo $reason['reason_id']; ?></span></td>
                                <td class="reason-text"><?php echo htmlspecialchars($reason['reason_text']); ?></td>
                                <td class="action-buttons">
                                    <a href="?edit_id=<?php echo $reason['reason_id']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <form method="POST" action="" onsubmit="return confirm('⚠️ Delete reason \'<?php echo htmlspecialchars(addslashes($reason['reason_text'])); ?>\'? This action cannot be undone.');" style="display:inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="reason_id" value="<?php echo $reason['reason_id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="empty-state">
                                <i class="fas fa-exchange-alt"></i>
                                <p>No return reasons found.</p>
                                <small>Add your first return reason using the form above.</small>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="note">
            <i class="fas fa-check-circle" style="color: #dc2626;"></i> 
            Total reasons: <?php echo count($reasons); ?> | Manage reasons for customer returns
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