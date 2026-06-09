<?php
// includes/handle_bitrix_form.php
require_once __DIR__ . '/auth_lib.php';
$BITRIX_WEBHOOK = 'https://k7s.bitrix24.by/rest/25370/y91iqahj9bllr1gt/crm.lead.add.json';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод запроса']);
    exit;
}

$name = htmlspecialchars(trim($_POST['name'] ?? ''));
$phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
$callbackTime = htmlspecialchars(trim($_POST['callback_time'] ?? ''));
$message = htmlspecialchars(trim($_POST['message'] ?? ''));
$form_type = htmlspecialchars(trim($_POST['form_type'] ?? 'Контактная форма'));

function is_valid_phone_prefix($phone)
{
    return app_is_valid_phone(app_normalize_phone((string)$phone));
}

if (empty($name) || empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Заполните имя и телефон']);
    exit;
}

if (!is_valid_phone_prefix($phone)) {
    echo json_encode(['success' => false, 'message' => 'Номер телефона невалидный']);
    exit;
}

$leadData = [
    'fields' => [
        'TITLE' => 'Заявка с сайта Медикатор ру - ' . $form_type,
        'NAME' => $name,
        'PHONE' => [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']],
        'SOURCE_ID' => 'WEB',
        'SOURCE_DESCRIPTION' => $form_type . ' на сайте',
        'ASSIGNED_BY_ID' => 1,
        'STATUS_ID' => 'NEW',
        'COMMENTS' => "Форма: $form_type\nИмя: $name\nТелефон: $phone\nУдобное время: $callbackTime\nСообщение: $message\n\nДата: " . date('d.m.Y H:i'),
    ]
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $BITRIX_WEBHOOK,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($leadData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json']
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

file_put_contents('bitrix_log.txt', 
    date('Y-m-d H:i:s') . " | HTTP: $httpCode | Форма: $form_type\n" .
    "Данные: " . print_r($leadData, true) . "\nОтвет: " . print_r($result, true) . "\n\n",
    FILE_APPEND
);

if (isset($result['result'])) {
    echo json_encode(['success' => true, 'message' => 'Заявка отправлена! Мы свяжемся с вами.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Ошибка отправки. Позвоните нам.']);
}