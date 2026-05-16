<?php
require 'db.php';
header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
    exit;
}

try {
    // 1. Fetch Main Record & Supplier Info
    $stmt = $pdo->prepare("
        SELECT m.*, s.supplier_name, s.email, s.contact_number 
        FROM qc_damage_main m 
        JOIN suppliers s ON m.supplier_id = s.supplier_id 
        WHERE m.record_id = ?
    ");
    $stmt->execute([$id]);
    $main = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$main) {
        echo json_encode(['success' => false, 'message' => 'Record not found']);
        exit;
    }

    // 2. Fetch Linked Items
    $stmtItems = $pdo->prepare("SELECT * FROM qc_damage_items WHERE record_id = ?");
    $stmtItems->execute([$id]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // 3. Return Combined Data
    echo json_encode([
        'success' => true,
        'main' => $main,
        'items' => $items
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}