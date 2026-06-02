<?php
header("Content-Type: text/event-stream");
header("Cache-Control: no-cache");
header("Connection: keep-alive");

$username = 'JTFBNP';
$password = 'cle1dbdoccuv0i';
$deviceId = 'U-ucDm6OQfO6FlCytxNIE';

// 1) get token
$ch = curl_init('https://api.sms-gate.app/3rdparty/v1/auth/token');

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_USERPWD => $username . ':' . $password,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'scopes' => ['messages:read'],
        'ttl' => 3600
    ])
]);

$tokenData = json_decode(curl_exec($ch), true);
curl_close($ch);

$token = $tokenData['access_token'] ?? '';

if (!$token) exit;
$sentIds = [];

while (true) {

    $url = "https://api.sms-gate.app/3rdparty/v1/messages?limit=20&offset=0&deviceId=$deviceId";

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "Accept: application/json"
        ]
    ]);

    $res = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($res, true);

    if (!is_array($data)) {
        sleep(3);
        continue;
    }

foreach ($data as $msg) {

    // รับเฉพาะข้อความเข้า
echo "data: " . json_encode($data) . "\n\n";
flush();
exit;

    $id = $msg['id'] ?? '';

    if (!$id) {
        continue;
    }

    // ถ้าเคยส่งไปแล้ว ข้าม
    if (in_array($id, $sentIds)) {
        continue;
    }

    $sentIds[] = $id;

    echo "data: " . json_encode($msg) . "\n\n";

    flush();
        }

    sleep(3);
}

