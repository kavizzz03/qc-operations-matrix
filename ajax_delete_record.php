<?php
require 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];

    try {
        $pdo->beginTransaction();

        // 1. Fetch and unlink physical image files
        $imgQuery = $pdo->prepare("SELECT image_path FROM qc_item_images WHERE record_id = ?");
        $imgQuery->execute([$id]);
        $images = $imgQuery->fetchAll();
        
        foreach ($images as $img) {
            if (file_exists($img['image_path'])) {
                unlink($img['image_path']);
            }
        }

        // 2. Delete database relations (Child entries first)
        $pdo->prepare("DELETE FROM qc_item_images WHERE record_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM qc_damage_items WHERE record_id = ?")->execute([$id]);

        // 3. Delete main record
        $pdo->prepare("DELETE FROM qc_damage_main WHERE record_id = ?")->execute([$id]);

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Access']);
}