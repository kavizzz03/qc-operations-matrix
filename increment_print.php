<?php
require 'db.php';
$id = $_POST['id'] ?? 0;
if ($id > 0) {
    $stmt = $pdo->prepare("UPDATE qc_damage_main SET print_count = print_count + 1 WHERE record_id = ?");
    $stmt->execute([$id]);
}