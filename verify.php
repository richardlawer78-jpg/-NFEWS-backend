<?php
$hash = '$2y$10$AF9g2kQdry.QLTFximBWehyIXK094.wooDi7fQo8sp9h/uBg2O6O';
$pass = 'nfews2026';
echo password_verify($pass, $hash) ? 'MATCH' : 'NO MATCH';