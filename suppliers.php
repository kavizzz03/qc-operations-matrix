<?php 
require 'db.php';
if (!isset($_SESSION['user_id'])) header("Location: index.php");

// Handle Delete Supplier
if (isset($_GET['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
    $stmt->execute([$_GET['delete_id']]);
    header("Location: suppliers.php?deleted=true");
    exit;
}

// Handle Add Supplier
if (isset($_POST['add_supplier'])) {
    $stmt = $pdo->prepare("INSERT INTO suppliers (supplier_name, system_id, contact_number, email, address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['s_name'], 
        $_POST['s_id'], 
        $_POST['s_contact'], 
        $_POST['s_email'], 
        $_POST['s_address']
    ]);
    header("Location: suppliers.php?added=true");
    exit;
}

$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY supplier_name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <title>ASB Hub | Supplier Management</title>
    <style>
        .glass-card { background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(10px); }
        .crimson-gradient { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); }
    </style>
</head>
<body class="bg-[#020617] text-slate-300 font-sans">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-72 bg-[#0f172a] border-r border-slate-800 p-8 flex flex-col fixed h-full">
            <h2 class="text-3xl font-black text-white mb-10 tracking-tighter">ASB <span class="text-red-600">HUB</span></h2>
            <nav class="space-y-3 flex-1">
                <a href="dashboard.php" class="flex items-center p-4 hover:bg-slate-800 rounded-2xl transition group">
                    <span class="group-hover:text-red-500 transition">Dashboard</span>
                </a>
                <a href="suppliers.php" class="flex items-center p-4 bg-red-600/10 text-red-500 border border-red-600/20 rounded-2xl font-bold">
                    Suppliers
                </a>
                <a href="users.php" class="flex items-center p-4 hover:bg-slate-800 rounded-2xl transition group">
                    <span class="group-hover:text-red-500 transition">User Management</span>
                </a>
            </nav>
            <a href="logout.php" class="mt-auto bg-red-600/10 text-red-500 text-center py-4 rounded-2xl border border-red-600/20 font-bold hover:bg-red-600 hover:text-white transition shadow-lg shadow-red-900/20">
                LOGOUT
            </a>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 ml-72 p-12">
            <div class="flex justify-between items-end mb-12">
                <div>
                    <h1 class="text-5xl font-black text-white mb-2">Suppliers</h1>
                    <p class="text-slate-500 uppercase tracking-widest text-xs">Partner Resource Management</p>
                </div>
                <button onclick="openModal()" class="crimson-gradient hover:scale-105 text-white px-8 py-4 rounded-2xl font-bold transition shadow-xl shadow-red-900/40">
                    + REGISTER PARTNER
                </button>
            </div>

            <!-- Supplier Table -->
            <div class="glass-card rounded-3xl border border-slate-800 overflow-hidden shadow-2xl">
                <table class="w-full text-left">
                    <thead class="bg-slate-900/80 text-slate-500 uppercase text-[10px] tracking-widest">
                        <tr>
                            <th class="p-6">Partner Details</th>
                            <th class="p-6">Communication</th>
                            <th class="p-6">Location</th>
                            <th class="p-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        <?php foreach($suppliers as $s): ?>
                        <tr class="hover:bg-slate-800/20 transition group">
                            <td class="p-6">
                                <span class="text-white font-bold text-lg block group-hover:text-red-500 transition"><?= $s['supplier_name'] ?></span>
                                <span class="text-[10px] bg-slate-800 text-slate-400 px-2 py-0.5 rounded mt-1 inline-block"><?= $s['system_id'] ?></span>
                            </td>
                            <td class="p-6">
                                <span class="block text-slate-200 font-mono text-sm mb-1"><?= $s['contact_number'] ?></span>
                                <span class="block text-xs text-slate-500 italic"><?= $s['email'] ?></span>
                            </td>
                            <td class="p-6 text-xs text-slate-400 max-w-xs truncate"><?= $s['address'] ?></td>
                            <td class="p-6 text-right">
                                <div class="flex justify-end gap-3">
                                    <button class="bg-slate-800 p-2 rounded-lg hover:text-blue-400 transition">Edit</button>
                                    <button onclick="confirmDelete(<?= $s['supplier_id'] ?>)" class="bg-red-600/10 p-2 rounded-lg text-red-500 hover:bg-red-600 hover:text-white transition">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Modal -->
            <div id="supplierModal" class="hidden fixed inset-0 bg-black/90 backdrop-blur-md flex items-center justify-center z-50 p-4">
                <div class="bg-[#0f172a] border border-slate-800 p-10 rounded-[2.5rem] w-full max-w-xl shadow-3xl">
                    <h3 class="text-3xl font-black text-white mb-2">Register Partner</h3>
                    <p class="text-slate-500 text-sm mb-8">Add a new supplier to the ASB return ecosystem.</p>
                    
                    <form id="supplierForm" method="POST" onsubmit="return validateSriLankaNumber()" class="space-y-4">
                        <input type="text" name="s_name" placeholder="Supplier Full Name" class="w-full bg-slate-900/50 border border-slate-800 p-4 rounded-2xl text-white focus:border-red-600 transition" required>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <input type="text" name="s_id" placeholder="System ID (ST-000)" class="bg-slate-900/50 border border-slate-800 p-4 rounded-2xl text-white focus:border-red-600 transition">
                            <input type="text" id="s_contact" name="s_contact" placeholder="Contact (94xxxxxxxxx)" class="bg-slate-900/50 border border-slate-800 p-4 rounded-2xl text-white focus:border-red-600 transition" required>
                        </div>

                        <input type="email" name="s_email" placeholder="Corporate Email Address" class="w-full bg-slate-900/50 border border-slate-800 p-4 rounded-2xl text-white focus:border-red-600 transition">
                        
                        <textarea name="s_address" placeholder="Physical Warehouse Address" class="w-full bg-slate-900/50 border border-slate-800 p-4 rounded-2xl text-white h-28 focus:border-red-600 transition"></textarea>
                        
                        <div class="flex gap-4 pt-6">
                            <button name="add_supplier" class="flex-[2] crimson-gradient py-4 rounded-2xl font-bold text-white shadow-lg shadow-red-900/20">AUTHORIZE & SAVE</button>
                            <button type="button" onclick="closeModal()" class="flex-1 bg-slate-800 py-4 rounded-2xl text-white font-bold">CANCEL</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        function openModal() { document.getElementById('supplierModal').classList.remove('hidden'); }
        function closeModal() { document.getElementById('supplierModal').classList.add('hidden'); }

        // Sri Lankan Number Validator (Must start with 94 and have 11 or 12 digits total)
        function validateSriLankaNumber() {
            const num = document.getElementById('s_contact').value;
            const slRegex = /^(94)[0-9]{9}$/; // Exact 94 + 9 digits (total 11)
            
            if (!slRegex.test(num)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Contact',
                    text: 'Please enter a valid Sri Lankan number starting with 94 (e.g., 94771234567)',
                    background: '#0f172a',
                    color: '#fff',
                    confirmButtonColor: '#dc2626'
                });
                return false;
            }
            return true;
        }

        // Deletion Confirmation
        function confirmDelete(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This supplier will be permanently removed from ASB Hub.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#1e293b',
                confirmButtonText: 'Yes, Delete!',
                background: '#0f172a',
                color: '#fff'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'suppliers.php?delete_id=' + id;
                }
            })
        }

        // Success Alerts
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.has('added')) {
            Swal.fire({ icon: 'success', title: 'Partner Registered', background: '#0f172a', color: '#fff', showConfirmButton: false, timer: 1500 });
        }
        if(urlParams.has('deleted')) {
            Swal.fire({ icon: 'success', title: 'Partner Removed', background: '#0f172a', color: '#fff', showConfirmButton: false, timer: 1500 });
        }
    </script>
</body>
</html>