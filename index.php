<?php

$username = 'JTFBNP';
$password = 'cle1dbdoccuv0i';

/* STEP 1: ขอ Token */
$ch = curl_init('https://api.sms-gate.app/3rdparty/v1/auth/token');

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_USERPWD => $username . ':' . $password,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'scopes' => [
            'devices:list'
        ],
        'ttl' => 3600
    ])
]);

$tokenResponse = json_decode(curl_exec($ch), true);
curl_close($ch);

$token = $tokenResponse['access_token'] ?? '';

/* STEP 2: ดึง Device */
$ch = curl_init('https://api.sms-gate.app/3rdparty/v1/devices');

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Authorization: Bearer ' . $token
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

echo "<h2>HTTP CODE: {$httpCode}</h2>";
echo "<pre>";
print_r($response);
echo "</pre>";
