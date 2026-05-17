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
    // Get all available tasks
    $allTasksStmt = $pdo->query("SELECT task_id, task_name, description FROM tasks WHERE is_active = 1 ORDER BY task_id ASC");
    $allTasks = $allTasksStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user's assigned tasks
    $userTasksStmt = $pdo->prepare("SELECT task_id FROM user_tasks WHERE user_id = ?");
    $userTasksStmt->execute([$user_id]);
    $userTasks = $userTasksStmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'all_tasks' => $allTasks,
        'user_tasks' => $userTasks
    ]);
} catch (PDOException $e) {
    error_log("Database error in get_user_tasks.php: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred']);
}
?>