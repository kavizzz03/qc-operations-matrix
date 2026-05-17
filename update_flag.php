<?php
// Turn off ALL error reporting completely
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require 'db.php';
session_start();

// Set JSON header
header('Content-Type: application/json');

// Function to send JSON response
function sendJson($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// Check login
if (!isset($_SESSION['user_id'])) {
    sendJson(false, 'Please login first');
}

// Get POST parameters
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$column = isset($_POST['column']) ? trim($_POST['column']) : '';

// Validate
if ($id <= 0 || empty($column)) {
    sendJson(false, 'Invalid request parameters');
}

// Allowed columns
$allowed = ['is_informed', 'is_store_received', 'is_gate_cleared', 'is_handover_complete'];
if (!in_array($column, $allowed)) {
    sendJson(false, 'Invalid column name');
}

// Map columns
$fieldMap = [
    'is_informed' => ['user' => 'informed_by_user', 'time' => 'informed_datetime'],
    'is_store_received' => ['user' => 'store_user', 'time' => 'store_datetime'],
    'is_gate_cleared' => ['user' => 'gate_user', 'time' => 'gate_datetime'],
    'is_handover_complete' => ['user' => 'handover_user', 'time' => 'handover_datetime']
];

$userField = $fieldMap[$column]['user'];
$timeField = $fieldMap[$column]['time'];
$username = $_SESSION['username'] ?? 'System';
$now = date('Y-m-d H:i:s');

// Simple update query
$sql = "UPDATE qc_damage_main SET $column = 1, $userField = ?, $timeField = ? WHERE record_id = ? AND $column = 0";
$stmt = $pdo->prepare($sql);

if ($stmt->execute([$username, $now, $id])) {
    if ($stmt->rowCount() > 0) {
        sendJson(true, 'Status updated successfully');
    } else {
        sendJson(false, 'Status already completed');
    }
} else {
    sendJson(false, 'Database update failed');
}
?>