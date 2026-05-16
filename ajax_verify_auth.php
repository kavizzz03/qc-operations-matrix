<?php
require 'db.php';
header('Content-Type: application/json');

$password = $_POST['password'] ?? '';

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Key cannot be empty']);
    exit;
}

try {
    // Queries the database for the plain-text key
    $stmt = $pdo->prepare("SELECT admin_id FROM qc_admins WHERE pass_key = ? LIMIT 1");
    $stmt->execute([$password]);
    $admin = $stmt->fetch();

    if ($admin) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid Security Key']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Auth System Offline']);
}