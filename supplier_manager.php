<?php
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$message = "";

// --- CREATE / UPDATE LOGIC ---
if (isset($_POST['save_supplier'])) {
    $id = $_POST['supplier_id'] ?? null;
    $name = $_POST['supplier_name'];
    $sys_id = $_POST['system_id'];
    $contact = $_POST['contact_number'];
    $email = $_POST['email'];
    $address = $_POST['address'];

    if ($id) {
        $sql = "UPDATE suppliers SET supplier_name=?, system_id=?, contact_number=?, email=?, address=? WHERE supplier_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $sys_id, $contact, $email, $address, $id]);
        $message = "Supplier Updated Successfully";
    } else {
        $sql = "INSERT INTO suppliers (supplier_name, system_id, contact_number, email, address) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $sys_id, $contact, $email, $address]);
        $message = "New Supplier Registered";
    }
}

// --- DELETE LOGIC (Now Functional) ---
if (isset($_GET['delete'])) {
    $del_id = $_GET['delete'];
    
    // Check for foreign key records in the QC damage table
    $check = $pdo->prepare("SELECT COUNT(*) FROM qc_damage_main WHERE supplier_id = ?");
    $check->execute([$del_id]);
    
    if ($check->fetchColumn() > 0) {
        $message = "ERROR: Cannot delete. Supplier has active QC records.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
        $stmt->execute([$del_id]);
        $message = "Supplier Removed Successfully";
    }
}

$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY supplier_name ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ASB HUB | Supplier Engine</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap');
        body { font-family: 'Inter', sans-serif; background: #020617; color: #cbd5e1; }
        .glass { background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.05); }
        input, textarea { background: rgba(30, 41, 59, 0.5) !important; border: 1px solid rgba(255,255,255,0.1) !important; color: white !important; }
        .back-btn { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); }
        .back-btn:hover { background: rgba(255,255,255,0.08); color: #fff; }
    </style>
</head>
<body class="p-8">

    <div class="max-w-6xl mx-auto">
        
        <!-- Top Navigation -->
        <div class="mb-10">
            <a href="dashboard.php" class="back-btn text-[9px] font-black uppercase tracking-widest px-4 py-2 rounded-lg transition inline-flex items-center gap-2">
                <span>←</span> Return to Dashboard
            </a>
        </div>

        <!-- Header -->
        <div class="flex justify-between items-end mb-10">
            <div>
                <h1 class="text-4xl font-black text-white italic uppercase tracking-tighter">Supplier <span class="text-red-600">Database</span></h1>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mt-1">System Architecture v3.0</p>
            </div>
            <button onclick="openModal()" class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition shadow-lg shadow-red-900/20">+ Add New Supplier</button>
        </div>

        <?php if($message): ?>
            <div class="mb-6 p-4 rounded-xl bg-slate-900 border border-slate-800 text-xs font-bold uppercase tracking-widest text-red-500 flex justify-between items-center">
                <span><?= $message ?></span>
                <button onclick="this.parentElement.remove()" class="text-slate-500 hover:text-white">✕</button>
            </div>
        <?php endif; ?>

        <!-- Supplier Table -->
        <div class="glass rounded-[2rem] overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-900/50 text-[9px] font-black uppercase text-slate-500 border-b border-slate-800">
                    <tr>
                        <th class="px-8 py-5">System ID</th>
                        <th class="px-8 py-5">Supplier Name</th>
                        <th class="px-8 py-5">Contact Info</th>
                        <th class="px-8 py-5 text-right">Management</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    <?php foreach($suppliers as $s): ?>
                    <tr class="hover:bg-white/[0.02] transition">
                        <td class="px-8 py-6 font-mono text-[10px] text-red-500 font-bold"><?= htmlspecialchars($s['system_id']) ?></td>
                        <td class="px-8 py-6 font-black text-white uppercase italic tracking-tighter text-lg"><?= htmlspecialchars($s['supplier_name']) ?></td>
                        <td class="px-8 py-6">
                            <p class="text-xs font-bold"><?= htmlspecialchars($s['contact_number']) ?></p>
                            <p class="text-[10px] text-slate-500"><?= htmlspecialchars($s['email']) ?></p>
                        </td>
                        <td class="px-8 py-6 text-right space-x-2">
                            <button onclick='editSupplier(<?= json_encode($s) ?>)' class="text-[9px] font-black uppercase bg-slate-800 px-4 py-2 rounded-lg hover:bg-blue-600 transition">Edit</button>
                            <!-- FIXED DELETE LINK -->
                            <a href="?delete=<?= $s['supplier_id'] ?>" 
                               onclick="return confirm('Are you sure you want to remove this supplier? This action cannot be undone.')" 
                               class="inline-block text-[9px] font-black uppercase bg-slate-800 px-4 py-2 rounded-lg hover:bg-red-600 transition">
                               Delete
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- CRUD MODAL (Unchanged) -->
    <div id="supplierModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
        <div class="glass max-w-xl w-full rounded-[2.5rem] p-10 border border-white/10">
            <h2 id="modalTitle" class="text-2xl font-black text-white uppercase italic mb-8">Add Supplier</h2>
            
            <form method="POST" class="grid grid-cols-2 gap-4">
                <input type="hidden" name="supplier_id" id="f_id">
                
                <div class="col-span-2">
                    <label class="text-[9px] font-black uppercase text-slate-500 ml-2">Full Business Name</label>
                    <input type="text" name="supplier_name" id="f_name" required class="w-full rounded-xl p-4 mt-1 text-sm outline-none focus:ring-2 ring-red-600">
                </div>

                <div>
                    <label class="text-[9px] font-black uppercase text-slate-500 ml-2">Internal System ID</label>
                    <input type="text" name="system_id" id="f_sysid" class="w-full rounded-xl p-4 mt-1 text-sm">
                </div>

                <div>
                    <label class="text-[9px] font-black uppercase text-slate-500 ml-2">Phone / WhatsApp</label>
                    <input type="text" name="contact_number" id="f_phone" class="w-full rounded-xl p-4 mt-1 text-sm">
                </div>

                <div class="col-span-2">
                    <label class="text-[9px] font-black uppercase text-slate-500 ml-2">Official Email Address</label>
                    <input type="email" name="email" id="f_email" class="w-full rounded-xl p-4 mt-1 text-sm">
                </div>

                <div class="col-span-2">
                    <label class="text-[9px] font-black uppercase text-slate-500 ml-2">Warehouse Address</label>
                    <textarea name="address" id="f_address" rows="3" class="w-full rounded-xl p-4 mt-1 text-sm"></textarea>
                </div>

                <div class="col-span-2 flex gap-4 mt-6">
                    <button type="submit" name="save_supplier" class="flex-1 bg-red-600 text-white font-black uppercase py-4 rounded-xl shadow-lg shadow-red-900/20">Save Data</button>
                    <button type="button" onclick="closeModal()" class="flex-1 bg-slate-800 text-white font-black uppercase py-4 rounded-xl">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('supplierModal');

        function openModal() {
            document.getElementById('modalTitle').innerText = "Add Supplier";
            document.getElementById('f_id').value = "";
            document.querySelector('form').reset();
            modal.classList.remove('hidden');
        }

        function closeModal() { modal.classList.add('hidden'); }

        function editSupplier(data) {
            document.getElementById('modalTitle').innerText = "Edit Supplier";
            document.getElementById('f_id').value = data.supplier_id;
            document.getElementById('f_name').value = data.supplier_name;
            document.getElementById('f_sysid').value = data.system_id;
            document.getElementById('f_phone').value = data.contact_number;
            document.getElementById('f_email').value = data.email;
            document.getElementById('f_address').value = data.address;
            modal.classList.remove('hidden');
        }
    </script>
</body>
</html>