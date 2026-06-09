<?php
$host = 'mail-ctrl1-inf-int-i1.hosterby.com';
$ports = [587, 2525, 25];

echo "<h2>Диагностика портов для $host</h2>";
foreach ($ports as $port) {
    $conn = @fsockopen($host, $port, $errno, $errstr, 5);
    if ($conn) {
        echo "✅ Порт $port: ДОСТУПЕН<br>";
        fclose($conn);
    } else {
        echo "❌ Порт $port: НЕТ ДОСТУПА (Таймаут/Блокировка). Ошибка: $errstr<br>";
    }
}
?>