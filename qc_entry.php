<?php
require 'db.php'; 

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) header("Location: index.php");

// Function to generate auto reference number (YYYYMMDDXXXXX format)
function generateReferenceNumber($pdo) {
    $date = date('Ymd');
    $prefix = $date;
    
    $stmt = $pdo->prepare("SELECT reference_number FROM qc_damage_main WHERE reference_number LIKE ? ORDER BY record_id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetch();
    
    if ($last) {
        $last_num = intval(substr($last['reference_number'], -5));
        $new_num = $last_num + 1;
        $new_ref = $prefix . str_pad($new_num, 5, '0', STR_PAD_LEFT);
    } else {
        $new_ref = $prefix . '00001';
    }
    
    return $new_ref;
}

$auto_ref = generateReferenceNumber($pdo);

// Fetch Lookup Options
$modes = $pdo->query("SELECT mode_id, mode_name FROM qc_modes ORDER BY mode_id")->fetchAll();
$reasons = $pdo->query("SELECT reason_id, reason_text FROM return_reasons ORDER BY reason_id")->fetchAll();

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] == 'search_suppliers') {
        $query = $_GET['query'] ?? '';
        $limit = 30;
        $stmt = $pdo->prepare("
            SELECT supplier_id, supplier_name, system_id, contact_number, address, email 
            FROM suppliers 
            WHERE supplier_name LIKE ? 
               OR system_id LIKE ? 
               OR contact_number LIKE ?
               OR email LIKE ?
            ORDER BY supplier_name ASC
            LIMIT ?
        ");
        $searchTerm = "%$query%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]);
        $results = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => $results,
            'has_more' => count($results) == $limit,
            'count' => count($results)
        ]);
        exit;
    }
    
    if ($_GET['ajax'] == 'load_more_suppliers') {
        $query = $_GET['query'] ?? '';
        $offset = intval($_GET['offset'] ?? 0);
        $limit = 30;
        
        $stmt = $pdo->prepare("
            SELECT supplier_id, supplier_name, system_id, contact_number, address, email 
            FROM suppliers 
            WHERE supplier_name LIKE ? 
               OR system_id LIKE ? 
               OR contact_number LIKE ?
               OR email LIKE ?
            ORDER BY supplier_name ASC
            LIMIT ? OFFSET ?
        ");
        $searchTerm = "%$query%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit, $offset]);
        $results = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => $results,
            'has_more' => count($results) == $limit
        ]);
        exit;
    }
    
    if ($_GET['ajax'] == 'search_reasons') {
        $query = $_GET['query'] ?? '';
        
        $stmt = $pdo->prepare("
            SELECT reason_id, reason_text 
            FROM return_reasons 
            WHERE reason_text LIKE ? 
            ORDER BY reason_text ASC
            LIMIT 20
        ");
        $stmt->execute(["%$query%"]);
        $results = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => $results,
            'count' => count($results)
        ]);
        exit;
    }
    
    if ($_GET['ajax'] == 'get_invoices') {
        $supplier_id = $_GET['supplier_id'];
        $stmt = $pdo->prepare("
            SELECT si.invoice_id, si.invoice_number, si.invoice_date,
                   COUNT(ii.invoice_item_id) as total_items,
                   SUM(ii.return_qty) as total_returns
            FROM supplier_invoices si
            LEFT JOIN invoice_items ii ON si.invoice_id = ii.invoice_id
            WHERE si.supplier_id = ?
            GROUP BY si.invoice_id
            ORDER BY si.invoice_date DESC
        ");
        $stmt->execute([$supplier_id]);
        echo json_encode($stmt->fetchAll());
        exit;
    }
    
    if ($_GET['ajax'] == 'get_invoice_items') {
        $invoice_id = $_GET['invoice_id'];
        $stmt = $pdo->prepare("
            SELECT ii.invoice_item_id, i.item_id, i.item_name, i.item_code, 
                   ii.return_qty, ii.defect_qty, i.cost_price
            FROM invoice_items ii
            LEFT JOIN items i ON ii.item_id = i.item_id
            WHERE ii.invoice_id = ? AND ii.return_qty > 0
        ");
        $stmt->execute([$invoice_id]);
        echo json_encode($stmt->fetchAll());
        exit;
    }
    
    if ($_GET['ajax'] == 'get_item_by_code') {
        $item_code = $_GET['item_code'];
        $stmt = $pdo->prepare("
            SELECT item_id, item_name, item_code, cost_price, selling_price
            FROM items 
            WHERE item_code = ?
        ");
        $stmt->execute([$item_code]);
        echo json_encode($stmt->fetch());
        exit;
    }
    
    if ($_GET['ajax'] == 'create_invoice') {
        try {
            $supplier_id = $_POST['supplier_id'];
            $invoice_number = $_POST['invoice_number'];
            $invoice_date = $_POST['invoice_date'];
            $floor_id = $_POST['floor_id'];
            $checker_name = $_POST['checker_name'];
            
            $stmt = $pdo->prepare("
                INSERT INTO supplier_invoices 
                (supplier_id, invoice_number, invoice_date, floor_id, checked_date, checker_name, added_by) 
                VALUES (?, ?, ?, ?, CURDATE(), ?, ?)
            ");
            $stmt->execute([$supplier_id, $invoice_number, $invoice_date, $floor_id, $checker_name, $_SESSION['username']]);
            $invoice_id = $pdo->lastInsertId();
            
            echo json_encode(['success' => true, 'invoice_id' => $invoice_id]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_GET['ajax'] == 'add_item') {
        try {
            $supplier_id = $_POST['supplier_id'];
            $item_code = $_POST['item_code'];
            $item_name = $_POST['item_name'];
            $cost_price = $_POST['cost_price'];
            $selling_price = $_POST['selling_price'] ?? 0;
            
            $stmt = $pdo->prepare("
                INSERT INTO items (supplier_id, item_code, item_name, cost_price, selling_price, quantity) 
                VALUES (?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([$supplier_id, $item_code, $item_name, $cost_price, $selling_price]);
            
            echo json_encode(['success' => true, 'item_id' => $pdo->lastInsertId(), 'cost_price' => $cost_price]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// Handle form submission
if (isset($_POST['save_qc_record'])) {
    try {
        $pdo->beginTransaction();
        
        $reference_number = generateReferenceNumber($pdo);
        
        $stmt = $pdo->prepare("
            INSERT INTO qc_damage_main 
            (record_date, supplier_id, invoice_number, reference_number, doc_number, mode_id, reason_id, added_by_user) 
            VALUES (CURDATE(), ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['supplier_id'], 
            $_POST['invoice_number'], 
            $reference_number,
            !empty($_POST['doc_number']) ? $_POST['doc_number'] : null,
            $_POST['mode_id'],
            $_POST['reason_id'],
            $_SESSION['username']
        ]);
        $record_id = $pdo->lastInsertId();
        
        $item_stmt = $pdo->prepare("
            INSERT INTO qc_damage_items (record_id, item_code, item_name, quantity, unit_cost, total_cost) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($_POST['item_code'] as $key => $code) {
            if (!empty($code)) {
                $item_name = !empty($_POST['item_name'][$key]) ? $_POST['item_name'][$key] : $code;
                $qty = !empty($_POST['item_qty'][$key]) ? intval($_POST['item_qty'][$key]) : 0;
                $cost = !empty($_POST['item_cost'][$key]) ? floatval($_POST['item_cost'][$key]) : 0.00;
                $total_cost = $qty * $cost;
                
                if ($qty > 0) {
                    $item_stmt->execute([$record_id, $code, $item_name, $qty, $cost, $total_cost]);
                }
            }
        }
        
        if (!empty($_FILES['qc_images']['name'][0])) {
            $upload_dir = 'uploads/qc_returns/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $uploaded_count = 0;
            foreach ($_FILES['qc_images']['tmp_name'] as $key => $tmp_name) {
                if ($uploaded_count >= 4) break;
                if ($_FILES['qc_images']['error'][$key] === 0) {
                    $file_ext = pathinfo($_FILES['qc_images']['name'][$key], PATHINFO_EXTENSION);
                    $file_name = "QC_" . bin2hex(random_bytes(4)) . "_" . time() . "." . $file_ext;
                    $target_file = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($tmp_name, $target_file)) {
                        $img_stmt = $pdo->prepare("INSERT INTO qc_item_images (record_id, image_path, uploaded_by) VALUES (?, ?, ?)");
                        $img_stmt->execute([$record_id, $target_file, $_SESSION['username']]);
                        $uploaded_count++;
                    }
                }
            }
        }
        
        $pdo->commit();
        header("Location: qc_entry.php?status=success&ref=" . urlencode($reference_number));
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>ASB Fashion | New QC Return Entry</title>
    <link rel="icon" type="image/png" href="logo.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            background: #ffffff;
            min-height: 100vh;
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .top-header {
            background: #ffffff;
            border-bottom: 2px solid #dc2626;
            padding: 20px 0;
            margin-bottom: 30px;
        }

        .header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .brand-section h1 {
            font-size: 1.8rem;
            font-weight: 800;
            color: #1e293b;
        }

        .brand-section h1 span {
            color: #dc2626;
        }

        .brand-section p {
            font-size: 0.7rem;
            color: #64748b;
            letter-spacing: 1px;
            margin-top: 5px;
        }

        .ref-box {
            background: #fef2f2;
            padding: 10px 20px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #dc2626;
        }

        .ref-box .label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #dc2626;
        }

        .ref-box .number {
            font-size: 1.3rem;
            font-weight: 800;
            font-family: monospace;
            color: #1e293b;
        }

        .action-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .dashboard-btn {
            background: #dc2626;
            color: white;
            padding: 8px 18px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .dashboard-btn:hover {
            background: #b91c1c;
        }

        .user-card {
            text-align: right;
        }

        .user-card .name {
            font-weight: 700;
            color: #1e293b;
        }

        .user-card .role {
            font-size: 0.7rem;
            color: #64748b;
        }

        /* Form Container - Single Section */
        .form-container {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
        }

        /* Form Grid Layout */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .full-width {
            grid-column: span 2;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 8px;
        }

        .form-label .required {
            color: #dc2626;
        }

        input, select, textarea {
            width: 100%;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 0.85rem;
            transition: all 0.2s;
            outline: none;
        }

        input:focus, select:focus, textarea:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
        }

        /* Dropdown Styles */
        .search-dropdown {
            position: relative;
        }

        .search-trigger {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }

        .search-trigger:hover {
            border-color: #dc2626;
        }

        .search-trigger .placeholder {
            color: #94a3b8;
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-top: 5px;
            z-index: 100;
            display: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-search {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .dropdown-search input {
            margin: 0;
        }

        .dropdown-options {
            max-height: 250px;
            overflow-y: auto;
        }

        .dropdown-option {
            padding: 10px 12px;
            cursor: pointer;
            transition: all 0.2s;
            border-bottom: 1px solid #f1f5f9;
        }

        .dropdown-option:hover {
            background: #fef2f2;
        }

        .dropdown-option .main-text {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.85rem;
        }

        .dropdown-option .sub-text {
            font-size: 0.7rem;
            color: #64748b;
            margin-top: 3px;
        }

        .search-stats {
            padding: 8px 12px;
            background: #f8fafc;
            font-size: 0.7rem;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
        }

        .no-results {
            padding: 20px;
            text-align: center;
            color: #94a3b8;
            font-size: 0.8rem;
        }

        .load-more {
            padding: 10px;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            color: #dc2626;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .load-more:hover {
            background: #fef2f2;
        }

        /* Selected Card */
        .selected-card {
            background: #f0fdf4;
            border: 1px solid #10b981;
            border-radius: 8px;
            padding: 10px 12px;
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .selected-info {
            flex: 1;
        }

        .selected-title {
            font-weight: 700;
            color: #065f46;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }

        .selected-details {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 0.7rem;
            color: #047857;
        }

        .selected-details span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .change-btn {
            background: white;
            border: 1px solid #10b981;
            color: #065f46;
            padding: 5px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.7rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .change-btn:hover {
            background: #10b981;
            color: white;
        }

        /* Invoice Section */
        .invoice-section {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
        }

        .invoice-header {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th {
            text-align: left;
            padding: 10px;
            background: #f8fafc;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
        }

        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .items-table input {
            width: 100%;
            padding: 6px 8px;
            font-size: 0.8rem;
        }

        .cost-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .edit-icon {
            cursor: pointer;
            color: #3b82f6;
            font-size: 0.8rem;
            padding: 4px;
        }

        .edit-icon:hover {
            color: #2563eb;
        }

        .add-item-btn {
            background: none;
            border: 1px dashed #dc2626;
            color: #dc2626;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            margin-top: 10px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .add-item-btn:hover {
            background: #fef2f2;
        }

        /* Upload Zone */
        .upload-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #fafafa;
        }

        .upload-zone:hover {
            border-color: #dc2626;
            background: #fef2f2;
        }

        .image-preview-area {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        /* Buttons */
        .btn-submit {
            background: #dc2626;
            color: white;
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
            margin-top: 20px;
        }

        .btn-submit:hover {
            background: #b91c1c;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.75rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #dc2626;
            color: #dc2626;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.75rem;
        }

        .btn-outline:hover {
            background: #dc2626;
            color: white;
        }

        .hidden {
            display: none;
        }

        .text-center {
            text-align: center;
        }

        .mt-2 {
            margin-top: 8px;
        }

        .mt-3 {
            margin-top: 12px;
        }

        .mb-2 {
            margin-bottom: 8px;
        }

        .mb-3 {
            margin-bottom: 12px;
        }

        .gap-2 {
            gap: 8px;
        }

        .flex {
            display: flex;
        }

        /* Error Alert */
        .error-alert {
            background: #fee2e2;
            border-left: 4px solid #dc2626;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #991b1b;
            font-size: 0.85rem;
        }

        /* Success Message */
        .success-alert {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #065f46;
            font-size: 0.85rem;
        }

        /* Footer */
        .footer-section {
            text-align: center;
            padding: 20px;
            color: #64748b;
            font-size: 0.7rem;
            border-top: 1px solid #e2e8f0;
        }

        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #e2e8f0;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .full-width {
                grid-column: span 1;
            }
            .header-flex {
                flex-direction: column;
                text-align: center;
            }
            .action-area {
                flex-direction: column;
            }
            .main-container {
                padding: 15px;
            }
            .form-container {
                padding: 20px;
            }
            .items-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        
        <!-- Header Section -->
        <div class="top-header">
            <div class="header-flex">
                <div class="brand-section">
                    <h1>ASB <span>FASHION</span></h1>
                    <p>QUALITY CONTROL & RETURN MANAGEMENT SYSTEM</p>
                </div>
                <div class="ref-box">
                    <div class="label">REFERENCE NUMBER</div>
                    <div class="number"><?= $auto_ref ?></div>
                </div>
                <div class="action-area">
                    <a href="print_records.php" class="dashboard-btn">← Dashboard</a>
                    <div class="user-card">
                        <div class="name">👤 <?= htmlspecialchars($_SESSION['username'] ?? 'QC Officer') ?></div>
                        <div class="role"><?= htmlspecialchars($_SESSION['role'] ?? 'Quality Control') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-alert">⚠️ <?= $error ?></div>
        <?php endif; ?>

        <?php if(isset($_GET['status']) && $_GET['status'] === 'success'): ?>
            <div class="success-alert">✅ Record Saved Successfully! Reference: <?= htmlspecialchars($_GET['ref'] ?? '') ?></div>
        <?php endif; ?>

        <!-- Single Form Container -->
        <div class="form-container">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    
                    <!-- Supplier Selection -->
                    <div class="form-group">
                        <label class="form-label">SUPPLIER <span class="required">*</span></label>
                        <div class="search-dropdown">
                            <div class="search-trigger" id="supplierTrigger">
                                <span id="supplierDisplayText" class="placeholder">Click to search supplier...</span>
                                <span>▼</span>
                            </div>
                            <div class="dropdown-menu" id="supplierMenu">
                                <div class="dropdown-search">
                                    <input type="text" id="supplierSearch" placeholder="Search by name, ID, phone or email..." autocomplete="off">
                                </div>
                                <div class="dropdown-options" id="supplierOptions"></div>
                            </div>
                        </div>
                        <input type="hidden" name="supplier_id" id="supplierId">
                        <div id="selectedSupplierCard" class="selected-card hidden">
                            <div class="selected-info">
                                <div class="selected-title" id="selectedSupplierName"></div>
                                <div class="selected-details" id="selectedSupplierDetails"></div>
                            </div>
                            <button type="button" onclick="clearSupplier()" class="change-btn">Change</button>
                        </div>
                    </div>

                    <!-- Document Number -->
                    <div class="form-group">
                        <label class="form-label">DOCUMENT NUMBER</label>
                        <input type="text" name="doc_number" placeholder="Reference document / GRN">
                    </div>

                    <!-- Invoice Selection -->
                    <div class="form-group full-width" id="invoiceSection" style="display: none;">
                        <label class="form-label">INVOICE DETAILS</label>
                        <div class="invoice-section">
                            <div class="invoice-header">📄 Select Existing Invoice</div>
                            <select id="invoiceSelect" style="margin-bottom: 15px;">
                                <option value="">-- Select Invoice --</option>
                            </select>
                            
                            <div class="text-center" style="margin: 15px 0; color: #94a3b8;">— OR —</div>
                            
                            <div class="form-grid" style="margin-top: 10px;">
                                <input type="text" id="newInvoiceNumber" placeholder="New Invoice Number">
                                <input type="date" id="newInvoiceDate">
                                <select id="floorId">
                                    <option value="1">Gents</option>
                                    <option value="2">Ladies</option>
                                    <option value="3">Kids & Infants</option>
                                    <option value="4">Unisex Denim</option>
                                </select>
                                <input type="text" id="checkerName" placeholder="Checker Name" value="<?= $_SESSION['username'] ?>">
                            </div>
                            
                            <button type="button" onclick="createNewInvoice()" class="btn-outline" style="margin-top: 10px; width: 100%;">
                                + Create New Invoice
                            </button>
                            <input type="hidden" name="invoice_number" id="invoiceNumber">
                        </div>
                    </div>

                    <!-- Mode Selection -->
                    <div class="form-group">
                        <label class="form-label">MODE <span class="required">*</span></label>
                        <select name="mode_id" required>
                            <option value="">Select Mode</option>
                            <?php foreach($modes as $mode): ?>
                                <option value="<?= $mode['mode_id'] ?>"><?= htmlspecialchars($mode['mode_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Return Reason -->
                    <div class="form-group">
                        <label class="form-label">RETURN REASON <span class="required">*</span></label>
                        <div class="search-dropdown">
                            <div class="search-trigger" id="reasonTrigger">
                                <span id="reasonDisplayText" class="placeholder">Click to search reason...</span>
                                <span>▼</span>
                            </div>
                            <div class="dropdown-menu" id="reasonMenu">
                                <div class="dropdown-search">
                                    <input type="text" id="reasonSearch" placeholder="Search reason..." autocomplete="off">
                                </div>
                                <div class="dropdown-options" id="reasonOptions"></div>
                            </div>
                        </div>
                        <input type="hidden" name="reason_id" id="reasonId">
                        <div id="selectedReasonCard" class="selected-card hidden">
                            <div class="selected-info">
                                <div class="selected-title" id="selectedReasonText"></div>
                            </div>
                            <button type="button" onclick="clearReason()" class="change-btn">Change</button>
                        </div>
                    </div>

                    <!-- Items Section -->
                    <div class="form-group full-width" id="itemsSection" style="display: none;">
                        <label class="form-label">RETURN ITEMS</label>
                        <div style="background: #f8fafc; padding: 15px; border-radius: 8px;">
                            <div class="flex gap-2" style="margin-bottom: 15px;">
                                <input type="text" id="itemSearch" placeholder="Search item by code..." style="flex: 1;">
                                <button type="button" onclick="searchItem()" class="btn-secondary">Search</button>
                            </div>
                            
                            <div style="overflow-x: auto;">
                                <table class="items-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 25%">Item Code</th>
                                            <th style="width: 35%">Item Name</th>
                                            <th style="width: 15%">Return Qty</th>
                                            <th style="width: 25%">Unit Cost (LKR)</th>
                                        </tr>
                                    </thead>
                                    <tbody id="itemsList"></tbody>
                                </table>
                            </div>
                            
                            <button type="button" onclick="addManualItem()" class="add-item-btn">
                                + Add Manual Item
                            </button>
                            <div style="margin-top: 10px; padding: 8px; background: #fef2f2; border-radius: 6px; font-size: 11px; color: #dc2626;">
                                💡 Tip: Click the edit icon (✏️) on cost to modify before submitting
                            </div>
                        </div>
                    </div>

                    <!-- Images Upload -->
                    <div class="form-group full-width">
                        <label class="form-label">EVIDENCE IMAGES (MAX 4)</label>
                        <div id="uploadZone" class="upload-zone">
                            <input type="file" name="qc_images[]" id="fileInput" multiple class="hidden" accept="image/*">
                            <div style="font-size: 40px; margin-bottom: 8px;">📷</div>
                            <p style="color: #666;">Click or drag images here</p>
                            <p style="color: #999; font-size: 11px;">Maximum 4 images (JPG, PNG, GIF)</p>
                            <div id="imagePreview" class="image-preview-area"></div>
                        </div>
                    </div>
                </div>

                <button type="submit" name="save_qc_record" class="btn-submit">
                    💾 Save Return Record
                </button>
            </form>
        </div>

        <!-- Footer -->
        <div class="footer-section">
            <p>© <?= date('Y') ?> ASB Fashion - Quality Control & Return Management System</p>
            <p>All Rights Reserved | ASB Group of Companies</p>
        </div>
    </div>

    <script>
        let currentSupplierId = null;
        let currentInvoiceId = null;
        let imageCount = 0;
        const MAX_IMAGES = 4;
        let currentOffset = 0;
        let currentQuery = '';
        let isLoadingMore = false;
        
        // ==================== SUPPLIER DROPDOWN ====================
        const supplierTrigger = document.getElementById('supplierTrigger');
        const supplierMenu = document.getElementById('supplierMenu');
        const supplierSearch = document.getElementById('supplierSearch');
        const supplierOptions = document.getElementById('supplierOptions');
        
        supplierTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            supplierMenu.classList.toggle('show');
            if (supplierMenu.classList.contains('show')) {
                supplierSearch.value = '';
                supplierSearch.focus();
                searchSuppliers('');
            }
        });
        
        supplierSearch.addEventListener('input', function() {
            searchSuppliers(this.value);
        });
        
        function searchSuppliers(query) {
            currentQuery = query;
            currentOffset = 0;
            supplierOptions.innerHTML = '<div class="search-stats">Searching suppliers...</div>';
            
            fetch(`?ajax=search_suppliers&query=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(response => {
                    if (response.success) {
                        renderSupplierOptions(response.data, response.has_more);
                    } else {
                        supplierOptions.innerHTML = '<div class="no-results">Error loading suppliers</div>';
                    }
                })
                .catch(() => {
                    supplierOptions.innerHTML = '<div class="no-results">Network error</div>';
                });
        }
        
        function renderSupplierOptions(data, hasMore) {
            if (!data || data.length === 0) {
                supplierOptions.innerHTML = '<div class="no-results">No suppliers found</div>';
                return;
            }
            
            let html = `<div class="search-stats">Found ${data.length} supplier(s)</div>`;
            
            data.forEach(supplier => {
                let details = '';
                if (supplier.system_id && supplier.system_id !== 'N/A') details += `ID: ${escapeHtml(supplier.system_id)} | `;
                if (supplier.contact_number && supplier.contact_number !== 'N/A') details += `Tel: ${escapeHtml(supplier.contact_number)}`;
                
                html += `
                    <div class="dropdown-option" onclick="selectSupplier(${supplier.supplier_id}, '${escapeHtml(supplier.supplier_name)}', '${escapeHtml(supplier.system_id || 'N/A')}', '${escapeHtml(supplier.contact_number || 'N/A')}', '${escapeHtml(supplier.address || 'N/A')}', '${escapeHtml(supplier.email || 'N/A')}')">
                        <div class="main-text">🏢 ${escapeHtml(supplier.supplier_name)}</div>
                        <div class="sub-text">${details}</div>
                    </div>
                `;
            });
            
            if (hasMore) {
                html += `<div class="load-more" onclick="loadMoreSuppliers()">Load More Suppliers...</div>`;
            }
            
            supplierOptions.innerHTML = html;
        }
        
        function loadMoreSuppliers() {
            if (isLoadingMore) return;
            isLoadingMore = true;
            currentOffset += 30;
            
            const loadMore = supplierOptions.querySelector('.load-more');
            if (loadMore) loadMore.remove();
            supplierOptions.innerHTML += '<div class="search-stats">Loading more...</div>';
            
            fetch(`?ajax=load_more_suppliers&query=${encodeURIComponent(currentQuery)}&offset=${currentOffset}`)
                .then(res => res.json())
                .then(response => {
                    const loadingMsg = supplierOptions.querySelector('.search-stats:last-child');
                    if (loadingMsg && loadingMsg.innerText.includes('Loading more')) loadingMsg.remove();
                    
                    if (response.success && response.data.length > 0) {
                        response.data.forEach(supplier => {
                            let details = '';
                            if (supplier.system_id && supplier.system_id !== 'N/A') details += `ID: ${escapeHtml(supplier.system_id)} | `;
                            if (supplier.contact_number && supplier.contact_number !== 'N/A') details += `Tel: ${escapeHtml(supplier.contact_number)}`;
                            
                            const div = document.createElement('div');
                            div.className = 'dropdown-option';
                            div.innerHTML = `
                                <div class="main-text">🏢 ${escapeHtml(supplier.supplier_name)}</div>
                                <div class="sub-text">${details}</div>
                            `;
                            div.onclick = () => selectSupplier(supplier.supplier_id, supplier.supplier_name, supplier.system_id || 'N/A', supplier.contact_number || 'N/A', supplier.address || 'N/A', supplier.email || 'N/A');
                            supplierOptions.appendChild(div);
                        });
                        
                        if (response.has_more) {
                            const loadMoreDiv = document.createElement('div');
                            loadMoreDiv.className = 'load-more';
                            loadMoreDiv.innerHTML = 'Load More Suppliers...';
                            loadMoreDiv.onclick = () => loadMoreSuppliers();
                            supplierOptions.appendChild(loadMoreDiv);
                        }
                    }
                    isLoadingMore = false;
                })
                .catch(() => { isLoadingMore = false; });
        }
        
        function selectSupplier(id, name, sysId, contact, address, email) {
            currentSupplierId = id;
            document.getElementById('supplierId').value = id;
            document.getElementById('selectedSupplierName').innerHTML = `🏢 ${escapeHtml(name)}`;
            
            let detailsHtml = '';
            if (sysId && sysId !== 'N/A') detailsHtml += `<span>ID: ${escapeHtml(sysId)}</span>`;
            if (contact && contact !== 'N/A') detailsHtml += `<span>Tel: ${escapeHtml(contact)}</span>`;
            if (email && email !== 'N/A' && email !== '') detailsHtml += `<span>Email: ${escapeHtml(email)}</span>`;
            
            document.getElementById('selectedSupplierDetails').innerHTML = detailsHtml;
            document.getElementById('selectedSupplierCard').classList.remove('hidden');
            document.getElementById('supplierDisplayText').innerHTML = name;
            document.getElementById('supplierDisplayText').style.color = '#333';
            supplierMenu.classList.remove('show');
            
            loadInvoices(id);
            document.getElementById('invoiceSection').style.display = 'block';
        }
        
        function clearSupplier() {
            currentSupplierId = null;
            document.getElementById('supplierId').value = '';
            document.getElementById('supplierDisplayText').innerHTML = 'Click to search supplier...';
            document.getElementById('supplierDisplayText').style.color = '#999';
            document.getElementById('selectedSupplierCard').classList.add('hidden');
            document.getElementById('invoiceSection').style.display = 'none';
            document.getElementById('itemsSection').style.display = 'none';
            document.getElementById('invoiceSelect').innerHTML = '<option value="">-- Select Invoice --</option>';
            document.getElementById('itemsList').innerHTML = '';
        }
        
        // ==================== REASON DROPDOWN ====================
        const reasonTrigger = document.getElementById('reasonTrigger');
        const reasonMenu = document.getElementById('reasonMenu');
        const reasonSearch = document.getElementById('reasonSearch');
        const reasonOptions = document.getElementById('reasonOptions');
        
        reasonTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            reasonMenu.classList.toggle('show');
            if (reasonMenu.classList.contains('show')) {
                reasonSearch.value = '';
                reasonSearch.focus();
                searchReasons('');
            }
        });
        
        reasonSearch.addEventListener('input', function() {
            searchReasons(this.value);
        });
        
        function searchReasons(query) {
            reasonOptions.innerHTML = '<div class="search-stats">Searching reasons...</div>';
            
            fetch(`?ajax=search_reasons&query=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(response => {
                    if (response.success) {
                        renderReasonOptions(response.data);
                    } else {
                        reasonOptions.innerHTML = '<div class="no-results">Error loading reasons</div>';
                    }
                })
                .catch(() => {
                    reasonOptions.innerHTML = '<div class="no-results">Network error</div>';
                });
        }
        
        function renderReasonOptions(data) {
            if (!data || data.length === 0) {
                reasonOptions.innerHTML = '<div class="no-results">No reasons found</div>';
                return;
            }
            
            let html = `<div class="search-stats">Found ${data.length} reason(s)</div>`;
            
            data.forEach(reason => {
                html += `
                    <div class="dropdown-option" onclick="selectReason(${reason.reason_id}, '${escapeHtml(reason.reason_text)}')">
                        <div class="main-text">💬 ${escapeHtml(reason.reason_text)}</div>
                    </div>
                `;
            });
            
            reasonOptions.innerHTML = html;
        }
        
        function selectReason(id, text) {
            document.getElementById('reasonId').value = id;
            document.getElementById('selectedReasonText').innerHTML = `✅ ${escapeHtml(text)}`;
            document.getElementById('selectedReasonCard').classList.remove('hidden');
            document.getElementById('reasonDisplayText').innerHTML = text;
            document.getElementById('reasonDisplayText').style.color = '#333';
            reasonMenu.classList.remove('show');
        }
        
        function clearReason() {
            document.getElementById('reasonId').value = '';
            document.getElementById('reasonDisplayText').innerHTML = 'Click to search reason...';
            document.getElementById('reasonDisplayText').style.color = '#999';
            document.getElementById('selectedReasonCard').classList.add('hidden');
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!supplierTrigger.contains(e.target) && !supplierMenu.contains(e.target)) {
                supplierMenu.classList.remove('show');
            }
            if (!reasonTrigger.contains(e.target) && !reasonMenu.contains(e.target)) {
                reasonMenu.classList.remove('show');
            }
        });
        
        // ==================== INVOICE FUNCTIONS ====================
        function loadInvoices(supplierId) {
            fetch(`?ajax=get_invoices&supplier_id=${supplierId}`)
                .then(res => res.json())
                .then(data => {
                    const select = document.getElementById('invoiceSelect');
                    select.innerHTML = '<option value="">-- Select Existing Invoice --</option>';
                    if (data.length > 0) {
                        data.forEach(inv => {
                            select.innerHTML += `<option value="${inv.invoice_id}">📄 ${escapeHtml(inv.invoice_number)} - ${inv.invoice_date}</option>`;
                        });
                    }
                });
        }
        
        document.getElementById('invoiceSelect').addEventListener('change', function() {
            const invoiceId = this.value;
            if (invoiceId) {
                currentInvoiceId = invoiceId;
                const selectedOption = this.options[this.selectedIndex];
                document.getElementById('invoiceNumber').value = selectedOption.text.split(' - ')[0].replace('📄', '').trim();
                loadInvoiceItems(invoiceId);
                document.getElementById('itemsSection').style.display = 'block';
            }
        });
        
        function loadInvoiceItems(invoiceId) {
            fetch(`?ajax=get_invoice_items&invoice_id=${invoiceId}`)
                .then(res => res.json())
                .then(data => {
                    const tbody = document.getElementById('itemsList');
                    if (data.length > 0) {
                        tbody.innerHTML = data.map((item, idx) => `
                            <tr data-item-code="${escapeHtml(item.item_code)}">
                                <td>
                                    <input type="hidden" name="item_code[]" value="${escapeHtml(item.item_code)}">
                                    <strong>${escapeHtml(item.item_code)}</strong>
                                </td>
                                <td>
                                    <input type="hidden" name="item_name[]" value="${escapeHtml(item.item_name)}">
                                    ${escapeHtml(item.item_name)}
                                </td>
                                <td>
                                    <input type="number" name="item_qty[]" value="${item.return_qty}" 
                                           style="width: 100%; padding: 6px 8px;" min="0" max="${item.return_qty}" step="1">
                                </td>
                                <td>
                                    <div class="cost-wrapper">
                                        <input type="number" step="0.01" name="item_cost[]" value="${item.cost_price || 0}" 
                                               style="flex: 1; padding: 6px 8px;" min="0">
                                        <span class="edit-icon" onclick="editCost(this)">✏️</span>
                                    </div>
                                </td>
                            </tr>
                        `).join('');
                    } else {
                        tbody.innerHTML = '<tr><td colspan="4" class="text-center" style="padding: 30px;">No returnable items found. Add items manually below.</td></tr>';
                    }
                });
        }
        
        function editCost(element) {
            const row = element.closest('tr');
            const costInput = row.querySelector('input[name="item_cost[]"]');
            const currentCost = costInput.value;
            const newCost = prompt('Enter new unit cost (LKR):', currentCost);
            if (newCost !== null && !isNaN(newCost) && parseFloat(newCost) >= 0) {
                costInput.value = parseFloat(newCost).toFixed(2);
                costInput.style.background = '#fef2f2';
                setTimeout(() => { costInput.style.background = ''; }, 500);
            }
        }
        
        function createNewInvoice() {
            const invoiceNumber = document.getElementById('newInvoiceNumber').value;
            const invoiceDate = document.getElementById('newInvoiceDate').value;
            const floorId = document.getElementById('floorId').value;
            const checkerName = document.getElementById('checkerName').value;
            
            if (!invoiceNumber || !invoiceDate) {
                alert('Please enter invoice number and date');
                return;
            }
            
            const formData = new FormData();
            formData.append('supplier_id', currentSupplierId);
            formData.append('invoice_number', invoiceNumber);
            formData.append('invoice_date', invoiceDate);
            formData.append('floor_id', floorId);
            formData.append('checker_name', checkerName);
            
            fetch('?ajax=create_invoice', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        currentInvoiceId = data.invoice_id;
                        document.getElementById('invoiceNumber').value = invoiceNumber;
                        document.getElementById('itemsSection').style.display = 'block';
                        document.getElementById('itemsList').innerHTML = '<tr><td colspan="4" class="text-center" style="padding: 30px;">✅ New invoice created! Add items manually below.</td></tr>';
                        alert('Invoice created successfully!');
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
        }
        
        // ==================== ITEM FUNCTIONS ====================
        function searchItem() {
            const itemCode = document.getElementById('itemSearch').value;
            if (!itemCode) return;
            
            fetch(`?ajax=get_item_by_code&item_code=${encodeURIComponent(itemCode)}`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.item_id) {
                        addItemToList(data.item_code, data.item_name, data.cost_price || 0);
                        document.getElementById('itemSearch').value = '';
                    } else {
                        if (confirm('Item not found. Would you like to add this item with cost?')) {
                            addNewItem(itemCode);
                        }
                    }
                });
        }
        
        function addNewItem(itemCode) {
            const itemName = prompt('Enter Item Name:');
            if (!itemName) return;
            
            const costPrice = prompt('Enter Unit Cost (LKR):', '0');
            
            const formData = new FormData();
            formData.append('supplier_id', currentSupplierId);
            formData.append('item_code', itemCode);
            formData.append('item_name', itemName);
            formData.append('cost_price', costPrice || 0);
            formData.append('selling_price', 0);
            
            fetch('?ajax=add_item', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        addItemToList(itemCode, itemName, data.cost_price || costPrice);
                        alert('Item added successfully with cost ' + (data.cost_price || costPrice) + ' LKR');
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
        }
        
        function addItemToList(code, name, cost) {
            const tbody = document.getElementById('itemsList');
            
            // Check if already exists
            const existing = Array.from(tbody.querySelectorAll('input[name="item_code[]"]')).some(input => input.value === code);
            if (existing) {
                alert('Item already in list');
                return;
            }
            
            // Remove "no items" message if present
            if (tbody.innerHTML.includes('No returnable items found')) {
                tbody.innerHTML = '';
            }
            
            const row = document.createElement('tr');
            row.setAttribute('data-item-code', code);
            row.innerHTML = `
                <td>
                    <input type="hidden" name="item_code[]" value="${escapeHtml(code)}">
                    <strong>${escapeHtml(code)}</strong>
                </td>
                <td>
                    <input type="hidden" name="item_name[]" value="${escapeHtml(name)}">
                    ${escapeHtml(name)}
                </td>
                <td>
                    <input type="number" name="item_qty[]" style="width: 100%; padding: 6px 8px;" min="1" value="1" step="1">
                </td>
                <td>
                    <div class="cost-wrapper">
                        <input type="number" step="0.01" name="item_cost[]" value="${cost}" style="flex: 1; padding: 6px 8px;" min="0">
                        <span class="edit-icon" onclick="editCost(this)">✏️</span>
                    </div>
                </td>
            `;
            tbody.appendChild(row);
            alert(`${code} added with cost ${cost} LKR`);
        }
        
        function addManualItem() {
            const tbody = document.getElementById('itemsList');
            
            if (tbody.innerHTML.includes('No returnable items found') || tbody.innerHTML.includes('New invoice created')) {
                tbody.innerHTML = '';
            }
            
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="text" name="item_code[]" placeholder="Item Code" style="width: 100%; padding: 6px 8px;" required></td>
                <td><input type="text" name="item_name[]" placeholder="Item Name" style="width: 100%; padding: 6px 8px;" required></td>
                <td><input type="number" name="item_qty[]" value="1" min="1" step="1" style="width: 100%; padding: 6px 8px;" required></td>
                <td><input type="number" step="0.01" name="item_cost[]" value="0" min="0" style="width: 100%; padding: 6px 8px;" placeholder="Cost"></td>
            `;
            tbody.appendChild(row);
        }
        
        // ==================== IMAGE UPLOAD ====================
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');
        const imagePreview = document.getElementById('imagePreview');
        
        uploadZone.onclick = () => fileInput.click();
        fileInput.onchange = (e) => handleFiles(e.target.files);
        uploadZone.ondragover = (e) => { e.preventDefault(); uploadZone.style.borderColor = '#dc2626'; uploadZone.style.background = '#fef2f2'; };
        uploadZone.ondragleave = () => { uploadZone.style.borderColor = '#cbd5e1'; uploadZone.style.background = '#fafafa'; };
        uploadZone.ondrop = (e) => {
            e.preventDefault();
            uploadZone.style.borderColor = '#cbd5e1';
            uploadZone.style.background = '#fafafa';
            handleFiles(e.dataTransfer.files);
        };
        
        function handleFiles(files) {
            const remaining = MAX_IMAGES - imageCount;
            if (files.length > remaining) {
                alert(`Maximum ${MAX_IMAGES} images allowed. You can upload ${remaining} more.`);
                return;
            }
            
            for (let file of files) {
                if (file.type.startsWith('image/')) {
                    if (file.size > 5 * 1024 * 1024) {
                        alert(`File ${file.name} exceeds 5MB limit`);
                        continue;
                    }
                    const reader = new FileReader();
                    reader.onload = (event) => {
                        const div = document.createElement('div');
                        div.style.position = 'relative';
                        div.style.display = 'inline-block';
                        div.style.margin = '5px';
                        div.innerHTML = `
                            <img src="${event.target.result}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 2px solid #e0e0e0;">
                            <div onclick="this.parentElement.remove(); imageCount--;" style="position: absolute; top: -8px; right: -8px; background: #dc2626; color: white; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 11px;">✕</div>
                        `;
                        imagePreview.appendChild(div);
                        imageCount++;
                    };
                    reader.readAsDataURL(file);
                }
            }
            
            const dt = new DataTransfer();
            for (let i = 0; i < fileInput.files.length; i++) dt.items.add(fileInput.files[i]);
            for (let file of files) dt.items.add(file);
            fileInput.files = dt.files;
        }
        
        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
        
        document.getElementById('newInvoiceDate').valueAsDate = new Date();
    </script>
</body>
</html>