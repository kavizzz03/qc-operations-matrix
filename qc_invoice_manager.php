<?php
// ==========================================
// BACKEND OPERATIONAL ROUTER & CONTROLLER
// ASB FASHION GROUP - COMPLETE OFFLINE VERSION
// Developed By Vexel IT | Kavizz
// ==========================================
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
        // Fetch Invoices with Advanced Filters
        if ($action === 'fetch_invoices') {
            $where = [];
            $params = [];
            
            if (!empty($_POST['supplier_id'])) {
                $where[] = "si.supplier_id = ?";
                $params[] = $_POST['supplier_id'];
            }
            if (!empty($_POST['branch_id'])) {
                $where[] = "si.branch_id = ?";
                $params[] = $_POST['branch_id'];
            }
            if (!empty($_POST['floor_id'])) {
                $where[] = "si.floor_id = ?";
                $params[] = $_POST['floor_id'];
            }
            if (!empty($_POST['date_from'])) {
                $where[] = "DATE(si.invoice_date) >= ?";
                $params[] = $_POST['date_from'];
            }
            if (!empty($_POST['date_to'])) {
                $where[] = "DATE(si.invoice_date) <= ?";
                $params[] = $_POST['date_to'];
            }
            if (!empty($_POST['invoice_number'])) {
                $where[] = "si.invoice_number LIKE ?";
                $params[] = '%' . $_POST['invoice_number'] . '%';
            }
            
            $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";
            
            $sql = "
                SELECT si.invoice_id, si.invoice_number, si.invoice_date, si.checked_date, si.checker_name, si.added_by,
                       s.supplier_name, s.system_id as supplier_system_id,
                       b.branch_name, b.branch_code,
                       f.floor_name,
                       COUNT(ii.invoice_item_id) as total_items,
                       COALESCE(SUM(ii.received_qty), 0) as total_received,
                       COALESCE(SUM(ii.defect_qty), 0) as total_defects,
                       COALESCE(SUM(ii.return_qty), 0) as total_returns,
                       SUM(CASE WHEN ii.status = 'FAIL' THEN 1 ELSE 0 END) as failed_items_count
                FROM supplier_invoices si
                LEFT JOIN suppliers s ON si.supplier_id = s.supplier_id
                LEFT JOIN branches b ON si.branch_id = b.branch_id
                LEFT JOIN floors f ON si.floor_id = f.floor_id
                LEFT JOIN invoice_items ii ON si.invoice_id = ii.invoice_id
                $whereClause
                GROUP BY si.invoice_id
                ORDER BY si.created_at DESC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll();
            echo json_encode(['status' => 'success', 'data' => $result]);
            exit;
        }

        // Get Complete Invoice Details
        if ($action === 'get_invoice_details') {
            $invoice_id = $_POST['invoice_id'];
            
            $stmt = $pdo->prepare("
                SELECT si.*, s.supplier_name, s.system_id as supplier_system_id,
                       b.branch_name, b.branch_code,
                       f.floor_name
                FROM supplier_invoices si
                LEFT JOIN suppliers s ON si.supplier_id = s.supplier_id
                LEFT JOIN branches b ON si.branch_id = b.branch_id
                LEFT JOIN floors f ON si.floor_id = f.floor_id
                WHERE si.invoice_id = ?
            ");
            $stmt->execute([$invoice_id]);
            $invoice = $stmt->fetch();
            
            if (!$invoice) {
                echo json_encode(['status' => 'error', 'message' => 'Invoice not found']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT ii.*, i.item_name, i.item_code, i.system_id as item_system_id, i.cost_price
                FROM invoice_items ii
                LEFT JOIN items i ON ii.item_id = i.item_id
                WHERE ii.invoice_id = ?
                ORDER BY ii.invoice_item_id
            ");
            $stmt->execute([$invoice_id]);
            $items = $stmt->fetchAll();
            
            foreach ($items as &$item) {
                $stmt = $pdo->prepare("
                    SELECT r.reason_id, r.reason_text, irr.return_qty
                    FROM item_return_reasons irr
                    LEFT JOIN return_reasons r ON irr.reason_id = r.reason_id
                    WHERE irr.invoice_item_id = ?
                ");
                $stmt->execute([$item['invoice_item_id']]);
                $item['reasons'] = $stmt->fetchAll();
            }
            
            echo json_encode(['status' => 'success', 'invoice' => $invoice, 'items' => $items]);
            exit;
        }

        // Update Invoice Item
        if ($action === 'update_invoice_item') {
            $pdo->beginTransaction();
            
            $invoice_item_id = $_POST['invoice_item_id'];
            $received_qty = intval($_POST['received_qty']);
            $checked_sample_qty = intval($_POST['checked_sample_qty']);
            $defect_qty = intval($_POST['defect_qty']);
            $return_qty = intval($_POST['return_qty']);
            $status = $_POST['status'];
            
            if ($checked_sample_qty > $received_qty) {
                throw new Exception("Checked sample quantity cannot exceed received quantity");
            }
            if ($defect_qty > $checked_sample_qty) {
                throw new Exception("Defect quantity cannot exceed checked sample quantity");
            }
            if ($status === 'FAIL' && $return_qty != $received_qty) {
                throw new Exception("For FAIL status, return quantity must equal received quantity");
            }
            
            $stmt = $pdo->prepare("UPDATE invoice_items SET received_qty = ?, checked_sample_qty = ?, defect_qty = ?, return_qty = ?, status = ? WHERE invoice_item_id = ?");
            $stmt->execute([$received_qty, $checked_sample_qty, $defect_qty, $return_qty, $status, $invoice_item_id]);
            
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Item updated successfully']);
            exit;
        }

        // Update Return Reasons
        if ($action === 'update_return_reasons') {
            $pdo->beginTransaction();
            
            $invoice_item_id = $_POST['invoice_item_id'];
            $return_qty = intval($_POST['return_qty']);
            $reasons = isset($_POST['reasons']) ? $_POST['reasons'] : [];
            
            $stmt = $pdo->prepare("DELETE FROM item_return_reasons WHERE invoice_item_id = ?");
            $stmt->execute([$invoice_item_id]);
            
            if (!empty($reasons) && $return_qty > 0) {
                $total_reason_qty = array_sum($reasons);
                if ($total_reason_qty != $return_qty) {
                    throw new Exception("Sum of return reasons does not match return quantity");
                }
                
                $stmtReason = $pdo->prepare("INSERT INTO item_return_reasons (invoice_item_id, reason_id, return_qty) VALUES (?, ?, ?)");
                foreach ($reasons as $reason_id => $r_qty) {
                    $r_qty = intval($r_qty);
                    if ($r_qty > 0) {
                        $stmtReason->execute([$invoice_item_id, $reason_id, $r_qty]);
                    }
                }
            }
            
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Return reasons updated']);
            exit;
        }

        // Update Invoice Header
        if ($action === 'update_invoice_header') {
            $stmt = $pdo->prepare("
                UPDATE supplier_invoices 
                SET invoice_number = ?, invoice_date = ?, branch_id = ?, floor_id = ?, checked_date = ?, checker_name = ?
                WHERE invoice_id = ?
            ");
            $stmt->execute([
                $_POST['invoice_number'],
                $_POST['invoice_date'],
                !empty($_POST['branch_id']) ? $_POST['branch_id'] : null,
                $_POST['floor_id'],
                $_POST['checked_date'],
                $_POST['checker_name'],
                $_POST['invoice_id']
            ]);
            
            echo json_encode(['status' => 'success', 'message' => 'Invoice header updated']);
            exit;
        }

        // Get All Return Reasons
        if ($action === 'get_return_reasons') {
            $stmt = $pdo->query("SELECT reason_id, reason_text FROM return_reasons ORDER BY reason_id");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
            exit;
        }
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) { 
            $pdo->rollBack(); 
        }
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// PRINT action (GET request) - Updated with only Supplier Name and System ID
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'print_full_report') {
    header('Content-Type: text/html');
    $invoice_id = $_GET['invoice_id'];
    
    $stmt = $pdo->prepare("
        SELECT si.*, s.supplier_name, s.system_id as supplier_system_id,
               b.branch_name, b.branch_code,
               f.floor_name
        FROM supplier_invoices si
        LEFT JOIN suppliers s ON si.supplier_id = s.supplier_id
        LEFT JOIN branches b ON si.branch_id = b.branch_id
        LEFT JOIN floors f ON si.floor_id = f.floor_id
        WHERE si.invoice_id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        die("Invoice not found");
    }
    
    $stmt = $pdo->prepare("
        SELECT ii.*, i.item_name, i.item_code, i.system_id as item_system_id, i.cost_price
        FROM invoice_items ii
        LEFT JOIN items i ON ii.item_id = i.item_id
        WHERE ii.invoice_id = ?
        ORDER BY ii.status DESC, ii.invoice_item_id
    ");
    $stmt->execute([$invoice_id]);
    $items = $stmt->fetchAll();
    
    foreach ($items as &$item) {
        $stmt = $pdo->prepare("
            SELECT r.reason_text, irr.return_qty
            FROM item_return_reasons irr
            LEFT JOIN return_reasons r ON irr.reason_id = r.reason_id
            WHERE irr.invoice_item_id = ?
        ");
        $stmt->execute([$item['invoice_item_id']]);
        $item['reasons'] = $stmt->fetchAll();
    }
    
    $total_received = array_sum(array_column($items, 'received_qty'));
    $total_defects = array_sum(array_column($items, 'defect_qty'));
    $total_returns = array_sum(array_column($items, 'return_qty'));
    $total_accepted = 0;
    $failed_items = [];
    $passed_items = [];
    
    foreach ($items as $item) {
        if ($item['status'] === 'PASS') {
            $total_accepted += ($item['received_qty'] - $item['return_qty']);
            $passed_items[] = $item;
        } else {
            $failed_items[] = $item;
        }
    }
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>QC Report - <?= htmlspecialchars($invoice['invoice_number']) ?></title>
        <style>
            @page {
                size: A4;
                margin: 1.2cm;
            }
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: 'Times New Roman', 'Arial', 'Segoe UI', sans-serif;
                background: white;
                color: #1a1a1a;
                font-size: 10pt;
                line-height: 1.4;
            }
            .print-container {
                max-width: 100%;
                margin: 0 auto;
                padding: 0;
            }
            .print-header {
                text-align: center;
                margin-bottom: 25px;
                padding-bottom: 15px;
                border-bottom: 3px solid #8b0000;
            }
            .company-logo {
                margin-bottom: 10px;
                font-size: 28px;
                font-weight: bold;
                color: #8b0000;
            }
            .print-header h1 {
                color: #8b0000;
                font-size: 18pt;
                font-weight: 800;
                margin-bottom: 5px;
            }
            .print-header h2 {
                color: #b22222;
                font-size: 12pt;
                font-weight: 600;
                margin-bottom: 5px;
            }
            .print-header p {
                color: #444;
                font-size: 9pt;
                margin-top: 5px;
            }
            .doc-info-bar {
                background: #f5f5f5;
                padding: 8px 12px;
                margin-bottom: 15px;
                display: flex;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 8px;
                border: 1px solid #ddd;
                font-size: 8pt;
            }
            .section-title {
                background: #f0f0f0;
                padding: 8px 12px;
                font-size: 11pt;
                font-weight: bold;
                border-left: 3px solid #b22222;
                margin: 15px 0 10px 0;
            }
            .section-title.failed {
                border-left-color: #c62828;
                background: #ffebee;
            }
            .info-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 12px;
                margin-bottom: 15px;
            }
            .info-item {
                border-bottom: 1px solid #eee;
                padding: 5px 0;
            }
            .info-label {
                font-weight: bold;
                color: #555;
                font-size: 7pt;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .info-value {
                font-size: 10pt;
                margin-top: 3px;
                font-weight: 500;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
                font-size: 9pt;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 6px;
                text-align: left;
                vertical-align: top;
            }
            th {
                background: #e8e8e8;
                font-weight: bold;
                text-align: center;
                font-size: 8pt;
            }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .status-pass { color: #2e7d32; font-weight: bold; }
            .status-fail { color: #c62828; font-weight: bold; }
            .failed-row { background-color: #fff3f3; }
            .summary-box {
                background: #f9f9f9;
                padding: 15px;
                border: 1px solid #ddd;
                margin-top: 15px;
            }
            .badge {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 4px;
                font-size: 7pt;
                font-weight: bold;
            }
            .badge-pass { background: #a5d6a7; color: #1b5e20; }
            .badge-fail { background: #ef9a9a; color: #b71c1c; }
            .warning-box {
                background: #fff3e0;
                border-left: 3px solid #ff9800;
                padding: 10px;
                margin: 10px 0;
            }
            .signature-area {
                margin-top: 30px;
                display: flex;
                justify-content: space-between;
                padding: 0 20px;
            }
            .signature-line {
                text-align: center;
                width: 200px;
            }
            .signature-line .line {
                border-top: 1px solid #333;
                margin-top: 40px;
                padding-top: 8px;
                font-size: 8pt;
            }
            .footer {
                margin-top: 30px;
                padding-top: 15px;
                border-top: 2px solid #ddd;
                text-align: center;
                font-size: 7pt;
                color: #666;
            }
            @media print {
                .bottom-bar { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            }
        </style>
    </head>
    <body>
        <div class="print-container">
            <div class="print-header">
                <div class="company-logo">ASB FASHION</div>
                <h1>QUALITY CONTROL COMPLETE REPORT</h1>
                <h2>Damage & Shortage Inspection Report</h2>
                <p>Official QC Document | System Generated Report</p>
            </div>
            
            <div class="doc-info-bar">
                <div><strong>Report ID:</strong> QC/<?= date('Ymd') ?>/<?= $invoice_id ?></div>
                <div><strong>Generated:</strong> <?= date('Y-m-d H:i:s') ?></div>
                <div><strong>Status:</strong> Certified</div>
            </div>
            
            <!-- Invoice Information -->
            <div class="section-title">📄 INVOICE INFORMATION</div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Invoice Number</div>
                    <div class="info-value"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Invoice Date</div>
                    <div class="info-value"><?= date('d-m-Y', strtotime($invoice['invoice_date'])) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">QC Inspection Date</div>
                    <div class="info-value"><?= date('d-m-Y', strtotime($invoice['checked_date'])) ?></div>
                </div>
            </div>
            
            <!-- Supplier Information - ONLY Name and System ID -->
            <div class="section-title">🏭 SUPPLIER INFORMATION</div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Supplier Name</div>
                    <div class="info-value"><strong><?= htmlspecialchars($invoice['supplier_name']) ?></strong></div>
                </div>
                <div class="info-item">
                    <div class="info-label">System ID</div>
                    <div class="info-value"><?= htmlspecialchars($invoice['supplier_system_id'] ?? 'N/A') ?></div>
                </div>
            </div>
            
            <!-- Location Information -->
            <div class="section-title">📍 LOCATION & INSPECTOR</div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Branch / Location</div>
                    <div class="info-value"><?= htmlspecialchars($invoice['branch_name'] ?? 'Not Assigned') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Floor / Section</div>
                    <div class="info-value"><?= htmlspecialchars($invoice['floor_name'] ?? 'N/A') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">QC Inspector</div>
                    <div class="info-value"><?= htmlspecialchars($invoice['checker_name']) ?></div>
                </div>
            </div>
            
            <!-- FAILED ITEMS SECTION -->
            <?php if (count($failed_items) > 0): ?>
            <div class="section-title failed">❌ FAILED ITEMS (REJECTED)</div>
            <div class="warning-box">
                <strong>⚠️ IMPORTANT:</strong> The following items have FAILED quality inspection. All received quantities are marked for return.
            </div>
            <table>
                <thead>
                    <tr>
                        <th width="30%">Item Description</th>
                        <th width="15%">Item Code</th>
                        <th width="10%">Received</th>
                        <th width="10%">Sampled</th>
                        <th width="10%">Defects</th>
                        <th width="10%">Return</th>
                        <th width="15%">Return Reasons</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($failed_items as $item): ?>
                    <tr class="failed-row">
                        <td><strong><?= htmlspecialchars($item['item_name']) ?></strong><br><small>Cost: <?= number_format($item['cost_price'], 2) ?> LKR</small></td>
                        <td class="text-center"><?= htmlspecialchars($item['item_code']) ?></td>
                        <td class="text-right"><strong><?= number_format($item['received_qty']) ?></strong></td>
                        <td class="text-right"><?= number_format($item['checked_sample_qty']) ?></td>
                        <td class="text-right" style="color:#c62828;"><strong><?= number_format($item['defect_qty']) ?></strong></td>
                        <td class="text-right" style="color:#e65100;"><strong><?= number_format($item['return_qty']) ?></strong></td>
                        <td>
                            <?php if (!empty($item['reasons'])): ?>
                                <?php foreach ($item['reasons'] as $reason): ?>
                                    <span class="badge badge-fail"><?= htmlspecialchars($reason['reason_text']) ?>: <?= $reason['return_qty'] ?> pcs</span><br>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <em>No specific reasons recorded</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot style="background:#ffebee; font-weight:bold;">
                    <tr>
                        <td colspan="2" class="text-right"><strong>FAILED ITEMS TOTAL:</strong></td>
                        <td class="text-right"><?= number_format(array_sum(array_column($failed_items, 'received_qty'))) ?></td>
                        <td class="text-right">-</td>
                        <td class="text-right"><?= number_format(array_sum(array_column($failed_items, 'defect_qty'))) ?></td>
                        <td class="text-right"><?= number_format(array_sum(array_column($failed_items, 'return_qty'))) ?></td>
                        <td class="text-right">-</td>
                    </tr>
                </tfoot>
            </table>
            <?php endif; ?>
            
            <!-- PASSED ITEMS SECTION -->
            <?php if (count($passed_items) > 0): ?>
            <div class="section-title">✅ PASSED ITEMS (ACCEPTED)</div>
            <table>
                <thead>
                    <tr>
                        <th width="30%">Item Description</th>
                        <th width="15%">Item Code</th>
                        <th width="10%">Received</th>
                        <th width="10%">Sampled</th>
                        <th width="10%">Defects</th>
                        <th width="10%">Return</th>
                        <th width="15%">Return Reasons (if any)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($passed_items as $item): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['item_name']) ?></strong><br><small>Cost: <?= number_format($item['cost_price'], 2) ?> LKR</small></td>
                        <td class="text-center"><?= htmlspecialchars($item['item_code']) ?></td>
                        <td class="text-right"><?= number_format($item['received_qty']) ?></td>
                        <td class="text-right"><?= number_format($item['checked_sample_qty']) ?></td>
                        <td class="text-right" style="color:#c62828;"><?= number_format($item['defect_qty']) ?></td>
                        <td class="text-right" style="color:#e65100;"><?= number_format($item['return_qty']) ?></td>
                        <td>
                            <?php if (!empty($item['reasons'])): ?>
                                <?php foreach ($item['reasons'] as $reason): ?>
                                    <span class="badge badge-pass"><?= htmlspecialchars($reason['reason_text']) ?>: <?= $reason['return_qty'] ?> pcs</span><br>
                                <?php endforeach; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot style="background:#e8f5e9; font-weight:bold;">
                    <tr>
                        <td colspan="2" class="text-right"><strong>PASSED ITEMS TOTAL:</strong></td>
                        <td class="text-right"><?= number_format(array_sum(array_column($passed_items, 'received_qty'))) ?></td>
                        <td class="text-right">-</td>
                        <td class="text-right"><?= number_format(array_sum(array_column($passed_items, 'defect_qty'))) ?></td>
                        <td class="text-right"><?= number_format(array_sum(array_column($passed_items, 'return_qty'))) ?></td>
                        <td class="text-right">-</td>
                    </tr>
                </tfoot>
            </table>
            <?php endif; ?>
            
            <!-- COMPLETE SUMMARY -->
            <div class="summary-box">
                <div class="section-title" style="margin-top: 0;">📈 COMPLETE QUALITY SUMMARY</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Total Received Quantity</div>
                        <div class="info-value"><?= number_format($total_received) ?> units</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Total Defective Units</div>
                        <div class="info-value" style="color:#c62828;"><?= number_format($total_defects) ?> units</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Total Return Quantity</div>
                        <div class="info-value" style="color:#e65100;"><?= number_format($total_returns) ?> units</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Accepted Quantity (PASS)</div>
                        <div class="info-value" style="color:#2e7d32;"><?= number_format($total_accepted) ?> units</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Failed Items Count</div>
                        <div class="info-value" style="color:#c62828;"><?= count($failed_items) ?> items</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Passed Items Count</div>
                        <div class="info-value" style="color:#2e7d32;"><?= count($passed_items) ?> items</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Acceptance Rate</div>
                        <div class="info-value">
                            <?php 
                            if ($total_received > 0) {
                                $rate = ($total_accepted / $total_received) * 100;
                                echo number_format($rate, 2) . '%';
                            } else { echo 'N/A'; }
                            ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Defect Rate</div>
                        <div class="info-value">
                            <?php 
                            if ($total_received > 0) {
                                $rate = ($total_defects / $total_received) * 100;
                                echo number_format($rate, 2) . '%';
                            } else { echo 'N/A'; }
                            ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Rejection Rate</div>
                        <div class="info-value" style="color:#e65100;">
                            <?php 
                            if ($total_received > 0) {
                                $rate = ($total_returns / $total_received) * 100;
                                echo number_format($rate, 2) . '%';
                            } else { echo 'N/A'; }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ACTION REQUIRED FOR FAILED ITEMS -->
            <?php if (count($failed_items) > 0): ?>
            <div class="warning-box" style="background: #ffebee; border-left-color: #c62828;">
                <strong>🔴 ACTION REQUIRED - FAILED ITEMS:</strong>
                <ul style="margin-top: 8px; margin-left: 20px;">
                    <li>All <?= count($failed_items) ?> failed item(s) must be returned to supplier immediately</li>
                    <li>Total return quantity for failed items: <?= number_format(array_sum(array_column($failed_items, 'return_qty'))) ?> units</li>
                    <li>Credit note should be issued for the full value of failed items</li>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="signature-area">
                <div class="signature-line">
                    <div class="line">QC Inspector Signature</div>
                    <div style="margin-top: 10px; font-size: 9pt;"><?= htmlspecialchars($invoice['checker_name']) ?></div>
                </div>
                <div class="signature-line">
                    <div class="line">Date & Stamp</div>
                    <div style="margin-top: 10px; font-size: 9pt;"><?= date('d-m-Y') ?></div>
                </div>
                <div class="signature-line">
                    <div class="line">Supplier Representative</div>
                </div>
            </div>
            
            <div class="footer">
                <div>© <?= date('Y') ?> ASB Fashion Group of Companies. All Rights Reserved.</div>
                <div>Developed & Maintained by <strong>VEXEL IT</strong> | Kavizz</div>
                <div>System Generated Report | QC Certified | Page 1 of 1</div>
            </div>
        </div>
        
        <script>
            window.onload = function() { window.print(); }
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Fetch Master Lookups for Filters
$suppliers = $pdo->query("SELECT supplier_id, supplier_name, system_id FROM suppliers ORDER BY supplier_name")->fetchAll();
$branches = $pdo->query("SELECT branch_id, branch_name, branch_code FROM branches ORDER BY branch_name")->fetchAll();
$floors = $pdo->query("SELECT floor_id, floor_name FROM floors ORDER BY floor_name")->fetchAll();
$reasons = $pdo->query("SELECT reason_id, reason_text FROM return_reasons ORDER BY reason_id")->fetchAll();
$username = $_SESSION['username'] ?? 'QC Manager';
$userRole = $_SESSION['role'] ?? 'Administrator';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASB Fashion | Invoice Management</title>
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

        /* Header */
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
        }
        .back-btn:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }
        .logout-btn {
            background: #f1f5f9;
            color: #475569;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        .logout-btn:hover {
            background: #e2e8f0;
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }

        /* Cards */
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
        }
        .card-body {
            padding: 24px;
        }

        /* Stats Cards */
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

        /* Filter Grid */
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .filter-group label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
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

        /* Buttons */
        .btn-primary {
            background: #dc2626;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
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
            padding: 10px 20px;
            border-radius: 10px;
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

        /* Table */
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

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-pass {
            background: #d1fae5;
            color: #065f46;
        }
        .status-fail {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
        }
        .modal-content {
            max-width: 1200px;
            width: 95%;
            margin: 20px auto;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.2);
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
        .modal-header h3 {
            font-size: 1.1rem;
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
            max-height: calc(100vh - 80px);
            overflow-y: auto;
        }

        /* Info Card */
        .info-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 12px;
            border-left: 3px solid #dc2626;
        }
        .info-card-label {
            font-size: 0.65rem;
            color: #64748b;
            text-transform: uppercase;
        }
        .info-card-value {
            font-weight: 700;
            margin-top: 4px;
            color: #1e293b;
        }

        /* Action Buttons */
        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px 10px;
            border-radius: 8px;
            transition: 0.2s;
            font-size: 0.9rem;
        }
        .action-btn:hover {
            background: #f3f4f6;
        }
        .text-blue { color: #3b82f6; }
        .text-green { color: #10b981; }

        /* Loading Spinner */
        .loading-spinner {
            display: inline-block;
            width: 24px;
            height: 24px;
            border: 2px solid #e2e8f0;
            border-top-color: #dc2626;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Toast */
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

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 40px;
            padding: 20px;
            color: #94a3b8;
            font-size: 0.7rem;
            border-top: 1px solid #e2e8f0;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        @media (max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr; }
            .filter-grid { grid-template-columns: 1fr; }
            .modal-content { width: 98%; margin: 10px auto; }
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-mono { font-family: monospace; }
        .hidden { display: none; }
        .flex { display: flex; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
    </style>
</head>
<body>

<div class="header">
    <div class="header-content">
        <div class="logo">
            <h1>ASB <span>FASHION</span></h1>
            <p>INVOICE MANAGEMENT</p>
        </div>
        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($username) ?></div>
                <div class="user-role"><?= htmlspecialchars($userRole) ?></div>
            </div>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</div>

<div class="container">
    <!-- FILTER SECTION -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-filter"></i> Advanced Invoice Filters</h2>
        </div>
        <div class="card-body">
            <div class="filter-grid">
                <div class="filter-group">
                    <label>🏢 Supplier</label>
                    <select id="filter_supplier" class="form-select">
                        <option value="">All Suppliers</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['supplier_id'] ?>"><?= htmlspecialchars($s['supplier_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>📍 Branch</label>
                    <select id="filter_branch" class="form-select">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['branch_id'] ?>"><?= htmlspecialchars($b['branch_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>🏢 Floor</label>
                    <select id="filter_floor" class="form-select">
                        <option value="">All Floors</option>
                        <?php foreach ($floors as $f): ?>
                            <option value="<?= $f['floor_id'] ?>"><?= htmlspecialchars($f['floor_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>📅 From Date</label>
                    <input type="date" id="filter_date_from" class="form-input">
                </div>
                <div class="filter-group">
                    <label>📅 To Date</label>
                    <input type="date" id="filter_date_to" class="form-input">
                </div>
                <div class="filter-group">
                    <label>🔢 Invoice Number</label>
                    <input type="text" id="filter_invoice_number" placeholder="Search invoice..." class="form-input">
                </div>
            </div>
            <div style="display: flex; gap: 12px; margin-top: 20px;">
                <button onclick="loadInvoices()" class="btn-primary"><i class="fas fa-search"></i> Search Invoices</button>
                <button onclick="resetFilters()" class="btn-secondary"><i class="fas fa-undo"></i> Reset Filters</button>
            </div>
        </div>
    </div>

    <!-- STATISTICS -->
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-title">📊 Total Invoices</div><div class="stat-value" id="totalInvoices">0</div></div>
        <div class="stat-card"><div class="stat-title">✅ Passed</div><div class="stat-value" id="totalPassed" style="color:#10b981;">0</div></div>
        <div class="stat-card"><div class="stat-title">❌ Failed</div><div class="stat-value" id="totalFailed" style="color:#dc2626;">0</div></div>
        <div class="stat-card"><div class="stat-title">🔄 Total Returns</div><div class="stat-value" id="totalReturns" style="color:#f59e0b;">0</div></div>
    </div>

    <!-- INVOICES LIST SECTION -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-file-invoice"></i> Invoice Records</h2>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Supplier</th>
                            <th>System ID</th>
                            <th>Branch</th>
                            <th>Floor</th>
                            <th>Date</th>
                            <th class="text-center">Items</th>
                            <th class="text-center">Received</th>
                            <th class="text-center">Defects</th>
                            <th class="text-center">Returns</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="invoicesTableBody">
                        <tr><td colspan="12" class="text-center" style="padding: 60px;"><div class="loading-spinner"></div><p class="mt-2 text-slate-500">Loading invoices...</p></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>© <?= date('Y') ?> ASB Fashion - Invoice Management System</p>
    </div>
</div>

<!-- VIEW/EDIT INVOICE MODAL -->
<div id="invoiceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-file-invoice"></i> Invoice Details</h3>
            <div style="display: flex; gap: 10px;">
                <button onclick="printFullReport()" class="btn-primary" style="background: #10b981; padding: 8px 16px;"><i class="fas fa-print"></i> Print Report</button>
                <button class="modal-close" onclick="closeInvoiceModal()">&times;</button>
            </div>
        </div>
        <div id="modalContent" class="modal-body"></div>
    </div>
</div>

<!-- EDIT ITEM MODAL -->
<div id="editItemModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Line Item</h3>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form id="editItemForm" class="modal-body">
            <input type="hidden" id="edit_invoice_item_id">
            <input type="hidden" id="edit_invoice_id">
            <div class="filter-group" style="margin-bottom: 16px;">
                <label>Item Name</label>
                <input type="text" id="edit_item_name" readonly class="form-input" style="background: #f3f4f6;">
            </div>
            <div class="grid-2">
                <div class="filter-group">
                    <label>Received Qty *</label>
                    <input type="number" id="edit_received_qty" required class="form-input">
                </div>
                <div class="filter-group">
                    <label>Sampled Qty *</label>
                    <input type="number" id="edit_checked_sample_qty" required class="form-input">
                </div>
            </div>
            <div class="grid-2">
                <div class="filter-group">
                    <label>Defect Qty</label>
                    <input type="number" id="edit_defect_qty" required class="form-input">
                </div>
                <div class="filter-group">
                    <label>Return Qty</label>
                    <input type="number" id="edit_return_qty" required class="form-input">
                </div>
            </div>
            <div class="filter-group" style="margin-bottom: 16px;">
                <label>Status</label>
                <select id="edit_status" class="form-select">
                    <option value="PASS">PASS</option>
                    <option value="FAIL">FAIL</option>
                </select>
            </div>
            <div id="editReturnReasonsBlock" style="display: none; background: #fef2f2; padding: 16px; border-radius: 12px; margin-bottom: 16px;">
                <label style="font-weight: bold; color: #dc2626; margin-bottom: 8px; display: block;">Return Reasons</label>
                <div id="editReturnReasonsList" style="margin-top: 8px; max-height: 150px; overflow-y: auto;"></div>
            </div>
            <div style="display: flex; gap: 12px; margin-top: 16px;">
                <button type="submit" class="btn-primary" style="flex: 1;">Update Item</button>
                <button type="button" onclick="closeEditModal()" class="btn-secondary" style="flex: 1;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    let currentInvoiceId = null;
    let currentReturnReasons = [];
    let allReturnReasons = [];
    
    // Load all return reasons
    async function loadReturnReasons() {
        try {
            const response = await fetch(window.location.pathname + '?action=get_return_reasons', { method: 'POST' });
            const result = await response.json();
            if (result.status === 'success') {
                allReturnReasons = result.data;
            }
        } catch(e) {
            console.error('Error loading return reasons:', e);
        }
    }
    
    // Load Invoices
    async function loadInvoices() {
        const tbody = document.getElementById('invoicesTableBody');
        tbody.innerHTML = '<tr><td colspan="12" class="text-center" style="padding: 60px;"><div class="loading-spinner"></div><p class="mt-2">Loading invoices...</p></td></tr>';
        
        const fd = new FormData();
        const supplier = document.getElementById('filter_supplier').value;
        const branch = document.getElementById('filter_branch').value;
        const floor = document.getElementById('filter_floor').value;
        const dateFrom = document.getElementById('filter_date_from').value;
        const dateTo = document.getElementById('filter_date_to').value;
        const invoiceNum = document.getElementById('filter_invoice_number').value;
        
        if (supplier) fd.append('supplier_id', supplier);
        if (branch) fd.append('branch_id', branch);
        if (floor) fd.append('floor_id', floor);
        if (dateFrom) fd.append('date_from', dateFrom);
        if (dateTo) fd.append('date_to', dateTo);
        if (invoiceNum) fd.append('invoice_number', invoiceNum);
        
        try {
            const response = await fetch(window.location.pathname + '?action=fetch_invoices', { method: 'POST', body: fd });
            const result = await response.json();
            
            if (result.status === 'error') throw new Error(result.message);
            
            const invoices = result.data || [];
            
            // Update stats
            const totalPassed = invoices.filter(i => i.failed_items_count === 0).length;
            const totalFailed = invoices.filter(i => i.failed_items_count > 0).length;
            const totalReturns = invoices.reduce((sum, i) => sum + (i.total_returns || 0), 0);
            
            document.getElementById('totalInvoices').innerText = invoices.length;
            document.getElementById('totalPassed').innerText = totalPassed;
            document.getElementById('totalFailed').innerText = totalFailed;
            document.getElementById('totalReturns').innerText = totalReturns.toLocaleString();
            
            if (invoices.length === 0) {
                tbody.innerHTML = '<tr><td colspan="12" class="text-center" style="padding: 60px; color: #9ca3af;">📁 No invoices found</td></tr>';
                return;
            }
            
            tbody.innerHTML = invoices.map(inv => `
                <tr onclick="viewInvoice(${inv.invoice_id})">
                    <td style="font-weight: 600;">${escapeHtml(inv.invoice_number)}</td>
                    <td><strong>${escapeHtml(inv.supplier_name)}</strong></td>
                    <td class="font-mono text-xs">${escapeHtml(inv.supplier_system_id || 'N/A')}</td>
                    <td>${escapeHtml(inv.branch_name || 'N/A')}</td>
                    <td>${escapeHtml(inv.floor_name || 'N/A')}</td>
                    <td>${inv.invoice_date}</td>
                    <td class="text-center">${inv.total_items || 0}</td>
                    <td class="text-center">${inv.total_received || 0}</td>
                    <td class="text-center" style="color:#dc2626;">${inv.total_defects || 0}</td>
                    <td class="text-center" style="color:#f59e0b;">${inv.total_returns || 0}</td>
                    <td class="text-center">
                        ${inv.failed_items_count > 0 ? 
                            '<span class="status-badge status-fail">⚠️ Failed</span>' : 
                            '<span class="status-badge status-pass">✓ All Passed</span>'}
                    </td>
                    <td class="text-center">
                        <button onclick="event.stopPropagation(); viewInvoice(${inv.invoice_id})" class="action-btn text-blue" title="View">👁️</button>
                        <button onclick="event.stopPropagation(); printFullReport(${inv.invoice_id})" class="action-btn text-green" title="Print">🖨️</button>
                    </td>
                </tr>
            `).join('');
        } catch(err) {
            console.error(err);
            tbody.innerHTML = `<tr><td colspan="12" class="text-center" style="padding: 60px; color: #dc2626;">⚠️ Error loading invoices: ${err.message}</td></tr>`;
            showToast('Error loading invoices: ' + err.message, 'error');
        }
    }
    
    // View Invoice Details
    async function viewInvoice(invoiceId) {
        currentInvoiceId = invoiceId;
        const fd = new FormData();
        fd.append('invoice_id', invoiceId);
        
        try {
            const response = await fetch(window.location.pathname + '?action=get_invoice_details', { method: 'POST', body: fd });
            const result = await response.json();
            
            if (result.status === 'error') throw new Error(result.message);
            
            const inv = result.invoice;
            const items = result.items;
            const failedItems = items.filter(i => i.status === 'FAIL');
            const passedItems = items.filter(i => i.status === 'PASS');
            
            const modalContent = document.getElementById('modalContent');
            modalContent.innerHTML = `
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
                    <div class="info-card"><div class="info-card-label">Invoice Number</div><div class="info-card-value">${escapeHtml(inv.invoice_number)}</div></div>
                    <div class="info-card"><div class="info-card-label">Invoice Date</div><div class="info-card-value">${inv.invoice_date}</div></div>
                    <div class="info-card"><div class="info-card-label">QC Date</div><div class="info-card-value">${inv.checked_date}</div></div>
                    <div class="info-card"><div class="info-card-label">Inspector</div><div class="info-card-value">${escapeHtml(inv.checker_name)}</div></div>
                    <div class="info-card"><div class="info-card-label">Supplier</div><div class="info-card-value"><strong>${escapeHtml(inv.supplier_name)}</strong></div></div>
                    <div class="info-card"><div class="info-card-label">System ID</div><div class="info-card-value">${escapeHtml(inv.supplier_system_id || 'N/A')}</div></div>
                    <div class="info-card"><div class="info-card-label">Branch</div><div class="info-card-value">${escapeHtml(inv.branch_name || 'Not Assigned')}</div></div>
                    <div class="info-card"><div class="info-card-label">Floor</div><div class="info-card-value">${escapeHtml(inv.floor_name || 'N/A')}</div></div>
                </div>
                
                ${failedItems.length > 0 ? `
                <div style="margin-bottom: 24px;">
                    <h4 style="font-size: 1rem; font-weight: bold; color: #dc2626; margin-bottom: 12px;">❌ FAILED ITEMS (${failedItems.length})</h4>
                    <div class="table-wrapper">
                        <table class="data-table" style="width: 100%;">
                            <thead><tr><th>Item</th><th>Code</th><th class="text-right">Received</th><th class="text-right">Sampled</th><th class="text-right">Defect</th><th class="text-right">Return</th><th class="text-center">Actions</th></tr></thead>
                            <tbody>
                                ${failedItems.map(item => `
                                    <tr style="background: #fef2f2;">
                                        <td><strong>${escapeHtml(item.item_name)}</strong><br><small class="text-slate-500">${escapeHtml(item.item_code)}</small></td>
                                        <td>${escapeHtml(item.item_code)}</td>
                                        <td class="text-right">${item.received_qty}</td>
                                        <td class="text-right">${item.checked_sample_qty}</td>
                                        <td class="text-right" style="color:#dc2626;">${item.defect_qty}</td>
                                        <td class="text-right" style="color:#f59e0b;">${item.return_qty}</td>
                                        <td class="text-center"><button onclick='openEditModal(${JSON.stringify(item)})' class="btn-secondary" style="padding: 4px 12px; font-size: 0.7rem;">✏️ Edit</button></td>
                                    </tr>
                                    ${item.reasons && item.reasons.length > 0 ? `
                                    <tr style="background: #fef2f2;">
                                        <td colspan="7" style="padding: 6px 12px; font-size: 0.7rem; color: #dc2626;">
                                            📋 Return Reasons: ${item.reasons.map(r => `${r.reason_text} (${r.return_qty})`).join(', ')}
                                        </td>
                                    </tr>
                                    ` : ''}
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
                ` : ''}
                
                ${passedItems.length > 0 ? `
                <div style="margin-bottom: 24px;">
                    <h4 style="font-size: 1rem; font-weight: bold; color: #10b981; margin-bottom: 12px;">✅ PASSED ITEMS (${passedItems.length})</h4>
                    <div class="table-wrapper">
                        <table class="data-table" style="width: 100%;">
                            <thead><tr><th>Item</th><th>Code</th><th class="text-right">Received</th><th class="text-right">Sampled</th><th class="text-right">Defect</th><th class="text-right">Return</th><th class="text-center">Actions</th></tr></thead>
                            <tbody>
                                ${passedItems.map(item => `
                                    <tr>
                                        <td><strong>${escapeHtml(item.item_name)}</strong><br><small class="text-slate-500">${escapeHtml(item.item_code)}</small></td>
                                        <td>${escapeHtml(item.item_code)}</td>
                                        <td class="text-right">${item.received_qty}</td>
                                        <td class="text-right">${item.checked_sample_qty}</td>
                                        <td class="text-right" style="color:#dc2626;">${item.defect_qty}</td>
                                        <td class="text-right" style="color:#f59e0b;">${item.return_qty}</td>
                                        <td class="text-center"><button onclick='openEditModal(${JSON.stringify(item)})' class="btn-secondary" style="padding: 4px 12px; font-size: 0.7rem;">✏️ Edit</button></td>
                                    </tr>
                                    ${item.reasons && item.reasons.length > 0 ? `
                                    <tr style="background: #ecfdf5;">
                                        <td colspan="7" style="padding: 6px 12px; font-size: 0.7rem; color: #059669;">
                                            📋 Return Reasons: ${item.reasons.map(r => `${r.reason_text} (${r.return_qty})`).join(', ')}
                                        </td>
                                    </tr>
                                    ` : ''}
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
                ` : ''}
                
                <div style="display: flex; justify-content: flex-end; gap: 12px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                    <button onclick="editInvoiceHeader()" class="btn-primary" style="background: #3b82f6;"><i class="fas fa-edit"></i> Edit Header</button>
                </div>
            `;
            document.getElementById('invoiceModal').style.display = 'block';
        } catch(err) {
            showToast('Error loading invoice details: ' + err.message, 'error');
        }
    }
    
    // Open Edit Modal
    function openEditModal(item) {
        document.getElementById('edit_invoice_item_id').value = item.invoice_item_id;
        document.getElementById('edit_invoice_id').value = currentInvoiceId;
        document.getElementById('edit_item_name').value = item.item_name;
        document.getElementById('edit_received_qty').value = item.received_qty;
        document.getElementById('edit_checked_sample_qty').value = item.checked_sample_qty;
        document.getElementById('edit_defect_qty').value = item.defect_qty;
        document.getElementById('edit_return_qty').value = item.return_qty;
        document.getElementById('edit_status').value = item.status;
        
        currentReturnReasons = item.reasons || [];
        
        if (item.return_qty > 0 && allReturnReasons.length > 0) {
            document.getElementById('editReturnReasonsBlock').style.display = 'block';
            const reasonsDiv = document.getElementById('editReturnReasonsList');
            reasonsDiv.innerHTML = '';
            allReturnReasons.forEach(reason => {
                const existingReason = currentReturnReasons.find(r => r.reason_id == reason.reason_id);
                const qty = existingReason ? existingReason.return_qty : 0;
                reasonsDiv.innerHTML += `
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <label style="font-size: 0.8rem;">
                            <input type="checkbox" class="edit-reason-check" value="${reason.reason_id}" ${qty > 0 ? 'checked' : ''}>
                            ${escapeHtml(reason.reason_text)}
                        </label>
                        <input type="number" placeholder="Qty" class="edit-reason-qty" style="width: 80px; padding: 6px; border: 1px solid #e2e8f0; border-radius: 8px;" ${qty > 0 ? '' : 'disabled'} value="${qty > 0 ? qty : ''}">
                    </div>
                `;
            });
            
            document.querySelectorAll('.edit-reason-check').forEach(cb => {
                cb.addEventListener('change', function() {
                    const qtyInput = this.closest('div').querySelector('.edit-reason-qty');
                    if (this.checked) {
                        qtyInput.removeAttribute('disabled');
                        qtyInput.value = '1';
                    } else {
                        qtyInput.setAttribute('disabled', 'true');
                        qtyInput.value = '';
                    }
                });
            });
        } else {
            document.getElementById('editReturnReasonsBlock').style.display = 'none';
        }
        
        document.getElementById('editItemModal').style.display = 'block';
    }
    
    // Edit Item Form Submit
    document.getElementById('editItemForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const invoice_item_id = document.getElementById('edit_invoice_item_id').value;
        const received_qty = parseInt(document.getElementById('edit_received_qty').value);
        const checked_sample_qty = parseInt(document.getElementById('edit_checked_sample_qty').value);
        const defect_qty = parseInt(document.getElementById('edit_defect_qty').value);
        const return_qty = parseInt(document.getElementById('edit_return_qty').value);
        const status = document.getElementById('edit_status').value;
        
        if (checked_sample_qty > received_qty) {
            showToast('Sample quantity cannot exceed received quantity', 'error');
            return;
        }
        if (defect_qty > checked_sample_qty) {
            showToast('Defect quantity cannot exceed sample quantity', 'error');
            return;
        }
        if (status === 'FAIL' && return_qty !== received_qty) {
            showToast('For FAIL status, return quantity must equal received quantity', 'error');
            return;
        }
        
        const fd = new FormData();
        fd.append('invoice_item_id', invoice_item_id);
        fd.append('received_qty', received_qty);
        fd.append('checked_sample_qty', checked_sample_qty);
        fd.append('defect_qty', defect_qty);
        fd.append('return_qty', return_qty);
        fd.append('status', status);
        
        try {
            const response = await fetch(window.location.pathname + '?action=update_invoice_item', { method: 'POST', body: fd });
            const result = await response.json();
            
            if (result.status === 'success') {
                if (return_qty > 0) {
                    const reasons = {};
                    document.querySelectorAll('.edit-reason-check:checked').forEach(cb => {
                        const qty = parseInt(cb.closest('div').querySelector('.edit-reason-qty').value) || 0;
                        if (qty > 0) reasons[cb.value] = qty;
                    });
                    
                    const totalReasonQty = Object.values(reasons).reduce((a, b) => a + b, 0);
                    if (totalReasonQty !== return_qty) {
                        showToast(`Return reason quantities (${totalReasonQty}) must equal return quantity (${return_qty})`, 'error');
                        return;
                    }
                    
                    const fdReasons = new FormData();
                    fdReasons.append('invoice_item_id', invoice_item_id);
                    fdReasons.append('return_qty', return_qty);
                    for (const [reason_id, qty] of Object.entries(reasons)) {
                        fdReasons.append(`reasons[${reason_id}]`, qty);
                    }
                    await fetch(window.location.pathname + '?action=update_return_reasons', { method: 'POST', body: fdReasons });
                }
                
                showToast('Item updated successfully', 'success');
                closeEditModal();
                viewInvoice(currentInvoiceId);
                loadInvoices();
            } else {
                showToast('Error: ' + result.message, 'error');
            }
        } catch(err) {
            showToast('Error updating item: ' + err.message, 'error');
        }
    });
    
    // Edit Invoice Header
    function editInvoiceHeader() {
        const modalContent = document.getElementById('modalContent');
        modalContent.innerHTML = `
            <form id="editHeaderForm" style="display: flex; flex-direction: column; gap: 16px;">
                <h4 style="font-size: 1rem; font-weight: bold;">✏️ Edit Invoice Header</h4>
                <div class="grid-2">
                    <div class="filter-group"><label>Invoice Number</label><input type="text" name="invoice_number" id="edit_invoice_number" required class="form-input"></div>
                    <div class="filter-group"><label>Invoice Date</label><input type="date" name="invoice_date" id="edit_invoice_date" required class="form-input"></div>
                    <div class="filter-group"><label>QC Date</label><input type="date" name="checked_date" id="edit_checked_date" required class="form-input"></div>
                    <div class="filter-group"><label>Inspector Name</label><input type="text" name="checker_name" id="edit_checker_name" required class="form-input"></div>
                    <div class="filter-group"><label>Branch</label><select name="branch_id" id="edit_branch_id" class="form-select"><option value="">-- None --</option><?php foreach ($branches as $b): ?><option value="<?= $b['branch_id'] ?>"><?= htmlspecialchars($b['branch_name']) ?></option><?php endforeach; ?></select></div>
                    <div class="filter-group"><label>Floor</label><select name="floor_id" id="edit_floor_id" required class="form-select"><option value="">-- Select --</option><?php foreach ($floors as $f): ?><option value="<?= $f['floor_id'] ?>"><?= htmlspecialchars($f['floor_name']) ?></option><?php endforeach; ?></select></div>
                </div>
                <div style="display: flex; gap: 12px;"><button type="submit" class="btn-primary">Save Changes</button><button type="button" onclick="viewInvoice(${currentInvoiceId})" class="btn-secondary">Cancel</button></div>
            </form>
        `;
        
        document.getElementById('editHeaderForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const fd = new FormData();
            fd.append('invoice_id', currentInvoiceId);
            fd.append('invoice_number', document.getElementById('edit_invoice_number').value);
            fd.append('invoice_date', document.getElementById('edit_invoice_date').value);
            fd.append('checked_date', document.getElementById('edit_checked_date').value);
            fd.append('checker_name', document.getElementById('edit_checker_name').value);
            fd.append('branch_id', document.getElementById('edit_branch_id').value);
            fd.append('floor_id', document.getElementById('edit_floor_id').value);
            
            try {
                const response = await fetch(window.location.pathname + '?action=update_invoice_header', { method: 'POST', body: fd });
                const result = await response.json();
                if (result.status === 'success') {
                    showToast('Header updated successfully', 'success');
                    viewInvoice(currentInvoiceId);
                    loadInvoices();
                } else {
                    showToast('Error: ' + result.message, 'error');
                }
            } catch(err) {
                showToast('Error updating header: ' + err.message, 'error');
            }
        });
    }
    
    // Print Full Report
    function printFullReport(invoiceId = null) {
        const id = invoiceId || currentInvoiceId;
        if (id) {
            window.open(window.location.pathname + '?action=print_full_report&invoice_id=' + id, '_blank');
        }
    }
    
    // Reset Filters
    function resetFilters() {
        document.getElementById('filter_supplier').value = '';
        document.getElementById('filter_branch').value = '';
        document.getElementById('filter_floor').value = '';
        document.getElementById('filter_date_from').value = '';
        document.getElementById('filter_date_to').value = '';
        document.getElementById('filter_invoice_number').value = '';
        loadInvoices();
    }
    
    // Close Modals
    function closeInvoiceModal() { document.getElementById('invoiceModal').style.display = 'none'; }
    function closeEditModal() { document.getElementById('editItemModal').style.display = 'none'; }
    
    // Toast Notification
    function showToast(msg, type) {
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.style.backgroundColor = type === 'success' ? '#10b981' : '#dc2626';
        toast.innerHTML = `${type === 'success' ? '✓' : '⚠️'} ${msg}`;
        document.body.appendChild(toast);
        toast.style.display = 'block';
        setTimeout(() => toast.remove(), 3000);
    }
    
    // Escape HTML
    function escapeHtml(str) { if (!str) return ''; return String(str).replace(/[&<>]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[m])); }
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        loadReturnReasons();
        loadInvoices();
    });
</script>
</body>
</html>