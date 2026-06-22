<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

// GET — fetch user profile
if ($method === 'GET') {
    $user_id = $_GET['user_id'] ?? null;

    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['error' => 'user_id is required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email, u.phone, u.role, u.created_at,
                   r.name as region_name, c.name as country_name
            FROM users u
            LEFT JOIN regions r ON u.region_id = r.id
            LEFT JOIN countries c ON r.country_id = c.id
            WHERE u.id = ? AND u.is_active = 1
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        echo json_encode($user);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch profile: ' . $e->getMessage()]);
    }

// PUT — update user profile
} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['user_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'user_id is required']);
        exit;
    }

    $user_id = $data['user_id'];
    $name    = trim($data['name'] ?? '');
    $phone   = trim($data['phone'] ?? '');

    if (!$name) {
        http_response_code(400);
        echo json_encode(['error' => 'Name is required']);
        exit;
    }

    try {
        // Update password if provided
        if (!empty($data['new_password'])) {
            if (strlen($data['new_password']) < 6) {
                http_response_code(400);
                echo json_encode(['error' => 'Password must be at least 6 characters']);
                exit;
            }

            // Verify current password
            $check = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $check->execute([$user_id]);
            $user = $check->fetch(PDO::FETCH_ASSOC);

            if (!password_verify($data['current_password'] ?? '', $user['password_hash'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Current password is incorrect']);
                exit;
            }

            $newHash = password_hash($data['new_password'], PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, password_hash = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $newHash, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $user_id]);
        }

        echo json_encode(['message' => 'Profile updated successfully']);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update profile: ' . $e->getMessage()]);
    }

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>