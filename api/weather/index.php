<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/db.php';

$API_KEY = 'a3bd07413e09031aae9adbd2714e2bef';
$district_id = $_GET['district_id'] ?? null;

if (!$district_id) {
    http_response_code(400);
    echo json_encode(['error' => 'district_id is required']);
    exit;
}

try {
    // Get district coordinates
    $stmt = $pdo->prepare("
        SELECT d.*, r.name as region_name, c.name as country_name
        FROM districts d
        JOIN regions r ON d.region_id = r.id
        JOIN countries c ON r.country_id = c.id
        WHERE d.id = ?
    ");
    $stmt->execute([$district_id]);
    $district = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$district) {
        http_response_code(404);
        echo json_encode(['error' => 'District not found']);
        exit;
    }

    $lat = $district['lat'];
    $lng = $district['lng'];

    // Fetch current weather from OpenWeatherMap
    $weatherUrl = "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lng}&appid={$API_KEY}&units=metric";
    $weatherJson = @file_get_contents($weatherUrl);

    // Fetch 5-day forecast
    $forecastUrl = "https://api.openweathermap.org/data/2.5/forecast?lat={$lat}&lon={$lng}&appid={$API_KEY}&units=metric&cnt=8";
    $forecastJson = @file_get_contents($forecastUrl);

    if (!$weatherJson) {
        // Return mock data if API not yet active
        echo json_encode([
            'district' => $district['name'],
            'region'   => $district['region_name'],
            'country'  => $district['country_name'],
            'lat'      => $lat,
            'lng'      => $lng,
            'current'  => [
                'temp'        => 28.5,
                'feels_like'  => 31.2,
                'humidity'    => 78,
                'description' => 'heavy rain',
                'icon'        => '10d',
                'wind_speed'  => 4.2,
                'rainfall_mm' => 12.4,
                'visibility'  => 5000,
            ],
            'forecast' => [
                ['time' => '12:00', 'temp' => 27, 'rain' => 8.2,  'description' => 'moderate rain'],
                ['time' => '15:00', 'temp' => 29, 'rain' => 4.1,  'description' => 'light rain'],
                ['time' => '18:00', 'temp' => 26, 'rain' => 15.3, 'description' => 'heavy rain'],
                ['time' => '21:00', 'temp' => 24, 'rain' => 2.0,  'description' => 'drizzle'],
                ['time' => '00:00', 'temp' => 23, 'rain' => 0.5,  'description' => 'light rain'],
                ['time' => '03:00', 'temp' => 22, 'rain' => 0.0,  'description' => 'clear sky'],
                ['time' => '06:00', 'temp' => 24, 'rain' => 1.2,  'description' => 'light rain'],
                ['time' => '09:00', 'temp' => 27, 'rain' => 6.8,  'description' => 'moderate rain'],
            ],
            'risk_assessment' => [
                'rainfall_threshold' => $district['rainfall_threshold_mm'],
                'total_forecast_rain' => 38.1,
                'risk_level' => 'danger',
                'recommendation' => 'Heavy rainfall expected. Authorities should prepare evacuation plans.'
            ],
            'source' => 'mock_data',
            'note'   => 'API key pending activation. Showing sample data.'
        ]);
        exit;
    }

    $weather  = json_decode($weatherJson, true);
    $forecast = json_decode($forecastJson, true);

    // Process current weather
    $current = [
        'temp'        => round($weather['main']['temp'], 1),
        'feels_like'  => round($weather['main']['feels_like'], 1),
        'humidity'    => $weather['main']['humidity'],
        'description' => $weather['weather'][0]['description'],
        'icon'        => $weather['weather'][0]['icon'],
        'wind_speed'  => $weather['wind']['speed'],
        'rainfall_mm' => $weather['rain']['1h'] ?? 0,
        'visibility'  => $weather['visibility'] ?? 10000,
    ];

    // Process forecast
    $forecastData = [];
    if (isset($forecast['list'])) {
        foreach ($forecast['list'] as $item) {
            $forecastData[] = [
                'time'        => date('H:i', $item['dt']),
                'temp'        => round($item['main']['temp'], 1),
                'rain'        => $item['rain']['3h'] ?? 0,
                'description' => $item['weather'][0]['description'],
            ];
        }
    }

    // Calculate total forecast rainfall
    $totalRain = array_sum(array_column($forecastData, 'rain'));
    $threshold = floatval($district['rainfall_threshold_mm']);

    // Risk assessment
    if ($totalRain >= $threshold) {
        $riskLevel = 'critical';
        $recommendation = 'CRITICAL: Forecast rainfall exceeds threshold. Immediate evacuation may be required.';
    } elseif ($totalRain >= $threshold * 0.85) {
        $riskLevel = 'danger';
        $recommendation = 'DANGER: Heavy rainfall expected. Authorities should prepare evacuation plans.';
    } elseif ($totalRain >= $threshold * 0.65) {
        $riskLevel = 'caution';
        $recommendation = 'CAUTION: Significant rainfall forecast. Monitor water levels closely.';
    } else {
        $riskLevel = 'safe';
        $recommendation = 'Rainfall levels within safe range. Continue routine monitoring.';
    }

    // Save to weather_logs
    $logStmt = $pdo->prepare("
        INSERT INTO weather_logs (district_id, rainfall_mm, temperature_c, humidity_pct, forecast_json, recorded_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $logStmt->execute([
        $district_id,
        $current['rainfall_mm'],
        $current['temp'],
        $current['humidity'],
        json_encode($forecastData)
    ]);

    echo json_encode([
        'district' => $district['name'],
        'region'   => $district['region_name'],
        'country'  => $district['country_name'],
        'lat'      => $lat,
        'lng'      => $lng,
        'current'  => $current,
        'forecast' => $forecastData,
        'risk_assessment' => [
            'rainfall_threshold'  => $threshold,
            'total_forecast_rain' => round($totalRain, 1),
            'risk_level'          => $riskLevel,
            'recommendation'      => $recommendation
        ],
        'source' => 'openweathermap'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Weather fetch failed: ' . $e->getMessage()]);
}
?>
