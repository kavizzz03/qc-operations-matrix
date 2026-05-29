<?php
// ==========================================
// AJAX Handler for Return Details
// ==========================================
session_start();

$host = '127.0.0.1';
$db   = 'return_qc';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$record_id = $_GET['record_id'] ?? 0;

if (!$record_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid record ID']);
    exit;
}

try {
    // Get main record
    $stmt = $pdo->prepare("
        SELECT dm.*, s.supplier_name, m.mode_name, rr.reason_text
        FROM qc_damage_main dm
        LEFT JOIN suppliers s ON dm.supplier_id = s.supplier_id
        LEFT JOIN qc_modes m ON dm.mode_id = m.mode_id
        LEFT JOIN return_reasons rr ON dm.reason_id = rr.reason_id
        WHERE dm.record_id = ?
    ");
    $stmt->execute([$record_id]);
    $main = $stmt->fetch();
    
    // Get items
    $stmt = $pdo->prepare("SELECT * FROM qc_damage_items WHERE record_id = ?");
    $stmt->execute([$record_id]);
    $items = $stmt->fetchAll();
    
    // Calculate total value
    $total_value = array_sum(array_map(function($item) {
        return $item['quantity'] * $item['unit_cost'];
    }, $items));
    
    echo json_encode([
        'status' => 'success',
        'main' => $main,
        'items' => $items,
        'total_value' => $total_value
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>