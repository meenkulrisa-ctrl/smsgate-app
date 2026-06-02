<?php
$res = apiRequest("POST", "/messages", [
    "deviceId" => "U-ucDm6OQfO6FlCytxNIE",
    "message" => "Test SMS",
    "phoneNumbers" => ["+66832707590"]
]);

echo "<pre>";
print_r($res);
