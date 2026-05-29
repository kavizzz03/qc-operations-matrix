<?php
require_once 'config/database.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$page_title = 'Supplier Management';

// Check if user has access to this page
$allowed_tabs = getUserTabs($pdo);
$has_access = false;
foreach ($allowed_tabs as $tab) {
    if ($tab['tab_url'] == 'suppliers.php') {
        $has_access = true;
        break;
    }
}
if (!$has_access && $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

// Helper function to validate Sri Lankan phone number
function validateSriLankanPhone($number) {
    $clean = preg_replace('/[^0-9+]/', '', $number);
    
    if (preg_match('/^947\d{8}$/', $clean)) {
        return $clean;
    } elseif (preg_match('/^\+947\d{8}$/', $clean)) {
        return substr($clean, 1);
    } elseif (preg_match('/^07\d{8}$/', $clean)) {
        return '94' . substr($clean, 1);
    }
    return false;
}

$message = '';
$messageType = '';

// CREATE Supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $supplier_name = trim($_POST['supplier_name']);
        $system_id = trim($_POST['system_id']);
        $contact_raw = trim($_POST['contact_number']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        
        if (empty($supplier_name)) {
            $message = "Supplier name is required.";
            $messageType = "error";
        } else {
            $validatedPhone = validateSriLankanPhone($contact_raw);
            if (!$validatedPhone && !empty($contact_raw)) {
                $message = "Invalid Sri Lankan phone number. Use format: 9474533333, +9474533333, or 0745333333";
                $messageType = "error";
            } else {
                try {
                    $sql = "INSERT INTO suppliers (supplier_name, system_id, contact_number, email, address) 
                            VALUES (:name, :system_id, :contact, :email, :address)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':name' => $supplier_name,
                        ':system_id' => $system_id ?: null,
                        ':contact' => $validatedPhone ?: null,
                        ':email' => $email ?: null,
                        ':address' => $address ?: null
                    ]);
                    $message = "Supplier added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding supplier: " . $e->getMessage();
                    $messageType = "error";
                }
            }
        }
    }
    
    // UPDATE Supplier
    elseif ($_POST['action'] === 'edit') {
        $supplier_id = $_POST['supplier_id'];
        $supplier_name = trim($_POST['supplier_name']);
        $system_id = trim($_POST['system_id']);
        $contact_raw = trim($_POST['contact_number']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        
        if (empty($supplier_name)) {
            $message = "Supplier name is required.";
            $messageType = "error";
        } else {
            $validatedPhone = validateSriLankanPhone($contact_raw);
            if (!$validatedPhone && !empty($contact_raw)) {
                $message = "Invalid Sri Lankan phone number. Use format: 9474533333, +9474533333, or 0745333333";
                $messageType = "error";
            } else {
                try {
                    $sql = "UPDATE suppliers SET 
                            supplier_name = :name, 
                            system_id = :system_id, 
                            contact_number = :contact, 
                            email = :email, 
                            address = :address 
                            WHERE supplier_id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':name' => $supplier_name,
                        ':system_id' => $system_id ?: null,
                        ':contact' => $validatedPhone ?: null,
                        ':email' => $email ?: null,
                        ':address' => $address ?: null,
                        ':id' => $supplier_id
                    ]);
                    $message = "Supplier updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating supplier: " . $e->getMessage();
                    $messageType = "error";
                }
            }
        }
    }
    
    // DELETE Supplier
    elseif ($_POST['action'] === 'delete') {
        $supplier_id = $_POST['supplier_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM suppliers WHERE supplier_id = :id");
            $stmt->execute([':id' => $supplier_id]);
            $message = "Supplier deleted successfully!";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Error deleting supplier: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Fetch all suppliers
$suppliers = [];
try {
    $stmt = $pdo->query("SELECT * FROM suppliers ORDER BY supplier_id DESC");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching suppliers: " . $e->getMessage();
    $messageType = "error";
}

// Get supplier data for editing
$editSupplier = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE supplier_id = :id");
    $stmt->execute([':id' => $_GET['edit_id']]);
    $editSupplier = $stmt->fetch(PDO::FETCH_ASSOC);
}

include 'includes/header.php';
?>

<style>
    .suppliers-container {
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

    .input-group input, .input-group textarea {
        padding: 0.75rem 1rem;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-size: 0.875rem;
        transition: all 0.2s;
        font-family: inherit;
        background: #f9fafb;
    }

    .input-group input:focus, .input-group textarea:focus {
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

    /* Phone Number */
    .phone-number {
        font-family: monospace;
        font-weight: 600;
        background: #f1f5f9;
        padding: 4px 10px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.8rem;
        color: #059669;
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

    .supplier-name {
        font-weight: 600;
        color: #1f2937;
    }

    .email-cell {
        color: #3b82f6;
        word-break: break-all;
    }

    .address-cell {
        max-width: 200px;
        word-break: break-word;
        color: #6b7280;
        font-size: 0.8rem;
    }

    .note {
        font-size: 0.7rem;
        color: #9ca3af;
        margin-top: 0.5rem;
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
        
        td, th {
            padding: 0.75rem;
        }
    }
</style>

<div class="suppliers-container">
    
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
                <i class="fas fa-truck"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo count($suppliers); ?></h3>
                <p>Total Suppliers</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #eff6ff; color: #3b82f6;">
                <i class="fas fa-building"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo count($suppliers); ?></h3>
                <p>Active Suppliers</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #ecfdf5; color: #10b981;">
                <i class="fas fa-phone-alt"></i>
            </div>
            <div class="stat-info">
                <h3>SL Format</h3>
                <p>947XXXXXXXX</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fffbeb; color: #f59e0b;">
                <i class="fas fa-database"></i>
            </div>
            <div class="stat-info">
                <h3>return_qc</h3>
                <p>Suppliers Table</p>
            </div>
        </div>
    </div>

    <!-- ADD / EDIT FORM -->
    <div class="form-card">
        <h2>
            <?php if ($editSupplier): ?>
                <i class="fas fa-pen-alt"></i> Edit Supplier
            <?php else: ?>
                <i class="fas fa-plus-circle"></i> Add New Supplier
            <?php endif; ?>
        </h2>
        <form method="POST" action="">
            <?php if ($editSupplier): ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="supplier_id" value="<?php echo $editSupplier['supplier_id']; ?>">
            <?php else: ?>
                <input type="hidden" name="action" value="add">
            <?php endif; ?>
            
            <div class="form-grid">
                <div class="input-group">
                    <label><i class="fas fa-building"></i> Supplier Name *</label>
                    <input type="text" name="supplier_name" required 
                           value="<?php echo $editSupplier ? htmlspecialchars($editSupplier['supplier_name']) : ''; ?>"
                           placeholder="e.g., Quality Traders (Pvt) Ltd">
                </div>
                <div class="input-group">
                    <label><i class="fas fa-qrcode"></i> System ID</label>
                    <input type="text" name="system_id" 
                           value="<?php echo $editSupplier ? htmlspecialchars($editSupplier['system_id']) : ''; ?>"
                           placeholder="Optional internal code">
                    <small>Internal reference code for this supplier</small>
                </div>
                <div class="input-group">
                    <label><i class="fas fa-phone-alt"></i> Contact Number (Sri Lanka)</label>
                    <input type="tel" name="contact_number" id="contact_number"
                           value="<?php echo $editSupplier ? htmlspecialchars($editSupplier['contact_number']) : ''; ?>"
                           placeholder="9474533333 or +9474533333 or 0745333333">
                    <small><i class="fas fa-info-circle"></i> Accepts: 947XXXXXXX , +947XXXXXXX , 07XXXXXXXX → stored as 947XXXXXXX</small>
                </div>
                <div class="input-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" 
                           value="<?php echo $editSupplier ? htmlspecialchars($editSupplier['email']) : ''; ?>"
                           placeholder="contact@example.com">
                </div>
                <div class="input-group">
                    <label><i class="fas fa-map-marker-alt"></i> Address</label>
                    <textarea name="address" placeholder="Full address"><?php echo $editSupplier ? htmlspecialchars($editSupplier['address']) : ''; ?></textarea>
                </div>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 1.5rem;">
                <?php if ($editSupplier): ?>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Supplier</button>
                    <a href="suppliers.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel Edit</a>
                <?php else: ?>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Supplier</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- SUPPLIERS LIST -->
    <div class="table-card">
        <h2>
            <i class="fas fa-list-ul"></i> All Suppliers
            <span style="font-size: 0.7rem; font-weight: 500; background: #f3f4f6; padding: 3px 12px; border-radius: 30px; color: #6b7280; margin-left: 10px;">
                Total: <?php echo count($suppliers); ?>
            </span>
        </h2>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th width="60">ID</th>
                        <th>Supplier Name</th>
                        <th width="100">System ID</th>
                        <th width="140">Contact Number</th>
                        <th>Email</th>
                        <th>Address</th>
                        <th width="150">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($suppliers) > 0): ?>
                        <?php foreach ($suppliers as $supplier): ?>
                            <tr>
                                <td><span class="id-badge">#<?php echo $supplier['supplier_id']; ?></span></td>
                                <td class="supplier-name"><?php echo htmlspecialchars($supplier['supplier_name']); ?></td>
                                <td><?php echo htmlspecialchars($supplier['system_id'] ?? '—'); ?></td>
                                <td>
                                    <?php if ($supplier['contact_number']): ?>
                                        <span class="phone-number">
                                            <i class="fas fa-mobile-alt"></i> <?php echo htmlspecialchars($supplier['contact_number']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #9ca3af;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="email-cell">
                                    <?php if ($supplier['email']): ?>
                                        <i class="fas fa-envelope" style="color: #9ca3af; margin-right: 6px;"></i>
                                        <?php echo htmlspecialchars($supplier['email']); ?>
                                    <?php else: ?>
                                        <span style="color: #9ca3af;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="address-cell">
                                    <?php if ($supplier['address']): ?>
                                        <i class="fas fa-location-dot" style="color: #9ca3af; margin-right: 6px;"></i>
                                        <?php echo htmlspecialchars($supplier['address']); ?>
                                    <?php else: ?>
                                        <span style="color: #9ca3af;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <a href="?edit_id=<?php echo $supplier['supplier_id']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <form method="POST" action="" onsubmit="return confirm('⚠️ Delete supplier \'<?php echo htmlspecialchars(addslashes($supplier['supplier_name'])); ?>\'? This action cannot be undone.');" style="display:inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="supplier_id" value="<?php echo $supplier['supplier_id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="empty-state">
                                <i class="fas fa-truck"></i>
                                <p>No suppliers found.</p>
                                <small>Add your first supplier using the form above.</small>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="note" style="margin-top: 1rem; text-align: right; border-top: 1px solid #f3f4f6; padding-top: 0.75rem;">
            <i class="fas fa-check-circle" style="color: #059669;"></i> 
            Phone numbers stored in <strong>947XXXXXXXX</strong> format (Sri Lanka standard)
        </div>
    </div>
</div>

<script>
    // Client-side validation for Sri Lankan number
    const phoneInput = document.getElementById('contact_number');
    if (phoneInput) {
        let warningDiv = null;
        
        function validatePhone() {
            let val = phoneInput.value.trim();
            if (val === "") {
                if (warningDiv) warningDiv.remove();
                return true;
            }
            
            const cleaned = val.replace(/[^0-9+]/g, '');
            let isValid = false;
            if (/^947\d{8}$/.test(cleaned)) isValid = true;
            else if (/^\+947\d{8}$/.test(cleaned)) isValid = true;
            else if (/^07\d{8}$/.test(cleaned)) isValid = true;
            
            if (!isValid) {
                if (!warningDiv) {
                    warningDiv = document.createElement('div');
                    warningDiv.className = 'note';
                    warningDiv.style.color = '#dc2626';
                    warningDiv.style.marginTop = '6px';
                    phoneInput.parentNode.appendChild(warningDiv);
                }
                warningDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Invalid Sri Lankan number. Use 9474533333, +9474533333 or 0745333333';
                return false;
            } else {
                if (warningDiv) warningDiv.remove();
                return true;
            }
        }
        
        phoneInput.addEventListener('blur', validatePhone);
        phoneInput.addEventListener('input', function() {
            if (warningDiv) warningDiv.remove();
        });
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