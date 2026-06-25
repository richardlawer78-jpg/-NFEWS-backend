<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

require_once '../../config/db.php';
$ARKESEL_API_KEY = "YWNQRnNocXJLUXZqbmtBTlJKaEE";
$SENDER_ID       = "NFEWS";

$data = json_decode(file_get_contents("php://input"), true);

$phone   = $data['phone']   ?? '';
$message = $data['message'] ?? '';
$district = $data['district'] ?? 'Unknown';
$risk_level = $data['risk_level'] ?? 'caution';

if (!$phone || !$message) {
    echo json_encode(["error" => "Phone and message are required"]);
    exit;
}

// Format phone number for Ghana (+233)
$phone = preg_replace('/\D/', '', $phone);
if (substr($phone, 0, 1) === '0') {
    $phone = '233' . substr($phone, 1);
}
if (substr($phone, 0, 3) !== '233') {
    $phone = '233' . $phone;
}

// Send SMS via Arkesel API v2
$url = "https://sms.arkesel.com/api/v2/sms/send";

$payload = json_encode([
    "sender"     => $SENDER_ID,
    "message"    => $message,
    "recipients" => [$phone]
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "api-key: " . $ARKESEL_API_KEY,
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

// Log to DB
try {
    $stmt = $pdo->prepare("
        INSERT INTO sms_logs (phone, message, district, risk_level, status, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $status = ($httpCode === 200) ? 'sent' : 'failed';
    $stmt->execute([$phone, $message, $district, $risk_level, $status]);
} catch (Exception $e) {
    // Log silently
}

echo json_encode([
    "success"   => $httpCode === 200,
    "http_code" => $httpCode,
    "response"  => $result
]);
?>
