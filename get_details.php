<?php
require 'db.php';
header('Content-Type: application/json');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'No record ID provided']);
    exit;
}

try {
    // Get main record with related data
    $stmt = $pdo->prepare("
        SELECT m.*, s.supplier_name, mo.mode_name, rr.reason_text as reason_name
        FROM qc_damage_main m 
        LEFT JOIN suppliers s ON m.supplier_id = s.supplier_id 
        LEFT JOIN qc_modes mo ON m.mode_id = mo.mode_id
        LEFT JOIN return_reasons rr ON m.reason_id = rr.reason_id
        WHERE m.record_id = ?
    ");
    $stmt->execute([$id]);
    $main = $stmt->fetch();
    
    if (!$main) {
        echo json_encode(['success' => false, 'message' => 'Record not found']);
        exit;
    }
    
    // Get items
    $stmt = $pdo->prepare("SELECT * FROM qc_damage_items WHERE record_id = ?");
    $stmt->execute([$id]);
    $items = $stmt->fetchAll();
    
    // Get images
    $stmt = $pdo->prepare("SELECT * FROM qc_item_images WHERE record_id = ?");
    $stmt->execute([$id]);
    $images = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'main' => $main,
        'items' => $items,
        'images' => $images
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>