<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(200); exit; }

require_once "../../config/db.php";

try {
    $stmt = $pdo->query("SELECT id, name, email, role, is_active, created_at FROM users ORDER BY created_at DESC");
    echo json_encode(["users" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>
