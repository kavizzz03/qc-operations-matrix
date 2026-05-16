<?php

require 'db.php';
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized session.']);
    exit;
}

$inputPass = $_POST['pass'] ?? '';

if (empty($inputPass)) {
    echo json_encode(['success' => false, 'message' => 'Password required.']);
    exit;
}

try {
    // Search for the password in the pass_key column
    // We also check for 'master' level if you want to restrict re-printing to high-level admins only
    $stmt = $pdo->prepare("SELECT admin_id FROM qc_admins WHERE pass_key = ? LIMIT 1");
    $stmt->execute([$inputPass]);
    $admin = $stmt->fetch();

    if ($admin) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid Admin Key.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}