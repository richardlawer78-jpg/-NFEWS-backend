<?php
header("Content-Type: application/json");

$data = [
    'district_id' => 47,
    'level_m'     => 1.8
];

$ch = curl_init('http://localhost/NFEWS/nfews-backend/api/sensors/reading.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
curl_close($ch);

echo $response;
?>