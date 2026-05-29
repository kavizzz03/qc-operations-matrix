<?php
// Set PHP timezone
date_default_timezone_set('Asia/Colombo');

// Database Connection
$host = 'localhost';
$db   = 'return_qc';
$user = 'root';
$pass = ''; // Your password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Force MySQL to use Sri Lanka Time for this session
    // Sri Lanka is UTC +5:30
    $pdo->exec("SET time_zone = '+05:30'");
    
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

session_start();
?>