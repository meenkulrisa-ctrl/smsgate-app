<?php
$result = '';
$status = '';
$messageId = '';
$currentState = '';
    $username = 'JTFBNP';
    $password = 'cle1dbdoccuv0i';
    $deviceId = 'U-ucDm6OQfO6FlCytxNIE';
function checkStatus($token,$messageId){

    $ch = curl_init(
        "https://api.sms-gate.app/3rdparty/v1/messages/".$messageId
    );

    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer ".$token
        ]
    ]);

    $res = curl_exec($ch);

    curl_close($ch);

    return $res;
}


function getInbox($token,$deviceId){

$url = "https://api.sms-gate.app/3rdparty/v1/messages?limit=20&offset=0&deviceId=".$deviceId;

    $ch = curl_init($url);

    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer ".$token,
            "Accept: application/json"
        ]
    ]);

    $res = curl_exec($ch);
    curl_close($ch);

    return json_decode($res,true);
}


$result = '';
$status = '';
$messageId = '';
if(isset($_GET['inbox'])){

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
            'scopes'=>[
                'devices:list',
                'messages:read',
                'messages:write',
                'messages:send',
                'messages:list'
            ],
            'ttl' => 3600
        ])
    ]);

    $tokenData = json_decode(curl_exec($ch), true);
    curl_close($ch);

    $token = $tokenData['access_token'] ?? '';

    if(!$token){
        header('Content-Type: application/json');
        echo json_encode([
            "error" => "no token",
            "raw" => $tokenData
        ]);
        exit;
    }

    $url = "https://api.sms-gate.app/3rdparty/v1/messages"
        . "?limit=20"
        . "&offset=0"
        . "&deviceId=" . $deviceId;

    $ch = curl_init($url);

    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer '.$token,
            'Accept: application/json'
        ]
    ]);

    $res = curl_exec($ch);
    curl_close($ch);

    header('Content-Type: application/json');
    echo $res;
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $phone = trim($_POST['phone']);
    $message = trim($_POST['message']);
    $phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
    $phone = '+66' . ltrim($phone, '0');
    // SMSGate Credentials
    

    // =========================
    // STEP 1 : GET TOKEN
    // =========================

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
'scopes' => [
    'devices:list',
    'messages:read',
    'messages:write',
    'messages:send'
],
            'ttl' => 3600
        ])
    ]);

    $tokenResponse = curl_exec($ch);
    $tokenCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    $tokenData = json_decode($tokenResponse, true);

    if (!isset($tokenData['access_token'])) {

        $result = '
        <div class="alert alert-danger">
            <h5>Token Error</h5>
            <strong>HTTP CODE:</strong> ' . $tokenCode . '
            <hr>
            <pre>' . htmlspecialchars($tokenResponse) . '</pre>
        </div>';

    } else {

        $token = $tokenData['access_token'];
        $inboxMessages = getInbox(
    $token,
    $deviceId
);

        // =========================
        // STEP 2 : SEND SMS
        // =========================

        $payload = [
            'deviceId' => $deviceId,
            'phoneNumbers' => [$phone],
            'textMessage' => [
                'text' => $message
            ],
            'simNumber' => 2
        ];

        $ch = curl_init('https://api.sms-gate.app/3rdparty/v1/messages?skipPhoneValidation=true');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ],
            CURLOPT_POSTFIELDS => json_encode($payload)
        ]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

$responseData = json_decode($response,true);

$messageId = $responseData['id'] ?? '';
if($messageId){

    sleep(2);

    $status = checkStatus($token,$messageId);

    $statusData = json_decode($status,true);

    $currentState = $statusData['state'] ?? 'Unknown';

}
        curl_close($ch);

$result = '
<div class="alert alert-info">

<h5>SMS Result</h5>

<p>
<strong>HTTP CODE:</strong> '.$httpCode.'
</p>

<hr>

<strong>Payload:</strong>

<pre>'.
htmlspecialchars(
json_encode(
$payload,
JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE
)
).
'</pre>

<strong>Message ID:</strong>

<pre>'.
htmlspecialchars($messageId).
'</pre>

<strong>Status:</strong>

<div class="alert alert-warning">
'.htmlspecialchars($currentState ?? 'Unknown').'
</div>

<strong>Response:</strong>

<pre>'.
htmlspecialchars($response).
'</pre>

</div>';
    }
}

?>

<!DOCTYPE html>
<html lang="th">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>SMS Gateway</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-md-8">

            <div class="card shadow-lg border-0">

                <div class="card-header bg-primary text-white text-center">

                    <h3 class="mb-0">
                        SMS Gateway
                    </h3>

                </div>

                <div class="card-body p-4">

                    <form method="POST">

                        <div class="mb-3">

                            <label class="form-label">
                                Phone Number
                            </label>

<div class="input-group">
    <span class="input-group-text">+66</span>
    <input
        type="text"
        name="phone"
        class="form-control"
        placeholder="ใส่เบอร์ที่จะส่งครับอิอิ"
        required>
</div>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Message
                            </label>

                            <textarea
                                name="message"
                                rows="5"
                                class="form-control"
                                placeholder="พิมพ์ข้อความที่ต้องการส่ง..."
                                required></textarea>

                        </div>

                        <button class="btn btn-success w-100">

                            Send SMS

                        </button>

                    </form>

<hr>

<?= $result ?>

<div id="inbox"></div>

</div>

            </div>

        </div>

    </div>

</div>

<script>
function loadInbox(){

fetch('?inbox=1')
.then(async r => {
    const text = await r.text();
    console.log("INBOX RAW:", data);
      console.log("RAW RESPONSE TEXT:", text);

    try {
        return JSON.parse(text);
    } catch (e) {
        console.log("Not JSON:", text);
        return null;
    }
})
.then(data => {
    console.log("INBOX RAW:", data);
if (!Array.isArray(data)) {
    console.log("API error:", data?.message || data);
    return;
}

    let rows = data;

    let html = `
        <div class="card mt-4">
        <div class="card-header bg-dark text-white">
        Inbox / Messages
        </div>
        <div class="card-body">
    `;

rows.forEach(row => {

    let msg =
        row.contentPreview ||
        row.message ||
        row.text ||
        (row.textMessage && row.textMessage.text) ||
        '-';

    let from = row.sender || row.from || '-';
    let date = row.createdAt || row.receivedAt || '-';

    html += `
        <div class="border rounded p-2 mb-2">
            <b>From:</b> ${from}<br>
            <b>Date:</b> ${date}<br>
            <b>Message:</b><br>
            ${msg}
        </div>
    `;
});

    html += `</div></div>`;
    document.getElementById('inbox').innerHTML = html;
})
    .catch(err => console.log(err));
}

loadInbox();
setInterval(loadInbox, 5000);
</script>

</html>
