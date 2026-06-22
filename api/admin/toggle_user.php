<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(200); exit; }

require_once "../../config/db.php";
$data = json_decode(file_get_contents("php://input"), true);

try {
    $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    $stmt->execute([$data["is_active"], $data["user_id"]]);
    echo json_encode(["message" => "User updated successfully"]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>
