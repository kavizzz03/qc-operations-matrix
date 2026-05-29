<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once 'db.php';
require_once 'sms_config.php';

// NO SESSION REQUIRED for internal calls
// This file is called via cURL from supplier_invoices.php

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
    | GET QC RECORD WITH INVOICE NUMBER AND SUPPLIER DETAILS
    |--------------------------------------------------------------------------
    */
    
    $stmt = $pdo->prepare("
        SELECT 
            dm.record_id,
            dm.reference_number,
            dm.supplier_id,
            dm.invoice_number,
            s.supplier_name,
            s.contact_number
        FROM qc_damage_main dm
        JOIN suppliers s ON dm.supplier_id = s.supplier_id
        WHERE dm.record_id = ?
    ");

    $stmt->execute([$recordId]);
    $record = $stmt->fetch();

    if (!$record) {
        echo json_encode([
            'success' => false,
            'message' => 'Record not found for ID: ' . $recordId
        ]);
        exit;
    }

    if (empty($record['contact_number']) || $record['contact_number'] == 'N/A') {
        echo json_encode([
            'success' => false,
            'message' => 'Supplier contact number not available for: ' . $record['supplier_name']
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | SMS MESSAGE WITH INVOICE NUMBER AND REFERENCE
    |--------------------------------------------------------------------------
    */
    
    $message = "ASB FASHION - RETURNS MANAGEMENT SYSTEM\n\n";
    $message .= "Dear " . $record['supplier_name'] . ",\n\n";
    $message .= "You have a new RETURN / QC record from ASB Fashion.\n\n";
    $message .= "Reference Number: " . ($record['reference_number'] ?? 'PENDING') . "\n";
    $message .= "Invoice Number: " . $record['invoice_number'] . "\n\n";
    $message .= "Please check and take necessary action regarding this return.\n\n";
    $message .= "Thank you,\nASB Fashion Team";

    /*
    |--------------------------------------------------------------------------
    | SEND SMS USING THE PROVIDED FUNCTION
    |--------------------------------------------------------------------------
    */
    
    // Check if sendSupplierSMS function exists
    if (function_exists('sendSupplierSMS')) {
        $result = sendSupplierSMS($record['contact_number'], $message);
        echo json_encode($result);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'sendSupplierSMS function not found'
        ]);
    }
    exit;

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'System error: ' . $e->getMessage()
    ]);
    exit;
}
?>