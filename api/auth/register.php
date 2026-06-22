 <?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/db.php';

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Name, email and password are required']);
    exit;
}

$name      = trim($data['name']);
$email     = trim($data['email']);
$password  = password_hash($data['password'], PASSWORD_BCRYPT);
$phone     = $data['phone'] ?? null;
$role      = $data['role'] ?? 'citizen';
$region_id = !empty($data['region_id']) ? $data['region_id'] : null;

try {
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Email already registered']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role, region_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $email, $phone, $password, $role, $region_id]);

    http_response_code(201);
    echo json_encode([
        'message' => 'Account created successfully',
        'user_id' => $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed: ' . $e->getMessage()]);
}
?>