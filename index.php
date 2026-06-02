<?php

$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $deviceId = $_POST['deviceId'] ?? '';

    $phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
    $phone = '+66' . ltrim($phone, '0');

    $message = trim($_POST['message'] ?? '');

    $sent = true;

    // MOCK RESULT
    $messageStatus = [
        'id' => 'MSG-' . rand(1000,9999),
        'state' => 'Pending'
    ];

    // MOCK INBOX
    $inbox = [
        [
            'createdAt' => date('Y-m-d H:i:s'),
            'sender' => '+66812345678',
            'contentPreview' => 'สวัสดีครับ'
        ],
        [
            'createdAt' => date('Y-m-d H:i:s'),
            'sender' => '+66999999999',
            'contentPreview' => 'ทดสอบตอบกลับ'
        ]
    ];
}

?>
<!doctype html>
<html lang="th">
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title>SMS Gateway Template</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

<div class="container py-5">

<div class="row justify-content-center">

<div class="col-lg-8">

<div class="card shadow border-0">

<div class="card-header bg-primary text-white">

<h3 class="mb-0">
SMS Gateway Template
</h3>

</div>

<div class="card-body">

<form method="post">

<div class="row">

<div class="col-md-4 mb-3">
<label>Username</label>
<input
type="text"
name="username"
class="form-control"
required>
</div>

<div class="col-md-4 mb-3">
<label>Password</label>
<input
type="password"
name="password"
class="form-control"
required>
</div>

<div class="col-md-4 mb-3">
<label>Device ID</label>
<input
type="text"
name="deviceId"
class="form-control"
required>
</div>

</div>

<div class="mb-3">

<label>Phone Number</label>

<div class="input-group">

<span class="input-group-text">
+66
</span>

<input
type="text"
name="phone"
class="form-control"
placeholder="812345678"
required>

</div>

</div>

<div class="mb-3">

<label>Message</label>

<textarea
name="message"
rows="4"
class="form-control"
required></textarea>

</div>

<div class="alert alert-secondary">

<strong>SIM:</strong> SIM2

</div>

<button
type="submit"
class="btn btn-success w-100"
onclick="return confirm('ยืนยันการส่ง SMS ?')">

Send SMS

</button>

</form>

<?php if($sent): ?>

<hr>

<div class="alert alert-info">

<h5>Preview</h5>

<p>

<strong>To:</strong>
<?= htmlspecialchars($phone) ?>

</p>

<p>

<strong>Message:</strong><br>

<?= nl2br(htmlspecialchars($message)) ?>

</p>

</div>

<div class="card mb-3">

<div class="card-header bg-warning">

Message Status

</div>

<div class="card-body">

<table class="table table-bordered">

<tr>
<th>ID</th>
<td><?= $messageStatus['id'] ?></td>
</tr>

<tr>
<th>Status</th>
<td><?= $messageStatus['state'] ?></td>
</tr>

</table>

</div>

</div>

<div class="card">

<div class="card-header bg-dark text-white">

Inbox (Reply SMS)

</div>

<div class="card-body">

<table class="table table-striped">

<thead>

<tr>

<th>เวลา</th>
<th>จาก</th>
<th>ข้อความ</th>

</tr>

</thead>

<tbody>

<?php foreach($inbox as $sms): ?>

<tr>

<td><?= htmlspecialchars($sms['createdAt']) ?></td>

<td><?= htmlspecialchars($sms['sender']) ?></td>

<td><?= htmlspecialchars($sms['contentPreview']) ?></td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

<?php endif; ?>

</div>

</div>

</div>

</div>

</div>

<script>

setInterval(function(){

console.log('Auto Refresh 5 sec');

},5000);

</script>

</body>
</html>
