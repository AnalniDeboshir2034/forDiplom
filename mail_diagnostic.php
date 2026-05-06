<?php
echo "<h1>Диагностика почты</h1>";

// 1. Проверяем, включена ли функция mail()
if (function_exists('mail')) {
    echo "✅ Функция mail() существует<br>";
} else {
    echo "❌ Функция mail() НЕ существует (отключена в PHP)<br>";
}

// 2. Проверяем, заблокирована ли mail() через disable_functions
$disabled = ini_get('disable_functions');
if (strpos($disabled, 'mail') !== false) {
    echo "❌ mail() заблокирована в disable_functions: " . htmlspecialchars($disabled) . "<br>";
} else {
    echo "✅ mail() НЕ в списке заблокированных функций<br>";
}

// 3. Пробуем отправить тестовое письмо через mail()
$to = "aleksfinski@gmail.com"; // ЗАМЕНИ НА СВОЙ!
$subject = "Тест mail()";
$message = "Это тестовое письмо, отправленное через mail()";
$headers = "From: info@diplomkbip.xyz\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

error_clear_last();
$result = @mail($to, $subject, $message, $headers);

if ($result) {
    echo "✅ mail() вернула true — письмо якобы отправлено<br>";
} else {
    echo "❌ mail() вернула false — отправка не удалась<br>";
    $error = error_get_last();
    if ($error) {
        echo "📝 Последняя ошибка: " . htmlspecialchars($error['message']) . "<br>";
    }
}

// 4. Проверяем настройки sendmail
echo "<h2>Настройки PHP для почты:</h2>";
echo "SMTP: " . ini_get('SMTP') . "<br>";
echo "smtp_port: " . ini_get('smtp_port') . "<br>";
echo "sendmail_from: " . ini_get('sendmail_from') . "<br>";
echo "sendmail_path: " . ini_get('sendmail_path') . "<br>";

// 5. Проверяем лог ошибок
echo "<h2>Лог ошибок PHP (последние строки):</h2>";
$log = ini_get('error_log');
if ($log && file_exists($log)) {
    echo "<pre>" . htmlspecialchars(shell_exec("tail -20 " . escapeshellarg($log))) . "</pre>";
} else {
    echo "Лог не найден или недоступен<br>";
}
?>