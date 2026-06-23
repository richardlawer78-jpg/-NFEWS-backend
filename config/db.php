 <?php
$host = getenv('MYSQLHOST') ?: 'mysql.railway.internal';
$db   = getenv('MYSQLDATABASE') ?: 'railway';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: 'CnyBnhNLkWIdThxplbLwzvmrqTzfJxcr';
$port = getenv('MYSQLPORT') ?: '3306';

$conn = mysqli_connect($host, $user, $pass, $db, (int)$port);

if (!$conn) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed: ' . mysqli_connect_error()]);
    exit;
}
?>