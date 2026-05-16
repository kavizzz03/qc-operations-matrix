<?php
require 'db.php'; 
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) header("Location: index.php");

// --- Auto-Generate Numeric Reference Number (YYYYMMDDXXXX) ---
$date_part = date('Ymd'); 
$count_query = $pdo->prepare("SELECT COUNT(*) FROM qc_damage_main WHERE record_date = CURDATE()");
$count_query->execute();
$count_today = $count_query->fetchColumn();
$next_id = str_pad($count_today + 1, 4, '0', STR_PAD_LEFT);
$auto_ref = $date_part . $next_id;

// --- Fetch Lookup Options for Dropdowns ---
try {
    $modes_query = $pdo->query("SELECT mode_id, mode_name FROM qc_modes ORDER BY mode_id ASC");
    $modes = $modes_query->fetchAll();

    $reasons_query = $pdo->query("SELECT reason_id, reason_name FROM qc_reasons ORDER BY reason_id ASC");
    $reasons = $reasons_query->fetchAll();
} catch (Exception $e) {
    die("Setup Error: " . $e->getMessage());
}

// --- THE SAVE METHOD ---
if (isset($_POST['save_qc_record'])) {
    try {
        $pdo->beginTransaction();

        // 1. Insert Main Record (Including doc_number, mode_id, reason_id)
        $stmt = $pdo->prepare("
            INSERT INTO qc_damage_main 
            (record_date, supplier_id, invoice_number, reference_number, doc_number, mode_id, reason_id, added_by_user) 
            VALUES (CURDATE(), ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['supplier_id'], 
            $_POST['inv_no'], 
            $_POST['ref_no'],
            !empty($_POST['doc_number']) ? $_POST['doc_number'] : null,
            !empty($_POST['mode_id']) ? $_POST['mode_id'] : null,
            !empty($_POST['reason_id']) ? $_POST['reason_id'] : null,
            $_SESSION['username']
        ]);
        $record_id = $pdo->lastInsertId();

        // 2. Insert Items (With item_code, quantity, and unit_cost fields)
        $item_stmt = $pdo->prepare("INSERT INTO qc_damage_items (record_id, item_code, quantity, unit_cost) VALUES (?, ?, ?, ?)");
        foreach ($_POST['item_code'] as $key => $code) {
            if (!empty($code)) {
                $qty = !empty($_POST['item_qty'][$key]) ? intval($_POST['item_qty'][$key]) : 0;
                $cost = !empty($_POST['item_cost'][$key]) ? floatval($_POST['item_cost'][$key]) : 0.00;
                $item_stmt->execute([$record_id, $code, $qty, $cost]);
            }
        }

        // 3. Multi-Image Upload
        if (!empty($_FILES['qc_images']['name'][0])) {
            $upload_dir = 'uploads/qc_returns/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            foreach ($_FILES['qc_images']['tmp_name'] as $key => $tmp_name) {
                if($_FILES['qc_images']['error'][$key] === 0) {
                    $file_ext = pathinfo($_FILES['qc_images']['name'][$key], PATHINFO_EXTENSION);
                    $file_name = "QC_" . bin2hex(random_bytes(4)) . "_" . time() . "." . $file_ext;
                    $target_file = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($tmp_name, $target_file)) {
                        $img_stmt = $pdo->prepare("INSERT INTO qc_item_images (record_id, image_path) VALUES (?, ?)");
                        $img_stmt->execute([$record_id, $target_file]);
                    }
                }
            }
        }

        $pdo->commit();
        header("Location: qc_queue.php?status=success");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "System Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <title>ASB Hub | Elite QC Entry</title>
    <style>
        .animate-fade-in { animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-[#020617] text-slate-300 font-sans">
    <div class="flex min-h-screen">
        <main class="flex-1 p-12 max-w-5xl mx-auto">
            
            <!-- HEADER WITH NEW DASHBOARD LINK BUTTON -->
            <header class="flex justify-between items-end mb-10">
                <div class="flex items-center gap-6">
                    <!-- BACK TO DASHBOARD BUTTON -->
                    <a href="dashboard.php" class="bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 text-slate-400 hover:text-white px-4 py-3 rounded-2xl text-xs font-bold tracking-wider transition flex items-center gap-2 group">
                        <span class="transform group-hover:-translate-x-1 transition-transform">←</span> DASHBOARD
                    </a>
                    <div>
                        <h1 class="text-5xl font-black text-white">New <span class="text-red-600">Entry</span></h1>
                        <p class="text-slate-500 uppercase tracking-widest text-[10px] mt-2">Internal Reference: <span class="text-red-500 font-bold"><?= $auto_ref ?></span></p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-[10px] text-slate-500 uppercase">Current Session</p>
                    <p class="text-white font-bold"><?= htmlspecialchars($_SESSION['username'] ?? 'System') ?></p>
                </div>
            </header>

            <form method="POST" enctype="multipart/form-data" class="space-y-8">
                <input type="hidden" name="ref_no" value="<?= $auto_ref ?>">

                <!-- META DOCUMENT WORKFLOW CARD -->
                <div class="grid grid-cols-2 gap-6 bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-800 shadow-2xl">
                    
                    <div class="col-span-2 md:col-span-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase mb-2 block ml-2">Invoice Number</label>
                        <input type="text" name="inv_no" class="w-full bg-slate-900 border border-slate-700 p-4 rounded-2xl text-white focus:border-red-600 outline-none transition placeholder:text-slate-700" placeholder="e.g. INV-9902" required>
                    </div>

                    <div class="col-span-2 md:col-span-1 relative">
                        <label class="text-[10px] font-bold text-slate-500 uppercase mb-2 block ml-2">Supplier Selection</label>
                        <input type="text" id="supplier_search" onkeyup="liveSearch(this.value)" class="w-full bg-slate-900 border border-slate-700 p-4 rounded-2xl text-white focus:border-red-600 outline-none transition" placeholder="Search Name, ID..." autocomplete="off">
                        <input type="hidden" name="supplier_id" id="selected_id" required>

                        <div id="selection_status" class="hidden mt-2 p-3 bg-red-600/10 border border-red-600/20 rounded-xl flex justify-between items-center animate-fade-in">
                            <span id="display_name" class="text-xs font-bold text-red-500 uppercase tracking-tighter"></span>
                            <button type="button" onclick="resetSupplier()" class="text-slate-500 hover:text-white transition text-xs font-black px-2">CHANGE</button>
                        </div>
                        <div id="search_results" class="absolute z-50 w-full mt-2 bg-[#0f172a] border border-slate-700 rounded-2xl shadow-2xl hidden overflow-hidden"></div>
                    </div>

                    <!-- NEW SCHEMA INCLUSIONS -->
                    <div class="col-span-2 md:col-span-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase mb-2 block ml-2">Document Number (Optional)</label>
                        <input type="text" name="doc_number" class="w-full bg-slate-900 border border-slate-700 p-4 rounded-2xl text-white focus:border-red-600 outline-none transition placeholder:text-slate-700" placeholder="e.g. DOC-4829">
                    </div>


                    <div class="col-span-2 md:col-span-1 grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-bold text-slate-500 uppercase mb-2 block ml-2">Audit Mode</label>
                            <select name="mode_id" class="w-full bg-slate-900 border border-slate-700 p-4 rounded-2xl text-white focus:border-red-600 outline-none transition appearance-none" required>
                                <option value="" disabled selected hidden>Select Mode</option>
                                <?php foreach($modes as $mode): ?>
                                    <option value="<?= $mode['mode_id'] ?>"><?= htmlspecialchars($mode['mode_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-slate-500 uppercase mb-2 block ml-2">Primary Reason</label>
                            <select name="reason_id" class="w-full bg-slate-900 border border-slate-700 p-4 rounded-2xl text-white focus:border-red-600 outline-none transition appearance-none" required>
                                <option value="" disabled selected hidden>Select Reason</option>
                                <?php foreach($reasons as $reason): ?>
                                    <option value="<?= $reason['reason_id'] ?>"><?= htmlspecialchars($reason['reason_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                </div>

                <!-- IMAGES FILE DROPAREA -->
                <div class="bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-800 shadow-2xl">
                    <label class="text-[10px] font-bold text-slate-500 uppercase mb-4 block text-center tracking-widest">Evidence Gallery</label>
                    <div id="drop-area" class="border-2 border-dashed border-slate-800 rounded-[2rem] p-12 text-center hover:border-red-600 transition cursor-pointer bg-slate-950/50 group">
                        <input type="file" name="qc_images[]" id="file-input" multiple class="hidden" accept="image/*">
                        <div class="mb-4 text-4xl group-hover:scale-110 transition duration-300">📷</div>
                        <p class="text-slate-500 text-sm">Drag multiple images or <span class="text-red-500 font-bold">Browse Files</span></p>
                        <div id="preview" class="flex flex-wrap gap-4 mt-8 justify-center"></div>
                    </div>
                </div>

                <!-- DYNAMIC ITEMS LISTING (UPDATED WITH COST PER UNIT) -->
                <div class="bg-[#0f172a] p-8 rounded-[2.5rem] border border-slate-800 shadow-2xl">
                    <h3 class="text-white font-bold mb-6 flex justify-between">
                        <span>Damaged Items Ledger</span>
                        <span class="text-[10px] text-slate-600 uppercase">Max 20 Items</span>
                    </h3>
                    
                    <div id="items-list" class="space-y-4">
                        <div class="grid grid-cols-12 gap-4">
                            <input type="text" name="item_code[]" placeholder="Item SKU Code" class="col-span-6 bg-slate-900 border border-slate-800 p-4 rounded-xl text-white outline-none focus:border-slate-600" required>
                            <input type="number" name="item_qty[]" placeholder="Qty" class="col-span-2 bg-slate-900 border border-slate-800 p-4 rounded-xl text-white outline-none focus:border-slate-600" min="1" required>
                            <input type="number" step="0.01" name="item_cost[]" placeholder="Unit Cost (LKR)" class="col-span-4 bg-slate-900 border border-slate-800 p-4 rounded-xl text-white outline-none focus:border-slate-600" min="0" required>
                        </div>
                    </div>
                    
                    <button type="button" onclick="addRow()" class="mt-6 text-[10px] font-black text-red-600 uppercase tracking-widest hover:text-red-400 transition">+ Add Item Line</button>
                </div>

                <button name="save_qc_record" class="w-full bg-red-600 hover:bg-red-700 text-white font-black py-7 rounded-[2rem] shadow-2xl shadow-red-900/30 transition transform active:scale-[0.98] text-lg uppercase tracking-widest">
                    Confirm & Save Return Record
                </button>
            </form>
        </main>
    </div>

    <!-- SCRIPTS -->
    <script>
        function liveSearch(val) {
            const res = document.getElementById('search_results');
            if (val.length < 1) { res.classList.add('hidden'); return; }
            fetch('ajax_search_supplier.php?query=' + encodeURIComponent(val))
                .then(r => r.text())
                .then(data => { res.innerHTML = data; res.classList.remove('hidden'); });
        }

        function selectSupplier(id, name, sid) {
            document.getElementById('selected_id').value = id;
            document.getElementById('display_name').innerText = name + " [" + sid + "]";
            document.getElementById('supplier_search').classList.add('hidden');
            document.getElementById('selection_status').classList.remove('hidden');
            document.getElementById('search_results').classList.add('hidden');
        }

        function resetSupplier() {
            document.getElementById('selected_id').value = "";
            document.getElementById('supplier_search').classList.remove('hidden');
            document.getElementById('selection_status').classList.add('hidden');
            document.getElementById('supplier_search').value = "";
        }

        // Droparea Implementation
        const dropArea = document.getElementById('drop-area');
        const fileInput = document.getElementById('file-input');
        const preview = document.getElementById('preview');
        let dt = new DataTransfer();

        dropArea.onclick = () => fileInput.click();
        fileInput.onchange = (e) => handleFiles(e.target.files);
        dropArea.ondragover = (e) => { e.preventDefault(); dropArea.classList.add('border-red-600', 'bg-red-600/5'); };
        dropArea.ondragleave = () => dropArea.classList.remove('border-red-600', 'bg-red-600/5');
        dropArea.ondrop = (e) => {
            e.preventDefault();
            dropArea.classList.remove('border-red-600', 'bg-red-600/5');
            handleFiles(e.dataTransfer.files);
        };

        function handleFiles(files) {
            for (let file of files) {
                if (file.type.startsWith('image/')) {
                    dt.items.add(file);
                    const reader = new FileReader();
                    reader.onload = (event) => {
                        const div = document.createElement('div');
                        div.className = "relative group animate-fade-in";
                        div.innerHTML = `
                            <img src="${event.target.result}" class="w-24 h-24 object-cover rounded-2xl border-2 border-slate-800 shadow-lg">
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition rounded-2xl flex items-center justify-center">
                                <span class="text-[8px] font-black text-white uppercase tracking-tighter">Ready</span>
                            </div>
                        `;
                        preview.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                }
            }
            fileInput.files = dt.files;
        }

        // Updated row constructor
        function addRow() {
            const container = document.getElementById('items-list');
            if(container.children.length < 20) {
                const div = document.createElement('div');
                div.className = "grid grid-cols-12 gap-4 animate-fade-in mt-4";
                div.innerHTML = `
                    <input type="text" name="item_code[]" placeholder="Item SKU Code" class="col-span-6 bg-slate-900 border border-slate-800 p-4 rounded-xl text-white outline-none focus:border-slate-600" required>
                    <input type="number" name="item_qty[]" placeholder="Qty" class="col-span-2 bg-slate-900 border border-slate-800 p-4 rounded-xl text-white outline-none focus:border-slate-600" min="1" required>
                    <input type="number" step="0.01" name="item_cost[]" placeholder="Unit Cost (LKR)" class="col-span-4 bg-slate-900 border border-slate-800 p-4 rounded-xl text-white outline-none focus:border-slate-600" min="0" required>
                `;
                container.appendChild(div);
            } else {
                Swal.fire({ icon: 'warning', title: 'Limit Exceeded', text: 'An A4 audit slip accommodates a maximum of 20 row items.', background: '#0f172a', color: '#fff', confirmButtonColor: '#dc2626' });
            }
        }

        <?php if(isset($error)): ?>
            Swal.fire({ icon: 'error', title: 'Execution Fault', text: '<?= addslashes($error) ?>', background: '#0f172a', color: '#fff', confirmButtonColor: '#dc2626' });
        <?php endif; ?>
    </script>
</body>
</html>