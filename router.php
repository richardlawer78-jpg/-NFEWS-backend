<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;
if (is_file($file)) { require $file; exit; }
$phpFile = __DIR__ . $uri . '.php';
if (is_file($phpFile)) { require $phpFile; exit; }
http_response_code(404);
echo json_encode(['error' => 'Not found']);