<?php
require 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get parameters
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$column = isset($_POST['column']) ? $_POST['column'] : '';

// Validate column name (security: only allow specific columns)
$allowed_columns = ['is_informed', 'is_store_received', 'is_gate_cleared', 'is_handover_complete'];
if (!in_array($column, $allowed_columns)) {
    echo json_encode(['success' => false, 'message' => 'Invalid column name']);
    exit;
}

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid record ID']);
    exit;
}

try {
    // Get current user name
    $username = $_SESSION['username'] ?? 'System User';
    $current_datetime = date('Y-m-d H:i:s');
    
    // First, get the current status of the record
    $checkStmt = $pdo->prepare("
        SELECT is_informed, is_store_received, is_gate_cleared, is_handover_complete 
        FROM qc_damage_main 
        WHERE record_id = ?
    ");
    $checkStmt->execute([$id]);
    $current = $checkStmt->fetch();
    
    if (!$current) {
        echo json_encode(['success' => false, 'message' => 'Record not found']);
        exit;
    }
    
    // SEQUENTIAL VALIDATION - Check if previous flags are completed
    if ($column === 'is_informed') {
        // is_informed can be updated anytime (first flag)
        // No previous flag to check
        $stmt = $pdo->prepare("
            UPDATE qc_damage_main 
            SET is_informed = 1, 
                informed_by_user = ?, 
                informed_datetime = ? 
            WHERE record_id = ?
        ");
        $stmt->execute([$username, $current_datetime, $id]);
        $message = "Supplier Informed status updated successfully";
        
    } elseif ($column === 'is_store_received') {
        // Check if is_informed is completed
        if (!$current['is_informed']) {
            echo json_encode([
                'success' => false, 
                'message' => '⚠️ Cannot update Store Received. Please complete "Supplier Informed" first.'
            ]);
            exit;
        }
        
        // Check if already completed
        if ($current['is_store_received']) {
            echo json_encode([
                'success' => false, 
                'message' => '⚠️ Store Received is already completed.'
            ]);
            exit;
        }
        
        $stmt = $pdo->prepare("
            UPDATE qc_damage_main 
            SET is_store_received = 1, 
                store_user = ?, 
                store_datetime = ? 
            WHERE record_id = ?
        ");
        $stmt->execute([$username, $current_datetime, $id]);
        $message = "Store Received status updated successfully";
        
    } elseif ($column === 'is_gate_cleared') {
        // Check if is_store_received is completed
        if (!$current['is_store_received']) {
            echo json_encode([
                'success' => false, 
                'message' => '⚠️ Cannot update Gate Cleared. Please complete "Store Received" first.'
            ]);
            exit;
        }
        
        // Check if already completed
        if ($current['is_gate_cleared']) {
            echo json_encode([
                'success' => false, 
                'message' => '⚠️ Gate Cleared is already completed.'
            ]);
            exit;
        }
        
        $stmt = $pdo->prepare("
            UPDATE qc_damage_main 
            SET is_gate_cleared = 1, 
                gate_user = ?, 
                gate_datetime = ? 
            WHERE record_id = ?
        ");
        $stmt->execute([$username, $current_datetime, $id]);
        $message = "Gate Cleared status updated successfully";
        
    } elseif ($column === 'is_handover_complete') {
        // Check if is_gate_cleared is completed
        if (!$current['is_gate_cleared']) {
            echo json_encode([
                'success' => false, 
                'message' => '⚠️ Cannot update Return Complete. Please complete "Gate Cleared" first.'
            ]);
            exit;
        }
        
        // Check if already completed
        if ($current['is_handover_complete']) {
            echo json_encode([
                'success' => false, 
                'message' => '⚠️ Return Complete is already completed.'
            ]);
            exit;
        }
        
        $stmt = $pdo->prepare("
            UPDATE qc_damage_main 
            SET is_handover_complete = 1, 
                handover_user = ?, 
                handover_datetime = ? 
            WHERE record_id = ?
        ");
        $stmt->execute([$username, $current_datetime, $id]);
        $message = "Return Complete status updated successfully";
    }
    
    // Insert audit log
    try {
        $auditStmt = $pdo->prepare("
            INSERT INTO qc_audit_log (record_id, action, field_name, new_value, changed_by, ip_address) 
            VALUES (?, 'FLAG_UPDATE', ?, '1', ?, ?)
        ");
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $auditStmt->execute([$id, $column, $username, $ip_address]);
    } catch (Exception $e) {
        // Audit table might not exist, continue anyway
    }
    
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>