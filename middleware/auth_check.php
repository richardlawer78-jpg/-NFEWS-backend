<?php
function authenticate() {
    $headers = getallheaders();
    
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No authorization token provided']);
        exit;
    }

    $token = str_replace('Bearer ', '', $headers['Authorization']);

    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token format']);
        exit;
    }

    return $token;
}

function requireRole($pdo, $token, $required_role) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE auth_token = ? AND is_active = 1");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        if ($user['role'] !== $required_role && $user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Access forbidden']);
            exit;
        }

        return $user;

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Auth check failed']);
        exit;
    }
}
?>