<?php

function app_order_status_label(string $status): string
{
    $map = [
        'new' => 'Новый',
        'paid' => 'Оплачен',
        'shipped' => 'Отправлен',
        'completed' => 'Завершён',
    ];
    return $map[$status] ?? $status;
}

function app_order_status_labels(): array
{
    return [
        'new' => 'Новый',
        'paid' => 'Оплачен',
        'shipped' => 'Отправлен',
        'completed' => 'Завершён',
    ];
}

function app_delivery_type_label(string $type): string
{
    $map = [
        'courier' => 'Курьером',
        'pickup' => 'Самовывоз',
    ];
    return $map[$type] ?? $type;
}

function app_payment_type_label(string $type): string
{
    $map = [
        'invoice' => 'Счёт на email',
        'card_on_delivery' => 'Картой при получении',
        'erip' => 'ЕРИП',
    ];
    return $map[$type] ?? $type;
}

function app_review_status_label(string $status): string
{
    $map = [
        'pending' => 'На модерации',
        'approved' => 'Одобрен',
        'rejected' => 'Отклонён',
    ];
    return $map[$status] ?? $status;
}

function app_promo_type_label(string $type): string
{
    $map = [
        'percent' => 'Процент',
        'fixed' => 'Фиксированная сумма',
    ];
    return $map[$type] ?? $type;
}

function app_user_role_label(string $role): string
{
    $map = [
        'admin' => 'Админ',
        'user' => 'Пользователь',
    ];
    return $map[$role] ?? $role;
}

function app_account_type_label(string $type): string
{
    $map = [
        'individual' => 'Физлицо',
        'legal' => 'Юрлицо',
    ];
    return $map[$type] ?? $type;
}

function app_category_label($mysqli, string $slug): string
{
    $slug = trim($slug);
    if ($slug === '' || $slug === 'Без категории') {
        return 'Без категории';
    }

    static $cache = [];
    if (isset($cache[$slug])) {
        return $cache[$slug];
    }

    $name = $slug;
    if ($mysqli instanceof mysqli) {
        $stmt = @$mysqli->prepare('SELECT name FROM subfilter WHERE slug = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $slug);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && ($row = $res->fetch_assoc()) && !empty($row['name'])) {
                $name = (string)$row['name'];
            }
            $stmt->close();
        }
    }

    $cache[$slug] = $name;
    return $name;
}

function app_localize_order_row(array $order): array
{
    $order['status_label'] = app_order_status_label((string)($order['status'] ?? ''));
    $order['delivery_type_label'] = app_delivery_type_label((string)($order['delivery_type'] ?? ''));
    $order['payment_type_label'] = app_payment_type_label((string)($order['payment_type'] ?? ''));
    if (function_exists('app_format_datetime') && !empty($order['created_at'])) {
        $order['created_at_label'] = app_format_datetime((string)$order['created_at']);
    }
    return $order;
}
