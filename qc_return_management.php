<?php
// ==========================================
// 1. BACKEND OPERATIONAL ROUTER & CONTROLLER
// ==========================================
// FILE: qc_return_management.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$host = '127.0.0.1';
$db   = 'return_qc';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]));
}

// Handle AJAX Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    
    try {
        if ($action === 'search_suppliers') {
            $term = '%' . ($_POST['term'] ?? '') . '%';
            $stmt = $pdo->prepare("SELECT supplier_id, supplier_name, system_id, contact_number, email FROM suppliers WHERE supplier_name LIKE ? OR system_id LIKE ? OR contact_number LIKE ? LIMIT 20");
            $stmt->execute([$term, $term, $term]);
            echo json_encode($stmt->fetchAll());
            exit;
        }

        if ($action === 'save_complete_invoice') {
            $pdo->beginTransaction();
            try {
                if (empty($_POST['supplier_id']) || empty($_POST['invoice_number'])) {
                    throw new Exception("Missing structural header transaction values.");
                }
                
                $stmt = $pdo->prepare("INSERT INTO supplier_invoices (supplier_id, invoice_number, invoice_date, branch_id, floor_id, checked_date, checker_name, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['supplier_id'], $_POST['invoice_number'], $_POST['invoice_date'],
                    !empty($_POST['branch_id']) ? $_POST['branch_id'] : null,
                    $_POST['floor_id'], $_POST['checked_date'], $_POST['checker_name'], $_POST['added_by']
                ]);
                $invoice_id = $pdo->lastInsertId();
                $lineItems = json_decode($_POST['line_items'], true);
                
                if (empty($lineItems)) throw new Exception("No line items to save");
                
                $stmtItem = $pdo->prepare("INSERT INTO invoice_items (invoice_id, item_id, received_qty, checked_sample_qty, defect_qty, return_qty, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                
                foreach ($lineItems as $item) {
                    $stmtItem->execute([$invoice_id, $item['item_id'], $item['received_qty'], $item['checked_sample_qty'], $item['defect_qty'], $item['return_qty'], $item['status']]);
                    $invoice_item_id = $pdo->lastInsertId();
                    
                    if (!empty($item['reasons']) && $item['return_qty'] > 0) {
                        $stmtReason = $pdo->prepare("INSERT INTO item_return_reasons (invoice_item_id, reason_id, return_qty) VALUES (?, ?, ?)");
                        foreach ($item['reasons'] as $reason) {
                            if (intval($reason['quantity']) > 0) {
                                $stmtReason->execute([$invoice_item_id, $reason['reason_id'], intval($reason['quantity'])]);
                            }
                        }
                    }
                    
                    $stock_to_add = 0;
                    if ($item['status'] === 'PASS') $stock_to_add = $item['received_qty'] - $item['return_qty'];
                    $stmtUpdateStock = $pdo->prepare("UPDATE items SET quantity = quantity + ? WHERE item_id = ?");
                    $stmtUpdateStock->execute([$stock_to_add, $item['item_id']]);
                }
                $pdo->commit();
                echo json_encode(['status' => 'success', 'invoice_id' => $invoice_id, 'items_saved' => count($lineItems)]);
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                exit;
            }
        }

        if ($action === 'search_items') {
            $supplier_id = $_POST['supplier_id'] ?? 0;
            $term = '%' . ($_POST['term'] ?? '') . '%';
            $stmt = $pdo->prepare("SELECT item_id, item_name, item_code, system_id, cost_price, selling_price FROM items WHERE supplier_id = ? AND (item_name LIKE ? OR item_code LIKE ? OR system_id LIKE ?)");
            $stmt->execute([$supplier_id, $term, $term, $term]);
            echo json_encode($stmt->fetchAll());
            exit;
        }

        if ($action === 'quick_add_item') {
            $stmt = $pdo->prepare("INSERT INTO items (supplier_id, item_name, item_code, system_id, quantity, cost_price, selling_price) VALUES (?, ?, ?, ?, 0, ?, ?)");
            $stmt->execute([$_POST['supplier_id'], $_POST['item_name'], $_POST['item_code'], !empty($_POST['system_id']) ? $_POST['system_id'] : null, $_POST['cost_price'] ?? 0, $_POST['selling_price'] ?? 0]);
            echo json_encode(['status' => 'success', 'item_id' => $pdo->lastInsertId()]);
            exit;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

$suppliers = $pdo->query("SELECT supplier_id, supplier_name, system_id, contact_number FROM suppliers")->fetchAll();
$branches  = $pdo->query("SELECT branch_id, branch_code, branch_name FROM branches")->fetchAll();
$floors    = $pdo->query("SELECT floor_id, floor_name FROM floors")->fetchAll();
$reasons   = $pdo->query("SELECT reason_id, reason_text FROM return_reasons ORDER BY reason_text")->fetchAll();
$username = $_SESSION['username'] ?? 'QC Officer';
$userRole = $_SESSION['role'] ?? 'Standard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="logo.png">
    <title>ASB Fashion| Add New AQL  </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f5f7fb;
            min-height: 100vh;
        }

        .header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 50;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .logo h1 {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #1e293b;
        }
        .logo span { color: #dc2626; }
        .logo p {
            font-size: 0.65rem;
            color: #64748b;
            letter-spacing: 1px;
            margin-top: 4px;
        }
        .user-info { text-align: right; }
        .user-name {
            font-weight: 700;
            color: #1e293b;
            font-size: 0.9rem;
        }
        .user-role {
            font-size: 0.7rem;
            color: #64748b;
        }
        .back-btn {
            background: #dc2626;
            color: white;
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.8rem;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .back-btn:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }

        .card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 24px;
            overflow: hidden;
        }
        .card-header {
            padding: 18px 24px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .card-header h2 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
        }
        .card-header h2 i { color: #dc2626; margin-right: 8px; }
        .card-body { padding: 24px; }

        .form-group { margin-bottom: 16px; }
        .form-label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 6px;
        }
        .form-input, .form-select {
            width: 100%;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.85rem;
            color: #1e293b;
            transition: all 0.2s;
            outline: none;
        }
        .form-input:focus, .form-select:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
        }

        .btn-primary {
            background: #dc2626;
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.8rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.8rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        .btn-success {
            background: #10b981;
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.8rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-success:hover { background: #059669; }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        @media (max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } }

        .table-wrapper { overflow-x: auto; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            padding: 16px;
            background: #f8fafc;
            color: #64748b;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
        }
        .data-table td {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            font-size: 0.85rem;
        }
        .data-table tr:hover { background: #fef2f2; cursor: pointer; }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fed7aa; color: #92400e; }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-container {
            background: white;
            border-radius: 20px;
            max-width: 900px;
            width: 100%;
            max-height: 85vh;
            overflow-y: auto;
        }
        .modal-header {
            padding: 18px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
        }
        .modal-header h3 { font-size: 1.1rem; font-weight: 700; color: #1e293b; }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #94a3b8;
        }
        .modal-body { padding: 24px; }

        .item-row {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .item-row input {
            flex: 1;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.8rem;
        }
        .remove-item {
            background: none;
            border: none;
            color: #dc2626;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0 10px;
        }

        .reason-popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            z-index: 999;
            display: none;
        }
        .reason-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1000;
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 550px;
            max-height: 80vh;
            overflow: hidden;
            display: none;
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.2);
        }
        .reason-popup-header {
            padding: 16px 20px;
            background: #fef2f2;
            border-bottom: 1px solid #fecaca;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .reason-popup-header h3 { font-size: 1rem; font-weight: 700; color: #1e293b; }
        .reason-popup-body {
            padding: 20px;
            max-height: 60vh;
            overflow-y: auto;
        }
        .reason-popup-footer {
            padding: 16px 20px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .reason-search {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            width: 200px;
            font-size: 0.8rem;
        }
        .reason-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 10px;
        }
        .reason-check { width: 18px; height: 18px; cursor: pointer; margin-right: 12px; }
        .reason-qty {
            width: 80px;
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            text-align: right;
        }

        .loading-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e2e8f0;
            border-top-color: #dc2626;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            background: white;
            padding: 10px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #1e293b;
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            display: none;
            z-index: 1000;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            padding: 20px;
            color: #94a3b8;
            font-size: 0.7rem;
            border-top: 1px solid #e2e8f0;
        }

        .hidden { display: none; }
        .flex { display: flex; }
        .flex-between { display: flex; justify-content: space-between; align-items: center; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mt-4 { margin-top: 16px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .mb-4 { margin-bottom: 16px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-mono { font-family: monospace; }
    </style>
</head>
<body>

<div class="header">
    <div class="header-content">
        <div class="logo">
            <h1>ASB <span>FASHION</span></h1>
            <p>QC & RETURN MANAGEMENT</p>
        </div>
        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($username) ?></div>
                <div class="user-role"><?= htmlspecialchars($userRole) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- STEP 1: INVOICE HEADER -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-file-invoice"></i> Invoice Header Information</h2>
        </div>
        <div class="card-body">
            <div class="mb-4 p-4 bg-slate-50 rounded-xl border border-slate-200">
                <div class="flex items-center gap-2 mb-3">
                    <span style="font-size:1rem;">🚚</span>
                    <span class="text-xs font-semibold uppercase text-slate-500">Supplier Search</span>
                </div>
                <div class="relative">
                    <div class="flex gap-3">
                        <div class="flex-1 relative">
                            <span style="position:absolute; left:12px; top:12px; color:#94a3b8;">🔍</span>
                            <input type="text" id="supplierSearchInput" placeholder="Type supplier name or system ID..." class="w-full bg-white border border-slate-300 rounded-xl pl-8 pr-4 py-3 text-sm">
                        </div>
                        <button type="button" id="clearSupplierSearch" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 rounded-xl text-slate-600 text-sm">✖ Clear</button>
                    </div>
                    <div id="supplierSearchResults" class="hidden mt-2 absolute left-0 right-0 bg-white border rounded-xl shadow-lg z-50 max-h-64 overflow-y-auto"></div>
                </div>
                <div id="supplierWarningMsg" class="hidden mt-3 bg-amber-50 border border-amber-200 rounded-xl p-3 text-amber-700 text-sm">⚠ No suppliers match your search criteria.</div>
            </div>

            <div class="mb-4">
                <label class="form-label">Or Select from List</label>
                <select id="supplier_id" class="form-select">
                    <option value="">-- Choose Vendor Option --</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['supplier_id'] ?>" data-system-id="<?= htmlspecialchars($s['system_id'] ?? '') ?>">
                            <?= htmlspecialchars($s['supplier_name']) ?>
                            <?php if (!empty($s['system_id'])): ?> (ID: <?= htmlspecialchars($s['system_id']) ?>)<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="selectedSupplierInfo" class="hidden mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg">
                <div class="flex items-center gap-2 text-emerald-600 text-xs font-semibold mb-2">✓ Selected Supplier Details</div>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div><span class="text-slate-500">Name:</span> <span id="selectedSupplierName" class="font-medium text-slate-700"></span></div>
                    <div><span class="text-slate-500">System ID:</span> <span id="selectedSupplierSystemId" class="font-mono text-slate-700"></span></div>
                </div>
            </div>

            <form id="headerForm" class="grid-2">
                <input type="hidden" name="supplier_id" id="hidden_supplier_id">
                <div class="form-group"><label class="form-label">Invoice Number *</label><input type="text" name="invoice_number" id="invoice_number" required class="form-input" placeholder="eg: INV-2026-9912"></div>
                <div class="form-group"><label class="form-label">Invoice Date *</label><input type="date" name="invoice_date" id="invoice_date" required class="form-input"></div>
                <div class="form-group"><label class="form-label">Branch (Optional)</label><select name="branch_id" id="branch_id" class="form-select"><option value="">-- Select Branch --</option><?php foreach ($branches as $b): ?><option value="<?= $b['branch_id'] ?>"><?= htmlspecialchars($b['branch_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label class="form-label">Floor *</label><select name="floor_id" id="floor_id" required class="form-select"><option value="">-- Select Floor --</option><?php foreach ($floors as $f): ?><option value="<?= $f['floor_id'] ?>"><?= htmlspecialchars($f['floor_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label class="form-label">QC Date *</label><input type="date" name="checked_date" id="checked_date" required class="form-input"></div>
                <div class="form-group"><label class="form-label">Inspector Name *</label><input type="text" name="checker_name" id="checker_name" required class="form-input" placeholder="Enter inspector name"></div>
                <div class="form-group"><label class="form-label">Added By</label><input type="text" name="added_by" id="added_by" value="<?= htmlspecialchars($username) ?>" class="form-input"></div>
            </form>

            <div class="flex gap-3 mt-4">
                <button type="button" id="saveDraftBtn" class="btn-secondary">💾 Save Draft</button>
                <button type="button" id="restoreDraftBtn" class="btn-secondary">↩ Restore Draft</button>
                <button type="button" id="clearStorageBtn" class="btn-secondary">🗑 Clear Draft</button>
                <button type="button" id="saveToDatabaseBtn" class="btn-primary" style="margin-left: auto;">💾 Save to Database</button>
            </div>
        </div>
    </div>

    <!-- STEP 2: LINE ITEMS -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-list-check"></i> Line Items & Auditing Grid</h2>
        </div>
        <div class="card-body">
            <div class="grid-2">
                <div>
                    <div class="form-group">
                        <label class="form-label">Search Items</label>
                        <div class="relative">
                            <input type="text" id="itemQuery" placeholder="Search by Code or Name..." class="form-input">
                            <span style="position:absolute; left:12px; top:12px; color:#94a3b8;">🔍</span>
                        </div>
                        <div id="searchResults" class="hidden absolute z-50 bg-white border rounded-xl shadow-lg w-full max-h-60 overflow-y-auto mt-1"></div>
                    </div>

                    <div id="itemSelectionReceipt" class="hidden p-4 bg-slate-50 border border-slate-200 rounded-xl mb-4">
                        <div class="text-xs text-slate-500 font-bold uppercase mb-2">Selected Item</div>
                        <div id="receiptName" class="font-bold text-emerald-600 text-sm">--</div>
                        <div class="grid grid-cols-2 gap-2 text-xs text-slate-400 pt-2 mt-2 border-t border-slate-200">
                            <div>Code: <span id="receiptCode" class="font-mono text-slate-600">--</span></div>
                            <div>Sys ID: <span id="receiptSysId" class="font-mono text-slate-600">--</span></div>
                        </div>
                    </div>

                    <form id="lineItemForm" class="hidden">
                        <input type="hidden" id="selected_item_id">
                        <div class="grid-2">
                            <div class="form-group"><label class="form-label">Delivered Qty</label><input type="number" min="1" id="received_qty" required class="form-input"></div>
                            <div class="form-group"><label class="form-label">Inspected Sample</label><input type="number" min="0" id="checked_sample_qty" required class="form-input"></div>
                        </div>
                        <div class="grid-2">
                            <div class="form-group"><label class="form-label">Defected Units</label><input type="number" min="0" id="defect_qty" required class="form-input"></div>
                            <div class="form-group"><label class="form-label">Return Qty</label><input type="number" min="0" id="return_qty" required class="form-input"></div>
                        </div>

                        <div id="returnReasonsBlock" class="hidden p-3 bg-rose-50 border border-rose-200 rounded-lg mb-3">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-xs font-semibold text-rose-600">⚠ Defect Reasons</span>
                                <button type="button" id="openReasonPopupBtn" class="px-2 py-1 bg-rose-100 hover:bg-rose-200 rounded text-xs text-rose-700">📋 Select Reasons</button>
                            </div>
                            <div id="selectedReasonsSummary" class="text-xs text-slate-500 max-h-20 overflow-y-auto">
                                <span class="italic">No reasons selected</span>
                            </div>
                        </div>

                        <div class="form-group"><label class="form-label">Decision</label><select id="line_status" class="form-select"><option value="PASS">PASS (Batch Accepted)</option><option value="FAIL">FAIL (Line Rejected)</option></select></div>
                        <button type="submit" class="btn-primary w-full">➕ Add to Session</button>
                    </form>
                </div>

                <div>
                    <div class="flex-between mb-3">
                        <span class="text-xs font-bold uppercase text-slate-500">Session Items (<span id="itemCount">0</span>)</span>
                    </div>
                    <div class="table-wrapper border rounded-xl">
                        <table class="data-table">
                            <thead><tr><th>Item</th><th class="text-right">Qty</th><th class="text-center">Status</th><th class="text-center">Action</th></tr></thead>
                            <tbody id="sessionItemsTable"><tr><td colspan="4" class="text-center py-8 text-slate-400">No items added</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>© <?= date('Y') ?> ASB Fashion - Quality Control & Return Management System</p>
    </div>
</div>

<!-- Reason Popup with Search -->
<div id="reasonPopupOverlay" class="reason-popup-overlay"></div>
<div id="reasonPopup" class="reason-popup">
    <div class="reason-popup-header">
        <h3>📋 Select Return Reasons</h3>
        <button id="closeReasonPopup" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
    </div>
    <div class="reason-popup-body">
        <div class="flex-between mb-3">
            <span>Total Return Qty: <strong id="popupReturnQty" style="color:#dc2626;">0</strong></span>
            <input type="text" id="reasonSearchInput" placeholder="🔍 Search reasons..." class="reason-search">
        </div>
        <div id="reasonsListContainer">
            <?php foreach ($reasons as $r): ?>
                <div class="reason-item" data-reason-text="<?= strtolower(htmlspecialchars($r['reason_text'])) ?>">
                    <div class="flex justify-between items-center">
                        <label class="flex items-center cursor-pointer flex-1">
                            <input type="checkbox" class="popup-reason-check" value="<?= $r['reason_id'] ?>" data-reason-text="<?= htmlspecialchars($r['reason_text']) ?>">
                            <span class="text-sm text-slate-700 reason-text"><?= htmlspecialchars($r['reason_text']) ?></span>
                        </label>
                        <input type="number" min="0" step="1" value="0" class="popup-reason-qty reason-qty" disabled placeholder="Qty">
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div id="noReasonsFound" class="hidden text-center text-slate-500 py-4">No matching reasons found</div>
    </div>
    <div class="reason-popup-footer">
        <span>Total Allocated: <span id="totalAllocatedQty" class="font-bold text-emerald-600">0</span></span>
        <div class="flex gap-2">
            <button id="cancelReasonPopup" class="btn-secondary">Cancel</button>
            <button id="confirmReasonsBtn" class="btn-primary">✓ Apply</button>
        </div>
    </div>
</div>

<!-- Quick Add Modal -->
<div id="quickAddModal" class="modal-overlay hidden">
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header"><h3>➕ Quick Add Item</h3><button class="modal-close" onclick="closeQuickAddModal()">&times;</button></div>
        <div class="modal-body">
            <form id="quickAddForm">
                <div class="form-group"><label class="form-label">Item Name *</label><input type="text" name="item_name" required class="form-input"></div>
                <div class="form-group"><label class="form-label">Item Code *</label><input type="text" name="item_code" id="modal_item_code" required class="form-input"></div>
                <div class="form-group"><label class="form-label">System ID</label><input type="text" name="system_id" class="form-input"></div>
                <div class="grid-2"><div class="form-group"><label class="form-label">Cost Price</label><input type="number" step="0.01" name="cost_price" value="0" class="form-input"></div>
                <div class="form-group"><label class="form-label">Selling Price</label><input type="number" step="0.01" name="selling_price" value="0" class="form-input"></div></div>
                <div class="flex gap-3 mt-4"><button type="button" onclick="closeQuickAddModal()" class="btn-secondary">Cancel</button><button type="submit" class="btn-primary">Save Item</button></div>
            </form>
        </div>
    </div>
</div>

<div id="loadingOverlay" class="loading-overlay"><div><div class="loading-spinner"></div><p class="text-white mt-3">Saving...</p></div></div>
<div id="toast" class="toast"></div>

<script>
    let globalSupplierId = null;
    let currentLineItems = [];
    let tempReasons = [];
    let currentReturnQty = 0;
    const STORAGE_KEY = 'asb_qc_session';
    const reasonsData = <?php echo json_encode($reasons); ?>;

    function escapeHtml(str) { if (!str) return ''; return String(str).replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[m])); }
    function showToast(msg, type = 'success') { const toast = document.getElementById('toast'); toast.textContent = msg; toast.style.backgroundColor = type === 'success' ? '#10b981' : '#ef4444'; toast.style.display = 'block'; setTimeout(() => toast.style.display = 'none', 3000); }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    function renderLineItemsTable() {
        const tbody = document.getElementById('sessionItemsTable');
        if (currentLineItems.length === 0) { tbody.innerHTML = '<tr><td colspan="4" class="text-center py-8 text-slate-400">No items added</td></tr>'; document.getElementById('itemCount').textContent = '0'; return; }
        tbody.innerHTML = '';
        currentLineItems.forEach((item, idx) => {
            const badgeClass = item.status === 'PASS' ? 'status-completed' : 'status-pending';
            tbody.innerHTML += `<tr><td><div class="font-bold">${escapeHtml(item.item_name)}</div><div class="text-[10px] text-slate-400">${escapeHtml(item.item_code)}</div>${item.return_qty > 0 ? `<div class="text-[10px] text-rose-500">📋 ${item.reasons?.length || 0} reason(s)</div>` : ''}</td>
                <td class="text-right">${item.received_qty}</td><td class="text-center"><span class="status-badge ${badgeClass}">${item.status}</span></td>
                <td class="text-center"><button onclick="removeItem(${idx})" style="color:#dc2626; background:none; border:none; cursor:pointer;">🗑</button></td></tr>`;
        });
        document.getElementById('itemCount').textContent = currentLineItems.length;
    }

    function removeItem(idx) { if(confirm('Remove item?')) { currentLineItems.splice(idx,1); renderLineItemsTable(); showToast('Item removed'); } }

    function saveToLocalStorage() {
        const header = { supplier_id: document.getElementById('hidden_supplier_id').value || document.getElementById('supplier_id').value, invoice_number: document.getElementById('invoice_number').value, invoice_date: document.getElementById('invoice_date').value, branch_id: document.getElementById('branch_id').value, floor_id: document.getElementById('floor_id').value, checked_date: document.getElementById('checked_date').value, checker_name: document.getElementById('checker_name').value, added_by: document.getElementById('added_by').value };
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ header, lineItems: currentLineItems }));
        showToast(`Draft saved (${currentLineItems.length} items)`);
    }

    function restoreFromLocalStorage() {
        const data = localStorage.getItem(STORAGE_KEY);
        if(!data) { showToast('No draft found','error'); return; }
        const session = JSON.parse(data);
        if(session.header.supplier_id) { document.getElementById('hidden_supplier_id').value = session.header.supplier_id; document.getElementById('supplier_id').value = session.header.supplier_id; }
        document.getElementById('invoice_number').value = session.header.invoice_number || '';
        document.getElementById('invoice_date').value = session.header.invoice_date || '';
        document.getElementById('branch_id').value = session.header.branch_id || '';
        document.getElementById('floor_id').value = session.header.floor_id || '';
        document.getElementById('checked_date').value = session.header.checked_date || '';
        document.getElementById('checker_name').value = session.header.checker_name || '';
        document.getElementById('added_by').value = session.header.added_by || '';
        if(session.lineItems) { currentLineItems = session.lineItems; renderLineItemsTable(); }
        showToast(`Restored ${currentLineItems.length} items`);
    }

    async function saveToDatabase() {
        if(currentLineItems.length === 0) { showToast('No items to save!','error'); return; }
        const supplierId = document.getElementById('hidden_supplier_id').value || document.getElementById('supplier_id').value;
        if(!supplierId) { showToast('Select a supplier first!','error'); return; }
        if(!document.getElementById('invoice_number').value) { showToast('Enter invoice number!','error'); return; }
        document.getElementById('loadingOverlay').style.display = 'flex';
        try {
            const fd = new FormData();
            fd.append('supplier_id', supplierId);
            fd.append('invoice_number', document.getElementById('invoice_number').value);
            fd.append('invoice_date', document.getElementById('invoice_date').value);
            fd.append('branch_id', document.getElementById('branch_id').value);
            fd.append('floor_id', document.getElementById('floor_id').value);
            fd.append('checked_date', document.getElementById('checked_date').value);
            fd.append('checker_name', document.getElementById('checker_name').value);
            fd.append('added_by', document.getElementById('added_by').value);
            fd.append('line_items', JSON.stringify(currentLineItems));
            const res = await fetch(window.location.pathname + '?action=save_complete_invoice', { method: 'POST', body: fd });
            const result = await res.json();
            document.getElementById('loadingOverlay').style.display = 'none';
            if(result.status === 'success') { showToast(`✓ Saved ${result.items_saved} items!`); currentLineItems = []; renderLineItemsTable(); if(confirm('Clear form?')) resetForm(); }
            else showToast('Error: ' + result.message, 'error');
        } catch(err) { document.getElementById('loadingOverlay').style.display = 'none'; showToast('Network error!','error'); }
    }

    function resetForm() {
        document.getElementById('headerForm').reset();
        document.getElementById('hidden_supplier_id').value = '';
        document.getElementById('supplier_id').value = '';
        document.getElementById('selectedSupplierInfo').classList.add('hidden');
        currentLineItems = [];
        renderLineItemsTable();
        document.getElementById('invoice_date').valueAsDate = new Date();
        document.getElementById('checked_date').valueAsDate = new Date();
    }

    // Supplier Search
    const supplierSearch = document.getElementById('supplierSearchInput');
    const supplierResults = document.getElementById('supplierSearchResults');
    supplierSearch.addEventListener('input', async function() {
        const q = this.value.trim();
        if(q.length < 1) { supplierResults.classList.add('hidden'); return; }
        const fd = new FormData(); fd.append('term', q);
        const res = await fetch(window.location.pathname + '?action=search_suppliers', { method: 'POST', body: fd });
        const suppliers = await res.json();
        supplierResults.innerHTML = '';
        if(suppliers.length) {
            suppliers.forEach(s => { const div = document.createElement('div'); div.className = 'p-3 hover:bg-slate-100 cursor-pointer border-b'; div.innerHTML = `<div class="font-semibold">${escapeHtml(s.supplier_name)}</div><div class="text-xs text-slate-500">ID: ${escapeHtml(s.system_id || 'N/A')}</div>`; div.onclick = () => { selectSupplier(s); supplierSearch.value = ''; supplierResults.classList.add('hidden'); }; supplierResults.appendChild(div); });
            supplierResults.classList.remove('hidden');
        } else supplierResults.classList.add('hidden');
    });

    function selectSupplier(s) {
        document.getElementById('hidden_supplier_id').value = s.supplier_id;
        document.getElementById('supplier_id').value = s.supplier_id;
        document.getElementById('selectedSupplierName').innerText = s.supplier_name;
        document.getElementById('selectedSupplierSystemId').innerText = s.system_id || 'N/A';
        document.getElementById('selectedSupplierInfo').classList.remove('hidden');
        globalSupplierId = s.supplier_id;
        showToast(`Supplier "${s.supplier_name}" selected`);
    }

    document.getElementById('clearSupplierSearch').onclick = () => { supplierSearch.value = ''; supplierResults.classList.add('hidden'); };
    document.getElementById('supplier_id').addEventListener('change', function() { if(this.value) { const opt = this.options[this.selectedIndex]; document.getElementById('hidden_supplier_id').value = this.value; globalSupplierId = this.value; document.getElementById('selectedSupplierName').innerText = opt.text.split(' (ID:')[0]; document.getElementById('selectedSupplierSystemId').innerText = opt.getAttribute('data-system-id') || 'N/A'; document.getElementById('selectedSupplierInfo').classList.remove('hidden'); } });

    // Item Search
    const itemQuery = document.getElementById('itemQuery');
    const searchRes = document.getElementById('searchResults');
    itemQuery.addEventListener('input', async function() {
        const q = this.value.trim();
        if(q.length < 1 || !globalSupplierId) { searchRes.classList.add('hidden'); return; }
        const fd = new FormData(); fd.append('supplier_id', globalSupplierId); fd.append('term', q);
        const res = await fetch(window.location.pathname + '?action=search_items', { method: 'POST', body: fd });
        const items = await res.json();
        searchRes.innerHTML = '';
        if(items.length) {
            items.forEach(i => { const div = document.createElement('div'); div.className = 'p-3 hover:bg-slate-100 cursor-pointer border-b'; div.innerHTML = `<div><strong>${escapeHtml(i.item_name)}</strong> <span class="text-slate-400">(${escapeHtml(i.item_code)})</span></div><div class="text-rose-600 text-xs">${i.cost_price} LKR</div>`; div.onclick = () => mountItem(i); searchRes.appendChild(div); });
            searchRes.classList.remove('hidden');
        } else {
            const div = document.createElement('div'); div.className = 'p-3 text-center text-amber-600 cursor-pointer'; div.innerHTML = `➕ "${escapeHtml(q)}" not found. Quick Add?`; div.onclick = () => openQuickAdd(q); searchRes.appendChild(div); searchRes.classList.remove('hidden');
        }
    });

    function mountItem(item) {
        searchRes.classList.add('hidden'); itemQuery.value = '';
        document.getElementById('selected_item_id').value = item.item_id;
        document.getElementById('receiptName').innerText = item.item_name;
        document.getElementById('receiptCode').innerText = item.item_code;
        document.getElementById('receiptSysId').innerText = item.system_id || 'N/A';
        document.getElementById('itemSelectionReceipt').classList.remove('hidden');
        document.getElementById('lineItemForm').classList.remove('hidden');
        document.getElementById('return_qty').value = '';
        document.getElementById('returnReasonsBlock').classList.add('hidden');
        tempReasons = [];
        document.getElementById('selectedReasonsSummary').innerHTML = '<span class="italic">No reasons selected</span>';
    }

    document.getElementById('return_qty').addEventListener('input', function() {
        if(parseInt(this.value) > 0) document.getElementById('returnReasonsBlock').classList.remove('hidden');
        else { document.getElementById('returnReasonsBlock').classList.add('hidden'); tempReasons = []; updateReasonsSummary(); }
    });

    function updateReasonsSummary() {
        const div = document.getElementById('selectedReasonsSummary');
        if(!tempReasons.length) { div.innerHTML = '<span class="italic">No reasons selected</span>'; return; }
        let html = ''; tempReasons.forEach(r => { html += `<div class="flex justify-between text-xs py-1"><span>${escapeHtml(r.reason_text)}</span><span class="text-rose-600">${r.quantity} units</span></div>`; });
        div.innerHTML = html;
    }

    // Reason Popup with Search
    function openReasonPopup() {
        const returnQty = parseInt(document.getElementById('return_qty').value) || 0;
        if(returnQty <= 0) { showToast('Enter return quantity first!','error'); return; }
        currentReturnQty = returnQty;
        document.getElementById('popupReturnQty').textContent = currentReturnQty;
        document.querySelectorAll('.popup-reason-check').forEach(cb => { cb.checked = false; cb.closest('.reason-item').querySelector('.popup-reason-qty').value = 0; cb.closest('.reason-item').querySelector('.popup-reason-qty').disabled = true; });
        document.getElementById('totalAllocatedQty').textContent = '0';
        document.getElementById('reasonSearchInput').value = '';
        filterReasons('');
        document.getElementById('reasonPopupOverlay').style.display = 'block';
        document.getElementById('reasonPopup').style.display = 'block';
    }

    function filterReasons(searchTerm) {
        const term = searchTerm.toLowerCase();
        const items = document.querySelectorAll('.reason-item');
        let visibleCount = 0;
        items.forEach(item => {
            const reasonText = item.getAttribute('data-reason-text');
            if(term === '' || reasonText.includes(term)) { item.style.display = 'block'; visibleCount++; }
            else item.style.display = 'none';
        });
        document.getElementById('noReasonsFound').classList.toggle('hidden', visibleCount > 0);
    }

    document.getElementById('reasonSearchInput')?.addEventListener('input', function() { filterReasons(this.value); });

    function closeReasonPopup() {
        document.getElementById('reasonPopupOverlay').style.display = 'none';
        document.getElementById('reasonPopup').style.display = 'none';
    }

    function updateTotalAllocated() {
        let total = 0;
        document.querySelectorAll('.popup-reason-check:checked').forEach(cb => { total += parseInt(cb.closest('.reason-item').querySelector('.popup-reason-qty').value) || 0; });
        document.getElementById('totalAllocatedQty').textContent = total;
        const span = document.getElementById('totalAllocatedQty');
        span.style.color = total === currentReturnQty ? '#10b981' : '#dc2626';
    }

    function applyReasons() {
        const reasons = [];
        let total = 0;
        document.querySelectorAll('.popup-reason-check:checked').forEach(cb => {
            const qty = parseInt(cb.closest('.reason-item').querySelector('.popup-reason-qty').value) || 0;
            if(qty > 0) { reasons.push({ reason_id: cb.value, reason_text: cb.dataset.reasonText, quantity: qty }); total += qty; }
        });
        if(total !== currentReturnQty) { showToast(`Total (${total}) must equal return qty (${currentReturnQty})!`,'error'); return; }
        tempReasons = reasons;
        updateReasonsSummary();
        closeReasonPopup();
        showToast(`${reasons.length} reason(s) allocated`);
    }

    document.getElementById('openReasonPopupBtn')?.addEventListener('click', openReasonPopup);
    document.getElementById('closeReasonPopup')?.addEventListener('click', closeReasonPopup);
    document.getElementById('cancelReasonPopup')?.addEventListener('click', closeReasonPopup);
    document.getElementById('confirmReasonsBtn')?.addEventListener('click', applyReasons);
    document.addEventListener('change', function(e) { if(e.target.classList.contains('popup-reason-check')) { const input = e.target.closest('.reason-item').querySelector('.popup-reason-qty'); if(e.target.checked) { input.disabled = false; input.value = 1; } else { input.disabled = true; input.value = 0; } updateTotalAllocated(); } });
    document.addEventListener('input', function(e) { if(e.target.classList.contains('popup-reason-qty')) { let v = parseInt(e.target.value) || 0; if(v<0) v=0; e.target.value=v; updateTotalAllocated(); } });

    document.getElementById('lineItemForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const rQty = parseInt(document.getElementById('return_qty').value) || 0;
        if(rQty > 0 && tempReasons.length === 0) { showToast('Select return reasons first!','error'); return; }
        if(rQty > 0) { let sum = tempReasons.reduce((a,b)=>a+b.quantity,0); if(sum !== rQty) { showToast(`Reason sum (${sum}) must equal ${rQty}!`,'error'); return; } }
        currentLineItems.push({ item_id: document.getElementById('selected_item_id').value, item_name: document.getElementById('receiptName').innerText, item_code: document.getElementById('receiptCode').innerText, received_qty: parseInt(document.getElementById('received_qty').value), checked_sample_qty: parseInt(document.getElementById('checked_sample_qty').value)||0, defect_qty: parseInt(document.getElementById('defect_qty').value)||0, return_qty: rQty, status: document.getElementById('line_status').value, reasons: [...tempReasons] });
        renderLineItemsTable();
        document.getElementById('lineItemForm').reset();
        document.getElementById('lineItemForm').classList.add('hidden');
        document.getElementById('itemSelectionReceipt').classList.add('hidden');
        document.getElementById('returnReasonsBlock').classList.add('hidden');
        tempReasons = [];
        updateReasonsSummary();
        showToast('Item added to session');
    });

    function openQuickAdd(query) { document.getElementById('modal_item_code').value = query.toUpperCase().replace(/\s+/g, '-'); document.getElementById('quickAddModal').classList.remove('hidden'); }
    function closeQuickAddModal() { document.getElementById('quickAddForm').reset(); document.getElementById('quickAddModal').classList.add('hidden'); }
    document.getElementById('quickAddForm').addEventListener('submit', async function(e) { e.preventDefault(); const fd = new FormData(this); fd.append('supplier_id', globalSupplierId); const res = await fetch(window.location.pathname + '?action=quick_add_item', { method: 'POST', body: fd }); const data = await res.json(); if(data.status === 'success') { mountItem({ item_id: data.item_id, item_name: this.elements['item_name'].value, item_code: this.elements['item_code'].value, system_id: this.elements['system_id'].value }); closeQuickAddModal(); showToast('Item created'); } else showToast('Error creating item','error'); });

    document.getElementById('saveDraftBtn').onclick = () => saveToLocalStorage();
    document.getElementById('restoreDraftBtn').onclick = () => restoreFromLocalStorage();
    document.getElementById('saveToDatabaseBtn').onclick = () => { if(currentLineItems.length) saveToDatabase(); else showToast('No items to save','error'); };
    document.getElementById('clearStorageBtn').onclick = () => { if(confirm('Clear draft?')) { localStorage.removeItem(STORAGE_KEY); showToast('Draft cleared'); } };

    document.getElementById('invoice_date').valueAsDate = new Date();
    document.getElementById('checked_date').valueAsDate = new Date();
    window.removeItem = removeItem;
</script>
</body>
</html>