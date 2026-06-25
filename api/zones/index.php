<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET");

require_once '../../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        // Check if a specific district id is requested
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("
                SELECT d.*, r.name as region_name, c.name as country_name 
                FROM districts d
                JOIN regions r ON d.region_id = r.id
                JOIN countries c ON r.country_id = c.id
                WHERE d.id = ?
            ");
            $stmt->execute([$_GET['id']]);
            $district = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$district) {
                http_response_code(404);
                echo json_encode(['error' => 'District not found']);
                exit;
            }

            echo json_encode($district);

        } else {
            // Get all districts with region and country info
            $country_filter = isset($_GET['country']) ? $_GET['country'] : null;

            if ($country_filter) {
                $stmt = $pdo->prepare("
                    SELECT d.*, r.name as region_name, c.name as country_name 
                    FROM districts d
                    JOIN regions r ON d.region_id = r.id
                    JOIN countries c ON r.country_id = c.id
                    WHERE c.code = ?
                    ORDER BY d.risk_level DESC
                ");
                $stmt->execute([$country_filter]);
            } else {
                $stmt = $pdo->query("
                    SELECT d.*, r.name as region_name, c.name as country_name 
                    FROM districts d
                    JOIN regions r ON d.region_id = r.id
                    JOIN countries c ON r.country_id = c.id
                    ORDER BY d.risk_level DESC
                ");
            }

            $districts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'total' => count($districts),
                'districts' => $districts
            ]);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch zones: ' . $e->getMessage()]);
    }

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
