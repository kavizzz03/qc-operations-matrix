<?php
require 'db.php';
session_start();

$id = $_POST['id'] ?? 0;
$column = $_POST['column'] ?? '';
$username = $_SESSION['username'] ?? 'System';
$now = date('Y-m-d H:i:s');

// Map the status column to its metadata columns
$metaMap = [
    'is_informed' => ['user' => 'informed_by_user', 'time' => 'informed_datetime'],
    'is_store_received' => ['user' => 'store_user', 'time' => 'store_datetime'],
    'is_gate_cleared' => ['user' => 'gate_user', 'time' => 'gate_datetime'],
    'is_handover_complete' => ['user' => 'handover_user', 'time' => 'handover_datetime']
];

if (isset($metaMap[$column])) {
    $userCol = $metaMap[$column]['user'];
    $timeCol = $metaMap[$column]['time'];
    
    $stmt = $pdo->prepare("UPDATE qc_damage_main SET $column = 1, $userCol = ?, $timeCol = ? WHERE record_id = ?");
    if ($stmt->execute([$username, $now, $id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB Update Failed']);
    }
}