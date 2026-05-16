<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_id'] != 1 && !userHasTask($pdo, $_SESSION['user_id'], 'USER_MGMT'))) {
    http_response_code(403);
    die(json_encode(['error' => 'Access denied']));
}

$user_id = $_GET['user_id'] ?? 0;

// Get user's assigned tasks
$stmt = $pdo->prepare("SELECT task_id FROM user_tasks WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_tasks = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get all tasks
$all_tasks = getAllTasks($pdo);

echo json_encode([
    'user_tasks' => $user_tasks,
    'all_tasks' => $all_tasks
]);
?>