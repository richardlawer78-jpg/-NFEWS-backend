<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once "../../config/db.php";

$ARKESEL_API_KEY = "YWNQRnNocXJLUXZqbmtBTlJKaEE";
$SENDER_ID = "NFEWS";

function sendAutoSMS($phone, $message, $districtName, $risk, $pdo, $apiKey, $sender) {
    $phone = preg_replace("/\D/", "", $phone);
    if (substr($phone, 0, 1) === "0") $phone = "233" . substr($phone, 1);
    if (substr($phone, 0, 3) !== "233") $phone = "233" . $phone;
    $payload = json_encode(["sender" => $sender, "message" => $message, "recipients" => [$phone]]);
    $ch = curl_init("https://sms.arkesel.com/api/v2/sms/send");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["api-key: " . $apiKey, "Content-Type: application/json"]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    try {
        $stmt = $pdo->prepare("INSERT INTO sms_logs (phone, message, district, risk_level, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$phone, $message, $districtName, $risk, $httpCode === 200 ? "sent" : "failed"]);
    } catch (Exception $e) {}
    return $httpCode === 200;
}

function sendAutoEmail($email, $name, $subject, $message) {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: NFEWS Alert System <noreply@nfews.org>\r\n";
    $body = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>";
    $body .= "<div style='background:#0ea5e9;padding:20px;text-align:center;'><h1 style='color:white;margin:0;'>NFEWS FLOOD ALERT</h1></div>";
    $body .= "<div style='padding:24px;background:#f8fafc;'><p>Dear " . $name . ",</p>";
    $body .= "<div style='background:white;border-left:4px solid #dc2626;padding:16px;border-radius:8px;margin:16px 0;'><p style='margin:0;font-size:16px;'>" . $message . "</p></div>";
    $body .= "<p style='color:#64748b;font-size:13px;'>This is an automated alert from NFEWS.</p></div>";
    $body .= "<div style='background:#1e293b;padding:12px;text-align:center;'><p style='color:#94a3b8;margin:0;font-size:12px;'>West Africa Flood Early Warning System</p></div></div>";
    return mail($email, $subject, $body, $headers);
}

$method = $_SERVER["REQUEST_METHOD"];

if ($method === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);
    if (empty($data["district_id"]) || empty($data["level_m"])) {
        http_response_code(400);
        echo json_encode(["error" => "district_id and level_m are required"]);
        exit;
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO water_level_readings (district_id, level_m) VALUES (?, ?)");
        $stmt->execute([$data["district_id"], $data["level_m"]]);

        $distStmt = $pdo->prepare("SELECT d.*, r.id as region_id FROM districts d JOIN regions r ON d.region_id = r.id WHERE d.id = ?");
        $distStmt->execute([$data["district_id"]]);
        $district = $distStmt->fetch(PDO::FETCH_ASSOC);

        $level = floatval($data["level_m"]);
        $limit = floatval($district["water_level_threshold_m"]);
        $risk = "safe";
        $message = null;

        if ($level >= $limit) {
            $risk = "critical";
            $message = "CRITICAL: Water level at " . $district["name"] . " has reached " . $level . "m - exceeds threshold of " . $limit . "m!";
        } elseif ($level >= $limit * 0.85) {
            $risk = "danger";
            $message = "DANGER: Water level at " . $district["name"] . " is " . $level . "m - approaching threshold!";
        } elseif ($level >= $limit * 0.65) {
            $risk = "caution";
            $message = "CAUTION: Water level at " . $district["name"] . " is rising (" . $level . "m). Monitor closely.";
        }

        $smsSent = 0;
        $emailSent = 0;

        if ($risk !== "safe") {
            $alert = $pdo->prepare("INSERT INTO alerts (district_id, risk_level, message, triggered_by) VALUES (?, ?, ?, 'water_level')");
            $alert->execute([$data["district_id"], $risk, $message]);

            $update = $pdo->prepare("UPDATE districts SET risk_level = ? WHERE id = ?");
            $update->execute([$risk, $data["district_id"]]);

            $users = $pdo->prepare("SELECT id, name, email, phone FROM users WHERE is_active = 1 AND (region_id = ? OR role = 'admin')");
            $users->execute([$district["region_id"]]);
            $userList = $users->fetchAll(PDO::FETCH_ASSOC);

            $subject = "NFEWS " . strtoupper($risk) . " ALERT - " . $district["name"];

            foreach ($userList as $u) {
                if (!empty($u["phone"])) {
                    $sent = sendAutoSMS($u["phone"], $message, $district["name"], $risk, $pdo, $ARKESEL_API_KEY, $SENDER_ID);
                    if ($sent) $smsSent++;
                }
                if (!empty($u["email"])) {
                    $sent = sendAutoEmail($u["email"], $u["name"], $subject, $message);
                    if ($sent) $emailSent++;
                }
                try {
                    $notif = $pdo->prepare("INSERT INTO notification_history (user_id, district_id, risk_level, message, channel) VALUES (?, ?, ?, ?, 'sms_email')");
                    $notif->execute([$u["id"], $data["district_id"], $risk, $message]);
                } catch (Exception $e) {}
            }
        }

        echo json_encode(["message" => "Reading recorded", "level_m" => $level, "risk_level" => $risk, "alert" => $message, "sms_sent" => $smsSent, "email_sent" => $emailSent]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to record reading: " . $e->getMessage()]);
    }

} elseif ($method === "GET") {
    $district_id = $_GET["district_id"] ?? null;
    try {
        if ($district_id) {
            $stmt = $pdo->prepare("SELECT w.*, d.name as district_name FROM water_level_readings w JOIN districts d ON w.district_id = d.id WHERE w.district_id = ? ORDER BY w.recorded_at DESC LIMIT 20");
            $stmt->execute([$district_id]);
        } else {
            $stmt = $pdo->query("SELECT w.*, d.name as district_name FROM water_level_readings w JOIN districts d ON w.district_id = d.id ORDER BY w.recorded_at DESC LIMIT 50");
        }
        $readings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["total" => count($readings), "readings" => $readings]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to fetch readings: " . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
}
?>
