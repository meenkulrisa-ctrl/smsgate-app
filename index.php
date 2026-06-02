<?php

$result = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $phone = $_POST['phone'];
    $message = $_POST['message'];

    $username = 'JTFBNP';
    $password = 'cle1dbdoccuv0i';
    $deviceId = 'U-ucDm6OQfO6FlCytxNIE';

    // Get Token
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
            'scopes' => ['messages:write'],
            'ttl' => 3600
        ])
    ]);

    $tokenData = json_decode(curl_exec($ch), true);
    curl_close($ch);

    $token = $tokenData['access_token'];

    // Send SMS
    $ch = curl_init('https://api.sms-gate.app/3rdparty/v1/messages');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'deviceId' => $deviceId,
            'phoneNumbers' => [$phone],
            'textMessage' => [
                'text' => $message
            ],
            'simNumber' => 1
        ])
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    $result = "HTTP CODE: {$httpCode}<br><pre>{$response}</pre>";
}
?>

<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>SMS Gateway</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>
<body class="bg-light">

<div class="container mt-5">

<div class="card shadow">
<div class="card-body">

<h3>Send SMS</h3>

<form method="post">

<div class="mb-3">
<label>Phone Number</label>
<input type="text" name="phone" class="form-control" placeholder="0812345678" required>
</div>

<div class="mb-3">
<label>Message</label>
<textarea name="message" class="form-control" rows="4" required></textarea>
</div>

<button class="btn btn-primary">
Send SMS
</button>

</form>

<hr>

<?= $result ?>

</div>
</div>

</div>

</body>
</html>
