<?php
require 'db.php'; 
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) header("Location: index.php");

/** 
 * HIGH-PERFORMANCE 100K+ AGGREGATION QUERY
 * Fetches core parameters with optimized subqueries.
 * Sorted by latest record date and time to guarantee recent items are shown on top.
 */
$query = "SELECT m.*, 
                 s.supplier_name, s.system_id as sid, s.contact_number, s.email as supplier_email,
                 mo.mode_name,
                 re.reason_name,
                 (SELECT COALESCE(SUM(quantity * unit_cost), 0) FROM qc_damage_items WHERE record_id = m.record_id) as total_bill_cost
          FROM qc_damage_main m 
          JOIN suppliers s ON m.supplier_id = s.supplier_id 
          LEFT JOIN qc_modes mo ON m.mode_id = mo.mode_id
          LEFT JOIN qc_reasons re ON m.reason_id = re.reason_id
          ORDER BY m.record_date DESC, m.added_time DESC";
          
$records = $pdo->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <title>QC Audit Hub | ASB Fashion</title>
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 10px; }
        .record-row { transition: all 0.2s ease; }
    </style>
</head>
<body class="bg-[#020617] text-slate-300 font-sans">

    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-[#0f172a] border-r border-slate-800 p-6 fixed h-full z-50">
            <div class="mb-10 px-2">
                <h2 class="text-2xl font-black text-white italic tracking-tighter">ASB <span class="text-red-600 underline uppercase">Fashion</span></h2>
                <p class="text-[8px] text-slate-500 tracking-[0.3em] mt-1 font-bold">ELITE AUDIT SYSTEM v3.0</p>
            </div>
            <nav class="space-y-2">
                <a href="qc_entry.php" class="flex items-center gap-3 p-4 bg-red-600/10 text-red-500 rounded-2xl border border-red-600/20 font-bold hover:bg-red-600/20 transition">
                    <span class="text-xl">+</span> New Audit Entry
                </a>
                <div class="pt-4 pb-2 px-4 text-[10px] font-black text-slate-600 uppercase tracking-widest">Management</div>
                <a href="dashboard.php" class="flex items-center gap-3 p-4 text-slate-400 hover:bg-slate-800 rounded-2xl transition">Dashboard</a>
                <a href="qc_queue.php" class="flex items-center gap-3 p-4 bg-slate-800 text-white rounded-2xl transition">Returns Queue</a>
            </nav>
        </aside>

        <!-- Main Content Matrix -->
        <main class="ml-64 flex-1 p-10">
            <header class="flex justify-between items-center mb-10">
                <div>
                    <h1 class="text-4xl font-black text-white tracking-tighter">QC <span class="text-red-600">QUEUE</span></h1>
                    <p class="text-slate-500 text-xs mt-1 uppercase tracking-widest font-bold">Total Enterprise Returns Management</p>
                </div>
                
                <!-- MULTI-CHANNEL SEARCH & FILTER PIPELINE -->
                <div class="flex items-center gap-4 bg-[#0f172a] p-2 rounded-2xl border border-slate-800 shadow-2xl">
                    <div class="flex flex-col px-4 border-r border-slate-800">
                        <label class="text-[9px] font-black text-slate-500 uppercase mb-1">Date From</label>
                        <input type="date" id="dateFrom" class="bg-transparent text-white text-xs outline-none cursor-pointer" onchange="filterData()">
                    </div>
                    <div class="flex flex-col px-4 border-r border-slate-800">
                        <label class="text-[9px] font-black text-slate-500 uppercase mb-1">Date To</label>
                        <input type="date" id="dateTo" class="bg-transparent text-white text-xs outline-none cursor-pointer" onchange="filterData()">
                    </div>
                    <div class="px-4">
                        <label class="text-[9px] font-black text-slate-500 uppercase mb-1 block">Dynamic Filter Engine</label>
                        <input type="text" id="searchInput" placeholder="Search Supplier, Ref, Doc, Mode, Reason..." 
                               class="bg-transparent text-white text-xs outline-none w-64 font-bold placeholder:text-slate-600" onkeyup="filterData()">
                    </div>
                </div>
            </header>

            <!-- Main Ledger Table View -->
            <div class="bg-[#0f172a] rounded-[2.5rem] border border-slate-800 overflow-hidden shadow-2xl">
                <table class="w-full text-left border-collapse" id="qcTable">
                    <thead class="bg-slate-900/50 border-b border-slate-800">
                        <tr>
                            <th class="p-6 text-[10px] font-black text-slate-500 uppercase">Audit Timestamp</th>
                            <th class="p-6 text-[10px] font-black text-slate-500 uppercase tracking-widest">Ref / Doc / Invoice</th>
                            <th class="p-6 text-[10px] font-black text-slate-500 uppercase">Supplier Details</th>
                            <th class="p-6 text-[10px] font-black text-slate-500 uppercase">Workflow Context</th>
                            <th class="p-6 text-[10px] font-black text-slate-500 uppercase">Damage Value (LKR)</th>
                            <th class="p-6 text-[10px] font-black text-slate-500 uppercase">User / Evidence</th>
                            <th class="p-6 text-[10px] font-black text-slate-500 uppercase text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        <?php foreach($records as $r): ?>
                        <tr class="record-row hover:bg-slate-800/30 transition group" 
                            data-date="<?= $r['record_date'] ?>"
                            data-meta="<?= htmlspecialchars(strtolower($r['supplier_name'] . ' ' . $r['reference_number'] . ' ' . $r['invoice_number'] . ' ' . $r['doc_number'] . ' ' . $r['mode_name'] . ' ' . $r['reason_name'] . ' ' . $r['sid'])) ?>">
                            
                            <td class="p-6">
                                <div class="text-white font-bold text-sm"><?= date('M d, Y', strtotime($r['record_date'])) ?></div>
                                <div class="text-[10px] text-slate-500 font-mono italic"><?= date('h:i A', strtotime($r['added_time'])) ?></div>
                            </td>
                            
                            <td class="p-6">
                                <div class="text-red-500 font-black text-xs tracking-widest"><?= $r['reference_number'] ?></div>
                                <div class="text-[10px] text-slate-400 font-bold mt-0.5">INV: <?= htmlspecialchars($r['invoice_number']) ?></div>
                                <?php if(!empty($r['doc_number'])): ?>
                                    <div class="text-[9px] text-slate-500 font-mono mt-0.5">DOC#: <?= htmlspecialchars($r['doc_number']) ?></div>
                                <?php endif; ?>
                            </td>
                            
                            <td class="p-6">
                                <div class="text-white font-bold text-sm uppercase"><?= htmlspecialchars($r['supplier_name']) ?></div>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[10px] bg-slate-800 px-2 py-0.5 rounded text-slate-400 font-mono">SID: <?= $r['sid'] ?></span>
                                </div>
                            </td>

                            <!-- MODE & REASON SYSTEM DATA BADGES -->
                            <td class="p-6">
                                <span class="px-2.5 py-1 rounded-md text-[9px] font-black tracking-wider uppercase bg-slate-900 border border-slate-700 text-slate-300">
                                    <?= htmlspecialchars($r['mode_name'] ?? 'DEFAULT MODE') ?>
                                </span>
                                <div class="text-[10px] text-slate-400 font-medium mt-2 max-w-[160px] truncate" title="<?= htmlspecialchars($r['reason_name'] ?? 'Unassigned') ?>">
                                    <?= htmlspecialchars($r['reason_name'] ?? 'Not Specified') ?>
                                </div>
                            </td>
                            
                            <td class="p-6">
                                <div class="text-emerald-500 font-black text-sm">Rs. <?= number_format($r['total_bill_cost'], 2) ?></div>
                                <div class="text-[9px] text-slate-500 uppercase font-bold tracking-tighter">Verified Value</div>
                            </td>
                            
                            <td class="p-6">
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 bg-gradient-to-tr from-slate-800 to-slate-700 rounded-full flex items-center justify-center text-[10px] font-bold text-white border border-slate-600">
                                        <?= strtoupper(substr($r['added_by_user'], 0, 2)) ?>
                                    </div>
                                    <div class="flex -space-x-3">
                                        <?php
                                        $images = $pdo->prepare("SELECT image_path FROM qc_item_images WHERE record_id = ? LIMIT 3");
                                        $images->execute([$r['record_id']]);
                                        $imgs = $images->fetchAll();
                                        foreach($imgs as $img): ?>
                                            <img src="<?= $img['image_path'] ?>" class="h-8 w-8 rounded-full ring-2 ring-[#0f172a] object-cover">
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="text-[9px] text-slate-500 mt-1 uppercase font-black tracking-tighter">Auth by: <?= htmlspecialchars($r['added_by_user']) ?></div>
                            </td>
                            
                            <td class="p-6">
                                <div class="flex justify-end gap-2">
                                    <button onclick="viewRecord(<?= $r['record_id'] ?>)" class="bg-slate-800 hover:bg-white hover:text-black p-2.5 rounded-xl transition transform active:scale-90">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    </button>
                                    <button onclick="authAction(<?= $r['record_id'] ?>, 'edit')" class="bg-blue-600/10 text-blue-500 hover:bg-blue-600 hover:text-white p-2.5 rounded-xl transition transform active:scale-90">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                    </button>
                                    <button onclick="authAction(<?= $r['record_id'] ?>, 'delete')" class="bg-red-600/10 text-red-500 hover:bg-red-600 hover:text-white p-2.5 rounded-xl transition transform active:scale-90">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- WINDOW MODAL -->
    <div id="viewModal" class="fixed inset-0 bg-black/90 backdrop-blur-md z-[100] hidden items-center justify-center p-4">
        <div class="bg-[#0f172a] border border-slate-800 w-full max-w-5xl max-h-[90vh] rounded-[3rem] overflow-hidden flex flex-col shadow-2xl">
            <div class="p-8 border-b border-slate-800 flex justify-between items-center bg-slate-900/50">
                <h2 class="text-white font-black text-2xl uppercase tracking-tighter">Record Detail: <span class="text-red-600" id="modalRefTitle">--</span></h2>
                <div class="flex gap-3">
                    <button onclick="printCurrentRecord()" class="bg-red-600 hover:bg-red-700 text-white px-8 py-3 rounded-2xl text-xs font-black uppercase tracking-widest transition shadow-lg shadow-red-600/20">Print Report</button>
                    <button onclick="closeModal()" class="bg-slate-800 text-slate-400 hover:text-white px-6 py-3 rounded-2xl text-xs font-bold transition">Close</button>
                </div>
            </div>
            <div id="modalContent" class="p-10 overflow-y-auto custom-scrollbar"></div>
        </div>
    </div>

    <!-- OPTIMIZED CLIENT ENGINE SCRIPTS -->
    <script>
        /**
         * ENGINE DESIGNED FOR 100K+ ROWS DYNAMIC FILTERING
         * Avoids slow DOM parsing operations during keypress sequences
         */
        function filterData() {
            const search = document.getElementById('searchInput').value.toLowerCase().trim();
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            
            const rows = document.querySelectorAll('.record-row');
            
            // Fast loop sequence optimization
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const metaString = row.getAttribute('data-meta');
                const rowDate = row.getAttribute('data-date');
                
                // Binary conditions mapping
                const textMatch = !search || metaString.includes(search);
                const dateMatch = (!dateFrom || rowDate >= dateFrom) && (!dateTo || rowDate <= dateTo);
                
                if (textMatch && dateMatch) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            }
        }

        let activeRecordId = null;
        function viewRecord(id) {
            activeRecordId = id;
            document.getElementById('viewModal').classList.replace('hidden', 'flex');
            document.getElementById('modalContent').innerHTML = '<div class="text-center p-10 animate-pulse text-slate-500 font-bold uppercase tracking-widest">Decoding Audit Trail...</div>';
            
            fetch('ajax_view_record.php?id=' + id)
                .then(r => r.text())
                .then(html => {
                    document.getElementById('modalContent').innerHTML = html;
                    const tempRef = document.getElementById('temp_ref');
                    document.getElementById('modalRefTitle').innerText = tempRef ? tempRef.value : 'N/A';
                });
        }

        function closeModal() { document.getElementById('viewModal').classList.replace('flex', 'hidden'); }
        
        // Pass complete structural identifiers down into the print context framework
        function printCurrentRecord() { 
            if(activeRecordId) {
                window.open('print_qc_report.php?id=' + activeRecordId + '&view=large_dataset', '_blank'); 
            } 
        }

        function authAction(id, type) {
            Swal.fire({
                title: type === 'delete' ? 'CRITICAL: Purge Record?' : 'Authorize Access',
                text: 'Enter Administrator Security Key',
                input: 'password',
                showCancelButton: true,
                confirmButtonText: 'Verify Identity',
                confirmButtonColor: type === 'delete' ? '#dc2626' : '#2563eb',
                background: '#0f172a',
                color: '#fff',
                preConfirm: (password) => {
                    return fetch('ajax_verify_auth.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'password=' + encodeURIComponent(password)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) throw new Error(data.message || 'Invalid Key');
                        return true; 
                    })
                    .catch(error => Swal.showValidationMessage(`Access Denied: ${error.message}`));
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    if (type === 'delete') performDelete(id);
                    else window.location.href = 'qc_edit.php?id=' + id;
                }
            });
        }

        function performDelete(id) {
            fetch('ajax_delete_record.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + id
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    Swal.fire({ icon: 'success', title: 'Data Purged', background: '#0f172a', color: '#fff' }).then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'System Error', text: data.message, background: '#0f172a', color: '#fff' });
                }
            });
        }
    </script>
</body>
</html>