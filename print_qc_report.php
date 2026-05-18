<?php
require 'db.php';

if (!isset($_GET['id'])) {
    die("Invalid Request: No ID provided.");
}

$id = $_GET['id'];

try {
    // 1. Fetch Main Data with explicit JOINs for Mode and Reason schemas
    $stmt = $pdo->prepare("
        SELECT m.*, 
               s.supplier_name, s.system_id as sid, s.address,
               mo.mode_name,
               re.reason_name
        FROM qc_damage_main m 
        JOIN suppliers s ON m.supplier_id = s.supplier_id 
        LEFT JOIN qc_modes mo ON m.mode_id = mo.mode_id
        LEFT JOIN qc_reasons re ON m.reason_id = re.reason_id
        WHERE m.record_id = ?
    ");
    $stmt->execute([$id]);
    $main = $stmt->fetch();

    if (!$main) die("Record not found.");

    // 2. Fetch Items (Limited to 20 for A4 height integrity)
    $items_stmt = $pdo->prepare("SELECT *, (quantity * unit_cost) as subtotal FROM qc_damage_items WHERE record_id = ? LIMIT 20");
    $items_stmt->execute([$id]);
    $items_data = $items_stmt->fetchAll();

    // 3. Fetch Images (Limited to 4 for bottom grid)
    $img_stmt = $pdo->prepare("SELECT * FROM qc_item_images WHERE record_id = ? LIMIT 4");
    $img_stmt->execute([$id]);
    $images_data = $img_stmt->fetchAll();

    $grand_total = 0;
    foreach($items_data as $item) { $grand_total += $item['subtotal']; }

} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ASB_QC_<?= htmlspecialchars($main['reference_number']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=JetBrains+Mono&display=swap');
        
        /* A4 Strict Sizing & CSS Page Paging Rule Matrices */
        @page { size: A4; margin: 0; }
        body { font-family: 'Inter', sans-serif; background-color: #cbd5e1; margin: 0; padding: 0; }
        
        .a4-container {
            width: 210mm;
            height: 297mm;
            padding: 10mm 12mm;
            margin: 10mm auto;
            background: white;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            position: relative;
        }

        /* Enforce absolute separation onto a new physical sheet during physical translation */
        .page-break { page-break-before: always; break-before: page; }

        @media print {
            body { background: none; padding: 0; margin: 0; }
            .a4-container { margin: 0; box-shadow: none; width: 210mm; height: 297mm; }
            .no-print { display: none !important; }
        }

        .mono { font-family: 'JetBrains Mono', monospace; }
        .dashed-line { border-top: 1px dashed #e2e8f0; }
    </style>
</head>
<body>

    <!-- PRIMARY SHEETS CONTAINER -->
    <div id="masterPrintWrapper">
        
        <!-- PAGE 1: RECONCILIATION & STRATEGIC AUDIT SHEET -->
        <div class="a4-container shadow-2xl">
            
            <!-- HEADER SECTION -->
            <div class="flex justify-between items-start border-b-4 border-slate-900 pb-3 mb-4">
                <div>
                    <h1 class="text-2xl font-black italic tracking-tighter text-slate-900 leading-none">ASB FASHIONS</h1>
                    <p class="text-[7px] tracking-[0.4em] text-slate-400 font-bold uppercase mt-1">Inventory Audit & Damage Assessment</p>
                </div>
                <div class="text-right">
                    <p class="text-[8px] font-bold text-slate-400 uppercase tracking-widest">Document Reference</p>
                    <p class="text-xl font-black text-red-600 mono leading-none">#<?= htmlspecialchars($main['reference_number']) ?></p>
                </div>
            </div>

            <!-- LOGISTICS, VENDOR, & METADATA GRID -->
            <div class="grid grid-cols-3 gap-4 mb-4">
                <div class="col-span-2 border-r border-slate-100 pr-4">
                    <h3 class="text-[8px] font-black text-slate-400 uppercase mb-1">Vendor Information</h3>
                    <p class="text-sm font-black text-slate-800 leading-none"><?= htmlspecialchars($main['supplier_name']) ?></p>
                    <p class="text-[9px] text-slate-500 mt-1 uppercase font-semibold">System ID: <?= htmlspecialchars($main['sid']) ?></p>
                    <p class="text-[9px] text-slate-400 mt-1 italic leading-tight"><?= htmlspecialchars($main['address']) ?></p>
                </div>
                <div class="bg-slate-50 p-2 rounded-lg text-[9px] flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between mb-1"><span class="text-slate-400 font-bold uppercase">Invoice:</span> <span class="font-black"><?= htmlspecialchars($main['invoice_number']) ?></span></div>
                        <div class="flex justify-between mb-1"><span class="text-slate-400 font-bold uppercase">Doc No:</span> <span class="font-black text-slate-900 font-mono"><?= !empty($main['doc_number']) ? htmlspecialchars($main['doc_number']) : 'UNASSIGNED' ?></span></div>
                        <div class="flex justify-between mb-1"><span class="text-slate-400 font-bold uppercase">Audit Mode:</span> <span class="font-black uppercase text-red-600"><?= htmlspecialchars($main['mode_name'] ?? 'N/A') ?></span></div>
                        <div class="flex justify-between mb-1"><span class="text-slate-400 font-bold uppercase">Date:</span> <span class="font-black"><?= date('d/m/Y', strtotime($main['added_time'])) ?></span></div>
                    </div>
                    <div class="border-t border-slate-200/60 pt-1 mt-1">
                        <div class="flex justify-between"><span class="text-slate-400 font-bold uppercase">Auditor:</span> <span class="font-black uppercase"><?= htmlspecialchars($main['added_by_user']) ?></span></div>
                    </div>
                </div>
            </div>

            <!-- STRATEGIC AUDIT CONTEXT PANEL -->
            <div class="w-full bg-slate-900 text-white rounded-lg p-2.5 mb-4 text-[9px]">
                <span class="text-slate-400 font-bold uppercase block tracking-widest text-[7px] mb-0.5">Primary Reason Assignment</span>
                <p class="font-medium text-slate-200 italic">"<?= htmlspecialchars($main['reason_name'] ?? 'No formal reason indicated statement.') ?>"</p>
            </div>

            <!-- ITEM TABLE (SCALED FOR 20 ITEMS) -->
            <div class="flex-grow">
                <table class="w-full text-[10px]">
                    <thead>
                        <tr class="bg-slate-900 text-white uppercase text-[8px] tracking-widest">
                            <th class="p-2 text-left rounded-l">Item SKU / Description</th>
                            <th class="p-2 text-center">Qty</th>
                            <th class="p-2 text-right">Unit LKR</th>
                            <th class="p-2 text-right rounded-r">Total LKR</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($items_data as $i): ?>
                        <tr>
                            <td class="px-2 py-1.5 font-bold text-slate-700 mono"><?= htmlspecialchars($i['item_code']) ?></td>
                            <td class="px-2 py-1.5 text-center font-black text-slate-900"><?= number_format($i['quantity']) ?></td>
                            <td class="px-2 py-1.5 text-right text-slate-400"><?= number_format($i['unit_cost'], 2) ?></td>
                            <td class="px-2 py-1.5 text-right font-black text-slate-900"><?= number_format($i['subtotal'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- TOTALS SECTION -->
            <div class="mt-2 border-t-2 border-slate-900 pt-2 flex justify-between items-center">
                <div class="text-[8px] font-bold text-slate-400 italic">
                    * This is a system-generated audit for credit note processing.
                </div>
                <div class="text-right border-l-2 border-slate-100 pl-6">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Grand Total Value</p>
                    <p class="text-2xl font-black text-slate-900">Rs. <?= number_format($grand_total, 2) ?></p>
                </div>
            </div>

            <!-- AUTHORIZATION AREA -->
            <div class="mt-8 grid grid-cols-3 gap-10 text-center">
                <div>
                    <div class="dashed-line mb-1"></div>
                    <p class="text-[7px] font-black uppercase text-slate-400">Warehouse Head</p>
                </div>
                <div>
                    <div class="dashed-line mb-1"></div>
                    <p class="text-[7px] font-black uppercase text-slate-400">Inventory Auditor</p>
                </div>
                <div>
                    <div class="dashed-line mb-1"></div>
                    <p class="text-[7px] font-black uppercase text-slate-400">Supplier Acknowledgement</p>
                </div>
            </div>

            <!-- FOOTER -->
            <div class="mt-8 pt-4 border-t border-slate-100 flex justify-between items-center text-[6px] font-bold text-slate-300 uppercase tracking-[0.2em]">
                <p>ASB Fashions QC Portal &copy; <?= date('Y') ?></p>
                <p>Security Hash: <?= strtoupper(md5($id . $main['reference_number'] . ($main['doc_number'] ?? ''))) ?></p>
            </div>
        </div>

        <!-- PAGE 2: SEPARATE DEDICATED INSPECTION EVIDENCE SHEET -->
        <?php if (count($images_data) > 0): ?>
        <div id="evidencePage" class="a4-container shadow-2xl page-break">
            
            <!-- EVIDENCE HEADER RIBBON CONTAINER -->
            <div class="flex justify-between items-start border-b-4 border-slate-900 pb-3 mb-6">
                <div>
                    <h1 class="text-2xl font-black italic tracking-tighter text-slate-900 leading-none">ASB FASHIONS</h1>
                    <p class="text-[7px] tracking-[0.4em] text-slate-400 font-bold uppercase mt-1">Inspection Evidence Annexure</p>
                </div>
                <div class="text-right">
                    <p class="text-[8px] font-bold text-slate-400 uppercase tracking-widest">Linked Document Ref</p>
                    <p class="text-xl font-black text-red-600 mono leading-none">#<?= htmlspecialchars($main['reference_number']) ?></p>
                </div>
            </div>

            <!-- MAPPED CROSS REFERENCE ANCHOR METADATA CARD -->
            <div class="bg-slate-900 text-white rounded-xl p-4 mb-8 grid grid-cols-2 gap-4 text-xs font-medium">
                <div>
                    <p class="text-slate-400 font-bold uppercase text-[8px] tracking-wider">Supplier / Vendor</p>
                    <p class="text-slate-100 text-sm font-black tracking-tight mt-0.5"><?= htmlspecialchars($main['supplier_name']) ?></p>
                </div>
                <div class="text-right">
                    <p class="text-slate-400 font-bold uppercase text-[8px] tracking-wider">Document Control ID</p>
                    <p class="text-slate-100 text-sm font-mono font-bold tracking-tight mt-0.5"><?= !empty($main['doc_number']) ? htmlspecialchars($main['doc_number']) : 'UNASSIGNED' ?></p>
                </div>
            </div>

            <!-- EVIDENCE GRAPHICS EXPANSION ARCHITECTURE -->
            <div class="flex-grow">
                <h3 class="text-[9px] font-black text-slate-400 uppercase mb-4 tracking-widest border-b border-slate-100 pb-2">Visual Assessment Records (Max 4 captures)</h3>
                <div class="grid grid-cols-2 gap-6">
                    <?php foreach($images_data as $img): ?>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-2 flex flex-col justify-between overflow-hidden shadow-sm h-[85mm]">
                        <div class="w-full h-[72mm] flex items-center justify-center overflow-hidden bg-white rounded-lg">
                            <img src="<?= htmlspecialchars($img['image_path']) ?>" class="max-w-full max-h-full object-contain">
                        </div>
                        <p class="text-[8px] font-mono font-bold text-slate-400 uppercase text-center tracking-wider mt-2">Captured File Object Evidence ID: <?= htmlspecialchars($img['image_id'] ?? rand(1000,9999)) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- EVIDENCE FOOTER -->
            <div class="mt-auto pt-4 border-t border-slate-100 flex justify-between items-center text-[6px] font-bold text-slate-300 uppercase tracking-[0.2em]">
                <p>ASB Fashions Verification Grid &copy; <?= date('Y') ?></p>
                <p>Verification Link Hash: <?= strtoupper(md5('EVIDENCE' . $id . $main['reference_number'])) ?></p>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- FLOATING PRINT ENGINE TRIGGER CONSOLE -->
    <div class="fixed bottom-8 right-8 no-print">
        <button onclick="executeReportPrintPipeline()" class="bg-slate-900 hover:bg-black text-white px-10 py-4 rounded-xl text-xs font-black uppercase tracking-widest shadow-2xl transition-all transform hover:scale-105">
            Print Final Report
        </button>
    </div>

    <!-- PIPELINE EXECUTION LIFECYCLE SCRIPTS -->
    <script>
        function executeReportPrintPipeline() {
            const evidenceExists = <?php echo (count($images_data) > 0) ? 'true' : 'false'; ?>;
            
            if (!evidenceExists) {
                // No images available, bypass prompt and execute default structure directly
                window.print();
                return;
            }

            // Prompt user for multi-page configuration layout
            Swal.fire({
                title: 'Print Configuration',
                text: 'Do you want to include inspection evidence images in this export?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, include image sheet',
                cancelButtonText: 'No, print text sheet only',
                confirmButtonColor: '#0f172a',
                cancelButtonColor: '#dc2626',
                background: '#ffffff',
                color: '#1e293b'
            }).then((result) => {
                const targetPage = document.getElementById('evidencePage');
                
                if (result.isConfirmed) {
                    // Include images: Ensure the element path is explicitly intact
                    if(targetPage) targetPage.style.setProperty('display', 'flex', 'important');
                    setTimeout(() => { window.print(); }, 250);
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    // Exclude images: Force-drop evidence page from document tree layout prior to initialization
                    if(targetPage) targetPage.style.setProperty('display', 'none', 'important');
                    setTimeout(() => { 
                        window.print(); 
                        // Restore structure dynamically after pipeline execution closes
                        setTimeout(() => { targetPage.style.setProperty('display', 'flex'); }, 1000);
                    }, 250);
                }
            });
        }
    </script>
</body>
</html>