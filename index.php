<?php
session_start();

$error = '';
$result = '';

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['login'])
) {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $deviceId = trim($_POST['device_id']);

    $ch = curl_init(
        'https://api.sms-gate.app/3rdparty/v1/auth/token'
    );

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'username' => $username,
            'password' => $password
        ])
    ]);

    $response = json_decode(
        curl_exec($ch),
        true
    );

    curl_close($ch);

    if (!empty($response['accessToken'])) {

        $_SESSION['token'] = $response['accessToken'];
        $_SESSION['username'] = $username;
        $_SESSION['device_id'] = $deviceId;

        header("Location: index.php");
        exit;
    }

    $error = '<pre>' . htmlspecialchars(json_encode($response, JSON_PRETTY_PRINT)) . '</pre>';
}

/*
|--------------------------------------------------------------------------
| SEND SMS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['send_sms'])
    && !empty($_SESSION['token'])
) {

    $phone = trim($_POST['phone']);
    $message = trim($_POST['message']);

    $payload = [
        'deviceId' => $_SESSION['device_id'],
        'phoneNumbers' => [$phone],
        'message' => $message
    ];

    $ch = curl_init(
        'https://api.sms-gate.app/3rdparty/v1/messages'
    );

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' .
            $_SESSION['token'],
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);

    $result = curl_exec($ch);

    curl_close($ch);
}
?>

<!doctype html>

<html>
<head>

<meta charset="utf-8">
<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>SMS Gateway</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

<div class="container py-5">

<?php if(empty($_SESSION['token'])): ?>

<div class="row justify-content-center">

<div class="col-md-5">

<div class="card shadow">

<div class="card-body p-4">

<h3 class="text-center mb-4">
SMS Gate Login
</h3>

<?php if($error): ?>

<div class="alert alert-danger">
<?= $error ?>
</div>

<?php endif; ?>

<form method="post">

<input type="hidden"
name="login"
value="1">

<div class="mb-3">

<label>
Username
</label>

<input
type="text"
name="username"
class="form-control"
required>

</div>

<div class="mb-3">

<label>
Password
</label>

<input
type="password"
name="password"
class="form-control"
required>

</div>

<div class="mb-3">

<label>
Device ID
</label>

<input
type="text"
name="device_id"
class="form-control"
required>

</div>

<button
type="submit"
class="btn btn-primary w-100">

Login

</button>

</form>

</div>
</div>

</div>

</div>

<?php else: ?>

<div class="row justify-content-center">

<div class="col-md-8">

<div class="card shadow">

<div class="card-header d-flex justify-content-between">

<div>

<strong>
SMS Gateway Dashboard
</strong>

</div>

<a
href="?logout=1"
class="btn btn-danger btn-sm">

Logout

</a>

</div>

<div class="card-body">

<div class="alert alert-success">

Logged in : <strong>

<?= htmlspecialchars($_SESSION['username']) ?>

</strong>

<br>

Device : <strong>

<?= htmlspecialchars($_SESSION['device_id']) ?>

</strong>

</div>

<form method="post">

<input
type="hidden"
name="send_sms"
value="1">

<div class="mb-3">

<label>
Phone Number
</label>

<input
type="text"
name="phone"
class="form-control"
placeholder="0812345678"
required>

</div>

<div class="mb-3">

<label>
Message
</label>

<textarea
name="message"
class="form-control"
rows="5"
required></textarea>

</div>

<button
type="submit"
class="btn btn-success">

Send SMS

</button>

</form>

<?php if($result): ?>

<hr>

<h5>API Response</h5>

<pre class="bg-dark text-white p-3 rounded">
<?= htmlspecialchars($result) ?>
</pre>

<?php endif; ?>

</div>

</div>

</div>

</div>

<?php endif; ?>

</div>

</body>
</html>
