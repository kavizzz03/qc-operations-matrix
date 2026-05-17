<?php 
require 'db.php'; 
require_once 'task_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in - prevent redirect loop
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Refresh user tasks in session if needed
if (!isset($_SESSION['user_tasks']) && function_exists('getUserTasks')) {
    $_SESSION['user_tasks'] = getUserTasks($pdo, $_SESSION['user_id']);
}

// Function to check if user can access a feature
function canAccess($task_code) {
    if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin']) return true;
    
    if (isset($_SESSION['user_tasks']) && is_array($_SESSION['user_tasks'])) {
        foreach ($_SESSION['user_tasks'] as $task) {
            if ($task['task_code'] === $task_code) {
                return true;
            }
        }
    }
    return false;
}

// Function to check if user is super admin
function isSuperAdmin() {
    return (isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1) || 
           (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] == 1);
}

// --- CONFIG & SANITIZATION ---
$limit = 50;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$searchDate = $_GET['search_date'] ?? '';

// Build Query Parameters
$where = " WHERE 1=1";
$params = [];

if (strlen($search) > 2) {
    $where .= " AND (m.reference_number LIKE ? OR m.invoice_number LIKE ? OR m.doc_number LIKE ? OR s.supplier_name LIKE ? OR mo.mode_name LIKE ? OR re.reason_name LIKE ?)";
    $term = "%$search%";
    array_push($params, $term, $term, $term, $term, $term, $term);
} elseif (!empty($search)) {
    $where .= " AND (m.reference_number = ? OR m.invoice_number = ? OR m.doc_number = ?)";
    array_push($params, $search, $search, $search);
}

if ($searchDate) {
    $where .= " AND m.record_date = ?";
    $params[] = $searchDate;
}

// 1. SECURE COUNT & PAGINATION
$countStr = "SELECT COUNT(m.record_id) 
             FROM qc_damage_main m 
             JOIN suppliers s ON m.supplier_id = s.supplier_id
             LEFT JOIN qc_modes mo ON m.mode_id = mo.mode_id
             LEFT JOIN qc_reasons re ON m.reason_id = re.reason_id 
             $where";
$countStmt = $pdo->prepare($countStr);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRecords / $limit));

// 2. FETCH WITH ALL MAPPED RELATIONSHIPS
$queryStr = "SELECT m.*, s.supplier_name, s.email, s.contact_number, s.address, mo.mode_name, re.reason_name 
             FROM qc_damage_main m 
             JOIN suppliers s ON m.supplier_id = s.supplier_id 
             LEFT JOIN qc_modes mo ON m.mode_id = mo.mode_id
             LEFT JOIN qc_reasons re ON m.reason_id = re.reason_id
             $where ORDER BY m.record_id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($queryStr);
$stmt->execute($params);
$recentRecords = $stmt->fetchAll();

// 3. SECURE TODAY VOLUME
$today = date('Y-m-d');
$todayStmt = $pdo->prepare("SELECT COUNT(*) FROM qc_damage_main WHERE record_date = ?");
$todayStmt->execute([$today]);
$todayCount = $todayStmt->fetchColumn();

// Get pending tasks count
$pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM qc_damage_main WHERE is_handover_complete = 0");
$pendingStmt->execute();
$pendingCount = $pendingStmt->fetchColumn();

// Get this week's total
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekStmt = $pdo->prepare("SELECT COUNT(*) FROM qc_damage_main WHERE record_date >= ?");
$weekStmt->execute([$weekStart]);
$weekCount = $weekStmt->fetchColumn();

// Get user's last login info
$lastLoginStmt = $pdo->prepare("SELECT last_login, last_login_ip, total_logins FROM users WHERE user_id = ?");
$lastLoginStmt->execute([$_SESSION['user_id']]);
$userLoginInfo = $lastLoginStmt->fetch();

// 4. URL PERSISTENCE HELPER
$queryParams = $_GET;
unset($queryParams['page']); 
$baseQuery = http_build_query($queryParams);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <title>ASB Fashion | QC & Return Management System</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #020617; color: #cbd5e1; }
        .glass-card { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
        .sequence-line { height: 2px; width: 20px; align-self: center; margin: 0 -2px; }
        .dot-active { box-shadow: 0 0 15px currentColor; }
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #dc2626; border-radius: 10px; }
        .footer-gradient { background: linear-gradient(135deg, #0f172a 0%, #020617 100%); }
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); }
        
        /* PRINT STYLES - A4 Sheet Format */
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            #printReportModal {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
                background: white !important;
                z-index: 9999 !important;
                display: block !important;
                overflow: auto !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .print-container {
                width: 100% !important;
                max-width: 210mm !important;
                margin: 0 auto !important;
                padding: 10mm !important;
                font-size: 12px !important;
                color: black !important;
            }
            .print-header {
                text-align: center !important;
                border-bottom: 2px solid #dc2626 !important;
                margin-bottom: 20px !important;
                padding-bottom: 10px !important;
            }
            .print-title {
                font-size: 24px !important;
                font-weight: bold !important;
                color: black !important;
            }
            .print-subtitle {
                font-size: 12px !important;
                color: #666 !important;
            }
            .print-section {
                margin-bottom: 20px !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
            .print-section-title {
                font-size: 16px !important;
                font-weight: bold !important;
                background: #f3f4f6 !important;
                padding: 8px !important;
                border-left: 4px solid #dc2626 !important;
                margin-bottom: 10px !important;
                color: black !important;
            }
            .print-info-grid {
                display: grid !important;
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 10px !important;
                margin-bottom: 15px !important;
            }
            .print-info-item {
                padding: 8px !important;
                border-bottom: 1px solid #e5e7eb !important;
            }
            .print-label {
                font-weight: bold !important;
                color: #374151 !important;
                font-size: 11px !important;
            }
            .print-value {
                color: black !important;
                font-size: 11px !important;
            }
            .print-status-table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin: 15px 0 !important;
            }
            .print-status-table th {
                background: #f3f4f6 !important;
                padding: 8px !important;
                text-align: left !important;
                font-size: 11px !important;
                border: 1px solid #d1d5db !important;
                color: black !important;
            }
            .print-status-table td {
                padding: 8px !important;
                border: 1px solid #d1d5db !important;
                font-size: 10px !important;
                color: black !important;
            }
            .print-status-completed {
                color: #16a34a !important;
                font-weight: bold !important;
            }
            .print-status-pending {
                color: #dc2626 !important;
                font-weight: bold !important;
            }
            .print-footer {
                text-align: center !important;
                margin-top: 30px !important;
                padding-top: 10px !important;
                border-top: 1px solid #e5e7eb !important;
                font-size: 9px !important;
                color: #6b7280 !important;
            }
            .print-signature {
                margin-top: 30px !important;
                display: flex !important;
                justify-content: space-between !important;
            }
            .print-signature-line {
                width: 200px !important;
                border-top: 1px solid black !important;
                margin-top: 30px !important;
                padding-top: 5px !important;
                text-align: center !important;
                font-size: 10px !important;
            }
            @page {
                size: A4;
                margin: 15mm;
            }
        }
    </style>
</head>
<body class="selection:bg-red-500 selection:text-white">

    <div class="flex min-h-screen flex-col">
        <div class="flex flex-1">
            <!-- SIDEBAR -->
            <aside class="w-72 bg-[#0f172a] border-r border-slate-800 flex flex-col fixed h-full z-40 no-print overflow-y-auto custom-scroll">
                <div class="p-8">
                    <h2 class="text-2xl font-black text-white italic tracking-tighter uppercase">ASB <span class="text-red-600">FASHION</span></h2>
                    <p class="text-[9px] text-slate-500 tracking-[0.3em] font-bold mt-1 uppercase">QC & Return System v4.0</p>
                </div>
                
                <nav class="flex-1 px-6 space-y-1.5 pb-6">
                    <a href="dashboard.php" class="flex items-center justify-between p-4 bg-red-600/10 text-red-500 rounded-2xl border border-red-600/20 transition-all group">
                        <span class="font-black uppercase text-[10px] tracking-widest">Dashboard</span>
                        <div class="w-1.5 h-1.5 rounded-full bg-red-600 animate-pulse"></div>
                    </a>

                    <?php if(canAccess('QC_ENTRY') || isSuperAdmin()): ?>
                    <a href="qc_entry.php" class="flex items-center p-4 hover:bg-slate-800/50 rounded-2xl transition group text-slate-500 hover:text-white">
                        <span class="text-[10px] uppercase font-black tracking-widest group-hover:translate-x-1 transition-transform">📝 New Entry</span>
                    </a>
                    <?php endif; ?>

                    <?php if(canAccess('QC_QUEUE') || isSuperAdmin()): ?>
                    <a href="qc_queue.php" class="flex items-center justify-between p-4 hover:bg-slate-800/50 rounded-2xl transition group text-slate-500 hover:text-white">
                        <span class="text-[10px] uppercase font-black tracking-widest group-hover:translate-x-1 transition-transform">📊 Live Queue</span>
                        <span class="text-[8px] bg-slate-800 px-2 py-1 rounded-md text-slate-400 font-mono group-hover:bg-red-600 group-hover:text-white transition-colors">LIVE</span>
                    </a>
                    <?php endif; ?>

                    <?php if(canAccess('REPORT_ENGINE') || isSuperAdmin()): ?>
                    <a href="print_records.php" class="flex items-center p-4 hover:bg-slate-800/50 rounded-2xl transition group text-slate-500 hover:text-white">
                        <span class="text-[10px] uppercase font-black tracking-widest group-hover:translate-x-1 transition-transform">📄 Report Engine</span>
                    </a>
                    <?php endif; ?>

                    <?php if(canAccess('SUPPLIER_MGMT') || isSuperAdmin()): ?>
                    <div class="pt-4 border-t border-slate-800/50 mt-4">
                        <p class="text-[8px] font-black text-slate-600 uppercase tracking-widest px-4 mb-2">Internal Tools</p>
                        <a href="supplier_manager.php" class="flex items-center p-3 hover:bg-slate-800/50 rounded-xl transition group text-slate-500 hover:text-white">
                            <span class="text-[10px] uppercase font-black tracking-widest">🏭 Suppliers</span>
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if(isSuperAdmin() || canAccess('MODE_MGMT') || canAccess('REASON_MGMT') || canAccess('USER_MGMT') || canAccess('PWD_MGMT')): ?>
                    <div class="pt-4 border-t border-slate-800/50 mt-2">
                        <p class="text-[8px] font-black text-slate-600 uppercase tracking-widest px-4 mb-2">⚙ System Admin</p>
                        
                        <?php if(canAccess('MODE_MGMT') || isSuperAdmin()): ?>
                        <a href="qc_add_mode.php" class="flex items-center p-3 hover:bg-slate-800/50 rounded-xl transition group text-slate-400 hover:text-white">
                            <span class="text-[10px] font-bold uppercase tracking-wider">➕ Add Workflow Mode</span>
                        </a>
                        <?php endif; ?>
                        
                        <?php if(canAccess('REASON_MGMT') || isSuperAdmin()): ?>
                        <a href="qc_add_reason.php" class="flex items-center p-3 hover:bg-slate-800/50 rounded-xl transition group text-slate-400 hover:text-white">
                            <span class="text-[10px] font-bold uppercase tracking-wider">➕ Add Damage Reason</span>
                        </a>
                        <?php endif; ?>
                        
                        <?php if(canAccess('USER_MGMT') || isSuperAdmin()): ?>
                        <a href="users.php" class="flex items-center p-3 hover:bg-slate-800/50 rounded-xl transition group text-slate-400 hover:text-white">
                            <span class="text-[10px] font-bold uppercase tracking-wider">👥 User Management</span>
                        </a>
                        <?php endif; ?>
                        
                        <?php if(canAccess('PWD_MGMT') || isSuperAdmin()): ?>
                        <a href="qc_change_password.php" class="flex items-center p-3 hover:bg-slate-800/50 rounded-xl transition group text-slate-400 hover:text-white">
                            <span class="text-[10px] font-bold uppercase tracking-wider">🔐 Change Password</span>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </nav>

                <div class="p-6 bg-slate-900/50 border-t border-slate-800">
                    <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 rounded-xl bg-red-600 flex items-center justify-center text-white font-black italic shadow-lg shadow-red-600/20 uppercase">
                            <?= substr($_SESSION['username'] ?? 'U', 0, 1) ?>
                        </div>
                        <div class="overflow-hidden">
                            <p class="text-[10px] font-black text-white uppercase truncate"><?= htmlspecialchars($_SESSION['username'] ?? 'Guest') ?></p>
                            <p class="text-[9px] text-red-500 font-bold uppercase italic"><?= htmlspecialchars($_SESSION['role'] ?? 'Standard') ?></p>
                            <?php if(isSuperAdmin()): ?>
                                <span class="text-[8px] bg-red-600/20 text-red-500 px-2 py-0.5 rounded-full mt-1 inline-block">SUPER ADMIN</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-800/50">
                        <p class="text-[8px] text-slate-600">Last Login: <?= date('d M Y H:i', strtotime($userLoginInfo['last_login'] ?? 'N/A')) ?></p>
                        <p class="text-[8px] text-slate-600">Total Logins: <?= $userLoginInfo['total_logins'] ?? 0 ?></p>
                    </div>
                    <a href="logout.php" class="mt-4 text-[9px] font-black uppercase text-slate-600 hover:text-red-500 tracking-tighter block transition">🔒 End Session</a>
                </div>
            </aside>

            <!-- MAIN CONTENT LAYER -->
            <main class="flex-1 ml-72 p-10">
                <header class="flex justify-between items-start mb-12 no-print">
                    <div>
                        <h1 class="text-6xl font-black text-white italic tracking-tighter uppercase leading-none">Quality <span class="text-red-600">Control</span></h1>
                        <p class="text-slate-500 text-[10px] font-black uppercase tracking-[0.4em] mt-3 flex items-center">
                            <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                            Welcome back, <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
                        </p>
                    </div>
                    <div class="bg-slate-900/80 px-10 py-6 rounded-[2.5rem] border border-slate-800 text-right shadow-2xl glass-card">
                        <p class="text-4xl font-black text-red-600 tracking-tighter italic" id="clock">00:00:00</p>
                        <p class="text-[9px] text-slate-500 font-black uppercase tracking-widest mt-1"><?= date('D, d M Y') ?></p>
                    </div>
                </header>

                <!-- STATS CARDS -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10 no-print">
                    <div class="stat-card bg-gradient-to-br from-red-600/20 to-transparent p-8 rounded-[2.5rem] border border-red-900/30 shadow-xl relative overflow-hidden group">
                        <div class="relative z-10">
                            <p class="text-[10px] font-black text-red-500 uppercase tracking-widest">Today's Volume</p>
                            <h3 class="text-5xl font-black text-white italic mt-2"><?= $todayCount ?></h3>
                        </div>
                        <div class="absolute -right-4 -bottom-4 text-8xl font-black text-white/5 italic group-hover:scale-110 transition-transform">QC</div>
                    </div>

                    <div class="stat-card bg-gradient-to-br from-blue-600/20 to-transparent p-8 rounded-[2.5rem] border border-blue-900/30 shadow-xl relative overflow-hidden group">
                        <div class="relative z-10">
                            <p class="text-[10px] font-black text-blue-500 uppercase tracking-widest">Pending Handover</p>
                            <h3 class="text-5xl font-black text-white italic mt-2"><?= $pendingCount ?></h3>
                        </div>
                        <div class="absolute -right-4 -bottom-4 text-8xl font-black text-white/5 italic group-hover:scale-110 transition-transform">PND</div>
                    </div>

                    <div class="stat-card bg-gradient-to-br from-green-600/20 to-transparent p-8 rounded-[2.5rem] border border-green-900/30 shadow-xl relative overflow-hidden group">
                        <div class="relative z-10">
                            <p class="text-[10px] font-black text-green-500 uppercase tracking-widest">This Week</p>
                            <h3 class="text-5xl font-black text-white italic mt-2"><?= $weekCount ?></h3>
                        </div>
                        <div class="absolute -right-4 -bottom-4 text-8xl font-black text-white/5 italic group-hover:scale-110 transition-transform">WK</div>
                    </div>

                    <div class="stat-card bg-gradient-to-br from-purple-600/20 to-transparent p-8 rounded-[2.5rem] border border-purple-900/30 shadow-xl relative overflow-hidden group">
                        <div class="relative z-10">
                            <p class="text-[10px] font-black text-purple-500 uppercase tracking-widest">Total Records</p>
                            <h3 class="text-5xl font-black text-white italic mt-2"><?= number_format($totalRecords) ?></h3>
                        </div>
                        <div class="absolute -right-4 -bottom-4 text-8xl font-black text-white/5 italic group-hover:scale-110 transition-transform">ALL</div>
                    </div>
                </div>

                <!-- SEARCH BOX -->
                <div class="mb-10 no-print">
                    <form method="GET" class="flex gap-4 bg-slate-900/50 p-4 rounded-[2.5rem] border border-slate-800 backdrop-blur-xl shadow-2xl">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Ref #, Invoice, Doc #, Supplier, Mode..." 
                               class="flex-1 bg-transparent border-none text-white px-6 focus:ring-0 font-black placeholder:text-slate-700 text-sm outline-none">
                        <input type="date" name="search_date" value="<?= $searchDate ?>" 
                               class="bg-slate-800 rounded-2xl px-6 text-[10px] font-black text-white border-none outline-none focus:bg-red-600 transition uppercase">
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-10 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg shadow-red-600/20 active:scale-95">🔍 Apply</button>
                        <?php if($search || $searchDate): ?>
                            <a href="dashboard.php" class="bg-slate-800 text-slate-400 flex items-center px-6 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:text-white transition">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- DATA GRID -->
                <div class="bg-[#0f172a]/80 rounded-[3.5rem] border border-slate-800 overflow-hidden shadow-2xl backdrop-blur-md">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="text-[9px] uppercase font-black text-slate-500 bg-slate-900/80 border-b border-slate-800">
                                <tr>
                                    <th class="px-10 py-6">Transaction / Identity</th>
                                    <th class="px-10 py-6">Documentation Ledger</th>
                                    <th class="px-10 py-6">Logistics Sequence Status</th>
                                    <th class="px-10 py-6 text-right">Quick Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/40">
                                <?php if(count($recentRecords) > 0): ?>
                                    <?php foreach($recentRecords as $row): ?>
                                    <tr class="hover:bg-red-600/[0.03] transition-all group cursor-pointer" onclick="viewAudit(<?= $row['record_id'] ?>)">
                                        <td class="px-10 py-8">
                                            <p class="text-white font-black text-xl tracking-tighter group-hover:text-red-500 transition">#<?= htmlspecialchars($row['reference_number']) ?></p>
                                            <p class="text-[10px] text-slate-600 font-bold uppercase mt-1">Date: <?= date('d M Y', strtotime($row['record_date'])) ?></p>
                                        </td>
                                        <td class="px-10 py-8">
                                            <p class="text-slate-300 font-black text-[11px] uppercase tracking-wider italic">INV: <?= htmlspecialchars($row['invoice_number']) ?></p>
                                            <p class="text-[10px] text-slate-400 font-mono mt-0.5">DOC: <?= htmlspecialchars($row['doc_number'] ?? 'N/A') ?></p>
                                            <p class="text-[10px] text-red-500 font-bold uppercase mt-1"><?= htmlspecialchars($row['supplier_name']) ?></p>
                                        </td>
                                        <td class="px-10 py-8">
                                            <div class="flex items-center gap-1">
                                                <?php 
                                                $steps = [
                                                    ['col' => 'is_informed', 'label' => 'Informed', 'color' => 'bg-green-500', 'title' => 'Supplier Informed'],
                                                    ['col' => 'is_store_received', 'label' => 'Store', 'color' => 'bg-blue-500', 'title' => 'Store Received'],
                                                    ['col' => 'is_gate_cleared', 'label' => 'Gate', 'color' => 'bg-yellow-500', 'title' => 'Gate Cleared'],
                                                    ['col' => 'is_handover_complete', 'label' => 'Return', 'color' => 'bg-red-500', 'title' => 'Return Complete']
                                                ];
                                                foreach($steps as $index => $step): 
                                                    $active = $row[$step['col']];
                                                ?>
                                                    <div class="relative group">
                                                        <div onclick="event.stopPropagation(); updateFlag(event, <?= $row['record_id'] ?>, '<?= $step['col'] ?>')" 
                                                             title="<?= $step['title'] ?>"
                                                             class="w-8 h-8 rounded-lg border-2 transition-all cursor-pointer flex items-center justify-center
                                                             <?= $active ? "$step[color] border-transparent text-white dot-active" : "border-slate-800 bg-slate-900 text-transparent hover:border-slate-600" ?>">
                                                             <span class="text-[12px] font-bold">✓</span>
                                                        </div>
                                                        <span class="absolute -bottom-6 left-1/2 transform -translate-x-1/2 text-[8px] text-slate-600 whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity">
                                                            <?= $step['label'] ?>
                                                        </span>
                                                    </div>
                                                    <?php if($index < 3): ?>
                                                        <div class="sequence-line <?= $active ? 'bg-slate-700' : 'bg-slate-900' ?>"></div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="mt-3 text-[9px] font-mono">
                                                <?php 
                                                if($row['is_handover_complete']):
                                                    echo '<span class="text-green-500">✓ Complete</span>';
                                                elseif($row['is_gate_cleared']):
                                                    echo '<span class="text-yellow-500">⏳ Gate Cleared</span>';
                                                elseif($row['is_store_received']):
                                                    echo '<span class="text-blue-500">📦 Store Received</span>';
                                                elseif($row['is_informed']):
                                                    echo '<span class="text-green-500">📧 Supplier Informed</span>';
                                                else:
                                                    echo '<span class="text-slate-600">⏰ Pending Start</span>';
                                                endif;
                                                ?>
                                            </div>
                                        </td>
                                        <td class="px-10 py-8 text-right">
                                            <button onclick="event.stopPropagation(); printRecord(<?= $row['record_id'] ?>)" 
                                                    class="text-[10px] font-black uppercase text-slate-600 group-hover:text-blue-500 tracking-widest border border-slate-800 group-hover:border-blue-600/30 px-6 py-3 rounded-xl transition-all mr-2">
                                                🖨 Print Report
                                            </button>
                                            <button onclick="event.stopPropagation(); viewAudit(<?= $row['record_id'] ?>)" 
                                                    class="text-[10px] font-black uppercase text-slate-600 group-hover:text-red-500 tracking-widest border border-slate-800 group-hover:border-red-600/30 px-6 py-3 rounded-xl transition-all">
                                                🔍 Audit View
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-10 py-20 text-center">
                                            <div class="text-center">
                                                <svg class="w-16 h-16 mx-auto text-slate-700 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                                <p class="text-slate-500 text-lg font-black uppercase tracking-wider">No records found</p>
                                                <p class="text-slate-600 text-xs mt-2">Try adjusting your search criteria</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- PAGINATION -->
                <?php if($totalPages > 1): ?>
                <div class="mt-10 flex justify-between items-center px-6 no-print">
                    <p class="text-[10px] font-black text-slate-600 uppercase tracking-widest italic">Page <?= $page ?> of <?= $totalPages ?></p>
                    <div class="flex gap-3">
                        <?php if($page > 1): ?>
                            <a href="?page=<?= $page-1 ?>&<?= $baseQuery ?>" class="px-6 py-3 rounded-xl bg-slate-900 border border-slate-800 text-[10px] font-black uppercase text-slate-500 hover:text-white transition">◀ Prev</a>
                        <?php endif; ?>
                        
                        <div class="flex gap-1">
                            <?php 
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);
                            for($i = $start; $i <= $end; $i++): ?>
                                <a href="?page=<?= $i ?>&<?= $baseQuery ?>" 
                                   class="w-10 h-10 flex items-center justify-center rounded-xl text-[10px] font-black transition-all 
                                   <?= $i == $page ? 'bg-red-600 text-white shadow-xl shadow-red-600/30 scale-110 z-10' : 'bg-slate-900 text-slate-600 hover:bg-slate-800' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                        </div>

                        <?php if($page < $totalPages): ?>
                            <a href="?page=<?= $page+1 ?>&<?= $baseQuery ?>" class="px-6 py-3 rounded-xl bg-slate-900 border border-slate-800 text-[10px] font-black uppercase text-slate-500 hover:text-white transition">Next ▶</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>

        <!-- FOOTER -->
        <footer class="footer-gradient border-t border-slate-800 no-print ml-72">
            <div class="max-w-full px-10 py-6">
                <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                    <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-red-600/20 rounded-xl flex items-center justify-center">
                            <span class="text-red-500 font-black text-xs">VX</span>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-400 font-black uppercase tracking-wider">
                                Developed by <span class="text-red-500">Vexel IT</span> | System Architect: Kavizz
                            </p>
                            <p class="text-[8px] text-slate-600 font-mono mt-1">
                                ASB Fashion Quality Control & Return Management System v4.0
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-6">
                        <a href="https://vexelit.xyz" target="_blank" class="text-[10px] text-slate-500 hover:text-red-500 transition font-black uppercase tracking-wider flex items-center gap-2">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.66 0 3-4 3-9s-1.34-9-3-9m0 18c-1.66 0-3-4-3-9s1.34-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                            vexelit.xyz
                        </a>
                        <a href="mailto:vexelit.sl@gmail.com" class="text-[10px] text-slate-500 hover:text-red-500 transition font-black uppercase tracking-wider flex items-center gap-2">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            vexelit.sl@gmail.com
                        </a>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <p class="text-[7px] text-slate-700 font-mono tracking-wider">
                        &copy; <?= date('Y') ?> Vexel IT Solutions. All rights reserved.
                    </p>
                </div>
            </div>
        </footer>
    </div>

    <!-- DETAIL MODAL -->
    <div id="detailModal" class="fixed inset-0 bg-black/95 backdrop-blur-3xl z-50 hidden flex items-center justify-center p-4">
        <div class="bg-[#0f172a] border border-slate-800 w-full max-w-6xl max-h-[90vh] overflow-hidden rounded-[4rem] shadow-2xl flex flex-col relative">
            <div class="p-12 pb-0 flex justify-between items-start no-print">
                <div>
                    <p id="mDate" class="text-red-500 text-[10px] font-black uppercase tracking-[0.4em] mb-2 italic"></p>
                    <h2 id="modalRef" class="text-6xl font-black text-white italic uppercase tracking-tighter"></h2>
                </div>
                <button onclick="closeModal()" class="bg-slate-800 hover:bg-red-600 w-16 h-16 rounded-[2rem] flex items-center justify-center text-white transition-all shadow-xl rotate-45 hover:rotate-90">
                    <span class="rotate-[-45deg]">✕</span>
                </button>
            </div>

            <div class="p-12 overflow-y-auto custom-scroll">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-12" id="printableArea">
                    <div class="space-y-6">
                        <section class="bg-slate-900/50 p-8 rounded-[3rem] border border-slate-800">
                            <h4 class="text-slate-500 text-[9px] font-black uppercase tracking-[0.3em] mb-4 italic underline decoration-red-600 underline-offset-8">Supplier Profile</h4>
                            <p id="mSupplier" class="text-2xl font-black text-white italic uppercase leading-tight"></p>
                            <div class="mt-4 space-y-1">
                                <p id="mEmail" class="text-slate-400 font-mono text-[10px] flex items-center truncate"></p>
                                <p id="mPhone" class="text-slate-400 font-mono text-[10px] flex items-center"></p>
                                <p id="mAddress" class="text-slate-400 font-mono text-[10px] flex items-center"></p>
                            </div>
                        </section>
                        <section class="bg-slate-900/50 p-6 rounded-2xl border border-slate-800/60 text-xs">
                            <div class="mb-2"><b>DOC NO:</b> <span id="mDocNo" class="font-mono text-white"></span></div>
                            <div class="mb-2"><b>INVOICE NO:</b> <span id="mInvoiceNo" class="font-mono text-white"></span></div>
                            <div class="mb-2"><b>AUDIT MODE:</b> <span id="mMode" class="text-red-500 font-bold uppercase"></span></div>
                            <div><b>REASON STATEMENT:</b> <p id="mReason" class="text-slate-400 italic mt-1 bg-slate-950 p-3 rounded-lg border border-slate-800"></p></div>
                        </section>
                        <div class="grid gap-3 no-print">
                            <button id="btnEmail" onclick="handleComms('email')" class="w-full py-4 rounded-xl font-black text-[10px] uppercase tracking-widest transition-all">📧 Send Email</button>
                            <button id="btnSMS" onclick="handleComms('sms')" class="w-full py-4 rounded-xl font-black text-[10px] uppercase tracking-widest transition-all">📱 Send SMS</button>
                        </div>
                    </div>

                    <div class="bg-slate-900/30 p-10 rounded-[3.5rem] border border-slate-800 shadow-inner">
                        <h4 class="text-slate-500 text-[9px] font-black uppercase mb-8 tracking-[0.3em] italic border-b border-slate-800 pb-4">Activity Audit Trail</h4>
                        <div id="logisticsTrail" class="space-y-8"></div>
                    </div>

                    <div class="bg-slate-900/30 p-10 rounded-[3.5rem] border border-slate-800 shadow-inner">
                        <h4 class="text-red-500 text-[9px] font-black uppercase mb-8 tracking-[0.3em] italic border-b border-slate-800 pb-4">Damage Inventory</h4>
                        <div id="mItems" class="space-y-3"></div>
                    </div>
                </div>
            </div>

            <div class="p-12 pt-0 no-print flex justify-end gap-4 mt-auto">
                <button onclick="printReportFromModal()" class="bg-blue-600 text-white px-12 py-5 rounded-[2rem] text-[10px] font-black uppercase tracking-[0.2em] hover:bg-blue-700 transition-all shadow-2xl">🖨 Print Report</button>
                <button onclick="window.print()" class="bg-white text-black px-12 py-5 rounded-[2rem] text-[10px] font-black uppercase tracking-[0.2em] hover:bg-red-600 hover:text-white transition-all shadow-2xl">📄 Print This View</button>
            </div>
        </div>
    </div>

    <!-- PRINT REPORT MODAL - A4 Format -->
    <div id="printReportModal" class="fixed inset-0 bg-black/95 backdrop-blur-3xl z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-[2rem] w-full max-w-[210mm] max-h-[90vh] overflow-y-auto shadow-2xl relative print-container">
            <div class="sticky top-0 bg-white p-4 border-b border-gray-200 no-print">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-bold text-gray-800">Print Preview - A4 Report</h3>
                    <div>
                        <button onclick="window.print()" class="bg-blue-600 text-white px-6 py-2 rounded-lg mr-2 hover:bg-blue-700 transition">🖨 Print</button>
                        <button onclick="closePrintModal()" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition">✖ Close</button>
                    </div>
                </div>
            </div>
            <div id="printReportContent">
                <!-- Dynamic print content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        let currentPrintData = null;

        function updateTime() {
            const options = { timeZone: 'Asia/Colombo', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
            document.getElementById('clock').innerText = new Intl.DateTimeFormat('en-GB', options).format(new Date());
        }
        setInterval(updateTime, 1000);
        updateTime();

        async function printRecord(id) {
            try {
                const res = await fetch(`get_details.php?id=${id}`);
                const data = await res.json();
                currentPrintData = data;
                
                const printContent = generatePrintHTML(data);
                document.getElementById('printReportContent').innerHTML = printContent;
                document.getElementById('printReportModal').classList.remove('hidden');
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Could not load report data.', background: '#0f172a', color: '#fff' });
            }
        }

        function generatePrintHTML(data) {
            const main = data.main;
            const items = data.items;
            
            const statusSteps = [
                { label: 'Supplier Informed', field: 'is_informed', user: main.informed_by_user, time: main.informed_datetime, icon: '📧' },
                { label: 'Store Received', field: 'is_store_received', user: main.store_user, time: main.store_datetime, icon: '📦' },
                { label: 'Gate Cleared', field: 'is_gate_cleared', user: main.gate_user, time: main.gate_datetime, icon: '🚪' },
                { label: 'Return Complete', field: 'is_handover_complete', user: main.handover_user, time: main.handover_datetime, icon: '✅' }
            ];

            return `
                <div class="print-container" style="padding: 15mm; font-family: Arial, sans-serif;">
                    <!-- Header -->
                    <div class="print-header" style="text-align: center; border-bottom: 3px solid #dc2626; padding-bottom: 15px; margin-bottom: 20px;">
                        <h1 style="font-size: 28px; font-weight: bold; margin: 0; color: #1f2937;">ASB FASHION</h1>
                        <p style="font-size: 14px; margin: 5px 0; color: #dc2626; font-weight: bold;">Quality Control & Return Management System</p>
                        <p style="font-size: 11px; color: #6b7280; margin: 5px 0;">QC Damage Report - Official Document</p>
                    </div>

                    <!-- Reference Info -->
                    <div class="print-section" style="margin-bottom: 25px;">
                        <div class="print-section-title" style="background: #f3f4f6; padding: 8px 12px; border-left: 4px solid #dc2626; font-weight: bold; margin-bottom: 15px;">
                            📋 DOCUMENT INFORMATION
                        </div>
                        <div class="print-info-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            <div class="print-info-item" style="padding: 6px 0; border-bottom: 1px solid #e5e7eb;">
                                <span class="print-label" style="font-weight: bold;">Reference Number:</span>
                                <span class="print-value" style="margin-left: 10px;">${main.reference_number || 'N/A'}</span>
                            </div>
                            <div class="print-info-item" style="padding: 6px 0; border-bottom: 1px solid #e5e7eb;">
                                <span class="print-label" style="font-weight: bold;">Record Date:</span>
                                <span class="print-value" style="margin-left: 10px;">${main.record_date || 'N/A'}</span>
                            </div>
                            <div class="print-info-item" style="padding: 6px 0; border-bottom: 1px solid #e5e7eb;">
                                <span class="print-label" style="font-weight: bold;">Invoice Number:</span>
                                <span class="print-value" style="margin-left: 10px;">${main.invoice_number || 'N/A'}</span>
                            </div>
                            <div class="print-info-item" style="padding: 6px 0; border-bottom: 1px solid #e5e7eb;">
                                <span class="print-label" style="font-weight: bold;">Document Number:</span>
                                <span class="print-value" style="margin-left: 10px;">${main.doc_number || 'N/A'}</span>
                            </div>
                            <div class="print-info-item" style="padding: 6px 0; border-bottom: 1px solid #e5e7eb;">
                                <span class="print-label" style="font-weight: bold;">Workflow Mode:</span>
                                <span class="print-value" style="margin-left: 10px;">${main.mode_name || 'Standard'}</span>
                            </div>
                            <div class="print-info-item" style="padding: 6px 0; border-bottom: 1px solid #e5e7eb;">
                                <span class="print-label" style="font-weight: bold;">Damage Reason:</span>
                                <span class="print-value" style="margin-left: 10px;">${main.reason_name || 'Not Specified'}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Supplier Information -->
                    <div class="print-section" style="margin-bottom: 25px;">
                        <div class="print-section-title" style="background: #f3f4f6; padding: 8px 12px; border-left: 4px solid #dc2626; font-weight: bold; margin-bottom: 15px;">
                            🏭 SUPPLIER INFORMATION
                        </div>
                        <div class="print-info-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            <div class="print-info-item" style="padding: 6px 0; border-bottom: 1px solid #e5e7eb;">
                                <span class="print-label" style="font-weight: bold;">Supplier Name:</span>
                                <span class="print-value" style="margin-left: 10px;">${main.supplier_name || 'N/A'}</span>
                            </div>
                            <div class="print-info-item" style="padding: 6px 0; border-bottom: 1px solid #e5e7eb;">
                                <span class="print-label" style="font-weight: bold;">Email:</span>
                                <span class="print-value" style="margin-left: 10px;">${main.email || 'N/A'}</span>
                            </div>
                            <div class="print-info-item" style="padding: 6px 0; border-bottom: 1px solid #e5e7eb;">
                                <span class="print-label" style="font-weight: bold;">Contact Number:</span>
                                <span class="print-value" style="margin-left: 10px;">${main.contact_number || 'N/A'}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Status Flags -->
                    <div class="print-section" style="margin-bottom: 25px;">
                        <div class="print-section-title" style="background: #f3f4f6; padding: 8px 12px; border-left: 4px solid #dc2626; font-weight: bold; margin-bottom: 15px;">
                            🚦 LOGISTICS STATUS FLAGS
                        </div>
                        <table class="print-status-table" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr><th style="background: #f3f4f6; padding: 10px; border: 1px solid #d1d5db; text-align: left;">Step</th>
                                    <th style="background: #f3f4f6; padding: 10px; border: 1px solid #d1d5db; text-align: left;">Status</th>
                                    <th style="background: #f3f4f6; padding: 10px; border: 1px solid #d1d5db; text-align: left;">Processed By</th>
                                    <th style="background: #f3f4f6; padding: 10px; border: 1px solid #d1d5db; text-align: left;">Date & Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${statusSteps.map(step => `
                                    <tr>
                                        <td style="padding: 8px; border: 1px solid #d1d5db;">${step.icon} ${step.label}</td>
                                        <td style="padding: 8px; border: 1px solid #d1d5db;">
                                            <span class="${main[step.field] ? 'print-status-completed' : 'print-status-pending'}" style="${main[step.field] ? 'color: #16a34a; font-weight: bold;' : 'color: #dc2626; font-weight: bold;'}">
                                                ${main[step.field] ? '✓ COMPLETED' : '○ PENDING'}
                                            </span>
                                        </td>
                                        <td style="padding: 8px; border: 1px solid #d1d5db;">${step.user || '—'}</td>
                                        <td style="padding: 8px; border: 1px solid #d1d5db;">${step.time ? new Date(step.time).toLocaleString() : '—'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>

                    <!-- Damage Items -->
                    <div class="print-section" style="margin-bottom: 25px;">
                        <div class="print-section-title" style="background: #f3f4f6; padding: 8px 12px; border-left: 4px solid #dc2626; font-weight: bold; margin-bottom: 15px;">
                            📦 DAMAGE INVENTORY
                        </div>
                        <table class="print-status-table" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr><th style="background: #f3f4f6; padding: 10px; border: 1px solid #d1d5db; text-align: left;">Item Code</th>
                                    <th style="background: #f3f4f6; padding: 10px; border: 1px solid #d1d5db; text-align: left;">Product Description</th>
                                    <th style="background: #f3f4f6; padding: 10px; border: 1px solid #d1d5db; text-align: center;">Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${items && items.length > 0 ? items.map(item => `
                                    <tr>
                                        <td style="padding: 8px; border: 1px solid #d1d5db;">${item.item_code || 'N/A'}</td>
                                        <td style="padding: 8px; border: 1px solid #d1d5db;">${item.description || 'No description'}</td>
                                        <td style="padding: 8px; border: 1px solid #d1d5db; text-align: center;">${item.quantity || 0}</td>
                                    </tr>
                                `).join('') : `
                                    <tr><td colspan="3" style="padding: 8px; text-align: center;">No items recorded</td></tr>
                                `}
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer & Signatures -->
                    <div class="print-footer" style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
                        <div class="print-signature" style="display: flex; justify-content: space-between; margin-top: 20px;">
                            <div class="print-signature-line" style="text-align: center;">
                                <div style="border-top: 1px solid black; width: 200px; margin-top: 30px; padding-top: 5px;"></div>
                                <p style="font-size: 10px; margin-top: 5px;">QC Officer Signature</p>
                            </div>
                            <div class="print-signature-line" style="text-align: center;">
                                <div style="border-top: 1px solid black; width: 200px; margin-top: 30px; padding-top: 5px;"></div>
                                <p style="font-size: 10px; margin-top: 5px;">Supplier Representative</p>
                            </div>
                            <div class="print-signature-line" style="text-align: center;">
                                <div style="border-top: 1px solid black; width: 200px; margin-top: 30px; padding-top: 5px;"></div>
                                <p style="font-size: 10px; margin-top: 5px;">Management Stamp</p>
                            </div>
                        </div>
                        <p style="text-align: center; font-size: 9px; color: #6b7280; margin-top: 20px;">
                            Generated on: ${new Date().toLocaleString()} | ASB Fashion Quality Control System v4.0
                        </p>
                        <p style="text-align: center; font-size: 8px; color: #9ca3af; margin-top: 10px;">
                            This is a system-generated document. No signature required for digital verification.
                        </p>
                    </div>
                </div>
            `;
        }

        function printReportFromModal() {
            window.print();
        }

        function closePrintModal() {
            document.getElementById('printReportModal').classList.add('hidden');
        }

        async function viewAudit(id) {
            try {
                const res = await fetch(`get_details.php?id=${id}`);
                const data = await res.json();
                window.currentRecord = data.main;
                
                document.getElementById('modalRef').innerText = data.main.reference_number;
                document.getElementById('mDate').innerText = "Recorded: " + data.main.record_date;
                document.getElementById('mSupplier').innerText = data.main.supplier_name;
                document.getElementById('mEmail').innerText = "✉ " + (data.main.email || "NOT SET");
                document.getElementById('mPhone').innerText = "📞 " + (data.main.contact_number || "NOT SET");
                document.getElementById('mAddress').innerText = "📍 " + (data.main.address || "NOT SET");
                document.getElementById('mDocNo').innerText = data.main.doc_number || "N/A";
                document.getElementById('mInvoiceNo').innerText = data.main.invoice_number || "N/A";
                document.getElementById('mMode').innerText = data.main.mode_name || "DEFAULT";
                document.getElementById('mReason').innerText = data.main.reason_name || "No reason flagged.";

                setupCommButton('btnEmail', data.main.email, data.main.is_informed);
                setupCommButton('btnSMS', data.main.contact_number, data.main.is_informed);

                const trails = [
                    {label: '📧 Supplier Informed', user: data.main.informed_by_user, date: data.main.informed_datetime},
                    {label: '📦 Store Received', user: data.main.store_user, date: data.main.store_datetime},
                    {label: '🚪 Gate Clearance', user: data.main.gate_user, date: data.main.gate_datetime},
                    {label: '✅ Final Handover', user: data.main.handover_user, date: data.main.handover_datetime}
                ];

                document.getElementById('logisticsTrail').innerHTML = trails.map(t => `
                    <div class="flex items-start gap-4">
                        <div class="w-2 h-2 rounded-full mt-1.5 ${t.user ? 'bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.5)]' : 'bg-slate-800'}"></div>
                        <div class="flex-1">
                            <p class="text-[9px] font-black uppercase tracking-widest ${t.user ? 'text-white' : 'text-slate-600'}">${t.label}</p>
                            <p class="text-[10px] font-bold text-slate-500 mt-1">${t.user || '⏳ WAITING...'}</p>
                            ${t.date ? `<p class="text-[8px] font-mono text-slate-700 mt-0.5">${t.date}</p>` : ''}
                        </div>
                    </div>
                `).join('');

                document.getElementById('mItems').innerHTML = data.items.map(i => `
                    <div class="flex justify-between items-center bg-slate-900 p-4 rounded-2xl border border-slate-800/50">
                        <div><p class="text-white font-black text-xs uppercase">📦 ${i.item_code}</p></div>
                        <span class="bg-red-600/10 text-red-500 px-4 py-2 rounded-xl font-black text-xs">QTY: ${i.quantity}</span>
                    </div>
                `).join('');

                document.getElementById('detailModal').classList.remove('hidden');
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Could not load record details.', background: '#0f172a', color: '#fff' });
            }
        }

async function updateFlag(event, id, column) {
    event.stopPropagation();
    event.preventDefault();
    
    const columnNames = {
        'is_informed': 'Supplier Informed',
        'is_store_received': 'Store Received',
        'is_gate_cleared': 'Gate Cleared',
        'is_handover_complete': 'Return Complete'
    };
    const displayName = columnNames[column] || column;
    
    const result = await Swal.fire({
        title: 'Update Status?',
        html: `Mark <strong>${displayName}</strong> as complete?`,
        icon: 'question',
        showCancelButton: true,
        background: '#0f172a',
        color: '#fff',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#1e293b',
        confirmButtonText: 'Yes, Update',
        cancelButtonText: 'Cancel'
    });
    
    if (!result.isConfirmed) return;

    // Show loading
    Swal.fire({
        title: 'Updating...',
        text: 'Please wait',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        const formData = new URLSearchParams();
        formData.append('id', id);
        formData.append('column', column);
        
        const res = await fetch('update_flag.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString()
        });
        
        const data = await res.json();
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Updated!',
                text: data.message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 1500,
                background: '#0f172a',
                color: '#fff'
            });
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Failed',
                text: data.message,
                background: '#0f172a',
                color: '#fff'
            });
        }
    } catch (err) {
        console.error('Error:', err);
        Swal.fire({
            icon: 'error',
            title: 'System Error',
            text: err.message || 'An error occurred. Please try again.',
            background: '#0f172a',
            color: '#fff'
        });
    }
}
        function setupCommButton(btnId, hasValue, isSent) {
            const btn = document.getElementById(btnId);
            if (!hasValue || hasValue === "NOT SET") {
                btn.disabled = true; 
                btn.innerText = "⚠ NO DATA";
                btn.className = "w-full py-4 rounded-xl bg-slate-800/50 text-slate-700 cursor-not-allowed text-[10px] font-black uppercase tracking-widest";
            } else {
                btn.disabled = false;
                btn.innerText = isSent == 1 ? "🔄 Resend" : "📤 Dispatch";
                btn.className = isSent == 1 ? "w-full py-4 rounded-xl border border-green-500/30 text-green-500 hover:bg-green-500 hover:text-white transition-all" : "w-full py-4 rounded-xl bg-red-600 text-white hover:bg-red-700 transition-all shadow-xl";
            }
        }

        async function handleComms(type) {
            const { value: pass } = await Swal.fire({ 
                title: 'Authorize', input: 'password', inputPlaceholder: 'System Key',
                background: '#0f172a', color: '#fff', confirmButtonColor: '#dc2626'
            });
            if (!pass) return;
            const p = new URLSearchParams(); 
            p.append('id', window.currentRecord.record_id); 
            p.append('type', type);
            p.append('auth_pass', pass);
            fetch('send_comms.php', { method: 'POST', body: p })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Sent!', background: '#0f172a', color: '#fff' }).then(() => viewAudit(window.currentRecord.record_id));
                } else {
                    Swal.fire({ icon: 'error', title: 'Failed', text: data.message, background: '#0f172a', color: '#fff' });
                }
            });
        }

        function closeModal() { 
            document.getElementById('detailModal').classList.add('hidden'); 
        }
        
        document.onkeydown = function(evt) { 
            if (evt.keyCode == 27) {
                closeModal();
                closePrintModal();
            }
        };
    </script>
</body>
</html>