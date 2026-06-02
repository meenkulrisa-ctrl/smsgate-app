<?php

$username = 'JTFBNP';
$password = 'cle1dbdoccuv0i';

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
            'devices:list',
            'messages:read',
            'messages:write'
        ],
        'ttl' => 3600
    ])
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

echo "<h3>HTTP CODE: {$httpCode}</h3>";
echo "<pre>";
print_r($response);
echo "</pre>";
