<?php
// Email Function File

function sendSupplierEmail($email, $supplierName, $record, $items)
{
    /*
    |--------------------------------------------------------------------------
    | BUILD ITEM TABLE ROWS WITH REASONS
    |--------------------------------------------------------------------------
    */
    $rows = "";
    $count = 0;
    $totalQty = 0;
    $totalValue = 0;
    
    foreach ($items as $item) {
        $count++;
        $bgColor = ($count % 2 === 0) ? '#f8fafc' : '#ffffff';
        $itemTotal = ($item['quantity'] ?? 0) * ($item['unit_cost'] ?? 0);
        $totalQty += $item['quantity'] ?? 0;
        $totalValue += $itemTotal;
        
        $rows .= "
        <tr style='background-color: {$bgColor};'>
            <td style='padding: 12px 15px; border-bottom: 1px solid #e2e8f0; font-family: Arial, sans-serif; font-size: 14px; color: #334155;'>
                " . htmlspecialchars($item['item_code']) . "
             </td>
            <td style='padding: 12px 15px; border-bottom: 1px solid #e2e8f0; font-family: Arial, sans-serif; font-size: 14px; color: #334155;'>
                " . htmlspecialchars($item['item_name'] ?? 'N/A') . "
             </td>
            <td style='padding: 12px 15px; border-bottom: 1px solid #e2e8f0; text-align: center; font-family: Arial, sans-serif; font-size: 14px; color: #334155; font-weight: bold;'>
                " . htmlspecialchars($item['quantity']) . "
             </td>
            <td style='padding: 12px 15px; border-bottom: 1px solid #e2e8f0; text-align: right; font-family: Arial, sans-serif; font-size: 14px; color: #334155;'>
                LKR " . number_format($item['unit_cost'] ?? 0, 2) . "
             </td>
            <td style='padding: 12px 15px; border-bottom: 1px solid #e2e8f0; text-align: right; font-family: Arial, sans-serif; font-size: 14px; color: #dc2626; font-weight: bold;'>
                LKR " . number_format($itemTotal, 2) . "
             </td>
         </tr>";
        
        // Add return reasons row if exists
        if (!empty($item['return_reasons'])) {
            $rows .= "
            <tr style='background-color: #fef3c7;'>
                <td colspan='5' style='padding: 8px 15px; font-family: Arial, sans-serif; font-size: 12px; color: #92400e;'>
                    <i class='fas fa-info-circle'></i> <strong>Return Reason:</strong> " . htmlspecialchars($item['return_reasons']) . "
                 </td>
             </tr>";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SUBJECT
    |--------------------------------------------------------------------------
    */
    $subject = "QC Return Notification: Ref #" . $record['reference_number'] . " - ASB Fashion";

    /*
    |--------------------------------------------------------------------------
    | MODERN & ATTRACTIVE EMAIL DESIGN WITH RETURN REASONS
    |--------------------------------------------------------------------------
    */
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>QC Return Notification</title>
        <style>
            @import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap");
            body { font-family: "Inter", Arial, sans-serif; }
        </style>
    </head>
    <body style="margin: 0; padding: 0; background-color: #f4f6f8; -webkit-text-size-adjust: none; text-size-adjust: none;">

        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f4f6f8; padding: 40px 20px;">
            <tr>
                <td align="center">
                    
                    <!-- Email Container -->
                    <table width="100%" max-width="650" style="max-width: 650px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.08); border-collapse: separate;" cellpadding="0" cellspacing="0" border="0">
                        
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
                                            <h1 style="margin: 0; font-family: \'Inter\', Arial, sans-serif; font-size: 28px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px;">
                                                ASB <span style="color: #dc2626;">FASHION</span>
                                            </h1>
                                            <p style="margin: 6px 0 0 0; font-family: \'Inter\', Arial, sans-serif; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 1px;">
                                                Quality Control & Returns Department
                                            </p>
                                        </td>
                                        <td align="right">
                                            <div style="background: #fef2f2; padding: 8px 16px; border-radius: 12px;">
                                                <span style="font-size: 12px; font-weight: 600; color: #dc2626;">QC RETURN</span>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                         </tr>

                        <!-- Body -->
                        <tr>
                            <td style="padding: 40px;">
                                
                                <p style="margin: 0 0 20px 0; font-family: \'Inter\', Arial, sans-serif; font-size: 16px; line-height: 1.6; color: #334155;">
                                    Dear <strong style="color: #0f172a;">' . htmlspecialchars($supplierName) . '</strong>,
                                </p>

                                <p style="margin: 0 0 25px 0; font-family: \'Inter\', Arial, sans-serif; font-size: 15px; line-height: 1.6; color: #475569;">
                                    This is an automated notification from the ASB Fashion Returns Management System. 
                                    A quality control return record has been issued under your profile.
                                </p>

                                <!-- Alert Box -->
                                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 30px; background-color: #fef2f2; border-left: 4px solid #dc2626; border-radius: 8px;">
                                    <tr>
                                        <td style="padding: 16px 20px;">
                                            <table width="100%" cellpadding="0" cellspacing="0">
                                                <tr>
                                                    <td width="30" style="vertical-align: top;">
                                                        <span style="font-size: 20px;">⚠️</span>
                                                    </td>
                                                    <td>
                                                        <h4 style="margin: 0 0 6px 0; font-family: \'Inter\', Arial, sans-serif; font-size: 14px; font-weight: 700; color: #991b1b;">
                                                            Action Required
                                                        </h4>
                                                        <p style="margin: 0; font-family: \'Inter\', Arial, sans-serif; font-size: 13px; line-height: 1.5; color: #7f1d1d;">
                                                            Please review the return details below and process accordingly.
                                                        </p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>

                                <!-- Record Information -->
                                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 35px;">
                                    <tr style="border-bottom: 1px solid #e2e8f0;">
                                        <td style="padding: 16px 20px; font-family: \'Inter\', Arial, sans-serif; font-size: 13px; font-weight: 600; color: #64748b; width: 40%;">
                                            Record ID:
                                        </td>
                                        <td style="padding: 16px 20px; font-family: \'Inter\', Arial, sans-serif; font-size: 14px; color: #1e293b; font-weight: 500;">
                                            #' . htmlspecialchars($record['record_id']) . '
                                        </td>
                                    </tr>
                                    <tr style="border-bottom: 1px solid #e2e8f0;">
                                        <td style="padding: 16px 20px; font-family: \'Inter\', Arial, sans-serif; font-size: 13px; font-weight: 600; color: #64748b;">
                                            Reference Number:
                                        </td>
                                        <td style="padding: 16px 20px; font-family: \'Inter\', Arial, sans-serif; font-size: 16px; font-weight: 700; color: #dc2626;">
                                            ' . htmlspecialchars($record['reference_number']) . '
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 16px 20px; font-family: \'Inter\', Arial, sans-serif; font-size: 13px; font-weight: 600; color: #64748b;">
                                            Invoice Number:
                                        </td>
                                        <td style="padding: 16px 20px; font-family: \'Inter\', Arial, sans-serif; font-size: 14px; color: #1e293b;">
                                            ' . htmlspecialchars($record['invoice_number']) . '
                                        </td>
                                    </tr>
                                }x

                                <!-- Items Table -->
                                <h3 style="margin: 0 0 16px 0; font-family: \'Inter\', Arial, sans-serif; font-size: 18px; font-weight: 700; color: #0f172a;">
                                    <span style="background: #dc2626; width: 8px; height: 8px; display: inline-block; border-radius: 4px; margin-right: 8px;"></span>
                                    Returned Items Summary
                                </h3>
                                
                                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse; margin-bottom: 35px; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
                                    <thead>
                                        <tr style="background-color: #f1f5f9;">
                                            <th align="left" style="padding: 14px 15px; font-family: \'Inter\', Arial, sans-serif; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Item Code</th>
                                            <th align="left" style="padding: 14px 15px; font-family: \'Inter\', Arial, sans-serif; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Item Name</th>
                                            <th align="center" style="padding: 14px 15px; font-family: \'Inter\', Arial, sans-serif; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Quantity</th>
                                            <th align="right" style="padding: 14px 15px; font-family: \'Inter\', Arial, sans-serif; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Unit Cost</th>
                                            <th align="right" style="padding: 14px 15px; font-family: \'Inter\', Arial, sans-serif; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ' . $rows . '
                                    </tbody>
                                    <tfoot style="background-color: #f8fafc; border-top: 2px solid #e2e8f0;">
                                        <tr>
                                            <td colspan="2" style="padding: 14px 15px; font-family: \'Inter\', Arial, sans-serif; font-size: 14px; font-weight: 700; color: #1e293b;">
                                                TOTAL
                                              </td>
                                            <td align="center" style="padding: 14px 15px; font-family: \'Inter\', Arial, sans-serif; font-size: 14px; font-weight: 700; color: #1e293b;">
                                                ' . $totalQty . ' pcs
                                              </td>
                                            <td align="right" style="padding: 14px 15px; font-family: \'Inter\', Arial, sans-serif; font-size: 14px;">
                                                &nbsp;
                                              </td>
                                            <td align="right" style="padding: 14px 15px; font-family: \'Inter\', Arial, sans-serif; font-size: 16px; font-weight: 800; color: #059669;">
                                                LKR ' . number_format($totalValue, 2) . '
                                              </td>
                                        </tr>
                                    </tfoot>
                                }x

                                <!-- Footer Note -->
                                <p style="margin: 0 0 30px 0; font-family: \'Inter\', Arial, sans-serif; font-size: 13px; line-height: 1.5; color: #64748b; font-style: italic;">
                                    <i class="fas fa-info-circle"></i> For any clarifications, please contact the ASB Quality Control Support Desk.
                                </p>

                                <hr style="border: 0; border-top: 1px solid #e2e8f0; margin-bottom: 25px;">

                                <!-- Signature -->
                                <p style="margin: 0; font-family: \'Inter\', Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #475569;">
                                    Sincerely,<br>
                                    <strong style="color: #0f172a;">ASB Fashion Head Office</strong><br>
                                    <span style="color: #64748b; font-size: 12px;">Returns Management & Quality Assurance<br>Waskaduwa, Kalutara, Sri Lanka</span>
                                </p>

                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style="background-color: #f8fafc; padding: 25px 40px; border-top: 1px solid #e2e8f0; text-align: center;">
                                <p style="margin: 0 0 8px 0; font-family: \'Inter\', Arial, sans-serif; font-size: 11px; line-height: 1.5; color: #94a3b8;">
                                    <strong>Note:</strong> This is an automated system notification. Please do not reply to this email.
                                </p>
                                <p style="margin: 0; font-family: \'Inter\', Arial, sans-serif; font-size: 10px; color: #94a3b8;">
                                    &copy; ' . date('Y') . ' ASB Group of Companies. All Rights Reserved.
                                </p>
                            </td>
                        </tr>

                    )}
                    
                </td>
            </tr>
        </table>
    </body>
    </html>
    ';

    /*
    |--------------------------------------------------------------------------
    | SEND TO API
    |--------------------------------------------------------------------------
    */
    $postData = [
        'api_key' => 'ASB_MAIL_2026',
        'email'   => $email,
        'name'    => $supplierName,
        'subject' => $subject,
        'message' => $html,
        'headers' => [
            "From: ASB Fashion System <no-reply@asbfashion.com>",
            "Reply-To: no-reply@asbfashion.com",
            "X-Auto-Response-Suppress: All",
            "Precedence: bulk",
            "Auto-Submitted: auto-generated"
        ]
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://whats.asbfashion.com/Mail/send_email.php',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($postData),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        return [
            'success' => false,
            'message' => curl_error($ch)
        ];
    }

    curl_close($ch);

    if ($httpCode == 200 && $response) {
        return json_decode($response, true);
    }

    return [
        'success' => false,
        'message' => 'Email service unavailable (HTTP: ' . $httpCode . ')'
    ];
}
?>