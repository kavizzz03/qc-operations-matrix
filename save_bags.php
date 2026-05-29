<?php
// save_bags.php - Save number of bags to database
require 'db.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$recordId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$numberOfBags = isset($_POST['number_of_bags']) ? (int)$_POST['number_of_bags'] : 0;

if ($recordId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid record ID']);
    exit;
}

if ($numberOfBags <= 0) {
    $numberOfBags = 1;
}

try {
    $stmt = $pdo->prepare("UPDATE qc_damage_main SET number_of_bags = ? WHERE record_id = ?");
    $stmt->execute([$numberOfBags, $recordId]);
    
    echo json_encode(['success' => true, 'message' => 'Bags count saved successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>