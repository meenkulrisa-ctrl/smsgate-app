<?php

$TOKEN = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzbXMtZ2F0ZS5hcHAiLCJzdWIiOiJKVEZCTlAiLCJleHAiOjE3ODA0Mjc3NzIsImlhdCI6MTc4MDQyNjg3MiwianRpIjoibmJNYTFtQXh0V0xHNi1mU2RvY0twIiwidXNlcl9pZCI6IkpURkJOUCIsInNjb3BlcyI6WyJkZXZpY2VzOmxpc3QiLCJtZXNzYWdlczpyZWFkIiwibWVzc2FnZXM6d3JpdGUiLCJtZXNzYWdlczpzZW5kIiwibWVzc2FnZXM6bGlzdCJdfQ.j2r-eBtNf-BJTquS59rMGPhjHHuN2s0tm4UorJ0svYU";

$ch = curl_init("https://sms-gate.app/3rdparty/v1/message");

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $TOKEN",
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode([
        "deviceId" => "U-ucDm6OQfO6FlCytxNIE",
        "phoneNumber" => "+66812345678",
        "message" => "Test"
    ])
]);

$response = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "HTTP = $http\n\n";
echo $response;
