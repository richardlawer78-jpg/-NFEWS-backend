<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

require_once '../../config/db.php';

try {
    $stmt = $pdo->query("SELECT * FROM sms_logs ORDER BY created_at DESC LIMIT 50");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["logs" => $logs]);
} catch (Exception $e) {
    echo json_encode(["logs" => [], "error" => $e->getMessage()]);
}
?>