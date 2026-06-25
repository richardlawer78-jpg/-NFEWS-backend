<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/db.php';

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['email']) || empty($data['new_password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and new password are required']);
    exit;
}

$email       = trim($data['email']);
$newPassword = $data['new_password'];

if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must be at least 6 characters']);
    exit;
}

try {
    // Check if user exists
    $check = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND is_active = 1");
    $check->execute([$email]);
    $user = $check->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'No account found with this email address']);
        exit;
    }

    // Update password
    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
    $stmt->execute([$hash, $email]);

    echo json_encode([
        'message' => 'Password reset successfully. You can now login with your new password.',
        'name'    => $user['name']
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Password reset failed: ' . $e->getMessage()]);
}
?>
