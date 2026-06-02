<?php

$username = 'JTFBNP';
$password = 'cle1dbdoccuv0i';

// GET TOKEN
$ch = curl_init('https://api.sms-gate.app/3rdparty/v1/auth/token');

curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_USERPWD => $username.':'.$password,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'scopes' => [
            'inbox:read'
        ],
        'ttl' => 3600
    ])
]);

$tokenData = json_decode(curl_exec($ch), true);
curl_close($ch);

$token = $tokenData['access_token'] ?? '';

if(!$token){
    die('Token Error');
}

// GET INBOX
$ch = curl_init(
'https://api.sms-gate.app/3rdparty/v1/inbox?limit=10'
);

curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer '.$token,
        'Accept: application/json'
    ]
]);

$response = curl_exec($ch);
$code = curl_getinfo($ch,CURLINFO_HTTP_CODE);

curl_close($ch);

echo "<h3>HTTP CODE: {$code}</h3>";
echo "<pre>";
print_r(json_decode($response,true));
echo "</pre>";
