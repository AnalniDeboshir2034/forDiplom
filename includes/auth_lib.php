<?php
require_once __DIR__ . '/config.php';

function app_starts_with(string $haystack, string $needle): bool
{
    if ($needle === '') return true;
    return substr($haystack, 0, strlen($needle)) === $needle;
}

function app_strlen(string $value): int
{
    return function_exists('mb_strlen') ? (int)mb_strlen($value, 'UTF-8') : strlen($value);
}

function app_strtolower(string $value): string
{
    return function_exists('mb_strtolower') ? (string)mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function app_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function app_json(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function app_is_email(string $value): bool
{
    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
}

function app_normalize_email(string $email): string
{
    return app_strtolower(trim($email));
}

function app_normalize_digits(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function app_normalize_unp(string $unp): string
{
    return app_normalize_digits($unp);
}

function app_is_valid_unp(string $unp): bool
{
    return strlen(app_normalize_unp($unp)) === 9;
}

function app_normalize_phone(string $phone): string
{
    $phone = trim($phone);
    $digits = app_normalize_digits($phone);
    if ($digits === '') {
        return '';
    }

    if (app_starts_with($phone, '+')) {
        return '+' . $digits;
    }
    if (strlen($digits) === 11 && app_starts_with($digits, '80')) {
        return '+375' . substr($digits, 2);
    }
    if (app_starts_with($digits, '375')) {
        return '+' . $digits;
    }
    if (strlen($digits) === 9) {
        return '+375' . $digits;
    }

    return '+' . $digits;
}

function app_is_valid_phone(string $phone): bool
{
    $normalized = app_normalize_phone($phone);
    if (!preg_match('/^\+\d{7,15}$/', $normalized)) {
        return false;
    }

    if (!app_starts_with($normalized, '+375')) {
        return false;
    }

    return strlen($normalized) === 13;
}

function app_validate_legal_profile_fields(
    string $companyName,
    string $representativeName,
    string $unp,
    string $phone,
    string $address
): ?string {
    if ($companyName === '' || $representativeName === '' || $address === '') {
        return 'Для юрлица заполните название компании, ФИО представителя и адрес';
    }
    if (!app_is_valid_unp($unp)) {
        return 'УНП должен содержать ровно 9 цифр';
    }
    if ($phone === '') {
        return 'Укажите номер телефона';
    }
    if (!app_is_valid_phone($phone)) {
        return 'Введите корректный белорусский номер телефона (+375 XX XXX XX XX)';
    }

    return null;
}

function app_password_is_hash(string $stored): bool
{
    return app_starts_with($stored, '$2y$') || app_starts_with($stored, '$2a$') || app_starts_with($stored, '$argon2');
}

/**
 * Verify password and optionally upgrade legacy plaintext -> hash.
 * Returns true if valid.
 */
function app_verify_password_and_upgrade(mysqli $mysqli, int $userId, string $plain, string $stored): bool
{
    if (app_password_is_hash($stored)) {
        return password_verify($plain, $stored);
    }

    // Legacy: plaintext compare (unsafe, but migrate on success).
    if (!hash_equals((string)$stored, (string)$plain)) {
        return false;
    }

    $newHash = password_hash($plain, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare("UPDATE `user` SET `password` = ? WHERE `id` = ? LIMIT 1");
    $stmt->bind_param('si', $newHash, $userId);
    $stmt->execute();
    $stmt->close();
    return true;
}

function app_auth_login(array $user): void
{
    app_session_start();
    $_SESSION['user_id'] = (int)($user['id'] ?? 0);
    $_SESSION['user_login'] = (string)($user['login'] ?? '');
    $_SESSION['user_role'] = (string)($user['role'] ?? 'user');
}

function app_auth_logout(): void
{
    app_session_start();
    unset($_SESSION['user_id'], $_SESSION['user_login'], $_SESSION['user_role']);
}

function app_current_user(mysqli $mysqli): ?array
{
    app_session_start();
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) return null;

    // Prefer full profile columns, but keep compatibility with older DB schema.
    $queries = [
        "SELECT id, login, email, name, unp, role, account_type, company_name, representative_name, phone, address FROM `user` WHERE id = ? LIMIT 1",
        "SELECT id, login, email, name, unp, role FROM `user` WHERE id = ? LIMIT 1",
        "SELECT id, login, email, role FROM `user` WHERE id = ? LIMIT 1",
    ];

    foreach ($queries as $sql) {
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if ($row) {
            if (!array_key_exists('name', $row)) $row['name'] = '';
            if (!array_key_exists('unp', $row)) $row['unp'] = '';
            if (!array_key_exists('email', $row)) $row['email'] = '';
            if (!array_key_exists('role', $row)) $row['role'] = 'user';
            if (!array_key_exists('account_type', $row)) $row['account_type'] = 'individual';
            if (!array_key_exists('company_name', $row)) $row['company_name'] = '';
            if (!array_key_exists('representative_name', $row)) $row['representative_name'] = '';
            if (!array_key_exists('phone', $row)) $row['phone'] = '';
            if (!array_key_exists('address', $row)) $row['address'] = '';
            return $row;
        }
    }

    return null;
}

