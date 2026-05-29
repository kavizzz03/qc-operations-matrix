<?php
require_once 'config/database.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$page_title = 'Floor Management';

// Check if user has access to this page
$allowed_tabs = getUserTabs($pdo);
$has_access = false;
foreach ($allowed_tabs as $tab) {
    if ($tab['tab_url'] == 'floors.php') {
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

// Helper function to check if a table exists
function tableExists($pdo, $tableName) {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// Helper function to get usage count from a table if it exists
function getUsageCount($pdo, $tableName, $columnName, $floor_id) {
    if (!tableExists($pdo, $tableName)) {
        return 0;
    }
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$tableName` WHERE `$columnName` = ?");
        $stmt->execute([$floor_id]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

// CREATE Floor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $floor_name = trim($_POST['floor_name']);
        
        if (empty($floor_name)) {
            $message = "Floor name is required.";
            $messageType = "error";
        } else {
            try {
                // Check if floor name already exists
                $check = $pdo->prepare("SELECT COUNT(*) FROM floors WHERE floor_name = ?");
                $check->execute([$floor_name]);
                if ($check->fetchColumn() > 0) {
                    $message = "Floor name already exists!";
                    $messageType = "error";
                } else {
                    $sql = "INSERT INTO floors (floor_name) VALUES (:name)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':name' => $floor_name]);
                    $message = "Floor added successfully!";
                    $messageType = "success";
                }
            } catch (PDOException $e) {
                $message = "Error adding floor: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
    
    // UPDATE Floor
    elseif ($_POST['action'] === 'edit') {
        $floor_id = $_POST['floor_id'];
        $floor_name = trim($_POST['floor_name']);
        
        if (empty($floor_name)) {
            $message = "Floor name is required.";
            $messageType = "error";
        } else {
            try {
                // Check if floor name exists for other records
                $check = $pdo->prepare("SELECT COUNT(*) FROM floors WHERE floor_name = ? AND floor_id != ?");
                $check->execute([$floor_name, $floor_id]);
                if ($check->fetchColumn() > 0) {
                    $message = "Floor name already exists!";
                    $messageType = "error";
                } else {
                    $sql = "UPDATE floors SET floor_name = :name WHERE floor_id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':name' => $floor_name,
                        ':id' => $floor_id
                    ]);
                    $message = "Floor updated successfully!";
                    $messageType = "success";
                }
            } catch (PDOException $e) {
                $message = "Error updating floor: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
    
    // DELETE Floor - with dynamic table checking
    elseif ($_POST['action'] === 'delete') {
        $floor_id = $_POST['floor_id'];
        
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // First, get the floor name for error message
            $getFloor = $pdo->prepare("SELECT floor_name FROM floors WHERE floor_id = ?");
            $getFloor->execute([$floor_id]);
            $floorInfo = $getFloor->fetch(PDO::FETCH_ASSOC);
            $floorName = $floorInfo ? $floorInfo['floor_name'] : 'Unknown';
            
            // List of possible tables that might reference floors
            $possibleTables = [
                'products' => 'floor_id',
                'items' => 'floor_id',
                'inventory' => 'floor_id',
                'stock' => 'floor_id',
                'product_locations' => 'floor_id',
                'location_mapping' => 'floor_id',
                'inventory_movements' => 'floor_id',
                'stock_adjustments' => 'floor_id',
                'return_products' => 'floor_id',
                'qc_products' => 'floor_id'
            ];
            
            $references = [];
            
            // Check each table that exists
            foreach ($possibleTables as $tableName => $columnName) {
                $count = getUsageCount($pdo, $tableName, $columnName, $floor_id);
                if ($count > 0) {
                    $references[] = "$tableName ($count record(s))";
                }
            }
            
            if (count($references) > 0) {
                $errorMsg = "❌ Cannot delete floor '<strong>" . htmlspecialchars($floorName) . "</strong>' because it is referenced in:<br>";
                foreach ($references as $ref) {
                    $errorMsg .= "• $ref<br>";
                }
                $errorMsg .= "<br>Please reassign or delete these records first.";
                $message = $errorMsg;
                $messageType = "error";
                $pdo->rollBack();
            } else {
                // No dependencies found, safe to delete
                $stmt = $pdo->prepare("DELETE FROM floors WHERE floor_id = :id");
                $stmt->execute([':id' => $floor_id]);
                
                if ($stmt->rowCount() > 0) {
                    $pdo->commit();
                    $message = "✅ Floor '<strong>" . htmlspecialchars($floorName) . "</strong>' deleted successfully!";
                    $messageType = "success";
                } else {
                    $pdo->rollBack();
                    $message = "Floor not found or already deleted.";
                    $messageType = "error";
                }
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            
            // Check for specific foreign key constraint error
            if ($e->errorInfo[1] == 1451) { // Foreign key constraint fails
                $message = "❌ Cannot delete this floor because it is referenced in other records (Foreign Key Constraint).<br>
                           Please check and remove all references to this floor first.";
                $messageType = "error";
            } else {
                $message = "Error deleting floor: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
}

// Fetch all floors
$floors = [];
try {
    $stmt = $pdo->query("SELECT * FROM floors ORDER BY floor_id DESC");
    $floors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching floors: " . $e->getMessage();
    $messageType = "error";
}

// Get floor data for editing
$editFloor = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM floors WHERE floor_id = :id");
    $stmt->execute([':id' => $_GET['edit_id']]);
    $editFloor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$editFloor) {
        $message = "Floor not found!";
        $messageType = "error";
        header('Location: floors.php');
        exit();
    }
}

// Get detailed statistics - check for existing tables only
$total_floors = count($floors);
$used_floors = 0;
$floor_usage = [];

// Check which tables actually exist in your database
$existingTables = [];
try {
    $stmt = $pdo->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Ignore error
}

foreach ($floors as $floor) {
    $totalUsage = 0;
    
    // Check products table if it exists
    if (in_array('products', $existingTables)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE floor_id = ?");
            $stmt->execute([$floor['floor_id']]);
            $totalUsage += $stmt->fetchColumn();
        } catch (PDOException $e) {
            // Skip if column doesn't exist
        }
    }
    
    // Check items table if it exists
    if (in_array('items', $existingTables)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE floor_id = ?");
            $stmt->execute([$floor['floor_id']]);
            $totalUsage += $stmt->fetchColumn();
        } catch (PDOException $e) {
            // Skip if column doesn't exist
        }
    }
    
    // Check inventory table if it exists
    if (in_array('inventory', $existingTables)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventory WHERE floor_id = ?");
            $stmt->execute([$floor['floor_id']]);
            $totalUsage += $stmt->fetchColumn();
        } catch (PDOException $e) {
            // Skip if column doesn't exist
        }
    }
    
    $floor_usage[$floor['floor_id']] = $totalUsage;
    if ($totalUsage > 0) {
        $used_floors++;
    }
}

include 'includes/header.php';
?>

<style>
    /* Your existing styles remain the same */
    .floors-container {
        max-width: 1400px;
        margin: 0 auto;
    }

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
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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

    .input-group input {
        padding: 0.75rem 1rem;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-size: 0.875rem;
        transition: all 0.2s;
        font-family: inherit;
        background: #f9fafb;
    }

    .input-group input:focus {
        outline: none;
        border-color: #dc2626;
        background: white;
        box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
    }

    .input-group small {
        font-size: 0.7rem;
        color: #9ca3af;
        margin-top: 0.25rem;
    }

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

    .id-badge {
        background: linear-gradient(135deg, #fef2f2, #fee2e2);
        padding: 4px 12px;
        border-radius: 30px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
        color: #dc2626;
    }

    .floor-name {
        font-weight: 600;
        color: #1f2937;
        font-size: 0.9rem;
    }

    .floor-name i {
        color: #3b82f6;
        margin-right: 8px;
    }

    .usage-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .usage-yes {
        background: #fef2f2;
        color: #dc2626;
    }

    .usage-no {
        background: #ecfdf5;
        color: #059669;
    }

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

    .btn-danger:hover:not(:disabled) {
        background: #dc2626;
        transform: translateY(-1px);
    }

    .btn-danger:disabled {
        opacity: 0.5;
        cursor: not-allowed;
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

    .created-date {
        font-size: 0.7rem;
        color: #6b7280;
    }

    .created-date i {
        margin-right: 4px;
    }

    .warning-note {
        background: #fffbeb;
        border-left: 4px solid #f59e0b;
        padding: 0.75rem 1rem;
        margin-top: 1rem;
        border-radius: 8px;
        font-size: 0.75rem;
        color: #92400e;
    }

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

<div class="floors-container">
    
    <!-- Display Message -->
    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef2f2; color: #dc2626;">
                <i class="fas fa-building"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $total_floors; ?></h3>
                <p>Total Floors</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #eff6ff; color: #3b82f6;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $total_floors - $used_floors; ?></h3>
                <p>Unused Floors</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #ecfdf5; color: #10b981;">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $used_floors; ?></h3>
                <p>Floors in Use</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fffbeb; color: #f59e0b;">
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
            <?php if ($editFloor): ?>
                <i class="fas fa-edit"></i> Edit Floor
            <?php else: ?>
                <i class="fas fa-plus-circle"></i> Add New Floor
            <?php endif; ?>
        </h2>
        <form method="POST" action="" id="floorForm">
            <?php if ($editFloor): ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="floor_id" value="<?php echo $editFloor['floor_id']; ?>">
            <?php else: ?>
                <input type="hidden" name="action" value="add">
            <?php endif; ?>
            
            <div class="form-grid">
                <div class="input-group">
                    <label><i class="fas fa-building"></i> Floor Name *</label>
                    <input type="text" name="floor_name" id="floor_name" required 
                           value="<?php echo $editFloor ? htmlspecialchars($editFloor['floor_name']) : ''; ?>"
                           placeholder="e.g., Ground Floor, First Floor, Second Floor">
                    <small>Enter the name of the floor (e.g., Ground Floor, Floor 01, etc.)</small>
                </div>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 1.5rem;">
                <?php if ($editFloor): ?>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Floor</button>
                    <a href="floors.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel Edit</a>
                <?php else: ?>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Floor</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- FLOORS LIST -->
    <div class="table-card">
        <h2>
            <i class="fas fa-list-ul"></i> Floors List
            <span style="font-size: 0.7rem; font-weight: 500; background: #f3f4f6; padding: 3px 12px; border-radius: 30px; color: #6b7280; margin-left: 10px;">
                Total: <?php echo $total_floors; ?>
            </span>
        </h2>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th width="60">ID</th>
                        <th>Floor Name</th>
                        <th width="120">Usage Status</th>
                        <th width="180">Created Date</th>
                        <th width="180">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($floors) > 0): ?>
                        <?php foreach ($floors as $floor): ?>
                            <?php 
                            $is_in_use = isset($floor_usage[$floor['floor_id']]) && $floor_usage[$floor['floor_id']] > 0;
                            $usage_count = $floor_usage[$floor['floor_id']] ?? 0;
                            ?>
                            <tr>
                                <td><span class="id-badge">#<?php echo $floor['floor_id']; ?></span></td>
                                <td class="floor-name">
                                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($floor['floor_name']); ?>
                                </td>
                                <td>
                                    <?php if ($is_in_use): ?>
                                        <span class="usage-badge usage-yes">
                                            <i class="fas fa-link"></i> In Use (<?php echo $usage_count; ?> items)
                                        </span>
                                    <?php else: ?>
                                        <span class="usage-badge usage-no">
                                            <i class="fas fa-check"></i> Free
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="created-date">
                                    <i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y h:i A', strtotime($floor['created_at'])); ?>
                                </td>
                                <td class="action-buttons">
                                    <a href="?edit_id=<?php echo $floor['floor_id']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <?php if (!$is_in_use): ?>
                                        <form method="POST" action="" onsubmit="return confirmDelete('<?php echo htmlspecialchars(addslashes($floor['floor_name'])); ?>');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="floor_id" value="<?php echo $floor['floor_id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-danger btn-sm" disabled title="Cannot delete - Floor is in use">
                                            <i class="fas fa-ban"></i> Locked
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="empty-state">
                                <i class="fas fa-building"></i>
                                <p>No floors found.</p>
                                <small>Add your first floor using the form above.</small>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($used_floors > 0): ?>
        <div class="warning-note">
            <i class="fas fa-info-circle"></i> 
            <strong>Note:</strong> <?php echo $used_floors; ?> floor(s) are currently in use and cannot be deleted. 
            To delete a floor that is in use, first reassign or remove all associated records.
        </div>
        <?php endif; ?>
        
        <div class="note" style="margin-top: 1rem; text-align: right; border-top: 1px solid #f3f4f6; padding-top: 0.75rem; font-size: 0.7rem; color: #9ca3af;">
            <i class="fas fa-info-circle"></i> 
            Floors are used to organize items by location. Floors that are in use cannot be deleted.
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
    
    // Delete confirmation
    function confirmDelete(floorName) {
        return confirm('⚠️ Delete floor "' + floorName + '"?\n\nThis action cannot be undone.\n\nClick OK to delete this floor.');
    }
    
    // Client-side validation for floor name
    document.getElementById('floorForm')?.addEventListener('submit', function(e) {
        const floorName = document.getElementById('floor_name')?.value.trim();
        if (!floorName) {
            e.preventDefault();
            alert('Floor name is required!');
            return false;
        }
        
        // Check minimum length
        if (floorName.length < 2) {
            e.preventDefault();
            alert('Floor name must be at least 2 characters long!');
            return false;
        }
        
        // Check maximum length
        if (floorName.length > 50) {
            e.preventDefault();
            alert('Floor name cannot exceed 50 characters!');
            return false;
        }
        
        return true;
    });
</script>

<?php include 'includes/footer.php'; ?>