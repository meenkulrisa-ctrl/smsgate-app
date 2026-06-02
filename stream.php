<?php
header("Content-Type: text/event-stream");
header("Cache-Control: no-cache");
header("Connection: keep-alive");

while (true) {

    $data = [
        "textMessage" => ["text" => "test"],
        "state" => "live"
    ];

    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();

    sleep(3);
}
$username = 'JTFBNP';
$password = 'cle1dbdoccuv0i';
$deviceId = 'U-ucDm6OQfO6FlCytxNIE';

function getToken($username,$password){

    $ch = curl_init('https://api.sms-gate.app/3rdparty/v1/auth/token');

    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $username.':'.$password,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'scopes'=>['messages:list'],
            'ttl'=>3600
        ])
    ]);

    $res = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($res,true);
    return $data['access_token'] ?? null;
}

$token = getToken($username,$password);

if(!$token){
    echo "data: ".json_encode(['error'=>'no token'])."\n\n";
    flush();
    exit;
}

$lastId = '';

while(true){

    $ch = curl_init("https://api.sms-gate.app/3rdparty/v1/messages?limit=1&offset=0&deviceId=$deviceId");

    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token"
        ]
    ]);

    $res = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($res,true);

    $msg = $data[0] ?? null;

    if($msg && $msg['id'] !== $lastId){

        $lastId = $msg['id'];

        echo "data: ".json_encode($msg)."\n\n";
        flush();
    }

    sleep(3);
}
