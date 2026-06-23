<?php
require_once __DIR__ . '/config/db.php';
$hash = password_hash('nfews2026', PASSWORD_BCRYPT);
$stmt = mysqli_prepare($conn, "UPDATE users SET password_hash = ? WHERE email = 'richgetrich7@gmail.com'");
mysqli_stmt_bind_param($stmt, "s", $hash);
mysqli_stmt_execute($stmt);
echo "Done! Hash: " . $hash;