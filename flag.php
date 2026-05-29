<?php 
require 'db.php'; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}                                

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Increase memory limit for large data handling
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300');

// --- CONFIG & SANITIZATION ---
$limit = 50; // Records per page
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$searchDate = $_GET['search_date'] ?? '';

// Build Query Parameters with optimized indexing
$where = " WHERE 1=1";
$params = [];

if (strlen($search) > 2) {
    $where .= " AND (m.reference_number LIKE ? OR m.invoice_number LIKE ? OR m.doc_number LIKE ? OR s.supplier_name LIKE ? OR mo.mode_name LIKE ?)";
    $term = "%$search%";
    array_push($params, $term, $term, $term, $term, $term);
} elseif (!empty($search)) {
    $where .= " AND (m.reference_number = ? OR m.invoice_number = ? OR m.doc_number = ?)";
    array_push($params, $search, $search, $search);
}

if ($searchDate) {
    $where .= " AND m.record_date = ?";
    $params[] = $searchDate;
}

// 1. OPTIMIZED COUNT
$countStr = "SELECT COUNT(m.record_id) 
             FROM qc_damage_main m
             JOIN suppliers s ON m.supplier_id = s.supplier_id
             LEFT JOIN qc_modes mo ON m.mode_id = mo.mode_id
             $where";
$countStmt = $pdo->prepare($countStr);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRecords / $limit));

// 2. OPTIMIZED FETCH WITH LIMIT
$queryStr = "SELECT m.*, s.supplier_name, s.system_id, s.email, s.contact_number, s.address, mo.mode_name 
             FROM qc_damage_main m
             JOIN suppliers s ON m.supplier_id = s.supplier_id 
             LEFT JOIN qc_modes mo ON m.mode_id = mo.mode_id
             $where ORDER BY m.record_id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($queryStr);
$stmt->execute($params);
$recentRecords = $stmt->fetchAll();

// 3. STATISTICS
$today = date('Y-m-d');
$todayStmt = $pdo->prepare("SELECT COUNT(*) FROM qc_damage_main WHERE record_date = ?");
$todayStmt->execute([$today]);
$todayCount = $todayStmt->fetchColumn();

$pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM qc_damage_main WHERE is_handover_complete = 0");
$pendingStmt->execute();
$pendingCount = $pendingStmt->fetchColumn();

$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekStmt = $pdo->prepare("SELECT COUNT(*) FROM qc_damage_main WHERE record_date >= ?");
$weekStmt->execute([$weekStart]);
$weekCount = $weekStmt->fetchColumn();

$username = $_SESSION['username'] ?? 'QC Officer';
$userRole = $_SESSION['role'] ?? 'Standard';

$queryParams = $_GET;
unset($queryParams['page']); 
$baseQuery = http_build_query($queryParams);

header('Cache-Control: private, max-age=300');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <link rel="icon" type="image/png" href="logo.png">
    <title>ASB Fashion | Flag Update Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, 'Inter', sans-serif;
            background: #f5f7fb;
            min-height: 100vh;
        }

        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px 24px;
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
            font-size: 1.75rem;
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
            margin-left: 12px;
        }
        .logout-btn:hover {
            background: #e2e8f0;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -10px rgba(0,0,0,0.1);
            border-color: #dc2626;
        }
        .stat-title {
            color: #dc2626;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: #1e293b;
            margin-top: 8px;
        }
        .stat-icon {
            float: right;
            font-size: 2rem;
            opacity: 0.3;
        }

        /* Search Bar */
        .search-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .search-form {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .search-input {
            flex: 1;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 18px;
            color: #1e293b;
            font-size: 0.85rem;
            outline: none;
            transition: all 0.2s;
        }
        .search-input:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
        }
        .search-input::placeholder {
            color: #94a3b8;
        }
        .search-date {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            color: #1e293b;
            font-size: 0.85rem;
            outline: none;
        }
        .search-date:focus {
            border-color: #dc2626;
        }
        .btn-primary {
            background: #dc2626;
            color: white;
            padding: 12px 28px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
        }

        /* Table */
        .table-wrapper {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .table-container {
            overflow-x: auto;
            max-height: 65vh;
            overflow-y: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead {
            background: #f8fafc;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        th {
            text-align: left;
            padding: 16px 20px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
        }
        td {
            padding: 16px 20px;
            font-size: 0.85rem;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        tr:hover {
            background: #fef2f2;
        }

        /* Flag Buttons */
        .flag-group {
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .flag-btn {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .flag-btn:hover:not(:disabled) {
            transform: scale(1.08);
        }
        .flag-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .flag-completed {
            background: #10b981;
            color: white;
        }
        .flag-available {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        .flag-available:hover {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
        }
        .flag-locked {
            background: #f8fafc;
            color: #cbd5e1;
            border: 1px solid #e2e8f0;
        }
        .status-badge {
            text-align: center;
            margin-top: 8px;
            font-size: 0.65rem;
        }
        .status-success { color: #10b981; font-weight: 600; }
        .status-warning { color: #f59e0b; font-weight: 600; }
        .status-info { color: #3b82f6; font-weight: 600; }
        .status-muted { color: #94a3b8; }

        /* Print Button */
        .print-btn {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .print-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37,99,235,0.3);
        }

        /* Pagination */
        .pagination {
            margin-top: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .pagination-info {
            color: #64748b;
            font-size: 0.75rem;
        }
        .pagination-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .page-link {
            background: white;
            border: 1px solid #e2e8f0;
            color: #475569;
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .page-link:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }
        .page-link.active {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal {
            background: white;
            border-radius: 20px;
            padding: 28px;
            max-width: 450px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.2);
        }
        .modal-icon { font-size: 4rem; margin-bottom: 16px; }
        .modal-title { font-size: 1.4rem; font-weight: 700; color: #1e293b; margin-bottom: 12px; }
        .modal-text { color: #64748b; margin-bottom: 24px; }
        .modal-buttons { display: flex; gap: 12px; justify-content: center; }
        .modal-confirm { background: #dc2626; color: white; padding: 10px 24px; border-radius: 12px; font-weight: 600; border: none; cursor: pointer; }
        .modal-cancel { background: #f1f5f9; color: #475569; padding: 10px 24px; border-radius: 12px; font-weight: 600; border: none; cursor: pointer; }

        /* Bags Input Modal */
        .bags-modal {
            max-width: 400px;
        }
        .bags-input {
            width: 100%;
            padding: 12px;
            font-size: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin: 15px 0;
            text-align: center;
            font-weight: 600;
        }
        .bags-input:focus {
            outline: none;
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
        }

        .loading-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e2e8f0;
            border-top-color: #dc2626;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            background: white;
            padding: 10px;
            border-radius: 50%;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

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

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        .empty-state-icon { font-size: 4rem; margin-bottom: 16px; opacity: 0.5; }

        .footer {
            text-align: center;
            margin-top: 40px;
            padding: 20px;
            color: #94a3b8;
            font-size: 0.7rem;
            border-top: 1px solid #e2e8f0;
        }

        @media (max-width: 768px) {
            .container { padding: 16px; }
            .stats-grid { grid-template-columns: 1fr; }
            .search-form { flex-direction: column; }
            th, td { padding: 12px; }
            .flag-group { gap: 4px; }
            .flag-btn { width: 38px; height: 38px; font-size: 1rem; }
            .header-content { flex-direction: column; text-align: center; }
            .user-info { text-align: center; }
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .container { padding: 0; margin: 0; }
            .stat-card, .search-card, .table-wrapper { background: none; border: 1px solid #ddd; box-shadow: none; }
            .flag-btn, .print-btn { display: none; }
        }
    </style>
</head>
<body>
    <div class="header no-print">
        <div class="header-content">
            <div class="logo">
                <h1>ASB <span>FASHION</span></h1>
                <p>QUALITY CONTROL & RETURNS</p>
            </div>
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <a href="dashboard.php" class="back-btn">
                    ← Back to Dashboard
                </a>
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($username) ?></div>
                    <div class="user-role"><?= htmlspecialchars($userRole) ?></div>
                </div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Stats Cards -->
        <div class="stats-grid no-print">
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-title">Today's Volume</div>
                <div class="stat-number"><?= number_format($todayCount) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-title">Pending Flags</div>
                <div class="stat-number"><?= number_format($pendingCount) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-title">This Week</div>
                <div class="stat-number"><?= number_format($weekCount) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-title">Total Records</div>
                <div class="stat-number"><?= number_format($totalRecords) ?></div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="search-card no-print">
            <form method="GET" class="search-form">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="🔍 Search by Reference, Invoice, Document, Supplier..." 
                       class="search-input">
                <input type="date" name="search_date" value="<?= $searchDate ?>" class="search-date">
                <button type="submit" class="btn-primary">Search</button>
                <?php if($search || $searchDate): ?>
                    <a href="flag.php" class="btn-secondary">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Records Table -->
        <div class="table-wrapper">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>REFERENCE</th>
                            <th>SUPPLIER</th>
                            <th>INVOICE</th>
                            <th style="text-align: center">FLAGS</th>
                            <th style="text-align: right">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($recentRecords) > 0): ?>
                            <?php foreach($recentRecords as $row): ?>
                            <tr>
                                <td>
                                    <strong style="color: #1e293b;">#<?= htmlspecialchars($row['reference_number']) ?></strong><br>
                                    <small style="color: #94a3b8;"><?= date('d M Y', strtotime($row['record_date'])) ?></small>
                                   </div>
                                </td>
                                <td>
                                    <strong style="color: #1e293b;"><?= htmlspecialchars($row['supplier_name']) ?></strong><br>
                                    <small style="color: #94a3b8;">ID: <?= htmlspecialchars($row['system_id'] ?? 'N/A') ?></small><br>
                                    <small style="color: #94a3b8;">Mode: <?= htmlspecialchars($row['mode_name'] ?? 'Standard') ?></small>
                                   </div>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['invoice_number']) ?><br>
                                    <small style="color: #94a3b8;">Doc: <?= htmlspecialchars($row['doc_number'] ?? 'N/A') ?></small>
                                   </div>
                                </td>
                                <td style="text-align: center">
                                    <div class="flag-group">
                                        <?php 
                                        $flags = [
                                            ['col' => 'is_informed', 'label' => '📧', 'name' => 'Informed'],
                                            ['col' => 'is_store_received', 'label' => '📦', 'name' => 'Store'],
                                            ['col' => 'is_gate_cleared', 'label' => '🚪', 'name' => 'Gate'],
                                            ['col' => 'is_handover_complete', 'label' => '✅', 'name' => 'Complete']
                                        ];
                                        
                                        foreach($flags as $flag): 
                                            $active = $row[$flag['col']];
                                            $canUpdate = false;
                                            
                                            if ($flag['col'] == 'is_informed') $canUpdate = true;
                                            if ($flag['col'] == 'is_store_received') $canUpdate = $row['is_informed'] && !$active;
                                            if ($flag['col'] == 'is_gate_cleared') $canUpdate = $row['is_store_received'] && !$active;
                                            if ($flag['col'] == 'is_handover_complete') $canUpdate = $row['is_gate_cleared'] && !$active;
                                            
                                            if ($active) {
                                                $btnClass = 'flag-completed';
                                                $title = "✓ {$flag['name']} - Completed";
                                                $disabled = false;
                                            } elseif ($canUpdate) {
                                                $btnClass = 'flag-available';
                                                $title = "Mark {$flag['name']} as completed";
                                                $disabled = false;
                                            } else {
                                                $btnClass = 'flag-locked';
                                                $title = "Complete previous step first";
                                                $disabled = true;
                                            }
                                        ?>
                                            <button onclick="<?= $disabled ? '' : "updateFlag({$row['record_id']}, '{$flag['col']}')" ?>" 
                                                    class="flag-btn <?= $btnClass ?>"
                                                    title="<?= $title ?>"
                                                    <?= $disabled ? 'disabled' : '' ?>>
                                                <?= $flag['label'] ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="status-badge">
                                        <?php
                                        if ($row['is_handover_complete']) {
                                            echo '<span class="status-success">✓ All steps completed</span>';
                                        } elseif ($row['is_gate_cleared']) {
                                            echo '<span class="status-warning">→ Ready for Return Complete</span>';
                                        } elseif ($row['is_store_received']) {
                                            echo '<span class="status-info">→ Ready for Gate Clearance</span>';
                                        } elseif ($row['is_informed']) {
                                            echo '<span class="status-success">→ Ready for Store Received</span>';
                                        } else {
                                            echo '<span class="status-muted">→ Start with Supplier Informed</span>';
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td style="text-align: right">
                                    <button onclick="promptForBagsAndPrint(<?= $row['record_id'] ?>)" class="print-btn">
                                        🖨️ Print
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <div class="empty-state-icon">📄</div>
                                    <p>No records found</p>
                                    <small>Try adjusting your search criteria</small>
                                 </div>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if($totalPages > 1): ?>
        <div class="pagination no-print">
            <div class="pagination-info">
                Page <?= $page ?> of <?= $totalPages ?> (<?= number_format($totalRecords) ?> records)
            </div>
            <div class="pagination-buttons">
                <?php if($page > 1): ?>
                    <a href="?page=1&<?= $baseQuery ?>" class="page-link">First</a>
                    <a href="?page=<?= $page-1 ?>&<?= $baseQuery ?>" class="page-link">← Prev</a>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                if($startPage > 1) echo '<span style="color:#94a3b8; padding:8px;">...</span>';
                for($i = $startPage; $i <= $endPage; $i++):
                ?>
                    <a href="?page=<?= $i ?>&<?= $baseQuery ?>" 
                       class="page-link <?= $i == $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor;
                if($endPage < $totalPages) echo '<span style="color:#94a3b8; padding:8px;">...</span>';
                ?>
                
                <?php if($page < $totalPages): ?>
                    <a href="?page=<?= $page+1 ?>&<?= $baseQuery ?>" class="page-link">Next →</a>
                    <a href="?page=<?= $totalPages ?>&<?= $baseQuery ?>" class="page-link">Last</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="footer">
            <p>© <?= date('Y') ?> ASB Fashion - Quality Control & Returns Management System</p>
        </div>
    </div>

    <!-- Modal -->
    <div id="confirmModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-icon" id="modalIcon">❓</div>
            <div class="modal-title" id="modalTitle">Confirm</div>
            <div class="modal-text" id="modalText">Are you sure?</div>
            <div class="modal-buttons">
                <button class="modal-cancel" id="modalCancel">Cancel</button>
                <button class="modal-confirm" id="modalConfirm">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Bags Input Modal -->
    <div id="bagsModal" class="modal-overlay">
        <div class="modal bags-modal">
            <div class="modal-icon">📦</div>
            <div class="modal-title">Number of Bags</div>
            <div class="modal-text">Please enter the number of bags for this return shipment:</div>
            <input type="number" id="bagsCount" class="bags-input" min="1" value="1" step="1">
            <div class="modal-buttons">
                <button class="modal-cancel" id="bagsCancel">Cancel</button>
                <button class="modal-confirm" id="bagsConfirm">Print Report</button>
            </div>
        </div>
    </div>

    <div id="loadingModal" class="loading-modal">
        <div class="spinner"></div>
    </div>

    <div id="toast" class="toast"></div>

    <script>
        // Modal handlers
        let pendingConfirm = null;
        let pendingPrintId = null;

        function showConfirm(title, text, onConfirm) {
            const modal = document.getElementById('confirmModal');
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalText').textContent = text;
            modal.style.display = 'flex';
            
            const confirmBtn = document.getElementById('modalConfirm');
            const cancelBtn = document.getElementById('modalCancel');
            
            const handleConfirm = () => {
                modal.style.display = 'none';
                confirmBtn.removeEventListener('click', handleConfirm);
                cancelBtn.removeEventListener('click', handleCancel);
                onConfirm();
            };
            
            const handleCancel = () => {
                modal.style.display = 'none';
                confirmBtn.removeEventListener('click', handleConfirm);
                cancelBtn.removeEventListener('click', handleCancel);
            };
            
            confirmBtn.addEventListener('click', handleConfirm);
            cancelBtn.addEventListener('click', handleCancel);
        }

        function showLoading() {
            document.getElementById('loadingModal').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loadingModal').style.display = 'none';
        }

        function showToast(message, isSuccess = true) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.style.backgroundColor = isSuccess ? '#10b981' : '#dc2626';
            toast.style.display = 'block';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }

        let isUpdating = false;

        async function updateFlag(id, column) {
            if(isUpdating) return;
            
            const columnNames = {
                'is_informed': 'Supplier Informed',
                'is_store_received': 'Store Received', 
                'is_gate_cleared': 'Gate Cleared',
                'is_handover_complete': 'Return Complete'
            };
            const displayName = columnNames[column] || column;
            
            showConfirm('Update Status Flag', `Mark "${displayName}" as completed?`, async () => {
                isUpdating = true;
                showLoading();
                
                try {
                    const formData = new URLSearchParams();
                    formData.append('id', id);
                    formData.append('column', column);
                    
                    const res = await fetch('update_flag.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData.toString()
                    });
                    
                    const data = await res.json();
                    hideLoading();
                    
                    if (data.success) {
                        showToast(data.message || 'Flag updated successfully!');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showToast(data.message || 'Failed to update flag', false);
                    }
                } catch (err) {
                    hideLoading();
                    showToast('Error: ' + err.message, false);
                } finally {
                    isUpdating = false;
                }
            });
        }

        // Prompt for number of bags before printing
        function promptForBagsAndPrint(id) {
            pendingPrintId = id;
            const bagsModal = document.getElementById('bagsModal');
            const bagsInput = document.getElementById('bagsCount');
            bagsInput.value = 1;
            bagsModal.style.display = 'flex';
            
            const confirmBtn = document.getElementById('bagsConfirm');
            const cancelBtn = document.getElementById('bagsCancel');
            
            const handleConfirm = async () => {
                let bags = parseInt(bagsInput.value);
                if (isNaN(bags) || bags < 1) {
                    bags = 1;
                }
                bagsModal.style.display = 'none';
                confirmBtn.removeEventListener('click', handleConfirm);
                cancelBtn.removeEventListener('click', handleCancel);
                
                // First save bags count to database
                showLoading();
                try {
                    const formData = new URLSearchParams();
                    formData.append('id', pendingPrintId);
                    formData.append('number_of_bags', bags);
                    
                    const saveRes = await fetch('save_bags.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData.toString()
                    });
                    const saveData = await saveRes.json();
                    
                    if (!saveData.success) {
                        showToast('Warning: ' + (saveData.message || 'Could not save bags count'), false);
                    }
                } catch (err) {
                    console.error('Error saving bags:', err);
                }
                hideLoading();
                
                // Then print the report
                printFlagReport(pendingPrintId, bags);
            };
            
            const handleCancel = () => {
                bagsModal.style.display = 'none';
                confirmBtn.removeEventListener('click', handleConfirm);
                cancelBtn.removeEventListener('click', handleCancel);
                pendingPrintId = null;
            };
            
            confirmBtn.removeEventListener('click', handleConfirm);
            cancelBtn.removeEventListener('click', handleCancel);
            confirmBtn.addEventListener('click', handleConfirm);
            cancelBtn.addEventListener('click', handleCancel);
        }

        async function printFlagReport(id, numberOfBags) {
            showLoading();
            
            try {
                const res = await fetch(`get_details.php?id=${id}`);
                const data = await res.json();
                hideLoading();
                
                if (data.success) {
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write(generatePrintHTML(data, numberOfBags));
                    printWindow.document.close();
                    printWindow.print();
                } else {
                    showToast(data.message || 'Could not load data', false);
                }
            } catch (err) {
                hideLoading();
                showToast('Error loading report: ' + err.message, false);
            }
        }

        function generatePrintHTML(data, numberOfBags) {
            const main = data.main;
            const items = data.items || [];
            const totalQuantity = items.reduce((sum, item) => sum + (parseInt(item.quantity) || 0), 0);
            const totalItems = items.length;
            const bags = numberOfBags || 1;
            
            // Compact A4 design with small font, up to 20 items per page
            return `<!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>ASB Fashion - QC Return Report</title>
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body { 
                        font-family: 'Segoe UI', 'Roboto', Arial, sans-serif; 
                        padding: 5mm 8mm; 
                        font-size: 8.5pt;
                        line-height: 1.25;
                        color: #1a1a2e;
                    }
                    @media print {
                        body { margin: 0; padding: 5mm; }
                        .page-break { page-break-before: always; }
                    }
                    .report-container {
                        max-width: 100%;
                        margin: 0 auto;
                    }
                    /* Header Section */
                    .header { 
                        text-align: center; 
                        border-bottom: 2px solid #dc2626; 
                        padding-bottom: 4px; 
                        margin-bottom: 10px;
                    }
                    .header h1 { 
                        font-size: 16pt; 
                        font-weight: 800; 
                        color: #1f2937; 
                        letter-spacing: 1px;
                    }
                    .header h1 span { color: #dc2626; }
                    .header p { 
                        font-size: 7pt; 
                        color: #6b7280; 
                        margin-top: 2px;
                    }
                    .report-title {
                        background: #fef2f2;
                        text-align: center;
                        padding: 4px;
                        font-size: 9pt;
                        font-weight: 700;
                        color: #dc2626;
                        margin-bottom: 10px;
                        border-radius: 4px;
                    }
                    /* Two Column Grid */
                    .info-grid {
                        display: grid;
                        grid-template-columns: repeat(2, 1fr);
                        gap: 6px 12px;
                        margin-bottom: 12px;
                        background: #f9fafb;
                        padding: 8px 10px;
                        border-radius: 6px;
                        border: 1px solid #e5e7eb;
                    }
                    .info-item {
                        display: flex;
                        align-items: baseline;
                        flex-wrap: wrap;
                    }
                    .info-label {
                        font-weight: 700;
                        font-size: 7pt;
                        color: #4b5563;
                        text-transform: uppercase;
                        min-width: 85px;
                        letter-spacing: 0.3px;
                    }
                    .info-value {
                        font-size: 8pt;
                        color: #1f2937;
                        font-weight: 500;
                    }
                    /* Items Table - Compact */
                    .items-section {
                        margin-bottom: 12px;
                    }
                    .section-title {
                        font-size: 9pt;
                        font-weight: 800;
                        background: #f3f4f6;
                        padding: 4px 8px;
                        border-left: 3px solid #dc2626;
                        margin-bottom: 6px;
                        color: #1f2937;
                    }
                    .items-table {
                        width: 100%;
                        border-collapse: collapse;
                        font-size: 7.5pt;
                    }
                    .items-table th {
                        background: #e5e7eb;
                        padding: 5px 4px;
                        border: 0.5px solid #9ca3af;
                        text-align: left;
                        font-weight: 700;
                        font-size: 7pt;
                        color: #374151;
                    }
                    .items-table td {
                        padding: 4px 4px;
                        border: 0.5px solid #d1d5db;
                        font-size: 7.5pt;
                        color: #1f2937;
                    }
                    /* Summary Row */
                    .summary-row {
                        background: #fef2f2;
                        font-weight: 700;
                    }
                    /* Status Flags Table */
                    .flags-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 8px;
                        font-size: 7.5pt;
                    }
                    .flags-table th {
                        background: #e5e7eb;
                        padding: 4px 6px;
                        border: 0.5px solid #9ca3af;
                        text-align: left;
                        font-size: 7pt;
                    }
                    .flags-table td {
                        padding: 4px 6px;
                        border: 0.5px solid #d1d5db;
                    }
                    .status-completed { color: #10b981; font-weight: 700; }
                    .status-pending { color: #dc2626; font-weight: 700; }
                    /* Signatures */
                    .signatures {
                        display: flex;
                        justify-content: space-between;
                        margin-top: 18px;
                        margin-bottom: 12px;
                    }
                    .signature {
                        text-align: center;
                        width: 30%;
                    }
                    .signature-line {
                        border-top: 0.8px solid #374151;
                        margin-top: 20px;
                        padding-top: 4px;
                        font-size: 6.5pt;
                        color: #4b5563;
                    }
                    .footer {
                        text-align: center;
                        margin-top: 10px;
                        padding-top: 6px;
                        border-top: 1px solid #e5e7eb;
                        font-size: 6pt;
                        color: #6b7280;
                    }
                    .developer-info {
                        text-align: center;
                        font-size: 5.5pt;
                        color: #9ca3af;
                        margin-top: 4px;
                    }
                    @media print {
                        .no-break { page-break-inside: avoid; }
                    }
                </style>
            </head>
            <body>
                <div class="report-container">
                    <div class="header">
                        <h1>ASB <span>FASHION</span></h1>
                        <p>QUALITY CONTROL & RETURN MANAGEMENT SYSTEM</p>
                    </div>
                    <div class="report-title">
                        OFFICIAL QC RETURN NOTE
                    </div>
                    
                    <!-- Supplier & Invoice Info -->
                    <div class="info-grid">
                        <div class="info-item"><span class="info-label">Supplier:</span><span class="info-value">${escapeHtml(main.supplier_name) || 'N/A'}</span></div>
                        <div class="info-item"><span class="info-label">System ID:</span><span class="info-value">${escapeHtml(main.system_id) || 'N/A'}</span></div>
                        <div class="info-item"><span class="info-label">Reference #:</span><span class="info-value"><strong>${escapeHtml(main.reference_number) || 'N/A'}</strong></span></div>
                        <div class="info-item"><span class="info-label">Invoice #:</span><span class="info-value">${escapeHtml(main.invoice_number) || 'N/A'}</span></div>
                        <div class="info-item"><span class="info-label">Document #:</span><span class="info-value">${escapeHtml(main.doc_number) || 'N/A'}</span></div>
                        <div class="info-item"><span class="info-label">Record Date:</span><span class="info-value">${main.record_date || 'N/A'}</span></div>
                        <div class="info-item"><span class="info-label">Workflow Mode:</span><span class="info-value">${escapeHtml(main.mode_name) || 'Standard'}</span></div>
                        <div class="info-item"><span class="info-label">Return Reason:</span><span class="info-value">${escapeHtml(main.reason_name) || 'Not Specified'}</span></div>
                        <div class="info-item"><span class="info-label">Number of Bags:</span><span class="info-value"><strong>${bags} bag(s)</strong></span></div>
                    </div>
                    
                    <!-- Return Items - Compact table for up to 20 items -->
                    <div class="items-section">
                        <div class="section-title">📦 RETURN ITEMS (${totalItems} items)</div>
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th style="width:25%">Item Code</th>
                                    <th style="width:55%">Item Name / Description</th>
                                    <th style="width:10%; text-align:center">Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${items.length > 0 ? items.map(item => `
                                <tr>
                                    <td><strong>${escapeHtml(item.item_code) || '—'}</strong></div>
                                    <td>${escapeHtml(item.item_name) || '—'}</div>
                                    <td style="text-align:center">${item.quantity || 0}</div>
                                </tr>
                                `).join('') : '<tr><td colspan="3" style="text-align:center">No items recorded</div></div>'}
                                <tr class="summary-row">
                                    <td colspan="2" style="text-align:right; font-weight:700;">TOTAL RETURN QUANTITY:</div>
                                    <td style="text-align:center; font-weight:700;">${totalQuantity.toLocaleString()} pcs</div>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Process Status Flags -->
                    <div class="items-section">
                        <div class="section-title">🚦 PROCESS FLAG STATUS</div>
                        <table class="flags-table">
                            <thead>
                                <tr>
                                    <th style="width:25%">Process Step</th>
                                    <th style="width:20%">Status</th>
                                    <th style="width:30%">Processed By</th>
                                    <th style="width:25%">Date & Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>📧 Supplier Informed</div>
                                    <td><span class="${main.is_informed ? 'status-completed' : 'status-pending'}">${main.is_informed ? '✓ COMPLETED' : '○ PENDING'}</span></div>
                                    <td>${escapeHtml(main.informed_by_user) || '—'}</div>
                                    <td>${main.informed_datetime ? new Date(main.informed_datetime).toLocaleString() : '—'}</div>
                                </tr>
                                <tr>
                                    <td>📦 Store Received</div>
                                    <td><span class="${main.is_store_received ? 'status-completed' : 'status-pending'}">${main.is_store_received ? '✓ COMPLETED' : '○ PENDING'}</span></div>
                                    <td>${escapeHtml(main.store_user) || '—'}</div>
                                    <td>${main.store_datetime ? new Date(main.store_datetime).toLocaleString() : '—'}</div>
                                </tr>
                                <tr>
                                    <td>🚪 Gate Cleared</div>
                                    <td><span class="${main.is_gate_cleared ? 'status-completed' : 'status-pending'}">${main.is_gate_cleared ? '✓ COMPLETED' : '○ PENDING'}</span></div>
                                    <td>${escapeHtml(main.gate_user) || '—'}</div>
                                    <td>${main.gate_datetime ? new Date(main.gate_datetime).toLocaleString() : '—'}</div>
                                </tr>
                                <tr>
                                    <td>✅ Return Complete</div>
                                    <td><span class="${main.is_handover_complete ? 'status-completed' : 'status-pending'}">${main.is_handover_complete ? '✓ COMPLETED' : '○ PENDING'}</span></div>
                                    <td>${escapeHtml(main.handover_user) || '—'}</div>
                                    <td>${main.handover_datetime ? new Date(main.handover_datetime).toLocaleString() : '—'}</div>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Signatures -->
                    <div class="signatures">
                        <div class="signature">
                            <div class="signature-line">QC Officer Signature</div>
                        </div>
                        <div class="signature">
                            <div class="signature-line">Supplier Representative</div>
                        </div>
                        <div class="signature">
                            <div class="signature-line">Management Stamp / Date</div>
                        </div>
                    </div>
                    
                    <div class="footer">
                        <p>Generated on: ${new Date().toLocaleString()} | ASB Fashion Quality Control System</p>
                        <div class="developer-info">
                            Developed by Vexel IT | Contact: info@vexelit.com
                        </div>
                    </div>
                </div>
            </body>
            </html>`;
        }

        function escapeHtml(text) {
            if (!text) return '';
            return String(text).replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
    </script>
</body>
</html>