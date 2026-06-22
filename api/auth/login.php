<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/db.php';

// Read raw input
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

// Debug — remove later
// error_log("Raw input: " . $raw);

if (empty($data['email']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Email and password are required',
        'received' => $data
    ]);
    exit;
}

$email    = trim($data['email']);
$password = $data['password'];

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'No account found with this email']);
        exit;
    }

    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Incorrect password']);
        exit;
    }

    $token = bin2hex(random_bytes(32));

    echo json_encode([
        'message' => 'Login successful',
        'token'   => $token,
        'user'    => [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role']
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Login failed: ' . $e->getMessage()]);
}
?>