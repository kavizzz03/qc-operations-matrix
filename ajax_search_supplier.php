<?php
require 'db.php'; // Ensure this uses Asia/Colombo timezone

if (isset($_GET['query'])) {
    $q = "%" . $_GET['query'] . "%";
    // Search across three fields for maximum flexibility
    $stmt = $pdo->prepare("SELECT * FROM suppliers 
                           WHERE supplier_name LIKE ? 
                           OR system_id LIKE ? 
                           OR contact_number LIKE ? 
                           LIMIT 5");
    $stmt->execute([$q, $q, $q]);
    $results = $stmt->fetchAll();

    if ($results) {
        foreach ($results as $s) {
            echo "
            <div class='p-4 hover:bg-red-600/10 cursor-pointer border-b border-slate-800 transition' 
                 onclick='selectSupplier({$s['supplier_id']}, \"{$s['supplier_name']}\", \"{$s['system_id']}\")'>
                <div class='text-white font-bold text-sm'>{$s['supplier_name']}</div>
                <div class='text-[10px] text-slate-500 uppercase'>ID: {$s['system_id']} • TEL: {$s['contact_number']}</div>
            </div>";
        }
    } else {
        echo "<div class='p-4 text-slate-500 text-xs'>No supplier matches found.</div>";
    }
}
?>