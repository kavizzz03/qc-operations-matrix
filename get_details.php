<?php
/**
 * QC Management System - Asynchronous JSON Telemetry Aggregator
 * Resolves comprehensive relational schema arrays for A6 print rendering pipeline.
 */

require 'db.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session Validation Barrier
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthenticated access token.']);
    exit;
}

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid or blank record index parameter.']);
    exit;
}

try {
    // FIXED: Upgraded query architecture with explicit LEFT JOIN declarations matching the central dashboard pipeline
    $mainQuery = "SELECT m.*, s.supplier_name, s.email, s.contact_number, mo.mode_name, re.reason_name
                  FROM qc_damage_main m 
                  JOIN suppliers s ON m.supplier_id = s.supplier_id 
                  LEFT JOIN qc_modes mo ON m.mode_id = mo.mode_id
                  LEFT JOIN qc_reasons re ON m.reason_id = re.reason_id
                  WHERE m.record_id = ? 
                  LIMIT 1";

    $stmt = $pdo->prepare($mainQuery);
    $stmt->execute([$id]);
    $main = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$main) {
        echo json_encode(['success' => false, 'message' => 'Target damage log tracking index not found.']);
        exit;
    }

    // 2. Fetch Linked Items Mapping Context
    $stmtItems = $pdo->prepare("SELECT * FROM qc_damage_items WHERE record_id = ?");
    $stmtItems->execute([$id]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // 3. Return Combined Data Unified Payloads
    echo json_encode([
        'success' => true,
        'main' => $main,
        'items' => $items
    ]);

} catch (PDOException $e) {
    // Sanitized general message output for runtime exceptions
    echo json_encode(['success' => false, 'message' => 'Database exception processing details data tree.']);
}