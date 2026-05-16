<?php
require 'db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { 
    header("Location: index.php"); 
    exit; 
}

// --- FILTERS & SEARCH PIPELINE ---
$search = trim($_GET['search'] ?? '');
$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';

$where = " WHERE 1=1";
$params = [];

if (!empty($search)) {
    // Upgraded global filter logic to systematically process Document, Mode and Reason names
    $where .= " AND (m.reference_number LIKE ? OR m.invoice_number LIKE ? OR m.doc_number LIKE ? OR s.supplier_name LIKE ? OR mo.mode_name LIKE ? OR re.reason_name LIKE ?)";
    $term = "%$search%";
    array_push($params, $term, $term, $term, $term, $term, $term);
}

if (!empty($fromDate) && !empty($toDate)) {
    $where .= " AND m.record_date BETWEEN ? AND ?";
    $params[] = $fromDate;
    $params[] = $toDate;
}

// Highly performance-optimized query joining auxiliary business schemas
$query = "SELECT m.*, s.supplier_name, mo.mode_name, re.reason_name
          FROM qc_damage_main m 
          JOIN suppliers s ON m.supplier_id = s.supplier_id 
          LEFT JOIN qc_modes mo ON m.mode_id = mo.mode_id
          LEFT JOIN qc_reasons re ON m.reason_id = re.reason_id
          $where ORDER BY m.record_id DESC LIMIT 20";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ASB HUB | A6 Landscape Engine</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; background: #020617; color: #cbd5e1; }
        .glass { background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.05); }
        
        /* A6 Landscape Media Print Layout Matrix */
        @media print {
            @page { size: A6 landscape; margin: 0; }
            body * { visibility: hidden !important; }
            #printArea, #printArea * { visibility: visible !important; }
            #printArea { 
                display: block !important;
                position: absolute; left: 0; top: 0; 
                width: 148mm; height: 105mm; 
                padding: 6mm 8mm; background: white !important; color: black !important;
                box-sizing: border-box;
            }
            .no-print { display: none !important; }
            table { width: 100%; border-collapse: collapse; }
            th { text-transform: uppercase; font-size: 8px; border-bottom: 1px solid black; padding-bottom: 2px; }
            td { font-size: 8.5px; padding: 2.5px 0; border-bottom: 0.5px solid #e2e8f0; }
        }

        .item-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    </style>
</head>
<body class="p-8">

    <!-- Screen Control Hub View -->
    <div class="max-w-6xl mx-auto no-print">
        <div class="flex justify-between items-center mb-10">
            <div>
                <h1 class="text-4xl font-black text-white italic uppercase tracking-tighter">A6 <span class="text-red-600">Landscape</span></h1>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mt-1">QC System | Multi-Column Data Hub</p>
            </div>
            <a href="dashboard.php" class="bg-slate-900 border border-slate-800 px-6 py-3 rounded-xl text-[10px] font-black uppercase text-slate-400 tracking-wider">Dashboard</a>
        </div>

        <form class="glass p-6 rounded-[2rem] mb-8 flex flex-wrap gap-4">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search Ref / Invoice / Doc / Supplier / Mode..." class="flex-1 bg-slate-800/50 border-none rounded-xl px-5 text-sm text-white placeholder:text-slate-600 font-medium focus:ring-1 focus:ring-red-600 outline-none">
            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-8 py-3 rounded-xl text-[10px] font-black uppercase tracking-wider transition">Filter Matrix</button>
        </form>

        <div class="glass rounded-[2.5rem] overflow-hidden shadow-2xl">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-900/50 text-[9px] font-black uppercase text-slate-500 border-b border-slate-800">
                    <tr>
                        <th class="px-8 py-5">Reference Details</th>
                        <th class="px-8 py-5">Supplier & Context</th>
                        <th class="px-8 py-5">Audit Mode</th>
                        <th class="px-8 py-5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50 text-slate-300">
                    <?php foreach($records as $row): ?>
                    <tr class="hover:bg-slate-800/20 transition">
                        <td class="px-8 py-6">
                            <div class="font-black text-white italic text-base">#<?= $row['reference_number'] ?></div>
                            <div class="text-[10px] text-slate-500 font-mono mt-0.5">DOC: <?= htmlspecialchars($row['doc_number'] ?? 'N/A') ?></div>
                        </td>
                        <td class="px-8 py-6">
                            <div class="text-xs font-bold text-slate-200"><?= htmlspecialchars($row['supplier_name']) ?></div>
                            <div class="text-[10px] text-slate-500 italic mt-0.5 truncate max-w-xs" title="<?= htmlspecialchars($row['reason_name'] ?? '') ?>">Reason: <?= htmlspecialchars($row['reason_name'] ?? 'Not Specified') ?></div>
                        </td>
                        <td class="px-8 py-6">
                            <span class="bg-slate-900 border border-slate-800 px-2.5 py-1 rounded text-[9px] font-bold uppercase tracking-wider text-red-500">
                                <?= htmlspecialchars($row['mode_name'] ?? 'DEFAULT') ?>
                            </span>
                        </td>
                        <td class="px-8 py-6 text-right">
                            <button onclick="preparePrint(<?= $row['record_id'] ?>, <?= $row['print_count'] ?>)" class="bg-slate-800 hover:bg-red-600 text-white text-[9px] font-black uppercase tracking-widest px-5 py-2.5 rounded-xl transition-all">Print A6 Card</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- HIGH-DENSITY A6 LANDSCAPE PRINT EMBED -->
    <div id="printArea" class="hidden" style="font-family: 'Inter', sans-serif;">
        <!-- Header Ribbon Container -->
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid black; padding-bottom: 4px; margin-bottom: 6px;">
            <div>
                <h1 style="font-size: 15px; font-weight: 900; margin: 0; tracking-tighter: -0.05em;">ASB FASHION</h1>
                <p style="font-size: 11px; font-weight: 700; text-transform: uppercase; margin: 0; color: #000;">Damage Return Note</p>
            </div>
            <div style="text-align: right;">
                <svg id="barcode"></svg>
            </div>
        </div>
        
        <!-- Logistics Metadata Mapping Context -->
        <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 6px; font-size: 8.5px; margin-bottom: 6px;">
            <div style="border: 1px solid #000; padding: 4px; border-radius: 3px; line-height: 1.3;">
                <b>SUPPLIER:</b> <span id="pSupplier" style="text-transform: uppercase; font-weight: 700; "></span><br>
                <b>INV NO:</b> <span id="pInvoice"></span> | <b>DOC NO:</b> <span id="pDocNumber" style="font-family: monospace; font-weight: 700;"></span>
            </div>
            <div style="border: 1px solid #000; padding: 4px; border-radius: 3px; line-height: 1.3;">
                <b>DATE:</b> <span id="pDate"></span> | <b>REF:</b> <span id="pRef" style="font-weight: 700;"></span><br>
                <b>MODE:</b> <span id="pMode" style="text-transform: uppercase; font-weight: 900; color: #000;"></span>
            </div>
        </div>

        <!-- REASON STATEMENT BANNER AREA -->
        <div style="width: 100%; border: 1px dashed #000; background: #f8fafc; padding: 3px 5px; border-radius: 3px; font-size: 8px; margin-bottom: 6px; line-height: 1.2;">
            <b>REASON FOR RETURN:</b> <span id="pReason" style="font-style: italic;"></span>
        </div>

        <!-- 2-COLUMN SPLIT LEDGER DATA TABLE -->
        <div class="item-grid">
            <!-- Left Data Column -->
            <table id="tableLeft">
                <thead><tr><th style="text-align:left;">Item SKU / Code</th><th style="text-align:right;">Qty</th></tr></thead>
                <tbody id="pItemsLeft"></tbody>
            </table>
            <!-- Right Data Column -->
            <table id="tableRight">
                <thead><tr><th style="text-align:left;">Item SKU / Code</th><th style="text-align:right;">Qty</th></tr></thead>
                <tbody id="pItemsRight"></tbody>
            </table>
        </div>

        <!-- Print Execution Footer Area -->
        <div style="margin-top: auto; padding-top: 10px; display: grid; grid-template-columns: 1fr 1fr; gap: 30px; text-align: center; font-size: 8px;">
            <div><div style="border-bottom: 1px solid black; height: 12px;"></div><b style="letter-spacing: 0.05em;">AUTHORIZED AUDITOR</b></div>
            <div><div style="border-bottom: 1px solid black; height: 12px;"></div><b style="letter-spacing: 0.05em;">SUPPLIER ACKNOWLEDGEMENT</b></div>
        </div>
    </div>

    <!-- PROCESSING SCRIPTS RUNTIME ENGINE -->
    <script>
        async function preparePrint(id, currentCount) {
            if (currentCount > 0) {
                const { value: password } = await Swal.fire({
                    title: 'Security Notice', text: 'Reprint Authorization Required', input: 'password',
                    background: '#0f172a', color: '#fff', confirmButtonColor: '#dc2626'
                });
                if (!password) return;
                const auth = await fetch('verify_admin.php', { method: 'POST', body: new URLSearchParams({ 'pass': password }) });
                const authData = await auth.json();
                if (!authData.success) return Swal.fire('Access Denied', 'Invalid Passkey Provided', 'error');
            }

            Swal.fire({ title: 'Configuring Document Landscape...', didOpen: () => { Swal.showLoading(); } });

            try {
                // Fetch deep record schema via details API
                const res = await fetch(`get_details.php?id=${id}`);
                const data = await res.json();
                Swal.close();

                if(data.success) {
                    const ref = data.main.reference_number;
                    
                    // Bind target dataset strings down into HTML template targets
                    document.getElementById('pSupplier').innerText = data.main.supplier_name;
                    document.getElementById('pInvoice').innerText = data.main.invoice_number;
                    document.getElementById('pDocNumber').innerText = data.main.doc_number ? data.main.doc_number : 'N/A';
                    document.getElementById('pRef').innerText = '#' + ref;
                    document.getElementById('pDate').innerText = data.main.record_date;
                    document.getElementById('pMode').innerText = data.main.mode_name ? data.main.mode_name : 'DEFAULT';
                    document.getElementById('pReason').innerText = data.main.reason_name ? data.main.reason_name : 'No explicit reason specified by management.';

                    // Layout barcode rendering module
                    JsBarcode("#barcode", ref, { format: "CODE128", width: 1.1, height: 18, displayValue: false, margin: 0 });

                    // Execute dual column pagination mapping loops
                    let leftHtml = '';
                    let rightHtml = '';
                    const mid = Math.ceil(data.items.length / 2);

                    data.items.forEach((item, index) => {
                        const row = `<tr><td style="font-family: monospace; font-weight: 600;">${item.item_code}</td><td style="text-align:right; font-weight:900;">${parseInt(item.quantity).toLocaleString()}</td></tr>`;
                        if (index < mid) leftHtml += row;
                        else rightHtml += row;
                    });

                    document.getElementById('pItemsLeft').innerHTML = leftHtml;
                    document.getElementById('pItemsRight').innerHTML = rightHtml;

                    // Increment system print configuration array tracking values
                    await fetch('increment_print.php', { method: 'POST', body: new URLSearchParams({ 'id': id }) });

                    // Invoke operating system print interface pipeline
                    setTimeout(() => {
                        window.print();
                        window.location.reload();
                    }, 400);
                }
            } catch (err) { 
                Swal.fire('System Failure', 'Failed to compile data metrics tree.', 'error'); 
            }
        }
    </script>
</body>
</html>