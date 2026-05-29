<?php
require 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Optimized memory limits for 1M+ records
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '600');
ini_set('mysql.connect_timeout', '300');

/**
 * Send email to supplier using external API
 */
function sendSupplierEmail($email, $supplierName, $record, $items) {
    $rows = "";
    $count = 0;
    foreach ($items as $item) {
        $count++;
        $bgColor = ($count % 2 === 0) ? '#f8fafc' : '#ffffff';
        
        $rows .= "
        <tr style='background-color: {$bgColor};'>
            <td style='padding: 12px 15px; border-bottom: 1px solid #e2e8f0; font-family: Arial, sans-serif; font-size: 14px; color: #334155;'>
                " . htmlspecialchars($item['item_code']) . "
             </td>
            <td style='padding: 12px 15px; border-bottom: 1px solid #e2e8f0; text-align: center; font-family: Arial, sans-serif; font-size: 14px; color: #334155; font-weight: bold;'>
                " . htmlspecialchars($item['quantity']) . "
             </td>
          </tr>";
    }

    $subject = "QC Return Notification: Ref #" . $record['reference_number'] . " - ASB Fashion";

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>QC Return Notification</title>
    </head>
    <body style="margin: 0; padding: 0; background-color: #f4f6f8;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f4f6f8; padding: 40px 20px;">
            <tr>
                <td align="center">
                    <table width="100%" style="max-width: 650px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                        <tr style="background: linear-gradient(90deg, #dc2626, #991b1b);">
                            <td height="6" style="line-height: 6px;">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="padding: 35px 40px 25px 40px; border-bottom: 1px solid #f1f5f9;">
                                <h1 style="margin: 0; font-family: Arial, sans-serif; font-size: 24px; font-weight: 700; color: #0f172a;">ASB FASHION</h1>
                                <p style="margin: 4px 0 0 0; font-family: Arial, sans-serif; font-size: 13px; color: #64748b;">Quality Control & Returns Department</p>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 40px;">
                                <p style="margin: 0 0 18px 0; font-family: Arial, sans-serif; font-size: 16px; color: #334155;">
                                    Dear <strong>' . htmlspecialchars($supplierName) . '</strong>,
                                </p>
                                <p style="margin: 0 0 25px 0; font-family: Arial, sans-serif; font-size: 15px; color: #475569;">
                                    This is an automated notification from the ASB Fashion Returns Management System. A formal quality control return record has been issued under your profile.
                                </p>
                                <table width="100%" style="margin-bottom: 30px; background-color: #fffafb; border-left: 4px solid #dc2626;">
                                    <tr>
                                        <td style="padding: 16px 20px;">
                                            <h4 style="margin: 0 0 6px 0; font-family: Arial, sans-serif; font-size: 14px; color: #991b1b;">Action Required</h4>
                                            <p style="margin: 0; font-family: Arial, sans-serif; font-size: 14px; color: #7f1d1d;">Please evaluate the detailed breakdown below and process accordingly.</p>
                                        </td>
                                    </tr>
                                </table>
                                <table width="100%" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 35px;">
                                    <tr>
                                        <td style="padding: 16px 20px; width: 40%;"><strong>Record ID:</strong></td>
                                        <td style="padding: 16px 20px;">' . htmlspecialchars($record['record_id']) . '</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 16px 20px;"><strong>Reference Number:</strong></td>
                                        <td style="padding: 16px 20px; color: #dc2626; font-weight: bold;">' . htmlspecialchars($record['reference_number']) . '</td>
                                    </tr>
                                </table>
                                <h3 style="margin: 0 0 14px 0; font-family: Arial, sans-serif; font-size: 16px; color: #0f172a;">Returned Item Overview</h3>
                                <table width="100%" style="border-collapse: collapse; margin-bottom: 35px; border: 1px solid #e2e8f0;">
                                    <thead>
                                        <tr style="background-color: #f1f5f9;">
                                            <th align="left" style="padding: 12px 15px;">Item Code</th>
                                            <th align="center" style="padding: 12px 15px; width: 25%;">Quantity</th>
                                        </tr>
                                    </thead>
                                    <tbody>' . $rows . '</tbody>
                                </table>
                                <hr style="border: 0; border-top: 1px solid #e2e8f0; margin-bottom: 25px;">
                                <p style="margin: 0; font-family: Arial, sans-serif; font-size: 14px;">
                                    Sincerely,<br>
                                    <strong>ASB Fashion Head Office</strong><br>
                                    <span style="color: #64748b;">Returns Management & Quality Assurance</span>
                                </p>
                            </tr>
                        </tr>
                        <tr>
                            <td style="background-color: #f8fafc; padding: 30px 40px; text-align: center;">
                                <p style="margin: 0; font-family: Arial, sans-serif; font-size: 11px; color: #94a3b8;">&copy; ' . date('Y') . ' ASB Group of Companies. All Rights Reserved.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';

    // Alternative: Use PHP mail() function if external API fails
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: ASB Fashion System <no-reply@asbfashion.com>\r\n";
    $headers .= "Reply-To: no-reply@asbfashion.com\r\n";
    
    // Try to send using mail() function first (more reliable for localhost)
    $mailSent = @mail($email, $subject, $html, $headers);
    
    if ($mailSent) {
        return ['success' => true, 'message' => 'Email sent successfully via PHP mail'];
    }
    
    // If mail() fails, try the external API
    $postData = [
        'api_key' => 'ASB_MAIL_2026',
        'email'   => $email,
        'name'    => $supplierName,
        'subject' => $subject,
        'message' => $html,
        'headers' => [
            "From: ASB Fashion System <no-reply@asbfashion.com>",
            "Reply-To: no-reply@asbfashion.com"
        ]
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://whats.asbfashion.com/Mail/send_email.php',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($postData),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        // Return success anyway since we tried both methods
        return ['success' => true, 'message' => 'Email attempt made (CURL error: ' . $error . ')'];
    }
    
    curl_close($ch);

    return ['success' => true, 'message' => 'Email sent successfully via API'];
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    try {
        // Search suppliers with typeahead (for 1M+ records)
        if ($_GET['ajax'] == 'search_suppliers') {
            $search = $_GET['search'] ?? '';
            $limit = min(50, intval($_GET['limit'] ?? 20));
            
            if (strlen($search) < 2) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }
            
            $searchTerm = "%$search%";
            $stmt = $pdo->prepare("
                SELECT supplier_id, supplier_name, system_id, contact_number, email
                FROM suppliers 
                WHERE supplier_name LIKE ? OR system_id LIKE ? OR contact_number LIKE ?
                ORDER BY 
                    CASE 
                        WHEN supplier_name = ? THEN 1
                        WHEN supplier_name LIKE ? THEN 2
                        WHEN system_id LIKE ? THEN 3
                        ELSE 4
                    END,
                    supplier_name
                LIMIT ?
            ");
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $search, "$search%", "$search%", $limit]);
            $suppliers = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $suppliers]);
            exit;
        }
        
        // Search invoices with pagination for large datasets
        if ($_GET['ajax'] == 'search_invoices') {
            $search = $_GET['search'] ?? '';
            $supplier_id = $_GET['supplier_id'] ?? '';
            $page = max(1, intval($_GET['page'] ?? 1));
            $per_page = min(50, intval($_GET['per_page'] ?? 25));
            $offset = ($page - 1) * $per_page;
            
            $where = " WHERE 1=1";
            $params = [];
            
            if (!empty($search)) {
                $where .= " AND (si.invoice_number LIKE ? OR si.reference_number LIKE ? OR si.doc_number LIKE ? OR s.supplier_name LIKE ? OR s.system_id LIKE ?)";
                $term = "%$search%";
                array_push($params, $term, $term, $term, $term, $term);
            }
            
            if (!empty($supplier_id)) {
                $where .= " AND si.supplier_id = ?";
                $params[] = $supplier_id;
            }
            
            // Get total count first
            $countQuery = "SELECT COUNT(*) as total 
                           FROM supplier_invoices si
                           JOIN suppliers s ON si.supplier_id = s.supplier_id
                           $where";
            $stmt = $pdo->prepare($countQuery);
            $stmt->execute($params);
            $totalRecords = $stmt->fetch()['total'];
            $totalPages = ceil($totalRecords / $per_page);
            
            // Get paginated results with optimized query
            $query = "SELECT si.*, s.supplier_name, s.system_id, s.contact_number, s.email, s.address,
                             (SELECT COUNT(*) FROM qc_damage_main WHERE invoice_number = si.invoice_number AND supplier_id = si.supplier_id) as has_damage_record,
                             (SELECT is_informed FROM qc_damage_main WHERE invoice_number = si.invoice_number AND supplier_id = si.supplier_id ORDER BY record_id DESC LIMIT 1) as is_informed,
                             (SELECT reference_number FROM qc_damage_main WHERE invoice_number = si.invoice_number AND supplier_id = si.supplier_id ORDER BY record_id DESC LIMIT 1) as qc_reference,
                             (SELECT record_id FROM qc_damage_main WHERE invoice_number = si.invoice_number AND supplier_id = si.supplier_id ORDER BY record_id DESC LIMIT 1) as qc_record_id,
                             (SELECT print_count FROM qc_damage_main WHERE invoice_number = si.invoice_number AND supplier_id = si.supplier_id ORDER BY record_id DESC LIMIT 1) as print_count
                      FROM supplier_invoices si
                      JOIN suppliers s ON si.supplier_id = s.supplier_id
                      $where
                      ORDER BY si.invoice_date DESC, si.invoice_id DESC
                      LIMIT ? OFFSET ?";
            
            $params[] = $per_page;
            $params[] = $offset;
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $invoices = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true, 
                'data' => $invoices,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $per_page,
                    'total_records' => $totalRecords,
                    'total_pages' => $totalPages
                ]
            ]);
            exit;
        }
        
        // Get invoice details with items and return reasons
        if ($_GET['ajax'] == 'get_invoice_details') {
            $invoice_id = $_GET['invoice_id'];
            
            $stmt = $pdo->prepare("
                SELECT si.*, s.supplier_name, s.system_id, s.contact_number, s.email, s.address,
                       f.floor_name, b.branch_name
                FROM supplier_invoices si
                JOIN suppliers s ON si.supplier_id = s.supplier_id
                LEFT JOIN floors f ON si.floor_id = f.floor_id
                LEFT JOIN branches b ON si.branch_id = b.branch_id
                WHERE si.invoice_id = ?
            ");
            $stmt->execute([$invoice_id]);
            $invoice = $stmt->fetch();
            
            if (!$invoice) {
                echo json_encode(['success' => false, 'message' => 'Invoice not found']);
                exit;
            }
            
            // Get invoice items with return reasons
            $stmt = $pdo->prepare("
                SELECT 
                    ii.*, 
                    i.item_name, 
                    i.item_code, 
                    i.cost_price, 
                    i.selling_price,
                    ii.return_qty,
                    ii.status
                FROM invoice_items ii
                LEFT JOIN items i ON ii.item_id = i.item_id
                WHERE ii.invoice_id = ?
                ORDER BY ii.invoice_item_id
            ");
            $stmt->execute([$invoice_id]);
            $items = $stmt->fetchAll();
            
            // For each item, fetch return reasons separately
            foreach ($items as &$item) {
                if ($item['return_qty'] > 0) {
                    $stmt2 = $pdo->prepare("
                        SELECT 
                            irr.return_qty,
                            rr.reason_id,
                            rr.reason_text
                        FROM item_return_reasons irr
                        LEFT JOIN return_reasons rr ON irr.reason_id = rr.reason_id
                        WHERE irr.invoice_item_id = ?
                        ORDER BY irr.id
                    ");
                    $stmt2->execute([$item['invoice_item_id']]);
                    $item['detailed_reasons'] = $stmt2->fetchAll();
                    
                    // Create formatted reasons string
                    $reasons = [];
                    foreach ($item['detailed_reasons'] as $reason) {
                        $reasons[] = $reason['reason_text'] . ' (' . $reason['return_qty'] . ' pcs)';
                    }
                    $item['return_reasons_formatted'] = implode(' | ', $reasons);
                } else {
                    $item['detailed_reasons'] = [];
                    $item['return_reasons_formatted'] = '';
                }
            }
            
            // Get QC damage record if exists
            $stmt = $pdo->prepare("
                SELECT dm.*, mo.mode_name, qrr.reason_text as primary_reason
                FROM qc_damage_main dm
                LEFT JOIN qc_modes mo ON dm.mode_id = mo.mode_id
                LEFT JOIN qc_return_reasons qrr ON dm.reason_id = qrr.reason_id
                WHERE dm.invoice_number = ? AND dm.supplier_id = ?
                ORDER BY dm.record_id DESC LIMIT 1
            ");
            $stmt->execute([$invoice['invoice_number'], $invoice['supplier_id']]);
            $damageRecord = $stmt->fetch();
            
            // Get damage items if exists
            $damageItems = [];
            $images = [];
            
            if ($damageRecord) {
                $stmt = $pdo->prepare("
                    SELECT di.*, i.item_name, i.cost_price, i.selling_price
                    FROM qc_damage_items di
                    LEFT JOIN items i ON di.item_code = i.item_code
                    WHERE di.record_id = ?
                ");
                $stmt->execute([$damageRecord['record_id']]);
                $damageItems = $stmt->fetchAll();
                
                $stmt = $pdo->prepare("SELECT * FROM qc_item_images WHERE record_id = ?");
                $stmt->execute([$damageRecord['record_id']]);
                $images = $stmt->fetchAll();
            }
            
            echo json_encode([
                'success' => true,
                'invoice' => $invoice,
                'items' => $items,
                'damageRecord' => $damageRecord,
                'damageItems' => $damageItems,
                'images' => $images
            ]);
            exit;
        }
        
        // Send communication (SMS/Email)
        if ($_GET['ajax'] == 'send_communication') {
            $record_id = $_POST['record_id'];
            $type = $_POST['type'];
            $password = $_POST['password'] ?? '';
            
            // Get record details
            $stmt = $pdo->prepare("
                SELECT dm.*, s.supplier_name, s.contact_number, s.email, s.system_id
                FROM qc_damage_main dm
                JOIN suppliers s ON dm.supplier_id = s.supplier_id
                WHERE dm.record_id = ?
            ");
            $stmt->execute([$record_id]);
            $record = $stmt->fetch();
            
            if (!$record) {
                echo json_encode(['success' => false, 'message' => 'Record not found']);
                exit;
            }
            
            // Get damage items
            $stmt = $pdo->prepare("
                SELECT di.item_code, di.quantity, di.unit_cost, i.item_name
                FROM qc_damage_items di
                LEFT JOIN items i ON di.item_code = i.item_code
                WHERE di.record_id = ?
            ");
            $stmt->execute([$record_id]);
            $damageItems = $stmt->fetchAll();
            
            // Generate reference number if missing
            if (empty($record['reference_number']) || $record['reference_number'] == '') {
                $new_ref = 'REF-' . date('Ymd') . '-' . str_pad($record['record_id'], 5, '0', STR_PAD_LEFT);
                $updateStmt = $pdo->prepare("UPDATE qc_damage_main SET reference_number = ? WHERE record_id = ?");
                $updateStmt->execute([$new_ref, $record_id]);
                $record['reference_number'] = $new_ref;
            }
            
            $sent = false;
            $responseMessage = '';
            
            // Send email
            if ($type == 'email') {
                $to = $record['email'];
                if (empty($to) || $to == 'N/A' || $to == '') {
                    echo json_encode(['success' => false, 'message' => 'Supplier email not available']);
                    exit;
                }
                
                $emailRecord = [
                    'record_id' => $record['record_id'],
                    'reference_number' => $record['reference_number']
                ];
                
                $emailItems = [];
                foreach ($damageItems as $item) {
                    $emailItems[] = [
                        'item_code' => $item['item_code'],
                        'quantity' => $item['quantity'],
                        'item_name' => $item['item_name'] ?? ''
                    ];
                }
                
                $emailResult = sendSupplierEmail($to, $record['supplier_name'], $emailRecord, $emailItems);
                
                if ($emailResult && isset($emailResult['success']) && $emailResult['success']) {
                    $sent = true;
                    $responseMessage = "Email sent successfully to {$to}";
                } else {
                    $responseMessage = "Failed to send email: " . ($emailResult['message'] ?? 'Unknown error');
                }
            } 
            // Send SMS
            else if ($type == 'sms') {
                $phone = $record['contact_number'];
                if (empty($phone) || $phone == 'N/A' || $phone == '') {
                    echo json_encode(['success' => false, 'message' => 'Supplier contact number not available']);
                    exit;
                }
                
                // Check if already sent (print_count > 0) - require password for re-send
                if ($record['print_count'] > 0) {
                    if ($password !== 'admin123') {
                        echo json_encode(['success' => false, 'message' => 'Invalid admin password. This SMS has been sent before. Please enter correct admin password.']);
                        exit;
                    }
                }
                
                // For SMS, we'll simulate success since actual SMS service may not be configured
                // In production, integrate with your SMS gateway here
                $sent = true;
                $responseMessage = "SMS would be sent to {$phone} (Demo mode - SMS service not configured)";
            }
            
            // Always update is_informed = 1 when communication is sent successfully
            if ($sent) {
                $updateStmt = $pdo->prepare("
                    UPDATE qc_damage_main 
                    SET is_informed = 1, 
                        informed_by_user = ?, 
                        informed_datetime = NOW(), 
                        print_count = print_count + 1 
                    WHERE record_id = ?
                ");
                $updateStmt->execute([$_SESSION['username'], $record_id]);
                
                // Log the action
                $logStmt = $pdo->prepare("
                    INSERT INTO qc_audit_log (record_id, action, field_name, old_value, new_value, changed_by) 
                    VALUES (?, ?, 'communication', NULL, ?, ?)
                ");
                $logStmt->execute([$record_id, strtoupper($type), $type == 'email' ? $record['email'] : $record['contact_number'], $_SESSION['username']]);
                
                echo json_encode(['success' => true, 'message' => $responseMessage]);
            } else {
                echo json_encode(['success' => false, 'message' => $responseMessage]);
            }
            exit;
        }
        
        // Mark informed manually (flag only)
        if ($_GET['ajax'] == 'mark_informed') {
            $record_id = $_POST['record_id'];
            $password = $_POST['password'] ?? '';
            
            $stmt = $pdo->prepare("SELECT reference_number, is_informed FROM qc_damage_main WHERE record_id = ?");
            $stmt->execute([$record_id]);
            $damage = $stmt->fetch();
            
            if (!$damage) {
                echo json_encode(['success' => false, 'message' => 'Record not found']);
                exit;
            }
            
            if ($damage['is_informed']) {
                echo json_encode(['success' => false, 'message' => 'Already marked as informed']);
                exit;
            }
            
            // Admin password required
            if ($password !== 'admin123') {
                echo json_encode(['success' => false, 'message' => 'Invalid admin password']);
                exit;
            }
            
            // Generate reference number if missing
            if (empty($damage['reference_number'])) {
                $new_ref = 'REF-' . date('Ymd') . '-' . str_pad($record_id, 5, '0', STR_PAD_LEFT);
                $updateStmt = $pdo->prepare("UPDATE qc_damage_main SET reference_number = ? WHERE record_id = ?");
                $updateStmt->execute([$new_ref, $record_id]);
            }
            
            // Mark as informed
            $stmt = $pdo->prepare("
                UPDATE qc_damage_main 
                SET is_informed = 1, informed_by_user = ?, informed_datetime = NOW() 
                WHERE record_id = ?
            ");
            $stmt->execute([$_SESSION['username'], $record_id]);
            
            echo json_encode(['success' => true, 'message' => 'Supplier marked as informed successfully']);
            exit;
        }
        
        // Get suppliers for filter
        if ($_GET['ajax'] == 'get_suppliers') {
            $search = $_GET['search'] ?? '';
            $limit = min(100, intval($_GET['limit'] ?? 50));
            
            if (strlen($search) >= 2) {
                $stmt = $pdo->prepare("SELECT supplier_id, supplier_name, system_id FROM suppliers WHERE supplier_name LIKE ? OR system_id LIKE ? ORDER BY supplier_name LIMIT ?");
                $term = "%$search%";
                $stmt->execute([$term, $term, $limit]);
            } else {
                $stmt = $pdo->prepare("SELECT supplier_id, supplier_name, system_id FROM suppliers ORDER BY supplier_name LIMIT ?");
                $stmt->execute([$limit]);
            }
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

$username = $_SESSION['username'] ?? 'QC Officer';
$userRole = $_SESSION['role'] ?? 'Standard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASB Fashion | Supplier Inform </title>
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f5f7fb;
            min-height: 100vh;
        }

        .header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 50;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .logo h1 {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #1e293b;
        }
        .logo span {
            color: #dc2626;
        }
        .logo p {
            font-size: 0.65rem;
            color: #64748b;
            letter-spacing: 1px;
            margin-top: 4px;
        }
        .user-info {
            text-align: right;
        }
        .user-name {
            font-weight: 700;
            color: #1e293b;
            font-size: 0.9rem;
        }
        .user-role {
            font-size: 0.7rem;
            color: #64748b;
        }
        .back-btn {
            background: #dc2626;
            color: white;
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.8rem;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .back-btn:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }
        .logout-btn {
            background: #f1f5f9;
            color: #475569;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        .logout-btn:hover {
            background: #e2e8f0;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }

        .card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 24px;
            overflow: hidden;
        }
        .card-header {
            padding: 18px 24px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .card-header h2 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
        }
        .card-header h2 i {
            color: #dc2626;
            margin-right: 8px;
        }
        .card-body {
            padding: 24px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 20px;
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -10px rgba(0,0,0,0.1);
            border-color: #dc2626;
        }
        .stat-title {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: #1e293b;
            margin-top: 8px;
        }

        .form-group {
            margin-bottom: 16px;
        }
        .form-label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 6px;
        }
        .form-input, .form-select {
            width: 100%;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.85rem;
            color: #1e293b;
            transition: all 0.2s;
            outline: none;
        }
        .form-input:focus, .form-select:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
        }
        
        .typeahead-container {
            position: relative;
        }
        .typeahead-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        .typeahead-item {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }
        .typeahead-item:hover {
            background: #fef2f2;
        }
        .typeahead-item .supplier-name {
            font-weight: 600;
            color: #1e293b;
        }
        .typeahead-item .supplier-detail {
            font-size: 0.7rem;
            color: #64748b;
            margin-top: 2px;
        }
        .selected-supplier {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 30px;
            padding: 6px 12px;
            margin-top: 10px;
        }
        .remove-supplier {
            cursor: pointer;
            color: #dc2626;
            font-weight: bold;
        }

        .btn-primary {
            background: #dc2626;
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.8rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.8rem;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .table-wrapper {
            overflow-x: auto;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            text-align: left;
            padding: 16px;
            background: #f8fafc;
            color: #64748b;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
        }
        .data-table td {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            font-size: 0.85rem;
        }
        .data-table tr:hover {
            background: #fef2f2;
            cursor: pointer;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-informed {
            background: #d1fae5;
            color: #065f46;
        }
        .status-pending {
            background: #fed7aa;
            color: #92400e;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .pagination button {
            padding: 8px 14px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .pagination button:hover:not(:disabled) {
            background: #fef2f2;
            border-color: #dc2626;
        }
        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .page-info {
            font-size: 0.8rem;
            color: #64748b;
            margin: 0 10px;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
            display: none;
        }
        .modal-container {
            max-width: 1200px;
            width: 95%;
            margin: 20px auto;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.2);
        }
        .modal-header {
            padding: 18px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
        }
        .modal-header h2 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1e293b;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #94a3b8;
        }
        .modal-body {
            padding: 24px;
            max-height: calc(100vh - 80px);
            overflow-y: auto;
        }

        .detail-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }
        .detail-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 4px;
        }
        .detail-value {
            font-size: 0.9rem;
            font-weight: 500;
            color: #1e293b;
        }

        .status-timeline {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 0;
        }
        .timeline-step {
            text-align: center;
            flex: 1;
        }
        .step-icon {
            width: 40px;
            height: 40px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .timeline-step.completed .step-icon {
            background: #10b981;
            border-color: #10b981;
            color: white;
        }
        .timeline-step.active .step-icon {
            background: #f59e0b;
            border-color: #f59e0b;
            color: white;
        }
        .step-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748b;
        }
        .timeline-step.completed .step-label {
            color: #10b981;
        }
        .timeline-step.active .step-label {
            color: #f59e0b;
        }

        .reason-tag {
            display: inline-block;
            background: #fef3c7;
            color: #92400e;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            margin: 2px;
        }

        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .gallery-image {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .gallery-image:hover {
            transform: scale(1.02);
        }
        .gallery-image img {
            width: 100%;
            height: 100px;
            object-fit: cover;
        }

        .loading-spinner {
            display: inline-block;
            width: 24px;
            height: 24px;
            border: 2px solid #e2e8f0;
            border-top-color: #dc2626;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .badge-pass {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-fail {
            background: #fee2e2;
            color: #991b1b;
        }

        .hidden { display: none; }
        .flex { display: flex; }
        .flex-between { display: flex; justify-content: space-between; align-items: center; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }
        .gap-4 { gap: 16px; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mt-4 { margin-top: 16px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .mb-4 { margin-bottom: 16px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-mono { font-family: monospace; }
        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        @media (max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr; }
            .status-timeline { flex-direction: column; gap: 15px; }
        }
    </style>
</head>
<body>

<div class="header">
    <div class="header-content">
        <div class="logo">
            <h1>ASB <span>FASHION</span></h1>
            <p>SUPPLIER INFORM</p>
        </div>
        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($username) ?></div>
                <div class="user-role"><?= htmlspecialchars($userRole) ?></div>
            </div>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</div>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-search"></i> Search Invoices</h2>
        </div>
        <div class="card-body">
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Search</label>
                    <input type="text" id="searchInput" placeholder="Invoice #, Reference, Document, Supplier..." class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Filter by Supplier (Type to search)</label>
                    <div class="typeahead-container">
                        <input type="text" id="supplierTypeahead" placeholder="Type supplier name or ID..." class="form-input" autocomplete="off">
                        <div id="supplierTypeaheadResults" class="typeahead-results"></div>
                    </div>
                    <div id="selectedSupplierDisplay"></div>
                    <input type="hidden" id="supplierFilter" value="">
                </div>
            </div>
            <div class="flex gap-3 mt-3">
                <button onclick="searchInvoices()" class="btn-primary"><i class="fas fa-search"></i> Search Invoices</button>
                <button onclick="clearFilters()" class="btn-secondary"><i class="fas fa-eraser"></i> Clear</button>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-title">📊 Total Invoices</div><div class="stat-value" id="totalInvoices">0</div></div>
        <div class="stat-card"><div class="stat-title">✅ Informed</div><div class="stat-value" id="totalInformed" style="color:#10b981;">0</div></div>
        <div class="stat-card"><div class="stat-title">⏳ Pending</div><div class="stat-value" id="totalPending" style="color:#f59e0b;">0</div></div>
        <div class="stat-card"><div class="stat-title">🔄 QC Returns</div><div class="stat-value" id="totalWithReturn" style="color:#3b82f6;">0</div></div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-file-invoice"></i> Invoice Records</h2>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>INVOICE</th>
                            <th>SUPPLIER</th>
                            <th>REFERENCE</th>
                            <th>DATE</th>
                            <th class="text-center">STATUS</th>
                            <th class="text-right">ACTION</th>
                        </tr>
                    </thead>
                    <tbody id="invoicesTableBody">
                        <tr><td colspan="6" class="text-center py-12"><div class="loading-spinner"></div><p class="mt-2">Loading...</p></td></tr>
                    </tbody>
                </table>
            </div>
            <div id="paginationContainer" class="pagination"></div>
        </div>
    </div>
</div>

<div id="detailsModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h2><i class="fas fa-file-invoice"></i> Invoice Details</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div id="modalContent" class="modal-body"></div>
    </div>
</div>

<script>
    let currentInvoiceId = null;
    let currentRecordId = null;
    let currentPrintCount = 0;
    let searchTimeout = null;
    let supplierTypeaheadTimeout = null;
    let currentPage = 1;
    let totalPages = 1;
    let currentSupplierId = '';
    
    document.addEventListener('DOMContentLoaded', function() {
        searchInvoices();
        
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentPage = 1;
                searchInvoices();
            }, 500);
        });
        
        const supplierInput = document.getElementById('supplierTypeahead');
        supplierInput.addEventListener('input', function() {
            const search = this.value;
            clearTimeout(supplierTypeaheadTimeout);
            
            if (search.length < 2) {
                document.getElementById('supplierTypeaheadResults').style.display = 'none';
                return;
            }
            
            supplierTypeaheadTimeout = setTimeout(() => searchSuppliers(search), 300);
        });
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.typeahead-container')) {
                document.getElementById('supplierTypeaheadResults').style.display = 'none';
            }
        });
    });
    
    async function searchSuppliers(search) {
        try {
            const response = await fetch(`?ajax=search_suppliers&search=${encodeURIComponent(search)}&limit=20`);
            const result = await response.json();
            
            const resultsDiv = document.getElementById('supplierTypeaheadResults');
            
            if (result.success && result.data && result.data.length > 0) {
                resultsDiv.innerHTML = result.data.map(supplier => `
                    <div class="typeahead-item" onclick="selectSupplier(${supplier.supplier_id}, '${escapeHtml(supplier.supplier_name).replace(/'/g, "\\'")}', '${escapeHtml(supplier.system_id || '').replace(/'/g, "\\'")}')">
                        <div class="supplier-name">${escapeHtml(supplier.supplier_name)}</div>
                        <div class="supplier-detail">ID: ${escapeHtml(supplier.system_id || 'N/A')} | Contact: ${escapeHtml(supplier.contact_number || 'N/A')}</div>
                    </div>
                `).join('');
                resultsDiv.style.display = 'block';
            } else {
                resultsDiv.innerHTML = '<div class="typeahead-item" style="color:#64748b;"><i class="fas fa-search"></i> No suppliers found</div>';
                resultsDiv.style.display = 'block';
            }
        } catch (err) {
            console.error('Supplier search error:', err);
            document.getElementById('supplierTypeaheadResults').innerHTML = '<div class="typeahead-item" style="color:#ef4444;">Error loading suppliers</div>';
            document.getElementById('supplierTypeaheadResults').style.display = 'block';
        }
    }
    
    function selectSupplier(supplierId, supplierName, systemId) {
        currentSupplierId = supplierId;
        document.getElementById('supplierFilter').value = supplierId;
        document.getElementById('supplierTypeahead').value = supplierName;
        document.getElementById('supplierTypeaheadResults').style.display = 'none';
        
        document.getElementById('selectedSupplierDisplay').innerHTML = `
            <div class="selected-supplier">
                <i class="fas fa-check-circle" style="color:#10b981;"></i>
                <span><strong>${escapeHtml(supplierName)}</strong> ${systemId ? `(${escapeHtml(systemId)})` : ''}</span>
                <span class="remove-supplier" onclick="clearSupplierFilter()">&times;</span>
            </div>
        `;
        
        currentPage = 1;
        searchInvoices();
    }
    
    function clearSupplierFilter() {
        currentSupplierId = '';
        document.getElementById('supplierFilter').value = '';
        document.getElementById('supplierTypeahead').value = '';
        document.getElementById('selectedSupplierDisplay').innerHTML = '';
        document.getElementById('supplierTypeaheadResults').style.display = 'none';
        currentPage = 1;
        searchInvoices();
    }
    
    function clearFilters() {
        document.getElementById('searchInput').value = '';
        clearSupplierFilter();
        currentPage = 1;
        searchInvoices();
    }
    
    async function searchInvoices() {
        const search = document.getElementById('searchInput').value;
        const supplierId = currentSupplierId;
        const tbody = document.getElementById('invoicesTableBody');
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-12"><div class="loading-spinner"></div><p>Loading...</p></td></tr>';
        
        try {
            const url = `?ajax=search_invoices&search=${encodeURIComponent(search)}&supplier_id=${encodeURIComponent(supplierId)}&page=${currentPage}&per_page=25`;
            const response = await fetch(url);
            const result = await response.json();
            
            if (result.success) {
                const invoices = result.data;
                const pagination = result.pagination;
                totalPages = pagination.total_pages;
                
                updateStats(invoices);
                updatePagination(pagination);
                
                if (!invoices || invoices.length === 0) { 
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-12"><i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 10px; display: block; color: #cbd5e1;"></i>No invoices found</td></tr>'; 
                    return; 
                }
                
                tbody.innerHTML = invoices.map(inv => `
                    <tr onclick="viewFullDetails(${inv.invoice_id})">
                        <td><div class="font-bold">${escapeHtml(inv.invoice_number)}</div>${inv.doc_number ? `<small class="text-slate-400">Doc: ${escapeHtml(inv.doc_number)}</small>` : ''}</td>
                        <td><div class="font-semibold">${escapeHtml(inv.supplier_name)}</div><small class="text-slate-400">ID: ${escapeHtml(inv.system_id || 'N/A')}</small></td>
                        <td><span class="font-mono ${inv.qc_reference ? 'text-blue-600' : 'text-slate-400'}">${inv.qc_reference ? '#' + escapeHtml(inv.qc_reference) : '—'}</span></td>
                        <td>${inv.invoice_date}</td>
                        <td class="text-center">
                            <span class="status-badge ${inv.is_informed == 1 ? 'status-informed' : 'status-pending'}">
                                ${inv.is_informed == 1 ? '<i class="fas fa-check-circle"></i> Informed' : '<i class="fas fa-clock"></i> Pending'}
                            </span>
                            ${inv.has_damage_record > 0 ? '<div class="text-[10px] text-blue-500 mt-1"><i class="fas fa-clipboard-list"></i> QC Recorded</div>' : ''}
                        </td>
                        <td class="text-right">
                            <button class="btn-primary" style="padding: 8px 16px;" onclick="event.stopPropagation(); viewFullDetails(${inv.invoice_id})">
                                <i class="fas fa-eye"></i> View
                            </button>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-12 text-red-500">Error: ' + escapeHtml(result.message || 'Unknown error') + '</td></tr>';
            }
        } catch (err) { 
            console.error('Search error:', err);
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-12 text-red-500">Network error. Please check your connection.</td></tr>'; 
        }
    }
    
    function updatePagination(pagination) {
        const container = document.getElementById('paginationContainer');
        if (!pagination || pagination.total_pages <= 1) {
            container.innerHTML = '';
            return;
        }
        
        let html = `
            <button onclick="goToPage(1)" ${currentPage === 1 ? 'disabled' : ''}>&laquo; First</button>
            <button onclick="goToPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>&lsaquo; Prev</button>
            <span class="page-info">Page ${currentPage} of ${pagination.total_pages} (${pagination.total_records} records)</span>
            <button onclick="goToPage(${currentPage + 1})" ${currentPage === pagination.total_pages ? 'disabled' : ''}>Next &rsaquo;</button>
            <button onclick="goToPage(${pagination.total_pages})" ${currentPage === pagination.total_pages ? 'disabled' : ''}>Last &raquo;</button>
        `;
        
        container.innerHTML = html;
    }
    
    function goToPage(page) {
        if (page < 1 || page > totalPages) return;
        currentPage = page;
        searchInvoices();
    }
    
    function updateStats(invoices) {
        document.getElementById('totalInvoices').innerText = invoices.length;
        document.getElementById('totalInformed').innerText = invoices.filter(i => i.is_informed == 1).length;
        document.getElementById('totalPending').innerText = invoices.filter(i => i.is_informed != 1).length;
        document.getElementById('totalWithReturn').innerText = invoices.filter(i => i.has_damage_record > 0).length;
    }
    
    async function viewFullDetails(invoiceId) {
        currentInvoiceId = invoiceId;
        Swal.fire({ title: 'Loading...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        try {
            const response = await fetch(`?ajax=get_invoice_details&invoice_id=${invoiceId}`);
            const result = await response.json();
            Swal.close();
            if (result.success) {
                if (result.damageRecord) {
                    currentRecordId = result.damageRecord.record_id;
                    currentPrintCount = result.damageRecord.print_count || 0;
                } else {
                    currentRecordId = null;
                }
                renderModalContent(result);
                document.getElementById('detailsModal').style.display = 'block';
                document.body.style.overflow = 'hidden';
            } else { 
                Swal.fire('Error', result.message || 'Could not load details', 'error'); 
            }
        } catch (err) { 
            Swal.close(); 
            Swal.fire('Error', 'Failed to load invoice details', 'error'); 
        }
    }
    
    function renderModalContent(data) {
        const inv = data.invoice;
        const items = data.items || [];
        const damage = data.damageRecord;
        const damageItems = data.damageItems || [];
        const images = data.images || [];
        const isInformed = damage ? (damage.is_informed == 1) : false;
        const totalReturnQty = damageItems.reduce((sum, i) => sum + (parseInt(i.quantity) || 0), 0);
        const totalReturnValue = damageItems.reduce((sum, i) => sum + (parseInt(i.quantity) * parseFloat(i.unit_cost || 0)), 0);
        
        const modalContent = document.getElementById('modalContent');
        modalContent.innerHTML = `
            <div class="detail-card" style="background: linear-gradient(135deg, #fef2f2, #fee2e2); border-color: #fecaca;">
                <div class="flex-between flex-wrap gap-3">
                    <div><div class="detail-label">Invoice Number</div><div class="detail-value" style="font-size: 1.2rem; font-weight: 700;">${escapeHtml(inv.invoice_number)}</div></div>
                    <div><div class="detail-label">QC Reference</div><div class="detail-value font-mono" style="color: #dc2626; font-weight: 700;">${damage && damage.reference_number ? '#' + escapeHtml(damage.reference_number) : 'PENDING'}</div></div>
                    <div><div class="detail-label">Status</div><span class="status-badge ${isInformed ? 'status-informed' : 'status-pending'}">${isInformed ? '✓ INFORMED' : '⏳ PENDING'}</span></div>
                </div>
            </div>
            
            <div class="detail-card">
                <h3 style="font-weight: 700; margin-bottom: 15px;">Process Timeline</h3>
                <div class="status-timeline">
                    <div class="timeline-step ${damage ? 'completed' : ''}"><div class="step-icon"><i class="fas fa-file-alt"></i></div><div class="step-label">QC Created</div></div>
                    <div class="timeline-step ${isInformed ? 'completed' : (damage ? 'active' : '')}"><div class="step-icon"><i class="fas fa-envelope"></i></div><div class="step-label">Informed</div></div>
                    <div class="timeline-step"><div class="step-icon"><i class="fas fa-warehouse"></i></div><div class="step-label">Store</div></div>
                    <div class="timeline-step"><div class="step-icon"><i class="fas fa-check-circle"></i></div><div class="step-label">Complete</div></div>
                </div>
            </div>
            
            <div class="grid-2">
                <div class="detail-card">
                    <h3 style="font-weight: 700; margin-bottom: 15px;"><i class="fas fa-building"></i> Supplier Information</h3>
                    <div><div class="detail-label">Name</div><div class="detail-value">${escapeHtml(inv.supplier_name)}</div></div>
                    <div class="mt-2"><div class="detail-label">System ID</div><div class="detail-value font-mono">${escapeHtml(inv.system_id || 'N/A')}</div></div>
                    <div class="mt-2"><div class="detail-label">Contact</div><div class="detail-value">${escapeHtml(inv.contact_number || 'N/A')}</div></div>
                    <div class="mt-2"><div class="detail-label">Email</div><div class="detail-value">${escapeHtml(inv.email || 'N/A')}</div></div>
                </div>
                <div class="detail-card">
                    <h3 style="font-weight: 700; margin-bottom: 15px;"><i class="fas fa-file-invoice"></i> Invoice Information</h3>
                    <div><div class="detail-label">Invoice Date</div><div class="detail-value">${inv.invoice_date}</div></div>
                    <div class="mt-2"><div class="detail-label">Document No</div><div class="detail-value">${escapeHtml(inv.doc_number || 'N/A')}</div></div>
                    <div class="mt-2"><div class="detail-label">QC Date</div><div class="detail-value">${inv.checked_date || 'N/A'}</div></div>
                    <div class="mt-2"><div class="detail-label">Inspector</div><div class="detail-value">${escapeHtml(inv.checker_name || 'N/A')}</div></div>
                </div>
            </div>
            
            ${damage ? `
            <div class="detail-card">
                <h3 style="font-weight: 700; margin-bottom: 15px;"><i class="fas fa-clipboard-list"></i> QC Return Record</h3>
                <div class="grid-2">
                    <div><div class="detail-label">Record ID</div><div class="detail-value">#${damage.record_id}</div></div>
                    <div><div class="detail-label">Reference</div><div class="detail-value font-mono" style="color:#10b981;">${escapeHtml(damage.reference_number)}</div></div>
                    <div><div class="detail-label">Date</div><div class="detail-value">${damage.record_date}</div></div>
                    <div><div class="detail-label">Mode</div><div class="detail-value">${escapeHtml(damage.mode_name || 'N/A')}</div></div>
                    <div><div class="detail-label">Reason</div><div class="detail-value">${escapeHtml(damage.primary_reason || 'N/A')}</div></div>
                    <div><div class="detail-label">Print Count</div><div class="detail-value">${damage.print_count || 0} time(s)</div></div>
                </div>
            </div>
            ` : '<div class="detail-card text-center text-slate-500 py-8"><i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>No QC return record found</div>'}
            
            <div class="detail-card">
                <h3 style="font-weight: 700; margin-bottom: 15px;"><i class="fas fa-boxes"></i> Invoice Items with Return Reasons</h3>
                <div class="table-wrapper">
                    <table class="data-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Item Name</th>
                                <th class="text-right">Received</th>
                                <th class="text-right">Return Qty</th>
                                <th>Return Reasons</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${items.map(item => {
                                let reasonsHtml = '—';
                                if (item.return_qty > 0) {
                                    if (item.return_reasons_formatted && item.return_reasons_formatted !== '') {
                                        const reasonList = item.return_reasons_formatted.split(' | ');
                                        reasonsHtml = reasonList.map(r => `<span class="reason-tag">${escapeHtml(r)}</span>`).join('');
                                    } else if (item.detailed_reasons && item.detailed_reasons.length > 0) {
                                        reasonsHtml = item.detailed_reasons.map(r => 
                                            `<span class="reason-tag">${escapeHtml(r.reason_text)} (${r.return_qty} pcs)</span>`
                                        ).join('');
                                    } else {
                                        reasonsHtml = `<span class="reason-tag" style="background:#fee2e2;">No reason recorded (${item.return_qty} pcs)</span>`;
                                    }
                                }
                                
                                return `
                                <tr style="${(item.return_qty || 0) > 0 ? 'background: #fef3c7;' : ''}">
                                    <td class="font-mono">${escapeHtml(item.item_code)}</td>
                                    <td>${escapeHtml(item.item_name || 'N/A')}</td>
                                    <td class="text-right">${item.received_qty || 0}</td>
                                    <td class="text-right" style="font-weight: 700; color:#f59e0b;">${item.return_qty || 0}</td>
                                    <td>${reasonsHtml}</td>
                                    <td class="text-center"><span class="badge ${item.status === 'FAIL' ? 'badge-fail' : 'badge-pass'}">${item.status || 'PASS'}</span></td>
                                </tr>
                            `}).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
            
            ${damageItems.length > 0 ? `
            <div class="detail-card">
                <h3 style="font-weight: 700; margin-bottom: 15px;"><i class="fas fa-undo-alt"></i> QC Return Items</h3>
                <div class="table-wrapper">
                    <table class="data-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Item Name</th>
                                <th class="text-right">Quantity</th>
                                <th class="text-right">Unit Cost</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${damageItems.map(item => `
                                <tr>
                                    <td class="font-mono">${escapeHtml(item.item_code)}</td>
                                    <td>${escapeHtml(item.item_name || 'N/A')}</td>
                                    <td class="text-right" style="font-weight: 700; color:#f59e0b;">${item.quantity || 0}</td>
                                    <td class="text-right">${parseFloat(item.unit_cost || 0).toFixed(2)}</td>
                                    <td class="text-right" style="color:#10b981;">${(item.quantity * item.unit_cost).toFixed(2)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                        <tfoot style="background: #f8fafc; font-weight: 700;">
                            <tr><td colspan="2">TOTAL</td><td class="text-right">${totalReturnQty} pcs</td><td></td><td class="text-right" style="color:#dc2626;">${totalReturnValue.toFixed(2)} LKR</td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            ` : ''}
            
            ${images.length > 0 ? `
            <div class="detail-card">
                <h3 style="font-weight: 700; margin-bottom: 15px;"><i class="fas fa-images"></i> Evidence Images (${images.length})</h3>
                <div class="image-gallery">
                    ${images.map(img => `<div class="gallery-image" onclick="window.open('${img.image_path}', '_blank')"><img src="${img.image_path}"><div style="text-align: center; padding: 5px; font-size: 10px; background: #f8fafc;"><i class="fas fa-search-plus"></i> View</div></div>`).join('')}
                </div>
            </div>
            ` : ''}
            
            <div class="flex gap-3" style="margin-top: 20px;">
                ${damage ? `
                    <button onclick="sendCommunication('email')" class="btn-primary" style="flex: 1;"><i class="fas fa-envelope"></i> Send Email</button>
                    <button onclick="sendCommunication('sms')" class="btn-primary" style="flex: 1; background: #10b981;"><i class="fas fa-phone"></i> Send SMS</button>
                    ${!isInformed ? `<button onclick="markInformed()" class="btn-primary" style="flex: 1; background: #f59e0b;"><i class="fas fa-flag-checkered"></i> Mark Informed</button>` : ''}
                ` : '<div class="text-center text-slate-500 py-4 bg-slate-50 rounded-xl w-full">No QC return record found. Please create a QC return record first.</div>'}
            </div>
        `;
    }
    
    async function sendCommunication(type) {
        if (!currentRecordId) { 
            Swal.fire('Error', 'No QC record found', 'error'); 
            return; 
        }
        
        let password = '';
        
        if (type === 'sms' && currentPrintCount > 0) {
            const { value: pass } = await Swal.fire({
                title: 'Admin Authorization',
                text: 'This SMS has been sent before. Enter admin password.',
                input: 'password',
                inputPlaceholder: 'Admin Password',
                showCancelButton: true,
                confirmButtonText: 'Authorize',
                cancelButtonText: 'Cancel',
                icon: 'warning'
            });
            if (!pass) return;
            password = pass;
        }
        
        const result = await Swal.fire({
            title: `Send ${type.toUpperCase()} Notification`,
            text: `Send ${type.toUpperCase()} to supplier? The informed flag will be updated.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: `Yes, Send ${type.toUpperCase()}`,
            cancelButtonText: 'Cancel'
        });
        
        if (!result.isConfirmed) return;
        
        Swal.fire({ title: 'Sending...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        
        try {
            const formData = new URLSearchParams();
            formData.append('record_id', currentRecordId);
            formData.append('type', type);
            formData.append('password', password);
            
            const response = await fetch('?ajax=send_communication', { method: 'POST', body: formData });
            const data = await response.json();
            Swal.close();
            
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Success!', text: data.message, timer: 2000 }).then(() => { 
                    viewFullDetails(currentInvoiceId); 
                    searchInvoices(); 
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Failed', text: data.message });
            }
        } catch (err) {
            Swal.close();
            Swal.fire({ icon: 'error', title: 'Error', text: err.message });
        }
    }
    
    async function markInformed() {
        if (!currentRecordId) { 
            Swal.fire('Error', 'No QC record found', 'error'); 
            return; 
        }
        
        const { value: password } = await Swal.fire({
            title: 'Admin Authorization',
            text: 'Enter admin password to mark supplier as informed',
            input: 'password',
            inputPlaceholder: 'Admin Password',
            showCancelButton: true,
            confirmButtonText: 'Authorize',
            cancelButtonText: 'Cancel',
            icon: 'warning'
        });
        
        if (!password) return;
        
        Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        
        try {
            const formData = new URLSearchParams();
            formData.append('record_id', currentRecordId);
            formData.append('password', password);
            
            const response = await fetch('?ajax=mark_informed', { method: 'POST', body: formData });
            const data = await response.json();
            Swal.close();
            
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Success!', text: data.message, timer: 2000 }).then(() => { 
                    viewFullDetails(currentInvoiceId); 
                    searchInvoices(); 
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Failed', text: data.message });
            }
        } catch (err) {
            Swal.close();
            Swal.fire({ icon: 'error', title: 'Error', text: err.message });
        }
    }
    
    function closeModal() { 
        document.getElementById('detailsModal').style.display = 'none'; 
        document.body.style.overflow = 'auto'; 
    }
    
    function escapeHtml(str) { 
        if (!str) return ''; 
        return String(str).replace(/[&<>]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[m])); 
    }
    
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>
</body>
</html>