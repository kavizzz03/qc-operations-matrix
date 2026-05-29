<?php
session_start();
header('Content-Type: application/json');

$password = isset($_POST['password']) ? $_POST['password'] : '';

// Change this password as needed
$admin_password = 'admin123';

if ($password === $admin_password) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>