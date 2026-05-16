<?php
require 'db.php';
date_default_timezone_set('Asia/Colombo');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $record_id = $_POST['record_id'];
    $column = $_POST['flag_column']; // e.g., is_informed
    
    // Mapping logic for user and datetime columns
    $user_map = [
        'is_informed' => 'informed_by_user',
        'is_store_received' => 'store_user',
        'is_gate_cleared' => 'gate_user',
        'is_handover_complete' => 'handover_user'
    ];
    $date_map = [
        'is_informed' => 'informed_datetime',
        'is_store_received' => 'store_datetime',
        'is_gate_cleared' => 'gate_datetime',
        'is_handover_complete' => 'handover_datetime'
    ];

    // Security Check: Ensure column is valid and stage isn't already locked
    $stmt = $pdo->prepare("SELECT $column FROM qc_damage_main WHERE record_id = ?");
    $stmt->execute([$record_id]);
    if ($stmt->fetchColumn() == 1) {
        echo json_encode(['status' => 'error', 'message' => 'Stage already finalized.']);
        exit;
    }

    $sql = "UPDATE qc_damage_main SET $column = 1, {$user_map[$column]} = ?, {$date_map[$column]} = ? WHERE record_id = ?";
    $update = $pdo->prepare($sql);
    
    if ($update->execute([$_SESSION['username'], date('Y-m-d H:i:s'), $record_id])) {
        echo json_encode(['status' => 'success']);
    }
}