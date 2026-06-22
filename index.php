<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

echo json_encode([
    'status' => 'NFEWS API is running',
    'version' => '1.0.0'
]);
?>