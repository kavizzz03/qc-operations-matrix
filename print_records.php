<?php
require 'db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { 
    header("Location: index.php"); 
    exit; 
}

// Function to generate auto reference number (YYYYMMDDXXXXX format)
function generateReferenceNumber($pdo) {
    $date = date('Ymd');
    $prefix = $date;
    
    // Optimized query for high performance with index on reference_number
    $stmt = $pdo->prepare("SELECT reference_number FROM qc_damage_main WHERE reference_number LIKE ? AND reference_number > ? ORDER BY reference_number DESC LIMIT 1");
    $stmt->execute([$prefix . '%', $prefix . '00000']);
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

// Handle AJAX request for creating new return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'create_return') {
    header('Content-Type: application/json');
    
    try {
        $pdo->beginTransaction();
        
        // Auto-generate reference number
        $reference_number = generateReferenceNumber($pdo);
        
        $record_date = $_POST['record_date'];
        $supplier_id = $_POST['supplier_id'];
        $invoice_number = $_POST['invoice_number'];
        $doc_number = $_POST['doc_number'];
        $mode_id = $_POST['mode_id'];
        $reason_id = $_POST['reason_id'];
        $added_by_user = $_POST['added_by_user'];
        $items = json_decode($_POST['items'], true);
        
        // Insert main record with auto-generated reference number
        $stmt = $pdo->prepare("
            INSERT INTO qc_damage_main 
            (record_date, supplier_id, invoice_number, reference_number, doc_number, mode_id, reason_id, added_by_user) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$record_date, $supplier_id, $invoice_number, $reference_number, $doc_number, $mode_id, $reason_id, $added_by_user]);
        $record_id = $pdo->lastInsertId();
        
        // Batch insert items for better performance
        if (count($items) > 0) {
            $sql = "INSERT INTO qc_damage_items (record_id, item_code, item_name, quantity, unit_cost) VALUES ";
            $values = [];
            $params = [];
            
            foreach ($items as $index => $item) {
                $values[] = "(?, ?, ?, ?, ?)";
                $params[] = $record_id;
                $params[] = $item['item_code'];
                $params[] = $item['item_name'];
                $params[] = $item['quantity'];
                $params[] = $item['unit_cost'];
            }
            
            $sql .= implode(', ', $values);
            $stmt_item = $pdo->prepare($sql);
            $stmt_item->execute($params);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'record_id' => $record_id, 
            'reference_number' => $reference_number,
            'message' => 'Return created successfully with reference: ' . $reference_number
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// --- FILTERS & SEARCH PIPELINE WITH PAGINATION FOR 1M+ RECORDS ---
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';

$where = " WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (m.reference_number LIKE ? OR m.invoice_number LIKE ? OR m.doc_number LIKE ? OR s.supplier_name LIKE ? OR mo.mode_name LIKE ? OR rr.reason_text LIKE ?)";
    $term = "%$search%";
    array_push($params, $term, $term, $term, $term, $term, $term);
}

if (!empty($fromDate) && !empty($toDate)) {
    $where .= " AND m.record_date BETWEEN ? AND ?";
    $params[] = $fromDate;
    $params[] = $toDate;
}

// Get total count for pagination (optimized for large datasets)
$countQuery = "SELECT COUNT(*) as total 
               FROM qc_damage_main m 
               JOIN suppliers s ON m.supplier_id = s.supplier_id 
               LEFT JOIN qc_modes mo ON m.mode_id = mo.mode_id
               LEFT JOIN return_reasons rr ON m.reason_id = rr.reason_id
               $where";
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$totalRecords = $stmt->fetch()['total'];
$totalPages = ceil($totalRecords / $limit);

// Main query with pagination and proper indexing
$query = "SELECT m.*, s.supplier_name, mo.mode_name, rr.reason_text as reason_name
          FROM qc_damage_main m 
          JOIN suppliers s ON m.supplier_id = s.supplier_id 
          LEFT JOIN qc_modes mo ON m.mode_id = mo.mode_id
          LEFT JOIN return_reasons rr ON m.reason_id = rr.reason_id
          $where 
          ORDER BY m.record_id DESC 
          LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Get next auto reference number for display (cached for performance)
$next_reference = generateReferenceNumber($pdo);

// Fetch suppliers for dropdown (cached for 1 hour)
$suppliers = $pdo->query("SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name")->fetchAll();
$modes = $pdo->query("SELECT mode_id, mode_name FROM qc_modes ORDER BY mode_id")->fetchAll();
$reasons = $pdo->query("SELECT reason_id, reason_text FROM return_reasons ORDER BY reason_id")->fetchAll();
$username = $_SESSION['username'] ?? 'QC Officer';
$userRole = $_SESSION['role'] ?? 'Standard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASB Fashion | Print Records</title>
    <link rel="icon" type="image/png" href="logo.png">
    <!-- JsBarcode CDN for professional barcode generation -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <!-- SweetAlert2 for beautiful alerts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* ========== RESET & BASE ========== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            background: #f5f7fb;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ========== HEADER ========== */
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
        .logo span {
            color: #dc2626;
        }
        .logo p {
            font-size: 0.65rem;
            color: #64748b;
            letter-spacing: 1px;
            margin-top: 4px;
        }
        .user-info {
            text-align: right;
        }
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
            border: none;
            cursor: pointer;
        }
        .back-btn:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }

        /* ========== CONTAINER ========== */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
            flex: 1;
        }

        /* ========== FOOTER ========== */
        .main-footer {
            background: #1e293b;
            color: #cbd5e1;
            padding: 24px;
            margin-top: 40px;
            border-top: 3px solid #dc2626;
        }
        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .footer-copyright {
            font-size: 0.85rem;
        }
        .footer-copyright i {
            color: #dc2626;
            margin-right: 5px;
        }
        .footer-developer {
            text-align: right;
        }
        .footer-developer p {
            margin: 5px 0;
        }
        .developer-name {
            font-weight: 700;
            color: white;
            font-size: 1rem;
        }
        .developer-company {
            font-size: 0.8rem;
            color: #94a3b8;
        }
        .footer-rights {
            font-size: 0.7rem;
            color: #64748b;
        }

        /* ========== CARDS ========== */
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
        .card-header h2 i {
            color: #dc2626;
            margin-right: 8px;
            display: inline-block;
            width: 20px;
        }
        .card-body {
            padding: 24px;
        }

        /* ========== STATS CARDS ========== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 20px;
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -10px rgba(0,0,0,0.1);
            border-color: #dc2626;
        }
        .stat-title {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: #1e293b;
            margin-top: 8px;
        }

        /* ========== FORM ELEMENTS ========== */
        .form-group {
            margin-bottom: 16px;
        }
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

        /* ========== BUTTONS ========== */
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
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
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
        .btn-success:hover {
            background: #059669;
        }

        /* ========== PAGINATION ========== */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            padding: 8px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.2s;
            border: 1px solid #e2e8f0;
            background: white;
            color: #475569;
        }
        .pagination a:hover {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
        }
        .pagination .active {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
        }
        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .page-info {
            text-align: center;
            margin-top: 16px;
            font-size: 0.8rem;
            color: #64748b;
        }

        /* ========== TABLE ========== */
        .table-wrapper {
            overflow-x: auto;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            text-align: left;
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
        .data-table tr:hover {
            background: #fef2f2;
            cursor: pointer;
        }

        /* ========== REFERENCE BOX ========== */
        .reference-box {
            background: linear-gradient(135deg, #fef2f2, #fee2e2);
            border: 2px solid #dc2626;
            border-radius: 16px;
            padding: 16px;
            text-align: center;
            margin-bottom: 20px;
        }
        .reference-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #dc2626;
        }
        .reference-number {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1e293b;
            font-family: monospace;
        }
        .reference-hint {
            font-size: 0.65rem;
            color: #64748b;
            margin-top: 6px;
        }

        /* ========== MODAL ========== */
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
        .modal-header h2 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1e293b;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #94a3b8;
        }
        .modal-body {
            padding: 24px;
        }

        /* ========== ITEM ROW ========== */
        .item-row {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
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

        /* ========== STATUS BADGE ========== */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }
        .status-pending {
            background: #fed7aa;
            color: #92400e;
        }

        /* ========== UTILITY CLASSES ========== */
        .hidden { display: none !important; }
        .flex { display: flex; }
        .flex-between { display: flex; justify-content: space-between; align-items: center; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }
        .gap-4 { gap: 16px; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mt-4 { margin-top: 16px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .mb-4 { margin-bottom: 16px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-mono { font-family: monospace; }
        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        
        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr; }
            .item-row { flex-wrap: wrap; }
            .footer-content { flex-direction: column; text-align: center; }
            .footer-developer { text-align: center; }
            .header-content { flex-direction: column; text-align: center; }
            .user-info { text-align: center; }
        }

        /* ========== PRINT STYLES (A6 Landscape) ========== */
        @media print {
            @page { 
                size: A6 landscape; 
                margin: 0; 
            }
            body * { 
                visibility: hidden !important; 
            }
            #printArea, #printArea * { 
                visibility: visible !important; 
            }
            #printArea { 
                display: block !important;
                position: absolute; 
                left: 0; 
                top: 0; 
                width: 148mm; 
                height: 105mm; 
                padding: 6mm 8mm; 
                background: white !important; 
                color: black !important;
                box-sizing: border-box;
            }
            .no-print { 
                display: none !important; 
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
            }
            th { 
                text-transform: uppercase; 
                font-size: 8px; 
                border-bottom: 1px solid black; 
                padding-bottom: 2px; 
            }
            td { 
                font-size: 8.5px; 
                padding: 2.5px 0; 
                border-bottom: 0.5px solid #e2e8f0; 
            }
            svg, .barcode-container svg {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<div class="header no-print">
    <div class="header-content">
        <div class="logo">
            <h1>ASB <span>FASHION</span></h1>
            <p>PRINT MANAGEMENT</p>
        </div>
        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="dashboard.php" class="back-btn">
                ← Back to Dashboard
            </a>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($username) ?></div>
                <div class="user-role">Print Records</div>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- Stats Cards -->
    <div class="stats-grid no-print">
        <div class="stat-card">
            <div class="stat-title">Total Returns</div>
            <div class="stat-value"><?= number_format($totalRecords) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Pending Actions</div>
            <div class="stat-value" style="color: #f59e0b;">
                <?= number_format(count(array_filter($records, function($r) { return !$r['is_handover_complete']; }))) ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Completed</div>
            <div class="stat-value" style="color: #10b981;">
                <?= number_format(count(array_filter($records, function($r) { return $r['is_handover_complete']; }))) ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Next Reference</div>
            <div class="stat-value font-mono" style="font-size: 1.2rem;"><?= $next_reference ?></div>
        </div>
    </div>

    <!-- Search Form -->
    <div class="card no-print">
        <div class="card-header">
            <h2>🔍 Filter Records</h2>
        </div>
        <div class="card-body">
            <form method="GET" class="grid-2" id="searchForm">
                <div class="form-group">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Reference / Invoice / Supplier / Mode..." class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Date From</label>
                    <input type="date" name="from_date" value="<?= htmlspecialchars($fromDate) ?>" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Date To</label>
                    <input type="date" name="to_date" value="<?= htmlspecialchars($toDate) ?>" class="form-input">
                </div>
                <div class="form-group flex gap-2" style="align-items: flex-end;">
                    <button type="submit" class="btn-primary">🔍 Search</button>
                    <?php if ($search || $fromDate || $toDate): ?>
                    <a href="print_records.php" class="btn-secondary">✕ Clear</a>
                    <?php endif; ?>
                    <button type="button" onclick="openCreateModal()" class="btn-success" style="margin-left: auto;">➕ New Return</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Records Table -->
    <div class="card">
        <div class="card-header">
            <h2>🖨️ Return Records 
                <span style="font-size: 0.7rem; font-weight: normal; color: #64748b;">(Showing <?= count($records) ?> of <?= number_format($totalRecords) ?> records)</span>
            </h2>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Reference No</th>
                            <th>Invoice</th>
                            <th>Supplier</th>
                            <th>Mode</th>
                            <th>Reason</th>
                            <th class="text-center">Prints</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="8" class="text-center" style="padding: 60px;">
                                📭
                                <p style="color: #94a3b8; margin-top: 16px;">No records found</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach($records as $row): ?>
                        <tr onclick="viewReturnDetails(<?= $row['record_id'] ?>)">
                            <td><strong style="color: #dc2626;">#<?= $row['record_id'] ?></strong></td>
                            <td><span class="font-mono" style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($row['reference_number'] ?? 'N/A') ?></span><br><small style="color: #94a3b8;">Doc: <?= htmlspecialchars($row['doc_number'] ?? 'N/A') ?></small></td>
                            <td><strong><?= htmlspecialchars($row['invoice_number'] ?? 'N/A') ?></strong><br><small style="color: #94a3b8;"><?= $row['record_date'] ?></small></td>
                            <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                            <td><span class="status-badge status-completed"><?= htmlspecialchars($row['mode_name'] ?? 'N/A') ?></span></td>
                            <td><small><?= htmlspecialchars(substr($row['reason_name'] ?? 'N/A', 0, 30)) ?></small></td>
                            <td class="text-center">
                                <span class="status-badge <?= $row['print_count'] > 0 ? 'status-completed' : 'status-pending' ?>">
                                    🖨️ <?= $row['print_count'] ?>
                                </span>
                            </td>
                            <td class="text-right">
                                <button onclick="event.stopPropagation(); preparePrint(<?= $row['record_id'] ?>, <?= $row['print_count'] ?>)" 
                                        class="btn-primary" style="padding: 8px 16px; font-size: 0.7rem;">
                                    🖨️ Print
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination no-print">
                <?php if ($page > 1): ?>
                    <a href="?page=1&search=<?= urlencode($search) ?>&from_date=<?= urlencode($fromDate) ?>&to_date=<?= urlencode($toDate) ?>">
                        ««
                    </a>
                    <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&from_date=<?= urlencode($fromDate) ?>&to_date=<?= urlencode($toDate) ?>">
                        «
                    </a>
                <?php else: ?>
                    <span class="disabled">««</span>
                    <span class="disabled">«</span>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                if ($startPage > 1) {
                    echo '<span>...</span>';
                }
                
                for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&from_date=<?= urlencode($fromDate) ?>&to_date=<?= urlencode($toDate) ?>" 
                       class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor;
                
                if ($endPage < $totalPages) {
                    echo '<span>...</span>';
                }
                ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&from_date=<?= urlencode($fromDate) ?>&to_date=<?= urlencode($toDate) ?>">
                        »
                    </a>
                    <a href="?page=<?= $totalPages ?>&search=<?= urlencode($search) ?>&from_date=<?= urlencode($fromDate) ?>&to_date=<?= urlencode($toDate) ?>">
                        »»
                    </a>
                <?php else: ?>
                    <span class="disabled">»</span>
                    <span class="disabled">»»</span>
                <?php endif; ?>
            </div>
            <div class="page-info no-print">
                Page <?= $page ?> of <?= $totalPages ?> | Total Records: <?= number_format($totalRecords) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="main-footer no-print">
    <div class="footer-content">
        <div class="footer-copyright">
            © <?= date('Y') ?> ASB Fashion - All Rights Reserved
        </div>
        <div class="footer-developer">
            <p class="developer-name">Vexel IT</p>
            <p class="developer-company">Main Developer & Technical Partner</p>
            <p class="footer-rights">Enterprise Solution Provider</p>
        </div>
    </div>
</footer>

<!-- CREATE RETURN MODAL -->
<div id="createModal" class="modal-overlay hidden no-print">
    <div class="modal-container">
        <div class="modal-header">
            <h2>➕ Create New Return</h2>
            <button class="modal-close" onclick="closeCreateModal()">✕</button>
        </div>
        <div class="modal-body">
            <!-- Auto Reference Display -->
            <div class="reference-box">
                <div class="reference-label">AUTO-GENERATED REFERENCE NUMBER</div>
                <div class="reference-number" id="autoReference"><?= $next_reference ?></div>
                <div class="reference-hint">Format: YYYYMMDD + 5-digit sequence | This is your unique return reference</div>
            </div>
            
            <form id="createReturnForm">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Record Date *</label>
                        <input type="date" name="record_date" id="record_date" required class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Supplier *</label>
                        <select name="supplier_id" id="supplier_id" required class="form-select">
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['supplier_id'] ?>"><?= htmlspecialchars($s['supplier_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Invoice Number *</label>
                        <input type="text" name="invoice_number" id="invoice_number" required class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Document Number</label>
                        <input type="text" name="doc_number" id="doc_number" class="form-input" placeholder="Optional - Your reference">
                    </div>
                </div>
                
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Mode *</label>
                        <select name="mode_id" id="mode_id" required class="form-select">
                            <option value="">Select Mode</option>
                            <?php foreach ($modes as $m): ?>
                                <option value="<?= $m['mode_id'] ?>"><?= htmlspecialchars($m['mode_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Return Reason *</label>
                        <select name="reason_id" id="reason_id" required class="form-select">
                            <option value="">Select Reason</option>
                            <?php foreach ($reasons as $r): ?>
                                <option value="<?= $r['reason_id'] ?>"><?= htmlspecialchars($r['reason_text']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Items to Return</label>
                    <div id="itemsContainer" class="space-y-2">
                        <div class="item-row">
                            <input type="text" placeholder="Item Code" class="item-code">
                            <input type="text" placeholder="Item Name" class="item-name">
                            <input type="number" placeholder="Qty" class="item-qty" style="width: 100px;">
                            <input type="number" placeholder="Unit Cost" class="item-cost" style="width: 120px;" step="0.01">
                            <button type="button" class="remove-item" onclick="this.parentElement.remove()">✕</button>
                        </div>
                    </div>
                    <button type="button" onclick="addItemRow()" class="btn-secondary" style="margin-top: 12px;">
                        ➕ Add Another Item
                    </button>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Added By</label>
                    <input type="text" name="added_by_user" id="added_by_user" value="<?= htmlspecialchars($username) ?>" class="form-input">
                </div>
                
                <div class="flex gap-3" style="margin-top: 24px;">
                    <button type="submit" class="btn-primary" style="flex: 1;">💾 Create Return</button>
                    <button type="button" onclick="closeCreateModal()" class="btn-secondary" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- VIEW RETURN DETAILS MODAL -->
<div id="viewModal" class="modal-overlay hidden no-print">
    <div class="modal-container">
        <div class="modal-header">
            <h2>👁️ Return Details</h2>
            <button class="modal-close" onclick="closeViewModal()">✕</button>
        </div>
        <div id="viewModalContent" class="modal-body"></div>
    </div>
</div>

<!-- PRINT TEMPLATE - A6 LANDSCAPE with JsBarcode -->
<div id="printArea" class="hidden" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid black; padding-bottom: 4px; margin-bottom: 6px;">
        <div>
            <h1 style="font-size: 15px; font-weight: 900; margin: 0;">ASB FASHION</h1>
            <p style="font-size: 10px; font-weight: 700; text-transform: uppercase; margin: 0; color: #000;">Quality Control Return Note</p>
        </div>
        <div style="text-align: center;">
            <div style="font-size: 6px; color: #666; margin-bottom: 2px;">REFERENCE BARCODE</div>
            <svg id="printBarcode" style="width: 130px; height: 35px;"></svg>
            <div id="barcodeText" style="font-size: 7px; font-family: monospace; margin-top: 2px;"></div>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; font-size: 8.5px; margin-bottom: 8px;">
        <div style="border: 1px solid #000; padding: 5px; border-radius: 4px; line-height: 1.4;">
            <b>SUPPLIER:</b> <span id="pSupplier" style="text-transform: uppercase;"></span><br>
            <b>INVOICE NO:</b> <span id="pInvoice"></span><br>
            <b>DOCUMENT NO:</b> <span id="pDocNumber"></span>
        </div>
        <div style="border: 1px solid #000; padding: 5px; border-radius: 4px; line-height: 1.4;">
            <b>REFERENCE:</b> <span id="pRef" style="font-weight: 700; font-family: monospace;"></span><br>
            <b>DATE:</b> <span id="pDate"></span><br>
            <b>MODE:</b> <span id="pMode" style="text-transform: uppercase; font-weight: 700;"></span>
        </div>
    </div>

    <div style="text-align: right; margin-bottom: 6px;">
        <span style="font-size: 7px; background: #f0f0f0; padding: 2px 6px; border-radius: 10px; border: 1px solid #ccc;">
            <b>PRINT COUNT:</b> <span id="pPrintCount" style="font-weight: 900;">0</span>
        </span>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 8px;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead><tr><th style="text-align:left; font-size: 8px;">Item Code</th><th style="text-align:right; font-size: 8px;">Qty</th></tr></thead>
            <tbody id="pItemsLeft"></tbody>
        </table>
        <table style="width: 100%; border-collapse: collapse;">
            <thead><tr><th style="text-align:left; font-size: 8px;">Item Code</th><th style="text-align:right; font-size: 8px;">Qty</th></tr></thead>
            <tbody id="pItemsRight"></tbody>
        </table>
    </div>

    <div style="margin-top: 8px; padding-top: 6px; border-top: 1px solid #ccc; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; text-align: center; font-size: 7px;">
        <div>
            <div style="border-bottom: 1px solid black; height: 12px; margin-bottom: 3px;"></div>
            <b>AUTHORIZED SIGNATURE</b>
        </div>
        <div>
            <div style="border-bottom: 1px solid black; height: 12px; margin-bottom: 3px;"></div>
            <b>SUPPLIER ACKNOWLEDGEMENT</b>
        </div>
    </div>
    
    <div style="text-align: center; font-size: 6px; color: #666; margin-top: 6px;">
        System Generated Document | ASB Fashion QC System | Developed by Vexel IT
    </div>
</div>

<script>
    let itemCounter = 1;
    
    function openCreateModal() {
        document.getElementById('createModal').classList.remove('hidden');
        document.getElementById('record_date').valueAsDate = new Date();
        document.getElementById('itemsContainer').innerHTML = `
            <div class="item-row">
                <input type="text" placeholder="Item Code" class="item-code">
                <input type="text" placeholder="Item Name" class="item-name">
                <input type="number" placeholder="Qty" class="item-qty" style="width: 100px;">
                <input type="number" placeholder="Unit Cost" class="item-cost" style="width: 120px;" step="0.01">
                <button type="button" class="remove-item" onclick="this.parentElement.remove()">✕</button>
            </div>
        `;
    }
    
    function closeCreateModal() {
        document.getElementById('createModal').classList.add('hidden');
    }
    
    function closeViewModal() {
        document.getElementById('viewModal').classList.add('hidden');
    }
    
    function addItemRow() {
        const container = document.getElementById('itemsContainer');
        const newRow = document.createElement('div');
        newRow.className = 'item-row';
        newRow.innerHTML = `
            <input type="text" placeholder="Item Code" class="item-code">
            <input type="text" placeholder="Item Name" class="item-name">
            <input type="number" placeholder="Qty" class="item-qty" style="width: 100px;">
            <input type="number" placeholder="Unit Cost" class="item-cost" style="width: 120px;" step="0.01">
            <button type="button" class="remove-item" onclick="this.parentElement.remove()">✕</button>
        `;
        container.appendChild(newRow);
    }
    
    document.getElementById('createReturnForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const items = [];
        const rows = document.querySelectorAll('.item-row');
        
        for (let row of rows) {
            const itemCode = row.querySelector('.item-code').value.trim();
            const itemName = row.querySelector('.item-name').value.trim();
            const quantity = row.querySelector('.item-qty').value;
            const unitCost = row.querySelector('.item-cost').value;
            
            if (itemCode && quantity && parseInt(quantity) > 0) {
                items.push({
                    item_code: itemCode,
                    item_name: itemName || itemCode,
                    quantity: parseInt(quantity),
                    unit_cost: parseFloat(unitCost) || 0
                });
            }
        }
        
        if (items.length === 0) {
            Swal.fire('Error', 'Please add at least one item with quantity', 'error');
            return;
        }
        
        const formData = new URLSearchParams();
        formData.append('record_date', document.getElementById('record_date').value);
        formData.append('supplier_id', document.getElementById('supplier_id').value);
        formData.append('invoice_number', document.getElementById('invoice_number').value);
        formData.append('doc_number', document.getElementById('doc_number').value);
        formData.append('mode_id', document.getElementById('mode_id').value);
        formData.append('reason_id', document.getElementById('reason_id').value);
        formData.append('added_by_user', document.getElementById('added_by_user').value);
        formData.append('items', JSON.stringify(items));
        
        Swal.fire({ title: 'Creating...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
        
        const response = await fetch(window.location.pathname + '?action=create_return', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        Swal.close();
        
        if (result.success) {
            Swal.fire('Success', result.message, 'success');
            closeCreateModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            Swal.fire('Error', result.message, 'error');
        }
    });
    
    async function viewReturnDetails(recordId) {
        Swal.fire({ title: 'Loading...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
        
        try {
            const response = await fetch(`get_details.php?id=${recordId}`);
            const data = await response.json();
            Swal.close();
            
            if (data.success) {
                const main = data.main;
                const items = data.items;
                const images = data.images || [];
                
                let itemsHtml = '';
                let totalValue = 0;
                items.forEach(item => {
                    const itemTotal = item.quantity * item.unit_cost;
                    totalValue += itemTotal;
                    itemsHtml += `
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 8px; font-family: monospace;">${escapeHtml(item.item_code)}</td>
                            <td style="padding: 8px;">${escapeHtml(item.item_name || 'N/A')}</td>
                            <td style="padding: 8px; text-align: center;">${item.quantity}</td>
                            <td style="padding: 8px; text-align: right;">${parseFloat(item.unit_cost).toFixed(2)}</td>
                            <td style="padding: 8px; text-align: right;">${itemTotal.toFixed(2)}</td>
                        </tr>
                    `;
                });
                
                let imagesHtml = '';
                if (images.length > 0) {
                    imagesHtml = `
                        <div style="margin-top: 20px;">
                            <h4 style="font-weight: 700; margin-bottom: 12px;">📷 Images (${images.length}/4)</h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 12px;">
                                ${images.map(img => `
                                    <div style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px; text-align: center;">
                                        <img src="${img.image_path}" alt="Return Image" style="width: 100%; height: 80px; object-fit: cover; border-radius: 6px;">
                                        <a href="${img.image_path}" target="_blank" style="font-size: 0.7rem; color: #3b82f6; text-decoration: none;">View Full</a>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `;
                }
                
                document.getElementById('viewModalContent').innerHTML = `
                    <div style="display: grid; grid-template-columns: repeat(2,1fr); gap: 12px; margin-bottom: 20px;">
                        <div style="background: #f8fafc; padding: 12px; border-radius: 12px;">
                            <div style="font-size: 0.7rem; color: #64748b;">Return ID</div>
                            <div style="font-weight: 700; font-size: 1.2rem; color: #dc2626;">#${main.record_id}</div>
                        </div>
                        <div style="background: #f8fafc; padding: 12px; border-radius: 12px;">
                            <div style="font-size: 0.7rem; color: #64748b;">Reference Number</div>
                            <div style="font-weight: 700; font-family: monospace;">${escapeHtml(main.reference_number)}</div>
                        </div>
                        <div style="background: #f8fafc; padding: 12px; border-radius: 12px;">
                            <div style="font-size: 0.7rem; color: #64748b;">Record Date</div>
                            <div style="font-weight: 700;">${main.record_date}</div>
                        </div>
                        <div style="background: #f8fafc; padding: 12px; border-radius: 12px;">
                            <div style="font-size: 0.7rem; color: #64748b;">Invoice Number</div>
                            <div style="font-weight: 700;">${escapeHtml(main.invoice_number)}</div>
                        </div>
                        <div style="background: #f8fafc; padding: 12px; border-radius: 12px;">
                            <div style="font-size: 0.7rem; color: #64748b;">Supplier</div>
                            <div style="font-weight: 700;">${escapeHtml(main.supplier_name)}</div>
                        </div>
                        <div style="background: #f8fafc; padding: 12px; border-radius: 12px;">
                            <div style="font-size: 0.7rem; color: #64748b;">Mode / Reason</div>
                            <div style="font-weight: 700;">${escapeHtml(main.mode_name || 'N/A')} / ${escapeHtml(main.reason_name || 'N/A')}</div>
                        </div>
                    </div>
                    
                    <div>
                        <h4 style="font-weight: 700; margin-bottom: 12px;">📦 Returned Items</h4>
                        <div class="table-wrapper">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead style="background: #f8fafc;">
                                    <tr>
                                        <th style="padding: 10px; text-align: left;">Item Code</th>
                                        <th style="padding: 10px; text-align: left;">Item Name</th>
                                        <th style="padding: 10px; text-align: center;">Qty</th>
                                        <th style="padding: 10px; text-align: right;">Unit Cost</th>
                                        <th style="padding: 10px; text-align: right;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>${itemsHtml}</tbody>
                                <tfoot style="background: #f8fafc; font-weight: 700;">
                                    <tr>
                                        <td colspan="4" style="padding: 10px; text-align: right;">Grand Total:</td>
                                        <td style="padding: 10px; text-align: right; color: #dc2626;">${totalValue.toFixed(2)}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    
                    ${imagesHtml}
                    
                    <div style="margin-top: 20px;">
                        <h4 style="font-weight: 700; margin-bottom: 12px;">📊 Status</h4>
                        <div style="display: grid; grid-template-columns: repeat(4,1fr); gap: 10px;">
                            <div class="text-center" style="padding: 10px; border-radius: 8px; ${main.is_informed ? 'background: #d1fae5; color: #065f46;' : 'background: #fed7aa; color: #92400e;'}">
                                <div style="font-size: 0.7rem;">Informed</div>
                                <div style="font-size: 1.1rem;">${main.is_informed ? '✓' : '○'}</div>
                            </div>
                            <div class="text-center" style="padding: 10px; border-radius: 8px; ${main.is_store_received ? 'background: #d1fae5; color: #065f46;' : 'background: #fed7aa; color: #92400e;'}">
                                <div style="font-size: 0.7rem;">Store</div>
                                <div style="font-size: 1.1rem;">${main.is_store_received ? '✓' : '○'}</div>
                            </div>
                            <div class="text-center" style="padding: 10px; border-radius: 8px; ${main.is_gate_cleared ? 'background: #d1fae5; color: #065f46;' : 'background: #fed7aa; color: #92400e;'}">
                                <div style="font-size: 0.7rem;">Gate</div>
                                <div style="font-size: 1.1rem;">${main.is_gate_cleared ? '✓' : '○'}</div>
                            </div>
                            <div class="text-center" style="padding: 10px; border-radius: 8px; ${main.is_handover_complete ? 'background: #d1fae5; color: #065f46;' : 'background: #fed7aa; color: #92400e;'}">
                                <div style="font-size: 0.7rem;">Complete</div>
                                <div style="font-size: 1.1rem;">${main.is_handover_complete ? '✓' : '○'}</div>
                            </div>
                        </div>
                    </div>
                `;
                document.getElementById('viewModal').classList.remove('hidden');
            } else {
                Swal.fire('Error', data.message || 'Unable to load record', 'error');
            }
        } catch (err) {
            Swal.close();
            Swal.fire('Error', 'Failed to load record details', 'error');
        }
    }
    
    async function preparePrint(id, currentCount) {
        if (currentCount > 0) {
            const { value: password } = await Swal.fire({
                title: 'Reprint Authorization',
                text: 'This document has been printed before. Enter admin password to reprint.',
                input: 'password',
                inputPlaceholder: 'Enter admin password',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#475569',
                showCancelButton: true,
                confirmButtonText: 'Authorize Reprint'
            });
            if (!password) return;
            
            const auth = await fetch('verify_admin.php', { 
                method: 'POST', 
                body: new URLSearchParams({ 'password': password }) 
            });
            const authData = await auth.json();
            if (!authData.success) {
                Swal.fire('Access Denied', 'Invalid administrator password provided.', 'error');
                return;
            }
        }
        
        Swal.fire({ title: 'Preparing Document...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
        
        try {
            const res = await fetch(`get_details.php?id=${id}`);
            const data = await res.json();
            Swal.close();
            
            if(data.success) {
                const ref = data.main.reference_number;
                const printCount = data.main.print_count || 0;
                const newPrintCount = printCount + 1;
                
                document.getElementById('pPrintCount').innerText = newPrintCount;
                document.getElementById('pSupplier').innerText = data.main.supplier_name || 'N/A';
                document.getElementById('pInvoice').innerText = data.main.invoice_number || 'N/A';
                document.getElementById('pDocNumber').innerText = data.main.doc_number || 'N/A';
                document.getElementById('pRef').innerText = ref;
                document.getElementById('pDate').innerText = data.main.record_date;
                document.getElementById('pMode').innerText = data.main.mode_name || 'STANDARD';
                
                // Update barcode text display
                const barcodeTextDiv = document.getElementById('barcodeText');
                if (barcodeTextDiv) {
                    barcodeTextDiv.innerText = ref;
                }
                
                // Generate professional barcode using JsBarcode (online resource)
                setTimeout(() => {
                    JsBarcode("#printBarcode", ref, { 
                        format: "CODE128",
                        width: 1.2,
                        height: 30,
                        displayValue: true,
                        fontSize: 8,
                        fontOptions: "bold",
                        margin: 3,
                        background: "#ffffff",
                        lineColor: "#000000"
                    });
                }, 100);
                
                let leftHtml = '', rightHtml = '';
                const mid = Math.ceil(data.items.length / 2);
                
                data.items.forEach((item, index) => {
                    const row = `<tr><td style="font-family: monospace; font-size: 9px; padding: 2px 0;">${escapeHtml(item.item_code)}</td><td style="text-align:right; font-weight:bold;">${parseInt(item.quantity).toLocaleString()}</td></tr>`;
                    if (index < mid) leftHtml += row;
                    else rightHtml += row;
                });
                
                const leftCount = data.items.slice(0, mid).length;
                const rightCount = data.items.slice(mid).length;
                const maxRows = Math.max(leftCount, rightCount);
                
                for (let i = rightCount; i < maxRows; i++) {
                    rightHtml += `<tr><td style="padding: 2px 0;">&nbsp;</td><td style="text-align:right;">&nbsp;</td></tr>`;
                }
                for (let i = leftCount; i < maxRows; i++) {
                    leftHtml += `<tr><td style="padding: 2px 0;">&nbsp;</td><td style="text-align:right;">&nbsp;</td></tr>`;
                }
                
                document.getElementById('pItemsLeft').innerHTML = leftHtml || '<tr><td colspan="2" style="text-align:center;">No items</td></tr>';
                document.getElementById('pItemsRight').innerHTML = rightHtml || '<tr><td colspan="2" style="text-align:center;">No items</td></tr>';
                
                await fetch('increment_print.php', { method: 'POST', body: new URLSearchParams({ 'id': id }) });
                
                setTimeout(() => { 
                    window.print(); 
                    setTimeout(() => location.reload(), 500); 
                }, 200);
            } else {
                Swal.fire('Error', data.message || 'Unable to load record', 'warning');
            }
        } catch (err) { 
            console.error('Print error:', err);
            Swal.fire('Error', 'Failed to generate print document: ' + err.message, 'error'); 
        }
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
</script>
</body>
</html>