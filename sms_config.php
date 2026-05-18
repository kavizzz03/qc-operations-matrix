<?php

function sendSupplierSMS($contactNumber, $message)
{
    $apiUrl = "https://whats.asbfashion.com/send_sms_batch.php";

    $contactNumber = trim(str_replace(" ", "", $contactNumber));

    $payload = [
        "numbers" => [$contactNumber],
        "content" => $message
    ];

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {

        return [
            "success" => false,
            "message" => curl_error($ch)
        ];
    }

    curl_close($ch);

    $data = json_decode($response, true);

    if (isset($data['success']) && $data['success']) {

        return [
            "success" => true,
            "message" => "SMS sent successfully"
        ];
    }

    return [
        "success" => false,
        "message" => "SMS API failed",
        "response" => $response
    ];
}
?>