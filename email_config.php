<?php
/**
 * Email Configuration File
 * Centralized email sending functionality for ASB Fashion System
 */

function sendSupplierEmailAPIEnhanced($data) {
    $items = $data['items'];
    $record = $data['record'];
    $supplierName = $data['supplierName'];
    $email = $data['email'];
    
    // Build item table rows
    $rows = "";
    $count = 0;
    foreach ($items as $item) {
        $count++;
        $bgColor = ($count % 2 === 0) ? '#f8fafc' : '#ffffff';
        $itemName = isset($item['item_name']) && $item['item_name'] ? $item['item_name'] : 'N/A';
        
        $rows .= "
        <tr style='background-color: {$bgColor};'>
            <td style='padding: 12px 15px; border-bottom: 1px solid #e2e8f0; font-family: Arial, sans-serif; font-size: 14px; color: #334155;'>
                " . htmlspecialchars($item['item_code']) . "
            </td>
            <td style='padding: 12px 15px; border-bottom: 1px solid #e2e8f0; font-family: Arial, sans-serif; font-size: 14px; color: #334155;'>
                " . htmlspecialchars($itemName) . "
            </td>
            <td style='padding: 12px 15px; border-bottom: 1px solid #e2e8f0; text-align: center; font-family: Arial, sans-serif; font-size: 14px; color: #334155; font-weight: bold;'>
                " . htmlspecialchars($item['quantity']) . "
            </td>
        </tr>";
    }
    
    // Calculate total quantity
    $totalQty = array_sum(array_column($items, 'quantity'));
    $totalValue = array_sum(array_map(function($item) {
        return $item['quantity'] * ($item['unit_cost'] ?? 0);
    }, $items));
    
    // Subject line
    $subject = "QC Return Notification: Ref #" . $record['reference_number'] . " - ASB Fashion";
    
    // Modern HTML email template
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>QC Return Notification</title>
        <style>
            @media only screen and (max-width: 600px) {
                .container { width: 100% !important; }
                .content { padding: 20px !important; }
            }
        </style>
    </head>
    <body style="margin: 0; padding: 0; background-color: #f4f6f8; -webkit-text-size-adjust: none; text-size-adjust: none;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f4f6f8; padding: 40px 20px;">
            <tr>
                <td align="center">
                    <table width="100%" max-width="650" style="max-width: 650px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border-collapse: separate;" cellpadding="0" cellspacing="0" border="0">
                        
                        <!-- Top Decorative Bar -->
                        <tr>
                            <td height="6" style="background: linear-gradient(90deg, #dc2626, #991b1b); line-height: 6px; font-size: 6px;">&nbsp;</td>
                        </tr>

                        <!-- Header -->
                        <tr>
                            <td style="padding: 35px 40px 25px 40px; border-bottom: 1px solid #f1f5f9;">
                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td>
                                            <h1 style="margin: 0; font-family: \'Segoe UI\', Arial, sans-serif; font-size: 24px; font-weight: 700; color: #0f172a; letter-spacing: 0.5px;">
                                                ASB FASHION
                                            </h1>
                                            <p style="margin: 4px 0 0 0; font-family: \'Segoe UI\', Arial, sans-serif; font-size: 13px; color: #64748b; text-transform: uppercase; letter-spacing: 1px;">
                                                Quality Control & Returns Department
                                            </p>
                                        </td>
                                        <td style="text-align: right;">
                                            <div style="width: 40px; height: 40px; background: #dc2626; border-radius: 8px; text-align: center; line-height: 40px;">
                                                <span style="color: white; font-size: 20px;">✓</span>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- Body -->
                        <tr>
                            <td style="padding: 40px;">
                                <p style="margin: 0 0 18px 0; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.6; color: #334155;">
                                    Dear <strong>' . htmlspecialchars($supplierName) . '</strong>,
                                </p>

                                <p style="margin: 0 0 25px 0; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.6; color: #475569;">
                                    This is an automated notification from the ASB Fashion Returns Management System. A formal quality control return record has been issued and registered under your profile.
                                </p>

                                <!-- Action Required Box -->
                                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 30px; background-color: #fffafb; border-left: 4px solid #dc2626; border-radius: 4px;">
                                    <tr>
                                        <td style="padding: 16px 20px;">
                                            <h4 style="margin: 0 0 6px 0; font-family: Arial, sans-serif; font-size: 14px; color: #991b1b; text-transform: uppercase; letter-spacing: 0.5px;">Action Required</h4>
                                            <p style="margin: 0; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.5; color: #7f1d1d;">
                                                Please evaluate the detailed breakdown below and process standard corrective handling procedures accordingly.
                                            </p>
                                        </td>
                                    </tr>
                                </table>

                                <!-- Record Details Card -->
                                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 35px;">
                                    <tr>
                                        <td style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; width: 40%; font-family: Arial, sans-serif; font-size: 14px; color: #64748b;">
                                            <strong>Record ID:</strong>
                                        </td>
                                        <td style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; font-family: Arial, sans-serif; font-size: 14px; color: #1e293b;">
                                            #' . htmlspecialchars($record['record_id']) . '
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 16px 20px; font-family: Arial, sans-serif; font-size: 14px; color: #64748b;">
                                            <strong>Reference Number:</strong>
                                        </td>
                                        <td style="padding: 16px 20px; font-family: Arial, sans-serif; font-size: 15px; font-weight: bold; color: #dc2626;">
                                            ' . htmlspecialchars($record['reference_number']) . '
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 16px 20px; border-top: 1px solid #e2e8f0; font-family: Arial, sans-serif; font-size: 14px; color: #64748b;">
                                            <strong>Date Issued:</strong>
                                        </td>
                                        <td style="padding: 16px 20px; border-top: 1px solid #e2e8f0; font-family: Arial, sans-serif; font-size: 14px; color: #1e293b;">
                                            ' . date('F d, Y') . '
                                        </td>
                                    </tr>
                                </table>

                                <!-- Items Table -->
                                <h3 style="margin: 0 0 14px 0; font-family: \'Segoe UI\', Arial, sans-serif; font-size: 16px; font-weight: 600; color: #0f172a;">
                                    Returned Item Overview
                                </h3>
                                
                                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse; margin-bottom: 25px; border: 1px solid #e2e8f0;">
                                    <thead>
                                        <tr style="background-color: #f1f5f9;">
                                            <th align="left" style="padding: 12px 15px; font-family: Arial, sans-serif; font-size: 13px; font-weight: 600; color: #475569; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">Item Code</th>
                                            <th align="left" style="padding: 12px 15px; font-family: Arial, sans-serif; font-size: 13px; font-weight: 600; color: #475569; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">Item Name</th>
                                            <th align="center" style="padding: 12px 15px; font-family: Arial, sans-serif; font-size: 13px; font-weight: 600; color: #475569; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; width: 25%;">Quantity</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ' . $rows . '
                                    </tbody>
                                    <tfoot>
                                        <tr style="background-color: #f8fafc;">
                                            <td colspan="2" style="padding: 12px 15px; font-family: Arial, sans-serif; font-size: 14px; font-weight: bold; color: #1e293b; border-top: 2px solid #e2e8f0;">
                                                TOTAL
                                            </td>
                                            <td align="center" style="padding: 12px 15px; font-family: Arial, sans-serif; font-size: 16px; font-weight: bold; color: #dc2626; border-top: 2px solid #e2e8f0;">
                                                ' . $totalQty . ' pcs
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>

                                ' . ($totalValue > 0 ? '
                                <div style="background-color: #fef2f2; border-radius: 6px; padding: 12px 15px; margin-bottom: 30px; text-align: right;">
                                    <span style="font-family: Arial, sans-serif; font-size: 13px; color: #991b1b;">Total Return Value: </span>
                                    <strong style="font-family: Arial, sans-serif; font-size: 16px; color: #dc2626;">LKR ' . number_format($totalValue, 2) . '</strong>
                                </div>
                                ' : '') . '

                                <!-- Additional Info -->
                                <p style="margin: 0 0 30px 0; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.5; color: #64748b; font-style: italic;">
                                    If you require additional technical validation metrics or clarification regarding this claim, please feel free to drop a fresh query line directly to the ASB Quality Control Support Desk.
                                </p>

                                <hr style="border: 0; border-top: 1px solid #e2e8f0; margin-bottom: 25px;">

                                <!-- Signature -->
                                <p style="margin: 0; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #475569;">
                                    Sincerely,<br>
                                    <strong style="color: #0f172a;">ASB Fashion Head Office</strong><br>
                                    <span style="color: #64748b; font-size: 13px;">Returns Management & Quality Assurance<br>Waskaduwa, Kalutara, Sri Lanka</span>
                                </p>

                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style="background-color: #f8fafc; padding: 30px 40px; border-top: 1px solid #e2e8f0; text-align: center;">
                                <p style="margin: 0 0 10px 0; font-family: Arial, sans-serif; font-size: 12px; line-height: 1.5; color: #94a3b8;">
                                    <strong>Please Note:</strong> This is an automated outbound transmission. Replies to this address will not be monitored.
                                </p>
                                <p style="margin: 0; font-family: Arial, sans-serif; font-size: 11px; color: #94a3b8;">
                                    &copy; ' . date('Y') . ' ASB Group of Companies. All Rights Reserved.
                                </p>
                            </td>
                        </tr>

                    </table>
                <td>
            </tr>
        </table>
    </body>
    </html>';
    
    // Prepare POST data for email API
    $postData = [
        'api_key' => 'ASB_MAIL_2026',
        'email' => $email,
        'name' => $supplierName,
        'subject' => $subject,
        'message' => $html,
        'record_id' => $record['record_id'],
        'reference_number' => $record['reference_number']
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://whats.asbfashion.com/Mail/send_email.php',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Log for debugging
    error_log("Email Debug - To: {$email}, HTTP: {$httpCode}");
    
    if ($httpCode == 200 && $response) {
        $result = json_decode($response, true);
        if ($result && isset($result['success']) && $result['success']) {
            return ['success' => true, 'message' => 'Email sent successfully'];
        }
        return ['success' => false, 'message' => $result['message'] ?? 'API returned error'];
    }
    
    if ($curlError) {
        return ['success' => false, 'message' => 'CURL Error: ' . $curlError];
    }
    
    return ['success' => false, 'message' => 'Email service unavailable (HTTP: ' . $httpCode . ')'];
}
?>