<?php
// ==========================================
// QC RETURN REPORT - ASB FASHION GROUP
// Developed By Vexel IT | Kavizz
// Belongs To ASB Group of Companies
// ==========================================
session_start();

require_once 'config/database.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$page_title = 'QC Return Report';

// Check if user has access to this page
$allowed_tabs = getUserTabs($pdo);
$has_access = false;
foreach ($allowed_tabs as $tab) {
    if ($tab['tab_url'] == 'qc_return_report.php') {
        $has_access = true;
        break;
    }
}
if (!$has_access && $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

// Get filter parameters
$record_id = $_GET['record_id'] ?? '';
$reference_number = $_GET['reference_number'] ?? '';
$invoice_number = $_GET['invoice_number'] ?? '';
$supplier_id = $_GET['supplier_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$doc_number = $_GET['doc_number'] ?? '';

// Build query
$where = [];
$params = [];

if (!empty($record_id)) {
    $where[] = "dm.record_id = ?";
    $params[] = $record_id;
}
if (!empty($reference_number)) {
    $where[] = "dm.reference_number LIKE ?";
    $params[] = "%$reference_number%";
}
if (!empty($invoice_number)) {
    $where[] = "dm.invoice_number LIKE ?";
    $params[] = "%$invoice_number%";
}
if (!empty($supplier_id)) {
    $where[] = "dm.supplier_id = ?";
    $params[] = $supplier_id;
}
if (!empty($doc_number)) {
    $where[] = "dm.doc_number LIKE ?";
    $params[] = "%$doc_number%";
}
if (!empty($date_from)) {
    $where[] = "DATE(dm.record_date) >= ?";
    $params[] = $date_from;
}
if (!empty($date_to)) {
    $where[] = "DATE(dm.record_date) <= ?";
    $params[] = $date_to;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Fetch main records
$sql = "
    SELECT dm.*, 
           s.supplier_name, s.system_id as supplier_code, s.contact_number, s.email, s.address,
           m.mode_name,
           rr.reason_text,
           (SELECT COUNT(*) FROM qc_damage_items WHERE record_id = dm.record_id) as total_items,
           (SELECT SUM(quantity) FROM qc_damage_items WHERE record_id = dm.record_id) as total_quantity,
           (SELECT SUM(total_cost) FROM qc_damage_items WHERE record_id = dm.record_id) as total_cost,
           (SELECT COUNT(*) FROM qc_item_images WHERE record_id = dm.record_id) as total_images
    FROM qc_damage_main dm
    LEFT JOIN suppliers s ON dm.supplier_id = s.supplier_id
    LEFT JOIN qc_modes m ON dm.mode_id = m.mode_id
    LEFT JOIN return_reasons rr ON dm.reason_id = rr.reason_id
    $whereClause
    ORDER BY dm.record_id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$returns = $stmt->fetchAll();

// Get all suppliers for filter dropdown
$suppliers = $pdo->query("SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name")->fetchAll();

// Calculate totals for summary
$total_records = count($returns);
$total_items_sum = array_sum(array_column($returns, 'total_items'));
$total_quantity_sum = array_sum(array_column($returns, 'total_quantity'));
$total_cost_sum = array_sum(array_column($returns, 'total_cost'));

$username = $_SESSION['username'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'User';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASB Fashion | QC Return Report </title>
    <link rel="icon" type="image/png" href="logo.png">
    <style>
        /* ============================================
           COMPLETE DESIGN - ASB FASHION GROUP
           ============================================ */
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

        /* Header */
        .header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 100;
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

        /* Main Container */
        .qc-report-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
            flex: 1;
            width: 100%;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
            width: 100%;
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
            font-size: 1.8rem;
            flex-shrink: 0;
        }

        .stat-info h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin: 0;
            line-height: 1.2;
        }

        .stat-info p {
            color: #6b7280;
            font-size: 0.75rem;
            margin: 0.25rem 0 0;
            font-weight: 500;
        }

        /* Form Card */
        .form-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
            width: 100%;
        }

        .form-card h2 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #f3f4f6;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            width: 100%;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
        }

        .filter-group input,
        .filter-group select {
            padding: 0.7rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 0.85rem;
            font-family: inherit;
            transition: all 0.2s;
            background: #f9fafb;
            width: 100%;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #dc2626;
            background: white;
            box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
        }

        /* Buttons */
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
            white-space: nowrap;
        }

        .btn-primary { background: #dc2626; color: white; }
        .btn-primary:hover { background: #b91c1c; transform: translateY(-1px); }
        .btn-secondary { background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; }
        .btn-secondary:hover { background: #e5e7eb; }
        .btn-export { background: #3b82f6; color: white; }
        .btn-export:hover { background: #2563eb; }

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
            width: 100%;
            overflow-x: auto;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 1.5rem;
        }

        .table-header h2 {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #1e293b;
        }

        .table-badge {
            font-size: 0.7rem;
            font-weight: 500;
            background: #f3f4f6;
            padding: 3px 12px;
            border-radius: 30px;
            color: #6b7280;
        }

        /* Data Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }

        .data-table thead {
            background: #f8fafc;
            border-bottom: 2px solid #e5e7eb;
        }

        .data-table th {
            text-align: left;
            padding: 1rem;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.8rem;
            vertical-align: middle;
        }

        .data-table tr:hover {
            background: #fef2f2;
            cursor: pointer;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.25rem 0.75rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .status-completed { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fed7aa; color: #92400e; }

        .type-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            background: #eff6ff;
            color: #1e3a8a;
        }

        .view-details-btn {
            background: none;
            border: none;
            font-weight: 600;
            color: #dc2626;
            font-size: 0.7rem;
            cursor: pointer;
            padding: 0.4rem 0.8rem;
            border-radius: 10px;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .view-details-btn:hover {
            background: #fef2f2;
        }

        .action-buttons-cell {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .print-record-btn {
            padding: 4px 8px;
            font-size: 0.65rem;
            border-radius: 8px;
            cursor: pointer;
            border: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .print-finance-btn {
            background: #8b5cf6;
            color: white;
        }
        .print-finance-btn:hover { background: #7c3aed; }

        .print-supplier-btn {
            background: #f59e0b;
            color: white;
        }
        .print-supplier-btn:hover { background: #d97706; }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            border-radius: 24px;
            max-width: 900px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 25px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            padding: 1.25rem 1.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 24px 24px 0 0;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .modal-header h3 {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
        }

        .modal-close {
            font-size: 1.8rem;
            cursor: pointer;
            background: rgba(255,255,255,0.1);
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }

        .modal-close:hover {
            background: #dc2626;
        }

        .modal-body {
            padding: 1.75rem;
        }

        .info-box {
            background: #f9fafb;
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid #dc2626;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.75rem;
        }

        .info-label {
            font-size: 0.65rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .info-value {
            font-weight: 700;
            margin-top: 0.25rem;
            color: #1f2937;
            word-break: break-word;
        }

        .spinner {
            text-align: center;
            padding: 2.5rem;
        }

        .spinner span {
            font-size: 2rem;
            display: inline-block;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Footer Stats */
        .footer-stats {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 20px;
            padding: 1.5rem;
            color: white;
            margin-top: 2rem;
            width: 100%;
        }

        .footer-stats-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .footer-stats-title h3 {
            color: white;
            margin-bottom: 5px;
            font-size: 1.1rem;
        }

        .footer-stats-title p {
            opacity: 0.85;
            font-size: 0.7rem;
        }

        .footer-stats-numbers {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }

        .footer-stat-item {
            text-align: center;
        }

        .footer-stat-number {
            font-size: 1.6rem;
            font-weight: 700;
        }

        .footer-stat-label {
            font-size: 0.65rem;
            opacity: 0.8;
        }

        .progress-container {
            margin-top: 1rem;
        }

        .progress-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.7rem;
            margin-bottom: 0.5rem;
            opacity: 0.85;
        }

        .progress-bar-bg {
            height: 8px;
            background: rgba(255,255,255,0.25);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            background: #10b981;
            border-radius: 4px;
            transition: width 0.5s ease;
        }

        .empty-state {
            text-align: center;
            padding: 3rem !important;
            color: #9ca3af;
        }

        .empty-state span {
            font-size: 3rem;
            display: block;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Page Footer */
        .page-footer {
            margin-top: 2rem;
            background: #f8fafc;
            border-radius: 20px;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            border: 1px solid #e5e7eb;
            font-size: 0.7rem;
            color: #64748b;
            width: 100%;
        }

        /* Main Footer */
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
        .footer-developer {
            text-align: right;
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

        /* Responsive */
        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 1rem; }
            .filter-grid { grid-template-columns: repeat(2, 1fr); }
            .footer-stats-inner { flex-direction: column; text-align: center; }
            .footer-stats-numbers { justify-content: center; }
        }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; gap: 1rem; }
            .filter-grid { grid-template-columns: 1fr; }
            .table-header { flex-direction: column; align-items: flex-start; }
            .page-footer { flex-direction: column; text-align: center; }
            .btn { padding: 0.5rem 1rem; font-size: 0.7rem; }
            .form-card, .table-card { padding: 1rem; }
            .stat-card { padding: 1rem; }
            .stat-icon { width: 45px; height: 45px; font-size: 1.2rem; }
            .stat-info h3 { font-size: 1.2rem; }
            .action-buttons-cell { flex-direction: column; }
            .header-content { flex-direction: column; text-align: center; }
            .user-info { text-align: center; }
        }

        @media print {
            .header, .form-card, .view-details-btn, .modal, .page-footer, 
            .btn-export, .btn-primary, .btn-secondary, .main-footer,
            .action-buttons-cell .print-record-btn { display: none !important; }
            
            .table-card { box-shadow: none; border: 1px solid #ddd; padding: 0; margin: 0; overflow: visible; }
            .data-table { min-width: 100%; }
            .data-table th { background: #e5e7eb !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px; }
            .footer-stats { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<!-- Header Section -->
<div class="header">
    <div class="header-content">
        <div class="logo">
            <h1>ASB <span>FASHION</span></h1>
            <p>QUALITY CONTROL SYSTEM</p>
        </div>
        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="dashboard.php" class="back-btn">
                ← Dashboard
            </a>
            <div class="user-info">
                <div class="user-name">👤 <?= htmlspecialchars($username) ?></div>
                <div class="user-role">QC Report</div>
            </div>
        </div>
    </div>
</div>

<div class="qc-report-container">
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef2f2; color: #dc2626;">📋</div>
            <div class="stat-info">
                <h3><?= number_format($total_records) ?></h3>
                <p>Total Claims</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #eff6ff; color: #3b82f6;">📦</div>
            <div class="stat-info">
                <h3><?= number_format($total_items_sum) ?></h3>
                <p>Items Returned</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #ecfdf5; color: #10b981;">📊</div>
            <div class="stat-info">
                <h3><?= number_format($total_quantity_sum) ?></h3>
                <p>Total Quantity</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fffbeb; color: #f59e0b;">💰</div>
            <div class="stat-info">
                <h3><?= number_format($total_cost_sum, 2) ?></h3>
                <p>Claim Value (LKR)</p>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="form-card">
        <h2>⚙️ Filter Return Records</h2>
        <form method="GET" action="">
            <div class="filter-grid">
                <div class="filter-group">
                    <label># Return ID</label>
                    <input type="text" name="record_id" placeholder="Enter ID" value="<?= htmlspecialchars($record_id) ?>">
                </div>
                <div class="filter-group">
                    <label>📦 Reference No</label>
                    <input type="text" name="reference_number" placeholder="Reference number" value="<?= htmlspecialchars($reference_number) ?>">
                </div>
                <div class="filter-group">
                    <label>📄 Invoice No</label>
                    <input type="text" name="invoice_number" placeholder="Invoice number" value="<?= htmlspecialchars($invoice_number) ?>">
                </div>
                <div class="filter-group">
                    <label>🏢 Supplier</label>
                    <select name="supplier_id">
                        <option value="">All Suppliers</option>
                        <?php foreach ($suppliers as $sup): ?>
                            <option value="<?= $sup['supplier_id'] ?>" <?= $supplier_id == $sup['supplier_id'] ? 'selected' : '' ?>><?= htmlspecialchars($sup['supplier_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>🏷️ Document No</label>
                    <input type="text" name="doc_number" placeholder="Document number" value="<?= htmlspecialchars($doc_number) ?>">
                </div>
                <div class="filter-group">
                    <label>📅 From Date</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="filter-group">
                    <label>📅 To Date</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button type="submit" class="btn btn-primary">🔍 Search</button>
                        <a href="qc_return_report.php" class="btn btn-secondary">↩️ Reset</a>
                        <button type="button" onclick="exportToCSV()" class="btn btn-export">📊 Export CSV</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Data Table -->
    <div class="table-card">
        <div class="table-header">
            <h2>📋 Return Records List <span class="table-badge">Total: <?php echo count($returns); ?></span></h2>
        </div>
        
        <div style="overflow-x: auto; width: 100%;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Reference</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Invoice/Doc</th>
                        <th>Type/Reason</th>
                        <th>Items/Qty</th>
                        <th>Value (LKR)</th>
                        <th>Status</th>
                        <th style="min-width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($returns) > 0): ?>
                        <?php foreach ($returns as $return): ?>
                        <tr onclick="viewDetails(<?= $return['record_id'] ?>)">
                            <td style="font-weight: 700; color: #dc2626;">#<?= $return['record_id'] ?></td>
                            <td><span style="font-family: monospace; font-weight: 600;"><?= htmlspecialchars($return['reference_number']) ?></span></td>
                            <td><?= date('Y-m-d', strtotime($return['record_date'])) ?></td>
                            <td><strong><?= htmlspecialchars($return['supplier_name'] ?? 'N/A') ?></strong></td>
                            <td>
                                <div>📄 <?= htmlspecialchars($return['invoice_number'] ?? '-') ?></div>
                                <div>🏷️ <?= htmlspecialchars($return['doc_number'] ?: '—') ?></div>
                            </div>
                            <td>
                                <span class="type-badge"><?= htmlspecialchars($return['mode_name'] ?? '—') ?></span>
                                <div style="font-size: 0.7rem; color: #6b7280; margin-top: 0.25rem;"><?= htmlspecialchars(substr($return['reason_text'] ?? '', 0, 30)) ?></div>
                            </div>
                            <td style="text-align: center;"><?= $return['total_items'] ?> items<br><small><?= $return['total_quantity'] ?> pcs</small></td>
                            <td style="font-weight: 700; color: #dc2626; text-align: right;"><?= number_format($return['total_cost'] ?? 0, 2) ?></td>
                            <td>
                                <div class="status-badge <?= $return['is_handover_complete'] ? 'status-completed' : 'status-pending' ?>">
                                    <?= $return['is_handover_complete'] ? '✓ Settled' : '⏰ Pending' ?>
                                </div>
                            </div>
                            <td class="action-buttons-cell" onclick="event.stopPropagation()">
                                <button onclick="viewDetails(<?= $return['record_id'] ?>)" class="view-details-btn" style="background:#e8e8e8;">
                                    📊 View
                                </button>
                                <button onclick="printFinanceRecord(<?= $return['record_id'] ?>)" class="print-record-btn print-finance-btn">
                                    🏦 Finance
                                </button>
                                <button onclick="printSupplierRecord(<?= $return['record_id'] ?>)" class="print-record-btn print-supplier-btn">
                                    📦 Supplier
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="empty-state">
                                <span>📁</span>
                                <p>No damage/shortage records found</p>
                                <?php if ($record_id || $reference_number || $invoice_number || $supplier_id || $doc_number || $date_from || $date_to): ?>
                                    <a href="qc_return_report.php" class="btn btn-primary" style="margin-top: 10px;">Clear Filters</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Footer Statistics -->
    <div class="footer-stats">
        <div class="footer-stats-inner">
            <div class="footer-stats-title">
                <h3>📈 Report Summary</h3>
                <p>Quality Control Return Analysis | ASB Fashion Group</p>
            </div>
            <div class="footer-stats-numbers">
                <div class="footer-stat-item">
                    <div class="footer-stat-number"><?= number_format($total_records) ?></div>
                    <div class="footer-stat-label">Total Claims</div>
                </div>
                <div class="footer-stat-item">
                    <div class="footer-stat-number"><?= number_format($total_items_sum) ?></div>
                    <div class="footer-stat-label">Items Returned</div>
                </div>
                <div class="footer-stat-item">
                    <div class="footer-stat-number"><?= number_format($total_quantity_sum) ?></div>
                    <div class="footer-stat-label">Total Quantity</div>
                </div>
                <div class="footer-stat-item">
                    <div class="footer-stat-number"><?= number_format($total_cost_sum, 2) ?></div>
                    <div class="footer-stat-label">Total Value (LKR)</div>
                </div>
            </div>
        </div>
        
        <div class="progress-container">
            <div class="progress-labels">
                <span>✓ Settled Claims</span>
                <span>⏰ Pending Claims</span>
            </div>
            <div class="progress-bar-bg">
                <?php 
                $settled_count = count(array_filter($returns, function($r) { return $r['is_handover_complete']; }));
                $settled_percent = $total_records > 0 ? ($settled_count / $total_records) * 100 : 0;
                ?>
                <div class="progress-bar-fill" style="width: <?= $settled_percent ?>%;"></div>
            </div>
            <div style="text-align: center; margin-top: 10px; font-size: 0.65rem; opacity: 0.7;">
                Settlement Rate: <?= round($settled_percent, 1) ?>% (<?= $settled_count ?> of <?= $total_records ?> claims)
            </div>
        </div>
    </div>

    <!-- Page Footer -->
    <div class="page-footer">
        <div>📅 Generated: <?= date('Y-m-d H:i:s') ?></div>
        <div>🏢 ASB Fashion Group of Companies</div>
        <div>💻 Developed & Maintained by Vexel IT | Kavizz</div>
    </div>
</div>

<!-- Main Footer -->
<footer class="main-footer">
    <div class="footer-content">
        <div>© <?= date('Y') ?> ASB Fashion - Quality Control System</div>
        <div class="footer-developer">
            <p class="developer-name">Vexel IT</p>
            <p class="developer-company">Main Developer & Technical Partner</p>
        </div>
    </div>
</footer>

<!-- Modal for Valuation Details -->
<div id="detailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📊 Damage & Shortage Valuation Statement</h3>
            <span class="modal-close" onclick="closeModal()">&times;</span>
        </div>
        <div id="modalBody" class="modal-body">
            <div class="spinner"><span>⚙️</span><p>Loading valuation details...</p></div>
        </div>
        <div style="padding: 1rem 1.75rem; border-top: 1px solid #eef2f8; display: flex; justify-content: flex-end; gap: 0.75rem;">
            <button onclick="closeModal()" class="btn btn-secondary">Close</button>
            <button onclick="printCurrentModal()" class="btn btn-primary">🖨️ Print Statement</button>
        </div>
    </div>
</div>

<script>
    let currentReturnData = null;
    
    async function fetchReturnDetails(recordId) {
        const resp = await fetch(`ajax_get_return_details.php?record_id=${recordId}`);
        const data = await resp.json();
        if(data.status === 'success') {
            return data;
        }
        throw new Error(data.message || 'Failed to load data');
    }
    
    async function viewDetails(recordId) {
        const modal = document.getElementById('detailsModal');
        const modalBody = document.getElementById('modalBody');
        modal.style.display = 'flex';
        modalBody.innerHTML = '<div class="spinner"><span>⚙️</span><p>Loading valuation details...</p></div>';
        
        try {
            const data = await fetchReturnDetails(recordId);
            currentReturnData = data;
            
            let totalValue = 0;
            const itemsHtml = data.items.map((item, index) => {
                const itemTotal = item.quantity * item.unit_cost;
                totalValue += itemTotal;
                return `
                    <tr style="${index % 2 === 0 ? 'background: #fff;' : 'background: #f9f9f9;'}">
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: left;">${escapeHtml(item.item_code)}</td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: left;">${escapeHtml(item.item_name)}</td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${item.quantity}</td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: right;">${parseFloat(item.unit_cost).toFixed(2)}</td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: right; font-weight: 600;">${itemTotal.toFixed(2)}</td>
                    </tr>
                `;
            }).join('');
            
            modalBody.innerHTML = `
                <div class="info-box">
                    <div class="info-grid">
                        <div><div class="info-label">CLAIM ID</div><div class="info-value">#${data.main.record_id}</div></div>
                        <div><div class="info-label">REFERENCE NUMBER</div><div class="info-value" style="font-family: monospace;">${escapeHtml(data.main.reference_number)}</div></div>
                        <div><div class="info-label">RECORD DATE</div><div class="info-value">${data.main.record_date}</div></div>
                        <div><div class="info-label">INVOICE NUMBER</div><div class="info-value">${escapeHtml(data.main.invoice_number)}</div></div>
                        <div><div class="info-label">SUPPLIER</div><div class="info-value"><strong>${escapeHtml(data.main.supplier_name)}</strong></div></div>
                        <div><div class="info-label">DAMAGE TYPE / REASON</div><div class="info-value">${escapeHtml(data.main.mode_name)} — ${escapeHtml(data.main.reason_text)}</div></div>
                    </div>
                </div>
                
                <h4 style="margin: 20px 0 12px 0; font-size: 12pt; font-weight: bold; border-bottom: 2px solid #ddd; padding-bottom: 5px;">📦 ITEMIZED VALUATION BREAKDOWN</h4>
                
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
                        <thead>
                            <tr>
                                <th style="background: #e8e8e8; padding: 10px; border: 1px solid #999; text-align: center; font-weight: bold;">Item Code</th>
                                <th style="background: #e8e8e8; padding: 10px; border: 1px solid #999; text-align: center; font-weight: bold;">Item Name</th>
                                <th style="background: #e8e8e8; padding: 10px; border: 1px solid #999; text-align: center; font-weight: bold;">Quantity</th>
                                <th style="background: #e8e8e8; padding: 10px; border: 1px solid #999; text-align: center; font-weight: bold;">Unit Value (LKR)</th>
                                <th style="background: #e8e8e8; padding: 10px; border: 1px solid #999; text-align: center; font-weight: bold;">Total Value (LKR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHtml}
                            <tr style="background: #fef0f0; border-top: 2px solid #b22222;">
                                <td colspan="4" style="padding: 12px; text-align: right; font-weight: 800; font-size: 11pt;">TOTAL CLAIM VALUE</td>
                                <td style="padding: 12px; text-align: right; font-weight: 800; font-size: 12pt; color: #8b0000;">${totalValue.toFixed(2)} LKR</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div style="margin-top: 15px; padding: 10px; background: #f8fafc; border-radius: 8px; text-align: center; border: 1px solid #e0e0e0;">
                    <strong>📋 Reference Document:</strong> ${escapeHtml(data.main.doc_number || '—')}
                </div>
            `;
        } catch(e) {
            modalBody.innerHTML = '<div style="text-align: center; color: #dc2626; padding: 2rem;"><span>⚠️</span> Failed to load valuation statement</div>';
        }
    }
    
    function closeModal() { 
        document.getElementById('detailsModal').style.display = 'none'; 
    }
    
    function printCurrentModal() {
        if (currentReturnData) {
            printFinanceRecord(currentReturnData.main.record_id);
        }
    }
    
    async function printFinanceRecord(recordId) {
        try {
            const data = await fetchReturnDetails(recordId);
            const win = window.open('', '_blank');
            const currentDate = new Date();
            const formattedDate = currentDate.toLocaleString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            let totalValue = 0;
            const itemsHtml = data.items.map((item, index) => {
                const itemTotal = item.quantity * item.unit_cost;
                totalValue += itemTotal;
                return `
                    <tr style="${index % 2 === 0 ? 'background: #fff;' : 'background: #f9f9f9;'}">
                        <td style="padding: 6px; border: 1px solid #ddd; text-align: left;">${escapeHtml(item.item_code)}</td>
                        <td style="padding: 6px; border: 1px solid #ddd; text-align: left;">${escapeHtml(item.item_name)}</td>
                        <td style="padding: 6px; border: 1px solid #ddd; text-align: center;">${item.quantity}</td>
                        <td style="padding: 6px; border: 1px solid #ddd; text-align: right;">${parseFloat(item.unit_cost).toFixed(2)}</td>
                        <td style="padding: 6px; border: 1px solid #ddd; text-align: right; font-weight: 600;">${itemTotal.toFixed(2)}</td>
                    </tr>
                `;
            }).join('');
            
            win.document.write(`<!DOCTYPE html>
            <html>
            <head>
                <title>ASB Fashion - FINANCE DOCUMENT - ${escapeHtml(data.main.reference_number)}</title>
                <meta charset="UTF-8">
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    @page { size: A5; margin: 0.8cm; }
                    body { font-family: 'Times New Roman', Arial, sans-serif; background: white; color: #1a1a1a; font-size: 9pt; line-height: 1.4; }
                    .print-container { max-width: 100%; margin: 0 auto; }
                    .print-header { text-align: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 3px solid #8b0000; }
                    .company-logo { margin-bottom: 5px; font-size: 22px; font-weight: bold; color: #8b0000; }
                    .print-header h1 { color: #8b0000; font-size: 16pt; font-weight: 800; margin-bottom: 3px; }
                    .print-header h2 { color: #b22222; font-size: 11pt; font-weight: 600; margin-bottom: 3px; }
                    .doc-info-bar { background: #f5f5f5; padding: 8px 12px; margin-bottom: 15px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; border: 1px solid #ddd; font-size: 7pt; }
                    .info-box { background: #fafafa; padding: 12px; margin-bottom: 15px; border-left: 4px solid #b22222; border: 1px solid #e0e0e0; border-left-width: 4px; }
                    .info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
                    .info-label { font-size: 6pt; font-weight: bold; text-transform: uppercase; color: #666; margin-bottom: 2px; }
                    .info-value { font-weight: bold; font-size: 9pt; color: #1a1a1a; }
                    .items-table { width: 100%; border-collapse: collapse; margin: 12px 0; font-size: 8pt; }
                    .items-table th { background: #e8e8e8; padding: 6px; text-align: center; font-weight: bold; border: 1px solid #999; font-size: 7pt; }
                    .items-table td { padding: 6px; border: 1px solid #ddd; text-align: center; }
                    .items-table td:nth-child(3), .items-table td:nth-child(4), .items-table td:nth-child(5) { text-align: right; }
                    .items-table td:nth-child(1), .items-table td:nth-child(2) { text-align: left; }
                    .total-row { background: #fef0f0 !important; }
                    .total-row td { font-weight: bold; color: #8b0000; font-size: 10pt; text-align: right; }
                    .importance-box { background: #fef2f2; padding: 12px; margin: 15px 0; border: 1px solid #dc2626; border-left-width: 4px; border-left-color: #dc2626; }
                    .importance-title { font-weight: bold; color: #dc2626; margin-bottom: 8px; font-size: 9pt; }
                    .importance-text { font-size: 8pt; color: #444; line-height: 1.5; margin-bottom: 5px; }
                    .print-footer { margin-top: 20px; padding-top: 10px; border-top: 2px solid #ddd; }
                    .footer-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 10px; }
                    .footer-section { text-align: center; font-size: 7pt; }
                    .footer-section h4 { font-size: 7pt; font-weight: bold; color: #444; margin-bottom: 5px; }
                    .signature-line { border-top: 1px solid #999; width: 80%; margin: 5px auto 3px auto; padding-top: 3px; }
                    .bottom-bar { background: #8b0000; color: white; padding: 8px 12px; margin-top: 15px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; font-size: 6pt; }
                    .doc-badge { background: #8b5cf6; color: white; display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 6pt; font-weight: bold; }
                    @media print { .bottom-bar { -webkit-print-color-adjust: exact; print-color-adjust: exact; } .importance-box { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
                </style>
            </head>
            <body>
                <div class="print-container">
                    <div class="print-header">
                        <div class="company-logo">ASB FASHION</div>
                        <h1>FINANCE VALUATION STATEMENT</h1>
                        <h2>Damage & Shortage Financial Impact Report</h2>
                        <p><span class="doc-badge">FINANCE DEPARTMENT DOCUMENT</span></p>
                    </div>
                    
                    <div class="doc-info-bar">
                        <div><strong>Document ID:</strong> FIN/QC/${currentDate.getFullYear()}/${Math.floor(Math.random() * 10000)}</div>
                        <div><strong>Generated:</strong> ${formattedDate}</div>
                        <div><strong>Department:</strong> Finance & Accounts</div>
                        <div><strong>Type:</strong> INTERNAL FINANCE COPY</div>
                    </div>
                    
                    <div class="importance-box">
                        <div class="importance-title">⚠️ FINANCE IMPORTANCE NOTICE - STRICTLY FOR INTERNAL USE ⚠️</div>
                        <div class="importance-text">
                            <strong>SUBJECT: Financial Adjustment Required - Supplier Payment Deduction</strong>
                        </div>
                        <div class="importance-text">
                            This document serves as an OFFICIAL FINANCIAL INSTRUMENT for deduction from supplier payment.
                        </div>
                        <div class="importance-text" style="margin-top: 8px; background: #fff0f0; padding: 8px; border-radius: 4px;">
                            <strong>💰 FINANCIAL INSTRUCTION:</strong> The total claim value of <strong>${totalValue.toFixed(2)} LKR</strong> 
                            MUST be deducted from the supplier's next payment cycle. Please issue a DEBIT NOTE against this valuation.
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <div class="info-grid">
                            <div><div class="info-label">CLAIM ID</div><div class="info-value">#${data.main.record_id}</div></div>
                            <div><div class="info-label">REFERENCE NUMBER</div><div class="info-value">${escapeHtml(data.main.reference_number)}</div></div>
                            <div><div class="info-label">SUPPLIER</div><div class="info-value"><strong>${escapeHtml(data.main.supplier_name)}</strong></div></div>
                            <div><div class="info-label">INVOICE NUMBER</div><div class="info-value">${escapeHtml(data.main.invoice_number)}</div></div>
                            <div><div class="info-label">RECORD DATE</div><div class="info-value">${data.main.record_date}</div></div>
                            <div><div class="info-label">DAMAGE TYPE</div><div class="info-value">${escapeHtml(data.main.mode_name)}</div></div>
                        </div>
                    </div>
                    
                    <h4 style="margin: 10px 0 8px 0; font-size: 10pt; font-weight: bold;">📦 ITEMIZED BREAKDOWN FOR FINANCIAL ADJUSTMENT</h4>
                    
                    <div style="overflow-x: auto;">
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>Item Code</th>
                                    <th>Item Name</th>
                                    <th style="text-align: center;">Qty</th>
                                    <th style="text-align: right;">Unit Value (LKR)</th>
                                    <th style="text-align: right;">Total Value (LKR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${itemsHtml}
                                <tr class="total-row">
                                    <td colspan="4" style="padding: 8px; text-align: right; font-weight: 800;">TOTAL DEDUCTION AMOUNT</td>
                                    <td style="padding: 8px; text-align: right; font-weight: 800; color: #8b0000;">${totalValue.toFixed(2)} LKR</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="print-footer">
                        <div class="footer-grid">
                            <div class="footer-section">
                                <h4>✓ FINANCE APPROVAL</h4>
                                <p>Authorized for deduction from supplier payment</p>
                                <div class="signature-line"></div>
                                <p>Finance Manager</p>
                            </div>
                            <div class="footer-section">
                                <h4>📊 ACCOUNTING VERIFICATION</h4>
                                <p>Debit note to be issued against this claim</p>
                                <div class="signature-line"></div>
                                <p>Chief Accountant</p>
                            </div>
                            <div class="footer-section">
                                <h4>🔒 INTERNAL AUDIT</h4>
                                <p>Verified and recorded in financial system</p>
                                <div class="signature-line"></div>
                                <p>Internal Audit</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bottom-bar">
                        <div>© ${currentDate.getFullYear()} ASB Fashion Group. All Rights Reserved.</div>
                        <div>Developed by <strong>VEXEL IT</strong></div>
                        <div>FINANCE COPY - Page 1 of 1</div>
                    </div>
                </div>
            </body>
            </html>`);
            
            win.document.close();
            win.print();
            win.onafterprint = function() { win.close(); };
        } catch(e) {
            alert('Error loading finance document: ' + e.message);
        }
    }
    
    async function printSupplierRecord(recordId) {
        try {
            const data = await fetchReturnDetails(recordId);
            const win = window.open('', '_blank');
            const currentDate = new Date();
            const formattedDate = currentDate.toLocaleString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            let totalValue = 0;
            const itemsHtml = data.items.map((item, index) => {
                const itemTotal = item.quantity * item.unit_cost;
                totalValue += itemTotal;
                return `
                    <tr style="${index % 2 === 0 ? 'background: #fff;' : 'background: #f9f9f9;'}">
                        <td style="padding: 6px; border: 1px solid #ddd; text-align: left;">${escapeHtml(item.item_code)}</td>
                        <td style="padding: 6px; border: 1px solid #ddd; text-align: left;">${escapeHtml(item.item_name)}</td>
                        <td style="padding: 6px; border: 1px solid #ddd; text-align: center;">${item.quantity}</td>
                        <td style="padding: 6px; border: 1px solid #ddd; text-align: right;">${parseFloat(item.unit_cost).toFixed(2)}</td>
                        <td style="padding: 6px; border: 1px solid #ddd; text-align: right; font-weight: 600;">${itemTotal.toFixed(2)}</td>
                    </tr>
                `;
            }).join('');
            
            win.document.write(`<!DOCTYPE html>
            <html>
            <head>
                <title>ASB Fashion - SUPPLIER DOCUMENT - ${escapeHtml(data.main.reference_number)}</title>
                <meta charset="UTF-8">
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    @page { size: A5; margin: 0.8cm; }
                    body { font-family: 'Times New Roman', Arial, sans-serif; background: white; color: #1a1a1a; font-size: 9pt; line-height: 1.4; }
                    .print-container { max-width: 100%; margin: 0 auto; }
                    .print-header { text-align: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 3px solid #8b0000; }
                    .company-logo { margin-bottom: 5px; font-size: 22px; font-weight: bold; color: #8b0000; }
                    .print-header h1 { color: #8b0000; font-size: 16pt; font-weight: 800; margin-bottom: 3px; }
                    .print-header h2 { color: #b22222; font-size: 11pt; font-weight: 600; margin-bottom: 3px; }
                    .doc-info-bar { background: #f5f5f5; padding: 8px 12px; margin-bottom: 15px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; border: 1px solid #ddd; font-size: 7pt; }
                    .info-box { background: #fafafa; padding: 12px; margin-bottom: 15px; border-left: 4px solid #b22222; border: 1px solid #e0e0e0; border-left-width: 4px; }
                    .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
                    .info-label { font-size: 6pt; font-weight: bold; text-transform: uppercase; color: #666; margin-bottom: 2px; }
                    .info-value { font-weight: bold; font-size: 9pt; color: #1a1a1a; }
                    .items-table { width: 100%; border-collapse: collapse; margin: 12px 0; font-size: 8pt; }
                    .items-table th { background: #e8e8e8; padding: 6px; text-align: center; font-weight: bold; border: 1px solid #999; font-size: 7pt; }
                    .items-table td { padding: 6px; border: 1px solid #ddd; text-align: center; }
                    .items-table td:nth-child(3), .items-table td:nth-child(4), .items-table td:nth-child(5) { text-align: right; }
                    .items-table td:nth-child(1), .items-table td:nth-child(2) { text-align: left; }
                    .total-row { background: #fef0f0 !important; }
                    .total-row td { font-weight: bold; color: #8b0000; font-size: 10pt; text-align: right; }
                    .importance-box { background: #fff8e7; padding: 12px; margin: 15px 0; border: 1px solid #f59e0b; border-left-width: 4px; border-left-color: #f59e0b; }
                    .importance-title { font-weight: bold; color: #d97706; margin-bottom: 8px; font-size: 9pt; }
                    .importance-text { font-size: 8pt; color: #444; line-height: 1.5; margin-bottom: 5px; }
                    .print-footer { margin-top: 20px; padding-top: 10px; border-top: 2px solid #ddd; }
                    .footer-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 10px; }
                    .footer-section { text-align: center; font-size: 7pt; }
                    .footer-section h4 { font-size: 7pt; font-weight: bold; color: #444; margin-bottom: 5px; }
                    .signature-line { border-top: 1px solid #999; width: 80%; margin: 5px auto 3px auto; padding-top: 3px; }
                    .bottom-bar { background: #8b0000; color: white; padding: 8px 12px; margin-top: 15px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; font-size: 6pt; }
                    .doc-badge { background: #f59e0b; color: white; display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 6pt; font-weight: bold; }
                    @media print { .bottom-bar { -webkit-print-color-adjust: exact; print-color-adjust: exact; } .importance-box { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
                </style>
            </head>
            <body>
                <div class="print-container">
                    <div class="print-header">
                        <div class="company-logo">ASB FASHION</div>
                        <h1>SUPPLIER NOTICE STATEMENT</h1>
                        <h2>Damage & Shortage Claim Intimation</h2>
                        <p><span class="doc-badge">📦 SUPPLIER COMMUNICATION DOCUMENT</span></p>
                    </div>
                    
                    <div class="doc-info-bar">
                        <div><strong>Notice ID:</strong> SUP/QC/${currentDate.getFullYear()}/${Math.floor(Math.random() * 10000)}</div>
                        <div><strong>Issue Date:</strong> ${formattedDate}</div>
                        <div><strong>Priority:</strong> High - Immediate Attention Required</div>
                        <div><strong>Type:</strong> OFFICIAL SUPPLIER NOTICE</div>
                    </div>
                    
                    <div class="importance-box">
                        <div class="importance-title">⚠️ SUPPLIER IMPORTANCE NOTICE - PLEASE READ CAREFULLY ⚠️</div>
                        <div class="importance-text">
                            <strong>SUBJECT: Damage & Shortage Items Identified - Handover to Main Stores</strong>
                        </div>
                        <div class="importance-text">
                            Dear Supplier,
                        </div>
                        <div class="importance-text">
                            This is to inform you that the below-mentioned damaged/shortage items have been identified during the Quality Control inspection. 
                            <strong>These items have been handed over to our Main Stores department for further processing.</strong>
                        </div>
                        <div class="importance-text" style="margin-top: 8px; background: #fff0d9; padding: 8px; border-radius: 4px;">
                            <strong>🔔 FINANCIAL IMPLICATION:</strong> The total claim value of <strong>${totalValue.toFixed(2)} LKR</strong> will be 
                            <strong>DEDUCTED FROM YOUR FUTURE PAYMENTS</strong> at the time of settlement. 
                            Please ensure you take note of this adjustment and maintain necessary documentation for your records.
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <div class="info-grid">
                            <div><div class="info-label">CLAIM ID</div><div class="info-value">#${data.main.record_id}</div></div>
                            <div><div class="info-label">REFERENCE NUMBER</div><div class="info-value">${escapeHtml(data.main.reference_number)}</div></div>
                            <div><div class="info-label">SUPPLIER NAME</div><div class="info-value"><strong>${escapeHtml(data.main.supplier_name)}</strong></div></div>
                            <div><div class="info-label">INVOICE NUMBER</div><div class="info-value">${escapeHtml(data.main.invoice_number)}</div></div>
                            <div><div class="info-label">RECORD DATE</div><div class="info-value">${data.main.record_date}</div></div>
                            <div><div class="info-label">DAMAGE TYPE</div><div class="info-value">${escapeHtml(data.main.mode_name)}</div></div>
                        </div>
                    </div>
                    
                    <h4 style="margin: 10px 0 8px 0; font-size: 10pt; font-weight: bold;">📦 DAMAGE/SHORTAGE ITEMS DETAILS</h4>
                    
                    <div style="overflow-x: auto;">
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>Item Code</th>
                                    <th>Item Name</th>
                                    <th style="text-align: center;">Qty</th>
                                    <th style="text-align: right;">Unit Value (LKR)</th>
                                    <th style="text-align: right;">Total Value (LKR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${itemsHtml}
                                <tr class="total-row">
                                    <td colspan="4" style="padding: 8px; text-align: right; font-weight: 800;">TOTAL CLAIM VALUE</td>
                                    <td style="padding: 8px; text-align: right; font-weight: 800; color: #8b0000;">${totalValue.toFixed(2)} LKR</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="print-footer">
                        <div class="footer-grid">
                            <div class="footer-section">
                                <h4>✓ SUPPLIER ACKNOWLEDGMENT</h4>
                                <p>I acknowledge receipt of this notice and confirm understanding</p>
                                <div class="signature-line"></div>
                                <p>Supplier Signature & Date</p>
                            </div>
                            <div class="footer-section">
                                <h4>🏢 ASB AUTHORIZATION</h4>
                                <p>This claim is verified for deduction from supplier payment</p>
                                <div class="signature-line"></div>
                                <p>QC Manager / Finance</p>
                            </div>
                            <div class="footer-section">
                                <h4>📞 CONTACT FOR QUERIES</h4>
                                <p>QC Department: +94 XX XXX XXXX<br>Email: qc@asbfashion.com</p>
                                <div class="signature-line"></div>
                                <p>Main Stores: Ext. 1234</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bottom-bar">
                        <div>© ${currentDate.getFullYear()} ASB Fashion Group. All Rights Reserved.</div>
                        <div>Developed by <strong>VEXEL IT</strong></div>
                        <div>SUPPLIER COPY - Page 1 of 1</div>
                    </div>
                    
                    <div style="text-align: center; margin-top: 10px; font-size: 6pt; color: #888;">
                        This is a system generated notice - No signature required for digital copy | Valid for 30 days
                    </div>
                </div>
            </body>
            </html>`);
            
            win.document.close();
            win.print();
            win.onafterprint = function() { win.close(); };
        } catch(e) {
            alert('Error loading supplier document: ' + e.message);
        }
    }
    
    function exportToCSV() {
        let csv = "ID,Reference Number,Date,Supplier,Invoice Number,Document Number,Type,Reason,Total Items,Total Quantity,Total Value (LKR),Status\n";
        <?php foreach ($returns as $return): ?>
            csv += `"<?= $return['record_id'] ?>","<?= addslashes($return['reference_number']) ?>","<?= $return['record_date'] ?>","<?= addslashes($return['supplier_name'] ?? 'N/A') ?>","<?= addslashes($return['invoice_number'] ?? '-') ?>","<?= addslashes($return['doc_number'] ?? '-') ?>","<?= addslashes($return['mode_name'] ?? '-') ?>","<?= addslashes(substr($return['reason_text'] ?? '', 0, 50)) ?>","<?= $return['total_items'] ?>","<?= $return['total_quantity'] ?>","<?= number_format($return['total_cost'] ?? 0, 2) ?>","<?= $return['is_handover_complete'] ? 'Settled' : 'Pending' ?>"\n`;
        <?php endforeach; ?>
        
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'ASB_QC_Return_Report_' + new Date().toISOString().slice(0,19).replace(/:/g, '-') + '.csv';
        a.click();
        URL.revokeObjectURL(url);
    }
    
    window.onclick = function(event) { 
        const modal = document.getElementById('detailsModal');
        if (event.target == modal) {
            closeModal();
        }
    }
    
    function escapeHtml(str) { 
        if(!str) return ''; 
        return String(str).replace(/[&<>]/g, function(m){ 
            if(m==='&') return '&amp;'; 
            if(m==='<') return '&lt;'; 
            if(m==='>') return '&gt;'; 
            return m;
        }); 
    }
</script>

</body>
</html>