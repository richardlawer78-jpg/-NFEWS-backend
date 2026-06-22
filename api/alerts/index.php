 <?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, POST");

require_once '../../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $stmt = $pdo->query("
            SELECT a.*, d.name as district_name, r.name as region_name, c.name as country_name
            FROM alerts a
            JOIN districts d ON a.district_id = d.id
            JOIN regions r ON d.region_id = r.id
            JOIN countries c ON r.country_id = c.id
            WHERE a.is_active = 1
            ORDER BY a.created_at DESC
        ");
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'total' => count($alerts),
            'alerts' => $alerts
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch alerts: ' . $e->getMessage()]);
    }

} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['district_id']) || empty($data['risk_level']) || empty($data['message'])) {
        http_response_code(400);
        echo json_encode(['error' => 'district_id, risk_level and message are required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO alerts (district_id, risk_level, message, triggered_by)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['district_id'],
            $data['risk_level'],
            $data['message'],
            $data['triggered_by'] ?? 'manual'
        ]);

        // Update district risk level
        $update = $pdo->prepare("UPDATE districts SET risk_level = ? WHERE id = ?");
        $update->execute([$data['risk_level'], $data['district_id']]);

        http_response_code(201);
        echo json_encode([
            'message' => 'Alert created successfully',
            'alert_id' => $pdo->lastInsertId()
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create alert: ' . $e->getMessage()]);
    }

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>