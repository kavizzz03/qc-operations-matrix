<?php
require 'db.php';

$query = isset($_GET['query']) ? trim($_GET['query']) : '';

if (strlen($query) < 1) {
    echo '';
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT supplier_id, supplier_name, system_id 
        FROM suppliers 
        WHERE supplier_name LIKE :query 
        OR system_id LIKE :query 
        OR contact_number LIKE :query
        LIMIT 15
    ");
    $stmt->execute(['query' => "%$query%"]);
    $results = $stmt->fetchAll();

    if (count($results) > 0) {
        foreach ($results as $row) {
            echo '<div onclick="selectSupplier(' . $row['supplier_id'] . ', \'' . addslashes($row['supplier_name']) . '\', \'' . addslashes($row['system_id'] ?? 'N/A') . '\')" 
                         class="p-4 hover:bg-slate-800 cursor-pointer transition border-b border-slate-800 last:border-0">
                        <div class="font-bold text-white text-sm">' . htmlspecialchars($row['supplier_name']) . '</div>
                        <div class="text-xs text-slate-500">ID: ' . htmlspecialchars($row['system_id'] ?? 'N/A') . '</div>
                    </div>';
        }
    } else {
        echo '<div class="p-4 text-center text-slate-500 text-sm">No suppliers found</div>';
    }
} catch (Exception $e) {
    echo '<div class="p-4 text-center text-red-500 text-sm">Error loading suppliers</div>';
}
?>