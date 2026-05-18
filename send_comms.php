<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once 'db.php';
require_once 'sms_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

$recordId = intval($_POST['id'] ?? 0);

if (!$recordId) {

    echo json_encode([
        'success' => false,
        'message' => 'Record ID missing'
    ]);
    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | GET QC RECORD
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT 
            record_id,
            reference_number,
            supplier_id
        FROM qc_damage_main
        WHERE record_id = ?
    ");

    $stmt->execute([$recordId]);

    $record = $stmt->fetch();

    if (!$record) {

        echo json_encode([
            'success' => false,
            'message' => 'Record not found'
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | GET SUPPLIER
    |--------------------------------------------------------------------------
    */

    $stmt2 = $pdo->prepare("
        SELECT supplier_name, contact_number
        FROM suppliers
        WHERE supplier_id = ?
    ");

    $stmt2->execute([$record['supplier_id']]);

    $supplier = $stmt2->fetch();

    if (!$supplier || empty($supplier['contact_number'])) {

        echo json_encode([
            'success' => false,
            'message' => 'Supplier contact not found'
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | PROFESSIONAL SMS MESSAGE
    |--------------------------------------------------------------------------
    */

    $message =
        "ASB FASHION - RETURNS MANAGEMENT SYSTEM\n\n" .
        "Dear " . $supplier['supplier_name'] . ",\n\n" .
        "You have a new RETURN / QC record from ASB Fashion.\n\n" .
        "Reference Number: " . $record['reference_number'] . "\n\n" .
        "Please check and take necessary action regarding this return.\n\n" .
        "Thank you,\nASB Fashion Team";

    /*
    |--------------------------------------------------------------------------
    | SEND SMS
    |--------------------------------------------------------------------------
    */

    $result = sendSupplierSMS(
        $supplier['contact_number'],
        $message
    );

    echo json_encode($result);
    exit;

} catch (Throwable $e) {

    echo json_encode([
        'success' => false,
        'message' => 'System error',
        'error' => $e->getMessage()
    ]);
    exit;
}
?>