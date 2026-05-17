<?php
require 'db.php';
session_start();

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Check if user_id parameter is provided
if (!isset($_GET['user_id']) || empty($_GET['user_id'])) {
    echo json_encode(['error' => 'No user ID provided']);
    exit;
}

$user_id = (int)$_GET['user_id'];

try {
    // Prepare and execute query
    $stmt = $pdo->prepare("SELECT user_id, name, role FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo json_encode([
            'user_id' => $user['user_id'],
            'name' => $user['name'],
            'role' => $user['role']
        ]);
    } else {
        echo json_encode(['error' => 'User not found']);
    }
} catch (PDOException $e) {
    error_log("Database error in get_user_details.php: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred']);
}
?>