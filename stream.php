<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

$username = 'JTFBNP';
$password = 'cle1dbdoccuv0i';
$deviceId = 'U-ucDm6OQfO6FlCytxNIE';

function getToken() {
    global $username, $password;

    $ch = curl_init('https://api.sms-gate.app/3rdparty/v1/auth/token');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_USERPWD => $username . ':' . $password,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'scopes' => ['messages:list','messages:read'],
            'ttl' => 3600
        ])
    ]);

    $res = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($res, true);
    return $data['access_token'] ?? null;
}

$token = getToken();

if (!$token) {
    echo "data: " . json_encode(['error' => 'no token']) . "\n\n";
    flush();
    exit;
}

$lastId = null;

while (true) {

    $url = "https://api.sms-gate.app/3rdparty/v1/messages"
        . "?limit=10&offset=0&deviceId=$deviceId";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token"
        ]
    ]);

    $res = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($res, true);
    if (!is_array($data)) $data = [];

    // เอาอันล่าสุด
    $latest = $data[0] ?? null;

    if ($latest && $latest['id'] !== $lastId) {
        $lastId = $latest['id'];

        echo "data: " . json_encode($latest) . "\n\n";
        flush();
    }

    sleep(2);
}
