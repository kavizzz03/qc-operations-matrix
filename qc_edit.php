<?php
require 'db.php';

// Authentication Check
if (!isset($_SESSION['user_id'])) header("Location: index.php");

$id = $_GET['id'] ?? null;
if (!$id) header("Location: qc_queue.php");

// 1. DATA RETRIEVAL (Main Record & Supplier)
$stmt = $pdo->prepare("SELECT m.*, s.supplier_name FROM qc_damage_main m 
                       JOIN suppliers s ON m.supplier_id = s.supplier_id 
                       WHERE m.record_id = ?");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) die("Audit Record Not Found.");

// Fetch Items (Using your qc_damage_items schema)
$itemStmt = $pdo->prepare("SELECT * FROM qc_damage_items WHERE record_id = ?");
$itemStmt->execute([$id]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Images (Evidence Gallery)
$imgStmt = $pdo->prepare("SELECT * FROM qc_item_images WHERE record_id = ?");
$imgStmt->execute([$id]);
$currentImages = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

// 2. CONSOLIDATED UPDATE LOGIC
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Update Main Data (Invoice & Internal Notes)
        $updateMain = $pdo->prepare("UPDATE qc_damage_main SET invoice_number = ?, notes = ? WHERE record_id = ?");
        $updateMain->execute([$_POST['invoice_number'], $_POST['notes'], $id]);

        // Update Item Rows (Matches your 'item_name', 'qty', 'reason' schema)
        if (!empty($_POST['items'])) {
            foreach ($_POST['items'] as $itemId => $details) {
                $updItem = $pdo->prepare("UPDATE qc_damage_items SET item_name = ?, qty = ?, reason = ? WHERE item_id = ?");
                $updItem->execute([$details['item_name'], $details['qty'], $details['reason'], $itemId]);
            }
        }

        // Handle Image Deletions (Physical file + DB record)
        if (!empty($_POST['delete_images'])) {
            foreach ($_POST['delete_images'] as $imgId) {
                $imgData = $pdo->prepare("SELECT image_path FROM qc_item_images WHERE image_id = ?");
                $imgData->execute([$imgId]);
                $path = $imgData->fetchColumn();
                
                if ($path && file_exists($path)) {
                    unlink($path); // Deletes physical file from server
                }
                
                $pdo->prepare("DELETE FROM qc_item_images WHERE image_id = ?")->execute([$imgId]);
            }
        }

        // Handle New Evidence Uploads
        if (!empty($_FILES['new_images']['name'][0])) {
            $targetDir = "uploads/qc/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

            foreach ($_FILES['new_images']['tmp_name'] as $key => $tmpName) {
                $fileExt = pathinfo($_FILES['new_images']['name'][$key], PATHINFO_EXTENSION);
                $newName = "QC_" . $id . "_" . time() . "_" . $key . "." . $fileExt;
                $finalPath = $targetDir . $newName;

                if (move_uploaded_file($tmpName, $finalPath)) {
                    $pdo->prepare("INSERT INTO qc_item_images (record_id, image_path) VALUES (?, ?)")
                        ->execute([$id, $finalPath]);
                }
            }
        }

        $pdo->commit();
        header("Location: qc_edit.php?id=$id&msg=success");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <title>Revision: <?= $record['reference_number'] ?></title>
    <style>
        .glass-panel { background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(12px); }
        input:focus { border-color: #ef4444 !important; }
    </style>
</head>
<body class="bg-[#020617] text-slate-300 p-4 md:p-10 font-sans">

    <div class="max-w-6xl mx-auto">
        <form method="POST" enctype="multipart/form-data">
            
            <!-- TOP BAR -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-10 gap-4">
                <div>
                    <h1 class="text-4xl font-black text-white uppercase italic tracking-tighter">
                        Audit <span class="text-red-600">Revision</span>
                    </h1>
                    <div class="flex items-center gap-3 mt-2">
                        <span class="bg-red-600/10 text-red-500 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest border border-red-600/20">
                            Authorized Entry
                        </span>
                        <p class="text-slate-500 text-xs font-bold uppercase tracking-widest">
                            Supplier: <?= htmlspecialchars($record['supplier_name']) ?>
                        </p>
                    </div>
                </div>
                <a href="qc_queue.php" class="group flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-white px-6 py-3 rounded-2xl text-xs font-black uppercase transition-all duration-300 shadow-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Return to Queue
                </a>
            </div>

            <!-- PRIMARY META DATA -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="glass-panel p-6 rounded-[2rem] border border-slate-800 shadow-2xl">
                    <label class="block text-[10px] font-black text-slate-500 uppercase mb-2">Reference (Locked)</label>
                    <input type="text" value="<?= $record['reference_number'] ?>" readonly 
                           class="w-full bg-slate-900/50 border border-slate-800/50 rounded-xl p-3 text-slate-500 font-mono cursor-not-allowed">
                </div>
                <div class="glass-panel p-6 rounded-[2rem] border border-slate-800 shadow-2xl">
                    <label class="block text-[10px] font-black text-slate-500 uppercase mb-2">Invoice Number</label>
                    <input type="text" name="invoice_number" value="<?= htmlspecialchars($record['invoice_number'] ?? '') ?>" 
                           class="w-full bg-slate-900 border border-slate-800 rounded-xl p-3 text-white focus:border-red-600 outline-none transition font-bold">
                </div>
                <div class="glass-panel p-6 rounded-[2rem] border border-slate-800 shadow-2xl">
                    <label class="block text-[10px] font-black text-red-600 uppercase mb-2">Audit Date</label>
                    <div class="p-3 text-white font-black text-lg">
                        <?= date('M d, Y', strtotime($record['record_date'])) ?>
                    </div>
                </div>
            </div>

            <!-- ITEM AUDIT DATA -->
            <div class="glass-panel rounded-[2.5rem] border border-slate-800 overflow-hidden shadow-2xl mb-8">
                <div class="bg-slate-900/50 p-6 border-b border-slate-800">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest">Itemized Audit Data</h3>
                </div>
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] text-slate-500 uppercase font-black border-b border-slate-800">
                            <th class="p-6">Item Description</th>
                            <th class="p-6 w-32 text-center">Qty</th>
                            <th class="p-6">Damage Reason</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        <?php foreach($items as $item): ?>
                        <tr class="hover:bg-slate-800/20 transition-colors">
                            <td class="p-4 px-6">
                                <input type="text" name="items[<?= $item['item_id'] ?>][item_name]" value="<?= htmlspecialchars($item['item_name']) ?>" 
                                       class="w-full bg-transparent border-b border-slate-800 focus:border-red-600 outline-none p-1 text-white text-sm font-medium">
                            </td>
                            <td class="p-4">
                                <input type="number" name="items[<?= $item['item_id'] ?>][qty]" value="<?= $item['qty'] ?>" 
                                       class="w-full bg-transparent border-b border-slate-800 focus:border-red-600 outline-none p-1 text-white text-sm text-center font-bold">
                            </td>
                            <td class="p-4 px-6">
                                <input type="text" name="items[<?= $item['item_id'] ?>][reason]" value="<?= htmlspecialchars($item['reason'] ?? '') ?>" 
                                       class="w-full bg-transparent border-b border-slate-800 focus:border-red-600 outline-none p-1 text-slate-400 text-sm italic">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- BOTTOM UTILITIES -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
                <!-- Administrative Notes -->
                <div class="glass-panel p-8 rounded-[3rem] border border-slate-800">
                    <label class="block text-[10px] font-black text-slate-500 uppercase mb-4 tracking-widest">Administrative Notes</label>
                    <textarea name="notes" rows="6" 
                              class="w-full bg-slate-900 border border-slate-800 rounded-2xl p-4 text-white focus:border-red-600 outline-none transition text-sm leading-relaxed" 
                              placeholder="Describe findings or supplier comments..."><?= htmlspecialchars($record['notes'] ?? '') ?></textarea>
                </div>

                <!-- Image Gallery Control -->
                <div class="glass-panel p-8 rounded-[3rem] border border-slate-800">
                    <div class="flex justify-between items-center mb-6">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Evidence Gallery</label>
                        <label class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase cursor-pointer transition-transform active:scale-90 shadow-lg shadow-red-600/20">
                            + Add Images
                            <input type="file" name="new_images[]" multiple class="hidden" accept="image/*">
                        </label>
                    </div>

                    <div class="grid grid-cols-4 gap-4">
                        <?php foreach($currentImages as $img): ?>
                        <label class="relative cursor-pointer group aspect-square">
                            <input type="checkbox" name="delete_images[]" value="<?= $img['image_id'] ?>" class="peer hidden">
                            <img src="<?= $img['image_path'] ?>" 
                                 class="w-full h-full object-cover rounded-2xl border-2 border-transparent peer-checked:border-red-600 transition-all grayscale group-hover:grayscale-0 peer-checked:grayscale-0">
                            <div class="absolute inset-0 flex items-center justify-center bg-red-600/60 opacity-0 peer-checked:opacity-100 rounded-2xl transition shadow-inner">
                                <span class="text-[9px] font-black text-white uppercase italic">Remove</span>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <p class="mt-4 text-[9px] text-slate-600 italic font-bold uppercase tracking-tight">* Select thumbnails to mark for removal</p>
                </div>
            </div>

            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-black py-6 rounded-[2.5rem] uppercase tracking-[0.4em] transition-all duration-300 shadow-2xl shadow-red-600/40 active:scale-[0.98] mb-10">
                Update Audit Record
            </button>
        </form>
    </div>

    <!-- Feedback Notifications -->
    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'success'): ?>
    <script>
        Swal.fire({
            title: 'REVISION COMMITTED',
            text: 'System records and gallery have been synchronized.',
            icon: 'success',
            background: '#0f172a',
            color: '#fff',
            confirmButtonColor: '#dc2626',
            timer: 3000
        });
    </script>
    <?php endif; ?>

    <?php if(isset($error)): ?>
    <script>
        Swal.fire({ title: 'Update Failed', text: '<?= $error ?>', icon: 'error', background: '#0f172a', color: '#fff' });
    </script>
    <?php endif; ?>

</body>
</html>