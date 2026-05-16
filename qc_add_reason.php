<?php
require 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { 
    header("Location: index.php"); 
    exit; 
}

$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason_name = trim($_POST['reason_name'] ?? '');
    
    if (empty($reason_name)) {
        $message = 'Return reason specification is required.';
        $status = 'error';
    } else {
        try {
            // Check for duplication
            $check = $pdo->prepare("SELECT COUNT(*) FROM qc_reasons WHERE LOWER(reason_name) = LOWER(?)");
            $check->execute([$reason_name]);
            
            if ($check->fetchColumn() > 0) {
                $message = 'This specific return reason code already exists.';
                $status = 'error';
            } else {
                // Insert into registry
                $stmt = $pdo->prepare("INSERT INTO qc_reasons (reason_name) VALUES (?)");
                $stmt->execute([$reason_name]);
                $message = 'Return reason logged successfully!';
                $status = 'success';
            }
        } catch (Exception $e) {
            $message = 'Database System Error: ' . $e->getMessage();
            $status = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>QC Engine | Add Return Reason</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; background: #020617; color: #cbd5e1; }
        .glass { background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.05); }
    </style>
</head>
<body class="min-h-screen flex">

    <!-- Sidebar Matrix -->
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
            <a href="qc_queue.php" class="flex items-center gap-3 p-4 text-slate-400 hover:bg-slate-800 rounded-2xl transition">Returns Queue</a>
        </nav>
    </aside>

    <!-- Content Engine Canvas -->
    <main class="ml-64 flex-1 p-10 flex flex-col justify-center items-center">
        <div class="w-full max-w-xl">
            <div class="mb-8">
                <h1 class="text-3xl font-black text-white tracking-tighter uppercase">Add Return <span class="text-red-600">Reason</span></h1>
                <p class="text-slate-500 text-xs mt-1 uppercase tracking-widest font-bold">Define Damage Classifications</p>
            </div>

            <div class="glass p-8 rounded-[2.5rem] shadow-2xl border border-slate-800">
                <form method="POST" action="" class="space-y-6">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Reason Description / Statement</label>
                        <textarea name="reason_name" rows="3" placeholder="e.g., Fabric tearing along side seams, Discolored logo prints" 
                                  class="w-full bg-slate-900 border border-slate-800 rounded-xl px-5 py-4 text-sm text-white font-bold placeholder:text-slate-700 focus:ring-1 focus:ring-red-600 outline-none transition custom-scrollbar"></textarea>
                    </div>

                    <div class="flex gap-4 pt-4">
                        <a href="dashboard.php" class="w-1/3 bg-slate-900 border border-slate-800 text-slate-400 text-[10px] font-black uppercase tracking-wider py-4 rounded-xl text-center hover:text-white transition">Cancel</a>
                        <button type="submit" class="w-2/3 bg-red-600 hover:bg-red-700 text-white text-[10px] font-black uppercase tracking-wider py-4 rounded-xl transition shadow-lg shadow-red-600/20">Save Return Reason</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Feedback Automation Layer -->
    <?php if (!empty($message)): ?>
    <script>
        Swal.fire({
            icon: '<?= $status ?>',
            title: '<?= $status === "success" ? "Completed" : "Action Blocked" ?>',
            text: '<?= $message ?>',
            background: '#0f172a',
            color: '#fff',
            confirmButtonColor: '<?= $status === "success" ? "#2563eb" : "#dc2626" ?>'
        }).then(() => {
            <?php if ($status === 'success'): ?> window.location.href = 'dashboard.php'; <?php endif; ?>
        });
    </script>
    <?php endif; ?>

</body>
</html>